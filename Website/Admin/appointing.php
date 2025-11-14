<?php
session_start();
include "../../db.php";

// Ensure $conn is valid
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check ../../db.php.");
}

// --- Data Fetching ---

// 1. Fetch all Appointments/Orders that need attention - FIXED QUERY
$sql_appointments = "
    SELECT
        O.Orders_id,
        ss.ScheduleDate AS schedule_date,
        C.Name AS ClientName,
        O.Status,
        P.Project_id,
        S.Name AS ServiceName,
        'Order' AS SourceType
    FROM
        Orders O
    JOIN
        Customers C ON O.customer_id = C.customer_id
    LEFT JOIN
        Services S ON O.Services_id = S.Services_id
    LEFT JOIN
        appointment_sched ss ON O.Orders_id = ss.Services_id
    LEFT JOIN
        Projects P ON O.Orders_id = P.Orders_id
    WHERE
        O.Status IN ('Pending', 'Ongoing', 'Completed')
    
    UNION ALL
    
    SELECT
        ss.sched_id AS Orders_id,
        ss.ScheduleDate AS schedule_date,
        c.Name AS ClientName,
        'Scheduled' AS Status,
        ss.project_id AS Project_id,
        COALESCE(s.Name, ss.appointment_type) AS ServiceName,
        'Schedule' AS SourceType
    FROM
        appointment_sched ss
    JOIN
        Customers c ON ss.customer_id = c.customer_id
    LEFT JOIN
        Services s ON ss.Services_id = s.Services_id
    WHERE
        ss.ScheduleDate IS NOT NULL
    
    ORDER BY
        schedule_date ASC
    LIMIT 8;
";
$appointments_result = $conn->query($sql_appointments);

// 2. Fetch all Staff for the assignment modal, INCLUDING POSITION
$sql_staff = "SELECT Staff_id, Name, Position FROM Staff ORDER BY Name ASC";
$staff_result = $conn->query($sql_staff);
$all_staff = [];
if ($staff_result) {
    while ($row = $staff_result->fetch_assoc()) {
        $all_staff[] = $row;
    }
}

/**
 * 3. Function to get current staff assigned to a project
 */
function getCurrentStaff($conn, $project_id) {
    if (!$project_id) return [];
    
    $sql = "
        SELECT S.Staff_id, S.Name
        FROM Staff S
        JOIN ProjectStaff PS ON S.Staff_id = PS.Staff_id
        WHERE PS.Project_id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in getCurrentStaff: " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $current_staff = [];
    while ($row = $res->fetch_assoc()) {
        $current_staff[] = $row;
    }
    if (isset($stmt)) $stmt->close();
    return $current_staff;
}

// --- Handle Project Creation POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_project') {
        $orders_id = $_POST['orders_id'] ?? null; 
        $source_type = $_POST['source_type'] ?? 'Order';
        
        if (is_numeric($orders_id)) {
            // Start Transaction for Data Safety
            $conn->begin_transaction();
            try {
                // PROJECT CREATION LOGIC
                
                // First, let's check if a project already exists for this order/schedule
                $check_sql = "SELECT Project_id FROM Projects WHERE Orders_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $orders_id);
                $check_stmt->execute();
                $existing_project = $check_stmt->get_result()->fetch_assoc();
                
                if ($existing_project) {
                    // Project already exists, redirect to it
                    header("Location: management_form.php?id=" . $existing_project['Project_id']);
                    exit;
                } else {
                    // Calculate total cost from order items if it's an order
                    $total_cost = 0;
                    if ($source_type === 'Order') {
                        $cost_sql = "
                            SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as total 
                            FROM orderitems oi 
                            WHERE oi.Orders_id = ?
                        ";
                        $cost_stmt = $conn->prepare($cost_sql);
                        $cost_stmt->bind_param("i", $orders_id);
                        $cost_stmt->execute();
                        $cost_result = $cost_stmt->get_result()->fetch_assoc();
                        $total_cost = $cost_result['total'] ?? 0;
                    }

                    // Create new project - FIXED: Removed project_name column
                    $insert_project_sql = "INSERT INTO Projects (Orders_id, StartDate, Total_Cost, Status) VALUES (?, NOW(), ?, 'Pending')";
                    
                    $stmt = $conn->prepare($insert_project_sql);
                    $stmt->bind_param("id", $orders_id, $total_cost);
                    $stmt->execute();
                    
                    // Get the ID of the newly created project
                    $new_project_id = $conn->insert_id;

                    if (!$new_project_id) {
                        throw new Exception("Failed to create new Project record.");
                    }

                    // If this was from a schedule, update the schedule with the project ID
                    if ($source_type === 'Schedule') {
                        $update_schedule_sql = "UPDATE appointment_sched SET project_id = ? WHERE sched_id = ?";
                        $update_stmt = $conn->prepare($update_schedule_sql);
                        $update_stmt->bind_param("ii", $new_project_id, $orders_id);
                        $update_stmt->execute();
                    }
                    
                    $conn->commit();
                    
                    // Redirect to management form with the new project ID
                    header("Location: management_form.php?id=" . $new_project_id);
                    exit;
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                // Store error in session to display after redirect
                $_SESSION['error'] = 'Project creation failed: ' . $e->getMessage();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
}

// Function to format Appointment ID
function formatApptId($orders_id, $date_str, $source_type = 'Order') {
    if (!$date_str) return "APPT-XXXX-N/A";
    $year = date('Y', strtotime($date_str));
    $prefix = $source_type === 'Schedule' ? 'SCH' : 'ORD';
    return "{$prefix}-" . $year . "-" . str_pad($orders_id, 4, '0', STR_PAD_LEFT);
}

// Function to format date and time
function formatDateTime($date_str) {
    if (!$date_str) return 'N/A';
    return date('M j, Y h:iA', strtotime($date_str));
}

// Function to format Project ID
function formatProjectId($project_id, $schedule_date) {
    if (!$project_id) return null;
    $project_year = date('Y', strtotime($schedule_date ?? date('Y-m-d')));
    return "PRJ-" . $project_year . "-" . str_pad($project_id, 3, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALG | Appointing Records</title>
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
        background: #1f2937;
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
        border-radius: 0.5rem;
        transition: all 0.2s;
        font-weight: 600;
    }
    .nav-link.active, .nav-link:hover {
        background-color: #374151;
        color: white;
    }
    .content-area {
        padding: 2rem;
    }
    .table-container {
        background-color: white;
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .appointing-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .appointing-table th {
        background-color: #eef2ff;
        color: #374151;
        padding: 1rem 1.5rem;
        text-align: left;
        font-size: 0.875rem;
        font-weight: 700;
        border-bottom: 2px solid #e5e7eb;
    }
    .appointing-table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
        font-size: 0.9375rem;
    }
    .appointing-table tr:hover {
        background-color: #f6f7f9;
    }
    .appointing-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Status Coloring - UPDATED for actual status values */
    .status-Completed {
        color: #059669;
        font-weight: 600;
        background-color: #d1fae5;
        padding: 0.25rem 0.5rem;
        border-radius: 0.5rem;
        display: inline-block;
        font-size: 0.8rem;
    }
    .status-Ongoing {
        color: #D97706;
        font-weight: 600;
        background-color: #fef3c7;
        padding: 0.25rem 0.5rem;
        border-radius: 0.5rem;
        display: inline-block;
        font-size: 0.8rem;
    }
    .status-Pending {
        color: #2563EB;
        font-weight: 600;
        background-color: #eff6ff;
        padding: 0.25rem 0.5rem;
        border-radius: 0.5rem;
        display: inline-block;
        font-size: 0.8rem;
    }
    .status-Scheduled {
        color: #7C3AED;
        font-weight: 600;
        background-color: #f3e8ff;
        padding: 0.25rem 0.5rem;
        border-radius: 0.5rem;
        display: inline-block;
        font-size: 0.8rem;
    }
    .action-button {
        background-color: #4F46E5;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        transition: background-color 0.2s, transform 0.1s;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        white-space: nowrap;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        border: none;
        cursor: pointer;
        font-size: 0.875rem;
    }
    .action-button:hover {
        background-color: #4338CA;
        transform: translateY(-1px);
    }
    .action-button.create-btn {
        background-color: #10B981;
    }
    .action-button.create-btn:hover {
        background-color: #059669;
    }
    .action-button.manage-btn {
        background-color: #8B5CF6;
    }
    .action-button.manage-btn:hover {
        background-color: #7C3AED;
    }

    /* Alert styles */
    .alert {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
    }
    .alert-error {
        background-color: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    .alert-success {
        background-color: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }

    .source-badge {
        font-size: 0.7rem;
        padding: 0.1rem 0.4rem;
        border-radius: 0.25rem;
        margin-left: 0.5rem;
    }
    .source-order {
        background-color: #dbeafe;
        color: #1e40af;
    }
    .source-schedule {
        background-color: #f3e8ff;
        color: #7c3aed;
    }
</style>
</head>
<body>

<header class="header">
    <div class="flex items-center space-x-4">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-indigo-300">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="16" y1="3" x2="16" y2="21"/><line x1="8" y1="3" x2="8" y2="21"/><line x1="3" y1="15" x2="21" y2="15"/>
        </svg>
        <div class="header-logo">ALG Enterprises</div>
    </div>
    <nav class="flex space-x-2">
        <a href="home.php" class="nav-link">Home</a>
        <a href="products.php" class="nav-link">Inventory</a>
        <a href="projects.php" class="nav-link active">Projects</a>
        <a href="staff.php" class="nav-link">Staff</a>
    </nav>
    <div class="flex items-center space-x-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-300 cursor-pointer" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.98 5.98 0 0010 16a5.979 5.98 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
        </svg>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white cursor-pointer hover:text-red-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
    </div>
</header>

<div class="content-area">
    <!-- Display Error Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= $_SESSION['error'] ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">Service & Appointment Assignments</h1>
        <div class="flex flex-wrap space-x-2 space-y-2 md:space-y-0">
             <button onclick="window.location.href='projects.php'" class="px-4 py-2 bg-indigo-600 text-white rounded-lg shadow-md hover:bg-indigo-700 transition">← Return to Projects</button>
             <button onclick="window.location.href='management_form.php'" class="px-4 py-2 bg-gray-600 text-white rounded-lg shadow-md hover:bg-gray-700 transition">Management Form</button>
             <button class="px-4 py-2 bg-red-600 text-white rounded-lg shadow-md hover:bg-red-700 transition">Export PDF</button>
             <button class="px-4 py-2 bg-green-600 text-white rounded-lg shadow-md hover:bg-green-700 transition">Export Excel</button>
        </div>
    </div>

    <!-- Appointing Table -->
    <div class="table-container">
        <div class="overflow-x-auto">
        <?php if ($appointments_result && $appointments_result->num_rows > 0): ?>
            <table class="appointing-table">
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>Client</th>
                        <th>Project ID</th> 
                        <th>Service Type</th> 
                        <th>Date & Time</th>
                        <th>Staff Assigned</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $appointments_result->fetch_assoc()):
                    $appt_id = formatApptId($row['Orders_id'], $row['schedule_date'], $row['SourceType']);
                    $appt_datetime = formatDateTime($row['schedule_date']);
                    $status_class = "status-" . str_replace(' ', '', $row['Status']);
                    $orders_id = $row['Orders_id'];
                    $project_id = $row['Project_id'];
                    $project_type = $row['ServiceName']; 
                    $source_type = $row['SourceType'];
                    $is_project_missing = empty($project_id);

                    // Get currently assigned staff
                    $current_staff = getCurrentStaff($conn, $project_id);
                    $staff_names = implode(', ', array_map(fn($s) => htmlspecialchars($s['Name']), $current_staff));
                ?>
                    <tr>
                        <td class="font-semibold text-indigo-700">
                            <?= htmlspecialchars($appt_id) ?>
                            <span class="source-badge source-<?= strtolower($source_type) ?>"><?= $source_type ?></span>
                        </td>
                        <td><?= htmlspecialchars($row['ClientName']) ?></td>
                        <td>
                            <?php if ($project_id): ?>
                                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($project_id) ?></span>
                            <?php else: ?>
                                <span class="text-red-500 font-medium text-xs">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-gray-600 text-sm"><?= htmlspecialchars($project_type) ?></span>
                        </td>
                        <td><?= htmlspecialchars($appt_datetime) ?></td>
                        <td>
                            <span class="text-gray-600 text-sm italic">
                                <?= $project_id ? ($staff_names ?: 'Unassigned') : 'N/A' ?>
                            </span>
                        </td>
                        <td><span class="<?= $status_class ?>"><?= htmlspecialchars($row['Status']) ?></span></td>
                        <td>
                            <div class="flex space-x-2">
                                <?php if ($is_project_missing): ?>
                                    <!-- Create Project Form -->
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="create_project">
                                        <input type="hidden" name="orders_id" value="<?= $orders_id ?>">
                                        <input type="hidden" name="source_type" value="<?= $source_type ?>">
                                        <button type="submit" class="action-button create-btn">
                                            Assign
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Direct link to management form for existing projects -->
                                    <a href="management_form.php?id=<?= $project_id ?>" class="action-button manage-btn">
                                        Manage Project
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <div class="p-4 flex justify-end text-sm text-gray-500 border-t border-gray-100">
                Showing next 8 appointments | <a href="#" class="text-indigo-600 hover:underline ml-1">View All</a>
            </div>
        <?php else: ?>
            <p class="p-8 text-center text-gray-500 text-lg">No appointments found requiring staff assignment.</p>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Simple confirmation for project creation
document.addEventListener('DOMContentLoaded', function() {
    const createForms = document.querySelectorAll('form[action=""]');
    
    createForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Assigning...';
        });
    });
});
</script>

<?php 
// Close database connection at the end of script execution
if (isset($conn)) $conn->close(); 
?>