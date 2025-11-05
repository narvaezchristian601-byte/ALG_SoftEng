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

// ========== AJAX PAGINATION WITH FILTERS ==========
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 6;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';

    $whereClauses = [];
    if (!empty($search)) {
        $whereClauses[] = "Staff.Name LIKE '%$search%'";
    }
    if (!empty($date)) {
        $whereClauses[] = "DATE(Orders.schedule_date) = '$date'";
    }
    $whereSQL = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $query = "
      SELECT 
        Staff.Name AS StaffMember,
        Customers.Name AS ClientName,
        Services.Name AS RoofingService,
        Orders.schedule_date AS AppointmentDate,
        Orders.schedule_time AS AppointmentTime,
        Customers.Address AS SiteLocation,
        Orders.notes AS Notes
      FROM Orders
      JOIN Customers ON Orders.customer_id = Customers.customer_id
      JOIN Services ON Orders.Services_id = Services.Services_id
      JOIN Projects ON Orders.Orders_id = Projects.Orders_id
      JOIN Staff ON Projects.Assigned_Staff = Staff.Staff_id
      $whereSQL
      ORDER BY Orders.schedule_date DESC
      LIMIT $limit OFFSET $offset
    ";

    $result = $conn->query($query);
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    echo json_encode($logs);
    exit();
}

// ========== EXPORT TO EXCEL ==========
if (isset($_GET['export']) && $_GET['export'] == '1') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=project_logs.xls");

    $query = "
      SELECT 
        Staff.Name AS 'Staff Member',
        Customers.Name AS 'Client Name',
        Services.Name AS 'Roofing Service',
        Orders.schedule_date AS 'Appointment Date',
        Orders.schedule_time AS 'Appointment Time',
        Customers.Address AS 'Site Location',
        Orders.notes AS 'Notes'
      FROM Orders
      JOIN Customers ON Orders.customer_id = Customers.customer_id
      JOIN Services ON Orders.Services_id = Services.Services_id
      JOIN Projects ON Orders.Orders_id = Projects.Orders_id
      JOIN Staff ON Projects.Assigned_Staff = Staff.Staff_id
      ORDER BY Orders.schedule_date DESC
    ";

    $result = $conn->query($query);

    echo "<table border='1'>";
    echo "<tr>
            <th>Staff Member</th>
            <th>Client Name</th>
            <th>Roofing Service</th>
            <th>Appointment Date</th>
            <th>Appointment Time</th>
            <th>Site Location</th>
            <th>Notes</th>
          </tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['Staff Member']}</td>
                <td>{$row['Client Name']}</td>
                <td>{$row['Roofing Service']}</td>
                <td>{$row['Appointment Date']}</td>
                <td>{$row['Appointment Time']}</td>
                <td>{$row['Site Location']}</td>
                <td>{$row['Notes']}</td>
              </tr>";
    }
    echo "</table>";
    exit();
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
    .filters-export input, .filters-export button {
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
  </style>
</head>
<body>
  <header class="navbar">
    <div class="nav-logo">
      <img src="../../images/alg-logo-black.png" alt="ALG Logo">
    </div>

    <nav class="nav-links">
      <a href="home.php">Home</a>
      <a href="projects.php" class="active">Projects</a>
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
      <h1>Project Logs</h1>

      <div class="top-controls">
        <a href="projects.php" class="btn-schedule">← Schedule</a>
        <div class="filters-export">
          <input type="text" id="searchInput" placeholder="Search Staff name...">
          <input type="date" id="dateFilter">
          <button id="filterBtn">Filter</button>
          <button id="exportBtn">Export Excel</button>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th>Staff Member</th>
            <th>Client Name</th>
            <th>Roofing Service</th>
            <th>Appointment Date</th>
            <th>Appointment Time</th>
            <th>Site Location</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody id="logsBody">
        </tbody>
      </table>

      <div class="pagination">
        <button id="prevBtn" disabled>← Previous</button>
        <button id="nextBtn" disabled>Next →</button>
      </div>
    </div>
  </main>

  <script>
    let currentPage = 1;
    let currentSearch = '';
    let currentDate = '';

    function loadLogs(page = 1) {
      fetch(`project-logs.php?ajax=1&page=${page}&search=${encodeURIComponent(currentSearch)}&date=${encodeURIComponent(currentDate)}`)
        .then(res => res.json())
        .then(data => {
          const body = document.getElementById('logsBody');
          body.innerHTML = '';

          if (!data || data.length === 0) {
            body.innerHTML = `<tr><td colspan="7" style="text-align:center;">No records found</td></tr>`;
          } else {
            data.forEach(row => {
              body.innerHTML += `
                <tr>
                  <td>${row.StaffMember}</td>
                  <td>${row.ClientName}</td>
                  <td>${row.RoofingService}</td>
                  <td>${row.AppointmentDate}</td>
                  <td>${row.AppointmentTime ?? '-'}</td>
                  <td>${row.SiteLocation ?? '-'}</td>
                  <td>${row.Notes ?? ''}</td>
                </tr>`;
            });
          }

          document.getElementById('prevBtn').disabled = (page === 1);
          document.getElementById('nextBtn').disabled = (data.length < 6);
        })
        .catch(err => {
          console.error("Error fetching logs:", err);
        });
    }

    document.getElementById('prevBtn').addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        loadLogs(currentPage);
      }
    });

    document.getElementById('nextBtn').addEventListener('click', () => {
      currentPage++;
      loadLogs(currentPage);
    });

    document.getElementById('filterBtn').addEventListener('click', () => {
      currentSearch = document.getElementById('searchInput').value.trim();
      currentDate = document.getElementById('dateFilter').value;
      currentPage = 1;
      loadLogs(currentPage);
    });

    document.getElementById('exportBtn').addEventListener('click', () => {
      window.location.href = 'project-logs.php?export=1';
    });

    window.onload = () => {
      loadLogs();
    };
  </script>
</body>
</html>
