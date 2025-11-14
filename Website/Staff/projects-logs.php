<?php
session_start();
require_once "../../db.php";

// Redirect if not logged in
if (!isset($_SESSION['Staff_id'])) {
    echo '<script>
        alert("Please login first.");
        window.location.href = "../login.php";
    </script>';
    exit();
}

$staff_id = $_SESSION['Staff_id'];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Filter Input
$filter_period = $_GET['filter_period'] ?? 'all';
$filter_day = $_GET['filter_day'] ?? '';
$filter_month = $_GET['filter_month'] ?? '';
$current_date = date('Y-m-d');

// Build the main query for project logs - Using LEFT JOINs to handle cases where logs might not exist
$where_conditions = [];
$params = [];
$types = '';

// Check if project_logs table exists
$table_check = $conn->query("SHOW TABLES LIKE 'project_logs'");
if ($table_check->num_rows > 0) {
    // Table exists, use it in the query
    $sql = "
        SELECT 
            PL.log_id,
            PL.log_date,
            PL.location,
            PL.notes,
            PL.client_feedback,
            PL.created_at,
            S.Name AS staff_name,
            S.Staff_id,
            P.Project_id,
            P.Project_Name,
            C.Name AS client_name,
            SV.Name AS service_name,
            COALESCE(ASched.ScheduleDate, O.order_date, P.StartDate) AS appointment_date
        FROM project_logs PL
        LEFT JOIN Projects P ON PL.project_id = P.Project_id
        LEFT JOIN Staff S ON PL.staff_id = S.Staff_id
        LEFT JOIN Orders O ON P.Orders_id = O.Orders_id
        LEFT JOIN Customers C ON O.customer_id = C.customer_id
        LEFT JOIN Services SV ON O.Services_id = SV.Services_id
        LEFT JOIN appointment_sched ASched ON P.Project_id = ASched.project_id
    ";
    
    // Add period filter
    if ($filter_period !== 'all') {
        switch ($filter_period) {
            case 'day':
                if (!empty($filter_day)) {
                    $where_conditions[] = "PL.log_date = ?";
                    $params[] = $filter_day;
                    $types .= 's';
                }
                break;
            case 'month':
                if (!empty($filter_month)) {
                    $where_conditions[] = "YEAR(PL.log_date) = YEAR(?) AND MONTH(PL.log_date) = MONTH(?)";
                    $params[] = $filter_month;
                    $params[] = $filter_month;
                    $types .= 'ss';
                }
                break;
            case 'week':
                $where_conditions[] = "PL.log_date >= DATE_SUB(?, INTERVAL 7 DAY)";
                $params[] = $current_date;
                $types .= 's';
                break;
            case 'today':
                $where_conditions[] = "PL.log_date = ?";
                $params[] = $current_date;
                $types .= 's';
                break;
        }
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY PL.log_date DESC, PL.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $logs_result = $stmt->get_result();
    } else {
        $logs_result = $conn->query($sql);
    }
} else {
    // Table doesn't exist, return empty result set
    $logs_result = false;
}

// Function to format date
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M j, Y', strtotime($date));
}

// Function to format datetime
function formatDateTime($datetime) {
    if (!$datetime) return 'N/A';
    return date('M j, Y g:i A', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Project Logs | ALG Staff</title>
  <style>
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: #a6a6a6;
      overflow-x: hidden;
    }

    /* ===== NAVBAR ===== */
    .navbar {
      width: 100%;
      background: #615e5e;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 40px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    .nav-logo img {
      height: 55px;
    }
    .nav-links a {
      color: white;
      margin: 0 15px;
      text-decoration: none;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 20px;
      transition: 0.3s;
    }
    .nav-links a.active {
      background: #d9d9d9;
      color: black;
    }
    .nav-links a:hover {
      background: rgba(255,255,255,0.2);
    }

    .nav-right a {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .nav-right img {
      width: 28px;
      height: 28px;
      filter: invert(1);
      cursor: pointer;
      transition: 0.3s;
    }
    .nav-right img:hover {
      opacity: 0.8;
      transform: scale(1.05);
    }

    main {
      padding: 40px 0;
      display: flex;
      justify-content: center;
    }
    .log-container {
      background: #f4f4f4;
      width: 90%;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      padding: 30px 40px;
    }
    h1 {
      color: #111;
      font-size: 2rem;
      margin-bottom: 15px;
    }

    .top-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .btn-schedule {
      background: #d9d9d9;
      color: #000;
      padding: 8px 16px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .btn-schedule:hover {
      background: #c6c6c6;
    }

    .filters-export {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .filters-export button {
      padding: 8px 12px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 0.9rem;
    }
    .filters-export button {
      background: #4f9bd2;
      color: white;
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
    }
    .filters-export button:hover {
      background: #3f8bc0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
    }
    th, td {
      padding: 12px 16px;
      border-bottom: 1px solid #ddd;
      text-align: left;
      color: #333;
    }
    th {
      background-color: #f9f9f9;
      font-weight: bold;
    }

    .pagination {
      display: flex;
      justify-content: flex-end;
      padding: 10px 0;
      gap: 15px;
      font-size: 0.9rem;
    }
    .pagination button {
      background: none;
      border: none;
      color: #007bff;
      cursor: pointer;
      font-weight: 600;
    }
    .pagination button:disabled {
      color: #aaa;
      cursor: default;
    }

    /* Filter styling */
    .filter-section {
      background: white;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .filter-row {
      display: flex;
      align-items: center;
      gap: 15px;
      flex-wrap: wrap;
      margin-bottom: 15px;
    }
    .filter-label {
      font-weight: 600;
      color: #374151;
      min-width: 120px;
    }
    .filter-options {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .filter-btn {
      background: #6b7280;
      color: white;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 600;
      text-decoration: none;
      transition: background-color 0.2s;
      border: none;
      cursor: pointer;
    }
    .filter-btn:hover {
      background: #4b5563;
    }
    .filter-btn.active {
      background: #3b82f6;
    }
    .date-inputs {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .date-input-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .date-input-group label {
      font-size: 0.875rem;
      font-weight: 500;
      color: #374151;
    }
    .date-input {
      padding: 8px 12px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 0.875rem;
    }
    .apply-filter-btn {
      background: #10B981;
      color: white;
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    .apply-filter-btn:hover {
      background: #059669;
    }
    .reset-filter-btn {
      background: #6B7280;
      color: white;
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.2s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    .reset-filter-btn:hover {
      background: #4B5563;
    }

    /* Notes cell styling */
    .notes-cell {
      max-width: 200px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      cursor: pointer;
    }
    .notes-cell.expanded {
      white-space: normal;
      overflow: visible;
      background-color: #f9fafb;
    }

    /* Alert styling */
    .alert {
      padding: 1rem;
      margin-bottom: 1rem;
      border-radius: 0.5rem;
      font-weight: 500;
    }
    .alert-info {
      background-color: #eff6ff;
      color: #1e40af;
      border: 1px solid #dbeafe;
    }

    .hidden {
      display: none;
    }
  </style>
</head>
<body>
  <header class="navbar">
    <div class="nav-logo">
      <img src="../../images/alg-logo-black.png" alt="ALG Logo">
    </div>

    <nav class="nav-links">
      <a href="home.php">Home</a>
      <a href="projects.php">Projects</a>
      <a href="profile.php">Staff</a> 
    </nav>

    <div class="nav-right">
      <a href="../logout.php" title="Logout">
        <img src="../../images/log-out.svg" alt="Logout">
      </a>
    </div>
  </header>

  <main>
    <div class="log-container">
      <h1>Project Logs (Staff View)</h1>

      <div class="top-controls">
        <a href="projects.php" class="btn-schedule">← Back to Projects</a>
        <div class="filters-export">
          <button id="exportBtn">Export Excel</button>
        </div>
      </div>

      <!-- Display Messages -->
      <?php if ($table_check->num_rows == 0): ?>
        <div class="alert alert-info">
          The project logs system is not yet set up. Please contact an administrator.
        </div>
      <?php endif; ?>

      <!-- Filters -->
      <div class="filter-section">
        <form id="filterForm" method="GET" action="projects-logs.php">
          <div class="filter-row">
            <div class="filter-label">Filter by Period:</div>
            <div class="filter-options">
              <button type="button" class="filter-btn <?= $filter_period === 'all' ? 'active' : '' ?>" onclick="setFilterPeriod('all')">
                All Time
              </button>
              <button type="button" class="filter-btn <?= $filter_period === 'today' ? 'active' : '' ?>" onclick="setFilterPeriod('today')">
                Today
              </button>
              <button type="button" class="filter-btn <?= $filter_period === 'week' ? 'active' : '' ?>" onclick="setFilterPeriod('week')">
                This Week
              </button>
              <button type="button" class="filter-btn <?= $filter_period === 'day' ? 'active' : '' ?>" onclick="setFilterPeriod('day')">
                Specific Day
              </button>
              <button type="button" class="filter-btn <?= $filter_period === 'month' ? 'active' : '' ?>" onclick="setFilterPeriod('month')">
                Specific Month
              </button>
            </div>
          </div>

          <!-- Day Filter -->
          <div id="dayFilter" class="filter-row <?= $filter_period !== 'day' ? 'hidden' : '' ?>">
            <div class="filter-label">Select Day:</div>
            <div class="date-inputs">
              <div class="date-input-group">
                <label for="filter_day">Date</label>
                <input type="date" id="filter_day" name="filter_day" class="date-input" value="<?= htmlspecialchars($filter_day) ?>">
              </div>
            </div>
          </div>

          <!-- Month Filter -->
          <div id="monthFilter" class="filter-row <?= $filter_period !== 'month' ? 'hidden' : '' ?>">
            <div class="filter-label">Select Month:</div>
            <div class="date-inputs">
              <div class="date-input-group">
                <label for="filter_month">Month</label>
                <input type="month" id="filter_month" name="filter_month" class="date-input" value="<?= htmlspecialchars($filter_month) ?>">
              </div>
            </div>
          </div>

          <div class="filter-row">
            <button type="submit" class="apply-filter-btn">Apply Filters</button>
            <a href="projects-logs.php" class="reset-filter-btn">Reset Filters</a>
          </div>

          <input type="hidden" id="filter_period" name="filter_period" value="<?= htmlspecialchars($filter_period) ?>">
        </form>
      </div>

      <!-- Project Logs Table -->
      <div class="table-container">
        <?php if ($logs_result && $logs_result->num_rows > 0): ?>
          <div style="margin-bottom: 15px; font-weight: 500; color: #374151;">
            Showing <?= $logs_result->num_rows ?> log<?= $logs_result->num_rows !== 1 ? 's' : '' ?>
            <?php if ($filter_period !== 'all'): ?>
              for 
              <?php 
              switch($filter_period) {
                  case 'today': 
                      echo 'Today'; 
                      break;
                  case 'week': 
                      echo 'This Week'; 
                      break;
                  case 'day': 
                      echo date('M j, Y', strtotime($filter_day)); 
                      break;
                  case 'month': 
                      echo date('F Y', strtotime($filter_month)); 
                      break;
              }
              ?>
            <?php endif; ?>
          </div>
          <table>
            <thead>
              <tr>
                <th>Staff Name</th>
                <th>Client Name</th>
                <th>Service</th>
                <th>Appointment Date</th>
                <th>Location</th>
                <th>Notes</th>
                <th>Client Feedback</th>
                <th>Log Date</th>
              </tr>
            </thead>
            <tbody>
            <?php while($log = $logs_result->fetch_assoc()): ?>
              <tr>
                <td class="font-semibold">
                  <?= htmlspecialchars($log['staff_name'] ?? 'N/A') ?>
                </td>
                <td>
                  <?= htmlspecialchars($log['client_name'] ?? 'N/A') ?>
                  <?php if (!empty($log['Project_Name'])): ?>
                    <div class="text-xs text-gray-500"><?= htmlspecialchars($log['Project_Name']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?= htmlspecialchars($log['service_name'] ?? 'N/A') ?>
                </td>
                <td>
                  <?= formatDate($log['appointment_date']) ?>
                </td>
                <td>
                  <?= htmlspecialchars($log['location'] ?? 'Not specified') ?>
                </td>
                <td class="notes-cell" onclick="toggleNotes(this)">
                  <?= htmlspecialchars($log['notes'] ?? 'No notes') ?>
                </td>
                <td class="notes-cell" onclick="toggleNotes(this)">
                  <?= htmlspecialchars($log['client_feedback'] ?? 'No feedback') ?>
                </td>
                <td>
                  <?= formatDateTime($log['created_at']) ?>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="p-8 text-center text-gray-500 bg-white">
            <h3 class="mt-2 text-sm font-medium text-gray-900">
              <?php echo ($table_check->num_rows == 0) ? 'Project Logs System Not Set Up' : 'No Project Logs Found'; ?>
            </h3>
            <p class="mt-1 text-sm text-gray-500">
              <?php if ($table_check->num_rows == 0): ?>
                The project logs system needs to be initialized. Please contact an administrator.
              <?php else: ?>
                <?= $filter_period !== 'all' ? 'Try changing your filters.' : 'No project logs have been created yet.' ?>
              <?php endif; ?>
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <script>
    // Toggle notes expansion
    function toggleNotes(element) {
      element.classList.toggle('expanded');
    }

    // Filter functions
    function setFilterPeriod(period) {
      document.getElementById('filter_period').value = period;
      
      // Show/hide date inputs based on selection
      document.getElementById('dayFilter').classList.toggle('hidden', period !== 'day');
      document.getElementById('monthFilter').classList.toggle('hidden', period !== 'month');
      
      // Update active button states
      document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
      });
      event.target.classList.add('active');
    }

    // Export functionality
    document.getElementById('exportBtn').addEventListener('click', () => {
      // Create a simple export to Excel functionality
      let table = document.querySelector('table');
      if (!table) {
        alert('No data to export');
        return;
      }
      
      let html = table.outerHTML;
      let url = 'data:application/vnd.ms-excel;charset=utf-8,' + encodeURIComponent(html);
      let downloadLink = document.createElement('a');
      downloadLink.href = url;
      downloadLink.download = 'project_logs.xls';
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    });

    // Initialize filter display on page load
    document.addEventListener('DOMContentLoaded', function() {
      setFilterPeriod('<?= $filter_period ?>');
    });
  </script>

  <?php 
  // Close prepared statement if it exists
  if (isset($stmt)) {
    $stmt->close();
  }
  // Close connection
  if (isset($conn)) {
    $conn->close();
  }
  ?>
</body>
</html>