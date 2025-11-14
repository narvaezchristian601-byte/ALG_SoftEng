<?php
// Project_Logs.php
include "../../db.php";
session_start();

// Ensure $conn is valid
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check ../../db.php.");
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Filter Input
$filter_period = $_GET['filter_period'] ?? 'all';
$current_date = date('Y-m-d');

// Handle Add Log Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_log') {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Invalid security token.';
            header("Location: Project_Logs.php");
            exit;
        }
        
        // Get and validate form data
        $project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
        $staff_id = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
        $log_date = $_POST['log_date'] ?? date('Y-m-d');
        $location = trim($_POST['location'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $client_feedback = trim($_POST['client_feedback'] ?? '');
        
        // Validate required fields
        if (!$project_id || !$staff_id) {
            $_SESSION['error'] = 'Project and Staff selection are required.';
            header("Location: Project_Logs.php");
            exit;
        }
        
        // Validate date
        if (!strtotime($log_date)) {
            $_SESSION['error'] = 'Invalid date format.';
            header("Location: Project_Logs.php");
            exit;
        }
        
        try {
            // First, let's check if project_logs table exists and has the correct structure
            $check_table = $conn->query("SHOW TABLES LIKE 'project_logs'");
            if ($check_table->num_rows == 0) {
                // Create the project_logs table if it doesn't exist
                $create_table_sql = "
                    CREATE TABLE IF NOT EXISTS project_logs (
                        log_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        project_id INT(11) NOT NULL,
                        staff_id INT(11) NOT NULL,
                        log_date DATE NOT NULL,
                        location VARCHAR(255) DEFAULT NULL,
                        notes TEXT DEFAULT NULL,
                        client_feedback TEXT DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (project_id) REFERENCES projects(Project_id) ON DELETE CASCADE,
                        FOREIGN KEY (staff_id) REFERENCES staff(Staff_id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ";
                if (!$conn->query($create_table_sql)) {
                    throw new Exception("Failed to create project_logs table: " . $conn->error);
                }
            }
            
            // Insert project log
            $stmt = $conn->prepare("INSERT INTO project_logs (project_id, staff_id, log_date, location, notes, client_feedback) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $project_id, $staff_id, $log_date, $location, $notes, $client_feedback);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Project log added successfully!';
            } else {
                throw new Exception("Failed to add project log: " . $stmt->error);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error adding project log: ' . $e->getMessage();
        }
        
        header("Location: Project_Logs.php");
        exit;
    }
}

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
                $where_conditions[] = "PL.log_date = ?";
                $params[] = $current_date;
                $types .= 's';
                break;
            case 'week':
                $where_conditions[] = "PL.log_date >= DATE_SUB(?, INTERVAL 7 DAY)";
                $params[] = $current_date;
                $types .= 's';
                break;
            case 'month':
                $where_conditions[] = "PL.log_date >= DATE_SUB(?, INTERVAL 30 DAY)";
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

// Fetch active projects for the dropdown
$projects_sql = "
    SELECT 
        P.Project_id,
        P.Project_Name,
        C.Name AS client_name,
        SV.Name AS service_name
    FROM Projects P
    LEFT JOIN Orders O ON P.Orders_id = O.Orders_id
    LEFT JOIN Customers C ON O.customer_id = C.customer_id
    LEFT JOIN Services SV ON O.Services_id = SV.Services_id
    WHERE P.Status IN ('Pending', 'Ongoing')
    ORDER BY P.Project_Name
";
$projects_result = $conn->query($projects_sql);
$active_projects = [];
if ($projects_result) {
    $active_projects = $projects_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch staff for the dropdown
$staff_sql = "SELECT Staff_id, Name, Position FROM Staff WHERE Role != 'Admin' ORDER BY Name";
$staff_result = $conn->query($staff_sql);
$staff_list = [];
if ($staff_result) {
    $staff_list = $staff_result->fetch_all(MYSQLI_ASSOC);
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
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALG | Project Logs</title>
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
    .action-button {
        padding: 0.6rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background-color 0.2s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        display: inline-block;
        text-align: center;
        border: none;
        cursor: pointer;
    }
    .btn-primary {
        background-color: #3B82F6;
        color: white;
    }
    .btn-primary:hover {
        background-color: #2563EB;
    }
    .btn-success {
        background-color: #10B981;
        color: white;
    }
    .btn-success:hover {
        background-color: #059669;
    }
    .btn-filter {
        background-color: #6B7280;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        transition: background-color 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-filter:hover {
        background-color: #4B5563;
    }
    .btn-filter.active {
        background-color: #3B82F6;
    }

    .logs-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
    }
    .logs-table th {
        background-color: #f3f4f6;
        color: #374151;
        padding: 1rem 1.5rem;
        text-align: left;
        font-size: 0.875rem;
        font-weight: 600;
        border-bottom: 2px solid #e5e7eb;
    }
    .logs-table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
        font-size: 0.9375rem;
    }
    .logs-table tr:hover {
        background-color: #f9fafb;
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
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .modal-header {
        margin-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 1rem;
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
    .required-field::after {
        content: " *";
        color: #ef4444;
    }
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        font-size: 0.875rem;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    .form-textarea {
        resize: vertical;
        min-height: 80px;
    }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        border-top: 1px solid #e5e7eb;
        padding-top: 1rem;
    }
    .btn {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }
    .btn-primary {
        background-color: #10B981;
        color: white;
    }
    .btn-primary:hover {
        background-color: #059669;
    }
    .btn-secondary {
        background-color: #6b7280;
        color: white;
    }
    .btn-secondary:hover {
        background-color: #4b5563;
    }
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

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
    .alert-info {
        background-color: #eff6ff;
        color: #1e40af;
        border: 1px solid #dbeafe;
    }

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

    /* Searchable Dropdown Styles */
    .searchable-dropdown {
        position: relative;
    }
    .search-input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        font-size: 0.875rem;
    }
    .search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    .dropdown-options {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1001;
        display: none;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .dropdown-option {
        padding: 0.5rem;
        cursor: pointer;
        border-bottom: 1px solid #f3f4f6;
    }
    .dropdown-option:hover {
        background-color: #f3f4f6;
    }
    .dropdown-option:last-child {
        border-bottom: none;
    }
    .selected-staff {
        margin-top: 0.5rem;
        padding: 0.5rem;
        background-color: #f3f4f6;
        border-radius: 0.375rem;
        font-size: 0.875rem;
    }
    .hidden {
        display: none;
    }

    @media (max-width: 1024px) {
        .actions-row {
            flex-direction: column;
            gap: 10px !important;
        }
        .actions-row > * {
            width: 100%;
        }
        .logs-table, .table-container {
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
        <a href="projects.php" class="nav-link">Projects</a>
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
    <!-- Display Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- Header and Actions -->
    <div class="flex flex-col lg:flex-row justify-between items-center mb-6 space-y-4 lg:space-y-0">
        <h1 class="text-3xl font-bold text-gray-800">Project Logs (Admin)</h1>
        <div class="flex actions-row gap-3">
            <button class="action-button btn-primary" onclick="openAddModal()">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Log Entry
            </button>
            <button onclick="window.location.href='projects.php'" class="action-button bg-gray-600 text-white hover:bg-gray-700">
                ← Back to Projects
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col lg:flex-row justify-between items-center mb-6 space-y-4 lg:space-y-0">
        <div class="flex items-center space-x-3">
            <label class="text-gray-600 font-semibold text-sm">Filter by Period:</label>
            <div class="flex space-x-2">
                <a href="Project_Logs.php?filter_period=all" 
                   class="btn-filter <?= $filter_period === 'all' ? 'active' : '' ?>">
                    All Time
                </a>
                <a href="Project_Logs.php?filter_period=day" 
                   class="btn-filter <?= $filter_period === 'day' ? 'active' : '' ?>">
                    Today
                </a>
                <a href="Project_Logs.php?filter_period=week" 
                   class="btn-filter <?= $filter_period === 'week' ? 'active' : '' ?>">
                    This Week
                </a>
                <a href="Project_Logs.php?filter_period=month" 
                   class="btn-filter <?= $filter_period === 'month' ? 'active' : '' ?>">
                    This Month
                </a>
            </div>
        </div>
        
        <div class="text-sm text-gray-600">
            <?php
            if ($logs_result) {
                $total_logs = $logs_result->num_rows;
                echo "Showing " . $total_logs . " log" . ($total_logs !== 1 ? 's' : '');
            } else {
                echo "No logs available";
            }
            ?>
        </div>
    </div>

    <!-- Project Logs Table -->
    <div class="table-container shadow-xl rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
        <?php if ($logs_result && $logs_result->num_rows > 0): ?>
            <table class="logs-table">
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
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">
                    <?php echo ($table_check->num_rows == 0) ? 'Project Logs System Not Set Up' : 'No Project Logs Found'; ?>
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    <?php if ($table_check->num_rows == 0): ?>
                        The project logs system needs to be initialized. Click the button below to set it up.
                    <?php else: ?>
                        <?= $filter_period !== 'all' ? 'Try changing your filters or ' : '' ?>
                        Get started by adding your first project log.
                    <?php endif; ?>
                </p>
                <div class="mt-6">
                    <button onclick="openAddModal()" class="action-button btn-success">
                        <?php echo ($table_check->num_rows == 0) ? 'Initialize Log System' : 'Add Log Entry'; ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Log Modal -->
<div id="addLogModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add Project Log Entry</h3>
        </div>
        <div class="modal-body">
            <form id="addLogForm" method="POST" action="">
                <input type="hidden" name="action" value="add_log">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="staff_id" id="selectedStaffId" value="">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label required-field">Project</label>
                        <select name="project_id" class="form-select" required>
                            <option value="">Select Project</option>
                            <?php foreach ($active_projects as $project): ?>
                                <option value="<?= $project['Project_id'] ?>">
                                    <?= htmlspecialchars($project['Project_Name']) ?> - <?= htmlspecialchars($project['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required-field">Staff Member</label>
                        <div class="searchable-dropdown">
                            <input type="text" 
                                   id="staffSearch" 
                                   class="search-input" 
                                   placeholder="Type to search staff..." 
                                   autocomplete="off">
                            <div id="staffOptions" class="dropdown-options"></div>
                            <div id="selectedStaff" class="selected-staff hidden"></div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Log Date</label>
                        <input type="date" name="log_date" class="form-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-input" placeholder="Enter location...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" placeholder="Enter project notes, observations, or updates..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Client Feedback</label>
                    <textarea name="client_feedback" class="form-textarea" placeholder="Enter client feedback or comments..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitLogForm()">Add Log Entry</button>
        </div>
    </div>
</div>

<script>
// Staff data from PHP
const staffData = <?php echo json_encode($staff_list); ?>;

// Modal Functions
function openAddModal() {
    document.getElementById('addLogModal').style.display = 'flex';
    // Reset staff selection when opening modal
    resetStaffSelection();
}

function closeAddModal() {
    document.getElementById('addLogModal').style.display = 'none';
    document.getElementById('addLogForm').reset();
    resetStaffSelection();
}

function resetStaffSelection() {
    document.getElementById('selectedStaffId').value = '';
    document.getElementById('selectedStaff').classList.add('hidden');
    document.getElementById('staffSearch').value = '';
    document.getElementById('staffOptions').innerHTML = '';
    document.getElementById('staffOptions').style.display = 'none';
}

// Searchable dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const staffSearch = document.getElementById('staffSearch');
    const staffOptions = document.getElementById('staffOptions');
    const selectedStaffId = document.getElementById('selectedStaffId');
    const selectedStaffDisplay = document.getElementById('selectedStaff');

    staffSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        staffOptions.innerHTML = '';
        
        if (searchTerm.length === 0) {
            staffOptions.style.display = 'none';
            return;
        }

        const filteredStaff = staffData.filter(staff => 
            staff.Name.toLowerCase().includes(searchTerm) ||
            staff.Position.toLowerCase().includes(searchTerm)
        );

        if (filteredStaff.length === 0) {
            staffOptions.innerHTML = '<div class="dropdown-option">No staff members found</div>';
        } else {
            filteredStaff.forEach(staff => {
                const option = document.createElement('div');
                option.className = 'dropdown-option';
                option.innerHTML = `
                    <div class="font-medium">${staff.Name}</div>
                    <div class="text-xs text-gray-600">${staff.Position}</div>
                `;
                option.addEventListener('click', function() {
                    selectStaff(staff.Staff_id, staff.Name, staff.Position);
                });
                staffOptions.appendChild(option);
            });
        }
        
        staffOptions.style.display = 'block';
    });

    function selectStaff(staffId, staffName, staffPosition) {
        selectedStaffId.value = staffId;
        selectedStaffDisplay.innerHTML = `
            <div class="font-medium">${staffName}</div>
            <div class="text-xs text-gray-600">${staffPosition}</div>
        `;
        selectedStaffDisplay.classList.remove('hidden');
        staffSearch.value = '';
        staffOptions.style.display = 'none';
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.searchable-dropdown')) {
            staffOptions.style.display = 'none';
        }
    });

    // Auto-fill current date if not set
    const dateInput = document.querySelector('input[name="log_date"]');
    if (dateInput && !dateInput.value) {
        dateInput.value = '<?= date('Y-m-d') ?>';
    }
});

function submitLogForm() {
    const form = document.getElementById('addLogForm');
    const projectSelect = form.querySelector('select[name="project_id"]');
    const staffIdInput = document.getElementById('selectedStaffId');
    
    // Validate required fields
    if (!projectSelect.value) {
        alert('Please select a project');
        projectSelect.focus();
        return;
    }
    
    if (!staffIdInput.value) {
        alert('Please select a staff member');
        document.getElementById('staffSearch').focus();
        return;
    }
    
    form.submit();
}

// Toggle notes expansion
function toggleNotes(element) {
    element.classList.toggle('expanded');
}

// Close modal when clicking outside
document.getElementById('addLogModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddModal();
    }
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