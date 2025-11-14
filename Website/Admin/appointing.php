<?php
session_start();
include "../../db.php";

// Ensure $conn is valid
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check ../../db.php.");
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Helper Functions ---

/**
 * Execute prepared statement with consistent error handling
 */
function executeStatement($stmt, $errorMessage) {
    if (!$stmt->execute()) {
        error_log("SQL Error: " . $stmt->error);
        throw new Exception($errorMessage);
    }
    return true;
}

/**
 * Verify if source record exists
 */
function verifySourceExists($conn, $source_id, $source_type) {
    $sql = "SELECT sched_id FROM appointment_sched WHERE sched_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    
    $stmt->bind_param("i", $source_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (bool)$result;
}

// --- Data Fetching ---

// 1. Fetch only Appointments (Schedules) that need attention - REMOVED ORDERS
$sql_appointments = "
    -- Schedules without projects
    SELECT
        ss.sched_id AS Orders_id,
        ss.ScheduleDate AS schedule_date,
        c.Name AS ClientName,
        c.PhoneNumber AS ClientPhone,
        'Scheduled' AS Status,
        ss.project_id AS Project_id,
        COALESCE(ss.appointment_type, 'Appointment') AS ServiceName,
        'Schedule' AS SourceType
    FROM
        appointment_sched ss
    JOIN
        Customers c ON ss.customer_id = c.customer_id
    LEFT JOIN
        Projects P ON ss.project_id = P.Project_id
    WHERE
        ss.ScheduleDate IS NOT NULL
        AND (ss.project_id IS NULL OR ss.project_id = 0 OR P.Project_id IS NULL)
    ORDER BY
        schedule_date ASC
    LIMIT 8;
";

$appointments_result = $conn->query($sql_appointments);
if (!$appointments_result) {
    error_log("Appointments query failed: " . $conn->error);
    $appointments_result = null;
} else {
    error_log("Debug: Found " . $appointments_result->num_rows . " appointments needing project creation");
}

// 2. Fetch all Staff for the assignment modal, INCLUDING POSITION
$sql_staff = "SELECT Staff_id, Name, Position FROM Staff ORDER BY Name ASC";
$staff_result = $conn->query($sql_staff);
$all_staff = [];
if ($staff_result) {
    while ($row = $staff_result->fetch_assoc()) {
        $all_staff[] = $row;
    }
} else {
    error_log("Staff query failed: " . $conn->error);
}

// --- Handle Project Creation POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_project') {
        
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Invalid security token.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // Input validation and sanitization
        $source_id = $_POST['orders_id'] ?? null; 
        $source_type = 'Schedule'; // Force to Schedule only
        $project_name = trim($_POST['project_name'] ?? '');
        
        // Validate project name
        if (empty($project_name)) {
            $_SESSION['error'] = 'Project name is required.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        if (!is_numeric($source_id) || $source_id <= 0) {
            $_SESSION['error'] = 'Invalid appointment ID provided.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        $source_id = (int)$source_id;
        
        // Verify source exists
        if (!verifySourceExists($conn, $source_id, $source_type)) {
            $_SESSION['error'] = 'Appointment record not found.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // Start Transaction for Data Safety
        $conn->begin_transaction();
        try {
            // Check if a project already exists
            $check_sql = "SELECT Project_id FROM Projects WHERE Project_id IN (SELECT project_id FROM appointment_sched WHERE sched_id = ?)";
            
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $source_id);
            executeStatement($check_stmt, "Failed to check existing project");
            $existing_project = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if ($existing_project) {
                // Project already exists, redirect to it
                $conn->commit();
                header("Location: management_form.php?id=" . $existing_project['Project_id']);
                exit;
            }
            
            // Calculate total cost and get project details
            $total_cost = 0;
            $orders_id_for_project = NULL;
            $customer_id = NULL;
            
            // Get schedule details
            $schedule_sql = "
                SELECT ss.sched_id, ss.customer_id, c.Name as client_name, COALESCE(ss.appointment_type, 'Appointment') as service_name, ss.Services_id
                FROM appointment_sched ss 
                JOIN Customers c ON ss.customer_id = c.customer_id 
                WHERE ss.sched_id = ?
            ";
            $schedule_stmt = $conn->prepare($schedule_sql);
            $schedule_stmt->bind_param("i", $source_id);
            executeStatement($schedule_stmt, "Failed to fetch schedule details");
            $schedule_details = $schedule_stmt->get_result()->fetch_assoc();
            $schedule_stmt->close();
            
            if ($schedule_details) {
                $customer_id = $schedule_details['customer_id'];
                
                // For schedules, we need to create an Order first since Projects.Orders_id cannot be NULL
                $services_id = $schedule_details['Services_id'];
                
                // If Services_id is NULL, use a default valid service
                if (!$services_id) {
                    $default_service_sql = "SELECT Services_id FROM Services LIMIT 1";
                    $default_service_result = $conn->query($default_service_sql);
                    if ($default_service_result && $default_service_result->num_rows > 0) {
                        $default_service = $default_service_result->fetch_assoc();
                        $services_id = $default_service['Services_id'];
                    } else {
                        throw new Exception("No services available in the system. Please add services first.");
                    }
                }
                
                // Verify the service exists
                $verify_service_sql = "SELECT Services_id FROM Services WHERE Services_id = ?";
                $verify_service_stmt = $conn->prepare($verify_service_sql);
                $verify_service_stmt->bind_param("i", $services_id);
                executeStatement($verify_service_stmt, "Service verification failed");
                $service_exists = $verify_service_stmt->get_result()->fetch_assoc();
                $verify_service_stmt->close();
                
                if (!$service_exists) {
                    throw new Exception("Invalid service ID. Please check service configuration.");
                }
                
                // Validate customer_id exists
                if (!$customer_id) {
                    throw new Exception("No customer associated with this schedule. Please check schedule configuration.");
                }
                
                // Verify customer exists
                $verify_customer_sql = "SELECT customer_id FROM Customers WHERE customer_id = ?";
                $verify_customer_stmt = $conn->prepare($verify_customer_sql);
                $verify_customer_stmt->bind_param("i", $customer_id);
                executeStatement($verify_customer_stmt, "Customer verification failed");
                $customer_exists = $verify_customer_stmt->get_result()->fetch_assoc();
                $verify_customer_stmt->close();
                
                if (!$customer_exists) {
                    throw new Exception("Invalid customer ID. Please check customer configuration.");
                }
                
                $create_order_sql = "
                    INSERT INTO Orders (Services_id, customer_id, total_amount, order_date, status, stock_adjusted, Payment_id) 
                    VALUES (?, ?, 0, NOW(), 'Pending', 0, NULL)
                ";
                $order_stmt = $conn->prepare($create_order_sql);
                $order_stmt->bind_param("ii", $services_id, $customer_id);
                executeStatement($order_stmt, "Failed to create order for schedule");
                $orders_id_for_project = $conn->insert_id;
                $order_stmt->close();
            } else {
                throw new Exception("Appointment not found.");
            }

            // Validate that we have all required data before creating project
            if (!$orders_id_for_project) {
                throw new Exception("Failed to obtain valid Orders_id for project creation.");
            }
            
            if (!$customer_id) {
                throw new Exception("No customer associated with this project. Please check data configuration.");
            }

            // Prevent race condition with row locking
            $lock_sql = "SELECT Orders_id FROM Orders WHERE Orders_id = ? FOR UPDATE";
            $lock_stmt = $conn->prepare($lock_sql);
            $lock_stmt->bind_param("i", $orders_id_for_project);
            executeStatement($lock_stmt, "Failed to acquire lock");
            $lock_stmt->close();

            // Create new project
            $insert_project_sql = "INSERT INTO Projects (Project_Name, Orders_id, StartDate, Total_Cost, Status) VALUES (?, ?, NOW(), ?, 'Pending')";
            
            $stmt = $conn->prepare($insert_project_sql);
            $stmt->bind_param("sid", $project_name, $orders_id_for_project, $total_cost);
            executeStatement($stmt, "Failed to create project");
            
            // Get the ID of the newly created project
            $new_project_id = $conn->insert_id;

            if (!$new_project_id) {
                throw new Exception("Failed to get new Project ID.");
            }

            // Update the schedule with the project ID
            $update_schedule_sql = "UPDATE appointment_sched SET project_id = ? WHERE sched_id = ?";
            $update_stmt = $conn->prepare($update_schedule_sql);
            $update_stmt->bind_param("ii", $new_project_id, $source_id);
            executeStatement($update_stmt, "Failed to update schedule");
            $update_stmt->close();
            
            $conn->commit();
            
            // Log successful project creation
            error_log("Project created successfully: ID $new_project_id from appointment $source_id");
            
            // Redirect to management form with the new project ID
            header("Location: management_form.php?id=" . $new_project_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Project creation error: " . $e->getMessage());
            $_SESSION['error'] = 'Project creation failed. Please try again.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Function to format Appointment ID
function formatApptId($sched_id, $date_str) {
    if (!$date_str) return "APPT-XXXX-N/A";
    $year = date('Y', strtotime($date_str));
    return "SCH-" . $year . "-" . str_pad($sched_id, 4, '0', STR_PAD_LEFT);
}

// Function to format date and time
function formatDateTime($date_str) {
    if (!$date_str) return 'N/A';
    return date('M j, Y h:iA', strtotime($date_str));
}

// Function to format phone number
function formatPhoneNumber($phone) {
    if (!$phone) return 'N/A';
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format as (XXX) XXX-XXXX if it's 10 digits
    if (strlen($phone) === 10) {
        return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    
    // Format as +X (XXX) XXX-XXXX if it's 11 digits starting with 1
    if (strlen($phone) === 11 && $phone[0] === '1') {
        return '+1 (' . substr($phone, 1, 3) . ') ' . substr($phone, 4, 3) . '-' . substr($phone, 7);
    }
    
    // Return as is if it doesn't match expected formats
    return $phone;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALG | Appointment Records</title>
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

    /* Status Coloring */
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

    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .modal-content {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .modal-header {
        margin-bottom: 1rem;
    }
    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
    }
    .modal-body {
        margin-bottom: 1.5rem;
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #374151;
    }
    .form-input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        font-size: 0.875rem;
    }
    .form-input:focus {
        outline: none;
        border-color: #3b82f6;
    }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }
    .btn {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-primary {
        background-color: #10B981;
        color: white;
        border: none;
    }
    .btn-primary:hover {
        background-color: #059669;
    }
    .btn-secondary {
        background-color: #6b7280;
        color: white;
        border: none;
    }
    .btn-secondary:hover {
        background-color: #4b5563;
    }
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
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
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">Appointment Assignments</h1>
        <div class="flex flex-wrap space-x-2 space-y-2 md:space-y-0">
             <button onclick="window.location.href='projects.php'" class="px-4 py-2 bg-indigo-600 text-white rounded-lg shadow-md hover:bg-indigo-700 transition">← Return to Projects</button>
             <button onclick="window.location.href='management_form.php'" class="px-4 py-2 bg-gray-600 text-white rounded-lg shadow-md hover:bg-gray-700 transition">Management Form</button>
             <button class="px-4 py-2 bg-red-600 text-white rounded-lg shadow-md hover:bg-red-700 transition">Export PDF</button>
             <button class="px-4 py-2 bg-green-600 text-white rounded-lg shadow-md hover:bg-green-700 transition">Export Excel</button>
        </div>
    </div>

    <!-- Appointments Table -->
    <div class="table-container">
        <div class="overflow-x-auto">
        <?php if ($appointments_result && $appointments_result->num_rows > 0): ?>
            <table class="appointing-table">
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>Client</th>
                        <th>Phone Number</th>
                        <th>Service Type</th> 
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $appointments_result->fetch_assoc()):
                    $appt_id = formatApptId($row['Orders_id'], $row['schedule_date']);
                    $appt_datetime = formatDateTime($row['schedule_date']);
                    $status_class = "status-Scheduled";
                    $source_id = $row['Orders_id'];
                    $project_id = $row['Project_id'];
                    $project_type = $row['ServiceName']; 
                    $is_project_missing = empty($project_id);
                    $phone_number = formatPhoneNumber($row['ClientPhone']);
                ?>
                    <tr>
                        <td class="font-semibold text-indigo-700">
                            <?= htmlspecialchars($appt_id) ?>
                        </td>
                        <td><?= htmlspecialchars($row['ClientName']) ?></td>
                        <td>
                            <span class="text-gray-600 text-sm"><?= htmlspecialchars($phone_number) ?></span>
                        </td>
                        <td>
                            <span class="text-gray-600 text-sm"><?= htmlspecialchars($project_type) ?></span>
                        </td>
                        <td><?= htmlspecialchars($appt_datetime) ?></td>
                        <td><span class="<?= $status_class ?>"><?= htmlspecialchars($row['Status']) ?></span></td>
                        <td>
                            <div class="flex space-x-2">
                                <?php if ($is_project_missing): ?>
                                    <!-- Create Project Button that opens modal -->
                                    <button type="button" 
                                            class="action-button create-btn create-project-btn"
                                            data-source-id="<?= $source_id ?>"
                                            data-client-name="<?= htmlspecialchars($row['ClientName']) ?>"
                                            data-service-type="<?= htmlspecialchars($project_type) ?>">
                                        Create Project
                                    </button>
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
            <div class="p-8 text-center text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No appointments found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    All appointments have been assigned to projects or there are no pending appointments.
                </p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Project Modal -->
<div id="createProjectModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Create New Project</h3>
        </div>
        <div class="modal-body">
            <form id="createProjectForm" method="POST" action="">
                <input type="hidden" name="action" value="create_project">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="orders_id" id="modal_orders_id">
                <input type="hidden" name="source_type" value="Schedule">
                
                <div class="form-group">
                    <label class="form-label">Client</label>
                    <input type="text" id="modal_client_name" class="form-input" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Service Type</label>
                    <input type="text" id="modal_service_type" class="form-input" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label required-field">Project Name</label>
                    <input type="text" name="project_name" id="modal_project_name" class="form-input" required 
                           placeholder="Enter project name...">
                    <small class="text-gray-500">This name will be used to identify the project</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmCreateBtn" onclick="submitProjectForm()">Create Project</button>
        </div>
    </div>
</div>

<script>
// Enhanced confirmation for project creation
document.addEventListener('DOMContentLoaded', function() {
    const createButtons = document.querySelectorAll('.create-project-btn');
    
    createButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const sourceId = this.getAttribute('data-source-id');
            const clientName = this.getAttribute('data-client-name');
            const serviceType = this.getAttribute('data-service-type');
            
            openModal(sourceId, clientName, serviceType);
        });
    });
    
    // Display any PHP errors in console for debugging
    <?php if (isset($_SESSION['error'])): ?>
        console.error('Project creation error:', '<?= addslashes($_SESSION['error']) ?>');
    <?php endif; ?>
});

function openModal(sourceId, clientName, serviceType) {
    const modal = document.getElementById('createProjectModal');
    document.getElementById('modal_orders_id').value = sourceId;
    document.getElementById('modal_client_name').value = clientName;
    document.getElementById('modal_service_type').value = serviceType;
    
    // Set default project name
    const defaultProjectName = serviceType + ' - ' + clientName;
    document.getElementById('modal_project_name').value = defaultProjectName;
    
    modal.style.display = 'flex';
    document.getElementById('modal_project_name').focus();
}

function closeModal() {
    const modal = document.getElementById('createProjectModal');
    modal.style.display = 'none';
    document.getElementById('createProjectForm').reset();
}

function submitProjectForm() {
    const projectName = document.getElementById('modal_project_name').value.trim();
    const confirmBtn = document.getElementById('confirmCreateBtn');
    
    if (!projectName) {
        alert('Please enter a project name.');
        document.getElementById('modal_project_name').focus();
        return;
    }
    
    // Disable button and show loading state
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Creating...';
    
    // Submit the form
    document.getElementById('createProjectForm').submit();
}

// Close modal when clicking outside
document.getElementById('createProjectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php 
// Close database connection at the end of script execution
if (isset($conn)) $conn->close(); 
?>