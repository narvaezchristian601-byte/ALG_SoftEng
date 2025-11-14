<?php
// projects.php
include "../../db.php";

// Ensure $conn is valid
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check ../../db.php.");
}

// ====================================================================
// FUNCTIONS & METRICS
// ====================================================================

// --- Handle Filter Input ---
$current_year = date('Y');
$current_month = date('n');

$selected_year = $_GET['filter_year'] ?? $current_year;
$selected_month = $_GET['filter_month'] ?? $current_month;

// 1. Get available unique years for the filter - FIXED: Using multiple date sources
$years_sql = "
    SELECT DISTINCT YEAR(COALESCE(SS.ScheduleDate, O.order_date, P.StartDate)) AS project_year
    FROM Projects P
    LEFT JOIN Orders O ON P.Orders_id = O.Orders_id
    LEFT JOIN appointment_sched SS ON P.Project_id = SS.project_id
    WHERE COALESCE(SS.ScheduleDate, O.order_date, P.StartDate) IS NOT NULL
    UNION
    SELECT DISTINCT YEAR(order_date) AS project_year
    FROM Orders
    WHERE order_date IS NOT NULL
    UNION
    SELECT DISTINCT YEAR(StartDate) AS project_year
    FROM Projects
    WHERE StartDate IS NOT NULL
    ORDER BY project_year DESC
";
$years_result = $conn->query($years_sql);
$available_years = [];
if ($years_result) {
    while ($row = $years_result->fetch_assoc()) {
        $available_years[] = $row['project_year'];
    }
}
// Ensure the current/selected year is in the list if no data exists for it yet
if (!in_array($current_year, $available_years)) {
    $available_years[] = $current_year;
    rsort($available_years);
}

// --- Dynamic Query Construction ---
$where_clauses = ["1=1"]; // Show all projects including completed ones

// Add Year filter - FIXED: Using multiple date sources
if ($selected_year && is_numeric($selected_year)) {
    $where_clauses[] = "(YEAR(COALESCE(SS.ScheduleDate, O.order_date, P.StartDate)) = " . $conn->real_escape_string($selected_year) . " 
                        OR YEAR(O.order_date) = " . $conn->real_escape_string($selected_year) . "
                        OR YEAR(P.StartDate) = " . $conn->real_escape_string($selected_year) . ")";
}

// Add Month filter - FIXED: Using multiple date sources
if ($selected_month && is_numeric($selected_month)) {
    $where_clauses[] = "(MONTH(COALESCE(SS.ScheduleDate, O.order_date, P.StartDate)) = " . $conn->real_escape_string($selected_month) . "
                        OR MONTH(O.order_date) = " . $conn->real_escape_string($selected_month) . "
                        OR MONTH(P.StartDate) = " . $conn->real_escape_string($selected_month) . ")";
}

$where_sql = "WHERE " . implode(' AND ', $where_clauses);

// MAIN QUERY: Fetch project info with joined client and staff names - FIXED
$sql = "
SELECT
    P.Project_id,
    P.Project_Name,
    COALESCE(SS.ScheduleDate, O.order_date, P.StartDate) AS AppointmentDate,
    C.Name AS ClientName,
    P.Status,
    P.Total_Cost,
    O.Payment_id,
    O.Orders_id,
    GROUP_CONCAT(DISTINCT S.Name SEPARATOR ', ') AS StaffAssigned
FROM
    Projects P
LEFT JOIN
    Orders O ON P.Orders_id = O.Orders_id
LEFT JOIN
    Customers C ON O.customer_id = C.customer_id
LEFT JOIN
    appointment_sched SS ON P.Project_id = SS.project_id
LEFT JOIN
    ProjectStaff PS ON P.Project_id = PS.Project_id
LEFT JOIN
    Staff S ON PS.Staff_id = S.Staff_id
$where_sql
GROUP BY
    P.Project_id, P.Project_Name, AppointmentDate, C.Name, P.Status, P.Total_Cost, O.Payment_id, O.Orders_id
ORDER BY
    COALESCE(SS.ScheduleDate, O.order_date, P.StartDate) DESC
LIMIT 50;
";

$result = $conn->query($sql);

// --- Aggregate Metrics ---
// Get total project count
$total_projects_sql = "SELECT COUNT(*) AS total_projects FROM Projects";
$total_projects = ($conn->query($total_projects_sql)->fetch_assoc()['total_projects']) ?? 0;

// Get paid project count - FIXED: Check if Payment_id is not NULL
$paid_projects_sql = "
    SELECT COUNT(DISTINCT P.Project_id) AS paid_projects
    FROM Projects P
    JOIN Orders O ON P.Orders_id = O.Orders_id
    WHERE O.Payment_id IS NOT NULL;
";
$paid_projects_result = $conn->query($paid_projects_sql);
$paid_projects = $paid_projects_result ? $paid_projects_result->fetch_assoc()['paid_projects'] : 0;

$payment_percentage = ($total_projects > 0) ? round(($paid_projects / $total_projects) * 100) : 0;

// Get status counts
$status_counts_sql = "
    SELECT Status, COUNT(*) as count 
    FROM Projects 
    GROUP BY Status
";
$status_counts_result = $conn->query($status_counts_sql);
$status_counts = [];
if ($status_counts_result) {
    while ($row = $status_counts_result->fetch_assoc()) {
        $status_counts[$row['Status']] = $row['count'];
    }
}

// Function to get materials used (Products) per project - FIXED
function getMaterialsUsed($conn, $project_id) {
    $sql = "
        SELECT
            PR.Name,
            OI.quantity
        FROM
            Projects P
        JOIN
            Orders O ON P.Orders_id = O.Orders_id
        JOIN
            orderitems OI ON O.Orders_id = OI.Orders_id
        JOIN
            Product PR ON OI.product_id = PR.Product_id
        WHERE
            P.Project_id = ?
            AND OI.product_id IS NOT NULL
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return "Error: " . $conn->error;
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $list[] = htmlspecialchars($row['Name']) . ' (' . $row['quantity'] . ')';
    }
    return empty($list) ? 'No materials' : implode(', ', $list);
}

// Function to format Project ID
function formatProjectId($project_id, $schedule_date) {
    $project_year = date('Y', strtotime($schedule_date ?? date('Y-m-d')));
    return "PRJ-" . $project_year . "-" . str_pad($project_id, 3, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALG | Projects</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f5f5;
        margin: 0;
        min-height: 100vh;
        color: #333;
    }
    .header {
        background: #3e3e3e;
        color: white;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .header-logo {
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: 0.05em;
    }
    .nav-link {
        color: white;
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        transition: all 0.2s;
        font-weight: 600;
    }
    .nav-link.active, .nav-link:hover {
        background-color: #d9d9d9;
        color: #1f2937;
    }
    .content-area {
        padding: 2rem;
    }
    .metric-card {
        background-color: white;
        padding: 1.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        min-width: 200px;
    }
    .metric-value {
        font-size: 3rem;
        font-weight: 700;
        color: #1f2937;
    }
    .metric-label {
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }
    .action-button {
        padding: 0.6rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background-color 0.2s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }
    .btn-pdf {
        background-color: #EF4444;
        color: white;
    }
    .btn-excel {
        background-color: #10B981;
        color: white;
    }
    .btn-pdf:hover {background-color: #DC2626;}
    .btn-excel:hover {background-color: #059669;}

    .btn-filter {
        background-color: #3B82F6;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        transition: background-color 0.2s;
        border: none;
        cursor: pointer;
    }
    .btn-filter:hover {
        background-color: #2563EB;
    }

    .projects-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
    }
    .projects-table th {
        background-color: #f3f4f6;
        color: #374151;
        padding: 1rem 1.5rem;
        text-align: left;
        font-size: 0.875rem;
        font-weight: 600;
        border-bottom: 2px solid #e5e7eb;
    }
    .projects-table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
        font-size: 0.9375rem;
    }
    .projects-table tr:hover {
        background-color: #f9fafb;
    }

    /* Status Coloring */
    .status-pending {color: #F59E0B; font-weight: 600;}
    .status-ongoing {color: #3B82F6; font-weight: 600;}
    .status-completed {color: #10B981; font-weight: 600;}
    .status-cancelled {color: #EF4444; font-weight: 600;}
    .payment-paid {color: #10B981; font-weight: 600;}
    .payment-pending {color: #EF4444; font-weight: 600;}

    select, input[type="text"] {
        padding: 0.5rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        font-size: 1rem;
        background-color: white;
    }

    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-badge-pending { background-color: #FEF3C7; color: #D97706; }
    .status-badge-ongoing { background-color: #DBEAFE; color: #1E40AF; }
    .status-badge-completed { background-color: #D1FAE5; color: #065F46; }
    .status-badge-cancelled { background-color: #FEE2E2; color: #DC2626; }

    @media (max-width: 1024px) {
        .metric-cards-row {
            flex-direction: column;
            gap: 15px !important;
        }
        .metric-card {
            width: 100%;
        }
        .actions-row {
            flex-direction: column;
            gap: 10px !important;
        }
        .actions-row > * {
            width: 100%;
        }
        .projects-table, .table-container {
            overflow-x: auto;
        }
    }
</style>
</head>
<body>

<header class="header">
    <div class="flex items-center">
        <div class="header-logo">ALG Enterprises</div>
    </div>
    <nav class="flex space-x-2">
        <a href="home.php" class="nav-link">Home</a>
        <a href="products.php" class="nav-link">Inventory</a>
        <a href="projects.php" class="nav-link active">Projects</a>
        <a href="staff.php" class="nav-link">Staff</a>
    </nav>
    <div class="flex items-center space-x-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white cursor-pointer" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.98 5.98 0 0010 16a5.979 5.98 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
        </svg>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
    </div>
</header>

<div class="content-area">

    <!-- Metrics and Export/Action Buttons -->
    <div class="flex flex-col lg:flex-row justify-between items-center mb-6 space-y-4 lg:space-y-0">
        <!-- Metrics Row -->
        <div class="flex metric-cards-row gap-5">
            <div class="metric-card">
                <div class="metric-label">Payment Percentage:</div>
                <div class="metric-value"><?php echo $payment_percentage; ?>%</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Total Projects:</div>
                <div class="metric-value"><?php echo $total_projects; ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Status Breakdown:</div>
                <div class="text-sm">
                    <?php foreach ($status_counts as $status => $count): ?>
                        <div class="flex justify-between mb-1">
                            <span class="status-<?php echo strtolower($status); ?>"><?php echo $status; ?>:</span>
                            <span class="font-semibold"><?php echo $count; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons Row -->
        <div class="flex actions-row gap-3">
            <button class="action-button btn-pdf">Export PDF</button>
            <button class="action-button btn-excel">Export Excel</button>
            <button class="action-button btn-pdf" onclick="window.location.href='project_calendar.php'">Calendar</button>
        </div>
    </div>

    <!-- Filters and Large Action Buttons -->
    <div class="flex flex-col lg:flex-row justify-between items-center mb-6 space-y-4 lg:space-y-0">
        <!-- Filters Section -->
        <form method="GET" action="projects.php" class="flex items-center space-x-3 w-full lg:w-auto flex-wrap">
            <label class="text-gray-600 font-semibold text-sm">Filter by:</label>

            <!-- YEAR FILTER -->
            <?php if (!empty($available_years)): ?>
            <select name="filter_year" class="p-2 border border-gray-300 rounded-md bg-white">
                <option value="">-- All Years --</option>
                <?php foreach ($available_years as $year): ?>
                    <option value="<?= $year ?>" <?= ((string)$year === (string)$selected_year) ? 'selected' : '' ?>>
                        <?= $year ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <!-- MONTH FILTER -->
            <select name="filter_month" class="p-2 border border-gray-300 rounded-md bg-white">
                <option value="">-- All Months --</option>
                <?php
                    $months = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    foreach ($months as $num => $name):
                ?>
                    <option value="<?= $num ?>" <?= ((string)$num === (string)$selected_month) ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- FILTER BUTTON -->
            <button type="submit" class="btn-filter">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V17l-4 4v-3.586L3.293 6.707A1 1 0 013 6V4z" />
                </svg>
                Filter
            </button>
        </form>

        <!-- Other Filter Tags and Large Action Buttons -->
        <div class="flex flex-col lg:flex-row space-y-4 lg:space-y-0 lg:space-x-3 w-full lg:w-auto">
            <button onclick="window.location.href='appointing.php'" class="action-button bg-gray-700 text-white hover:bg-gray-800 flex-1 lg:flex-none w-full lg:w-auto text-lg p-3">Appointing</button>
            <button onclick="window.location.href='management_form.php'" class="action-button bg-gray-700 text-white hover:bg-gray-800 flex-1 lg:flex-none w-full lg:w-auto text-lg p-3">Management Form</button>
        </div>
    </div>

    <!-- Projects Table -->
    <div class="table-container shadow-xl rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
        <?php if ($result && $result->num_rows > 0): ?>
            <table class="projects-table">
                <thead>
                    <tr>
                        <th>Project ID</th>
                        <th>Project Name</th>
                        <th>Appointment Date</th>
                        <th>Client Name</th>
                        <th>Staff Assigned</th>
                        <th>Materials Used</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $result->fetch_assoc()):
                    $project_code = formatProjectId($row['Project_id'], $row['AppointmentDate']);
                    $appt_date = $row['AppointmentDate'] ? date('M j, Y', strtotime($row['AppointmentDate'])) : 'Not Scheduled';
                    $materials = getMaterialsUsed($conn, $row['Project_id']);
                    $total_cost = "₱" . number_format($row['Total_Cost'], 2);

                    $status = $row['Status'];
                    $status_class = "status-" . strtolower($status);
                    $status_badge_class = "status-badge-" . strtolower($status);

                    $paid = ($row['Payment_id'] != NULL);
                ?>
                    <tr>
                        <td class="font-mono text-sm"><?= htmlspecialchars($project_code) ?></td>
                        <td class="font-semibold"><?= htmlspecialchars($row['Project_Name'] ?? 'Unnamed Project') ?></td>
                        <td><?= htmlspecialchars($appt_date) ?></td>
                        <td><?= htmlspecialchars($row['ClientName'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['StaffAssigned'] ?? 'Unassigned') ?></td>
                        <td class="text-sm"><?= $materials ?></td>
                        <td class="font-semibold"><?= htmlspecialchars($total_cost) ?></td>
                        <td>
                            <span class="status-badge <?= $status_badge_class ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                        </td>
                        <td class="<?= $paid ? 'payment-paid' : 'payment-pending' ?>">
                            <?= $paid ? 'Paid' : 'Pending' ?>
                        </td>
                        <td>
                            <a href="management_form.php?id=<?= $row['Project_id'] ?>" class="action-button bg-blue-600 text-white hover:bg-blue-700 text-sm px-3 py-1">
                                Manage
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <div class="p-4 flex justify-between items-center text-sm text-gray-500 bg-white border-t">
                <span>Showing <?php echo $result->num_rows; ?> projects</span>
                <div>
                    <button class="px-3 py-1 border rounded-l hover:bg-gray-50">← Previous</button>
                    <button class="px-3 py-1 border rounded-r hover:bg-gray-50">Next →</button>
                </div>
            </div>
        <?php else: ?>
            <div class="p-8 text-center text-gray-500 bg-white">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No projects found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    <?php echo (isset($_GET['filter_year']) || isset($_GET['filter_month'])) 
                        ? 'Try changing your filters or ' 
                        : 'Get started by creating a new project.' ?>
                </p>
                <div class="mt-6">
                    <a href="management_form.php" class="action-button bg-blue-600 text-white hover:bg-blue-700">
                        Create New Project
                    </a>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </div>

</div>

<?php if (isset($conn)) $conn->close(); ?>
</body>
</html>