<?php
// management_form.php - Enhanced Project Management Form
include "../../db.php";
session_start();

// Check for database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check ../../db.php.");
}

$project = [];
$staff_list = [];
$materials_list = [];
$current_staff_ids = [];
$current_materials = [];
$message = '';
$status = '';
$project_id_from_url = null;
$is_new_project = false;
$current_user = $_SESSION['user_name'] ?? 'Admin';

// ===================================================================
// 1. Handle Form Submission (Create/Update Logic)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $orders_id = filter_input(INPUT_POST, 'orders_id', FILTER_VALIDATE_INT);
    $schedule_date = trim($_POST['schedule_date'] ?? '');
    $schedule_time = trim($_POST['schedule_time'] ?? '');
    $status_update = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS);
    $project_name = filter_input(INPUT_POST, 'project_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $total_cost = filter_input(INPUT_POST, 'total_cost', FILTER_VALIDATE_FLOAT);
    $client_name = filter_input(INPUT_POST, 'client_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $client_contact = filter_input(INPUT_POST, 'client_contact', FILTER_SANITIZE_SPECIAL_CHARS);
    $client_email = filter_input(INPUT_POST, 'client_email', FILTER_SANITIZE_EMAIL);
    $project_site = filter_input(INPUT_POST, 'project_site', FILTER_SANITIZE_SPECIAL_CHARS);
    $payment_terms = filter_input(INPUT_POST, 'payment_terms', FILTER_SANITIZE_SPECIAL_CHARS);
    
    $assigned_staff_ids = $_POST['assigned_staff'] ?? [];
    $materials_data = $_POST['materials'] ?? [];

    // Start Transaction
    $conn->begin_transaction();
    try {
        if (empty($project_id)) {
            // CREATE NEW PROJECT - FIXED: Removed project_name and created_by columns
            $sql_project = "INSERT INTO Projects (Orders_id, Status, Notes, Total_Cost, StartDate) 
                           VALUES (?, ?, ?, ?, NOW())";
            $stmt_project = $conn->prepare($sql_project);
            if (!$stmt_project) throw new Exception("Prepare failed: " . $conn->error);
            
            $stmt_project->bind_param("issd", $orders_id, $status_update, $notes, $total_cost);
            if (!$stmt_project->execute()) throw new Exception("Execute failed: " . $stmt_project->error);
            
            $project_id = $conn->insert_id;
            $message = "New project created successfully!";
        } else {
            // UPDATE EXISTING PROJECT - FIXED: Removed project_name and updated_by columns
            $sql_project = "UPDATE Projects SET 
                           Status = ?, Notes = ?, Total_Cost = ?
                           WHERE Project_id = ?";
            $stmt_project = $conn->prepare($sql_project);
            if (!$stmt_project) throw new Exception("Prepare failed: " . $conn->error);
            
            $stmt_project->bind_param("ssdi", $status_update, $notes, $total_cost, $project_id);
            if (!$stmt_project->execute()) throw new Exception("Execute failed: " . $stmt_project->error);
            
            $message = "Project updated successfully!";
        }

        // Update service_sched table for schedule date/time - FIXED: Using service_sched instead of Orders.schedule_date
        if ($orders_id) {
            $datetime = $schedule_date . ' ' . $schedule_time;
            
            // Check if this is an order or schedule
            $source_check_sql = "SELECT Services_id FROM Orders WHERE Orders_id = ?";
            $stmt_check = $conn->prepare($source_check_sql);
            if ($stmt_check) {
                $stmt_check->bind_param("i", $orders_id);
                $stmt_check->execute();
                $source_result = $stmt_check->get_result();
                if ($source_row = $source_result->fetch_assoc()) {
                    // Update service_sched for this order
                    $update_schedule_sql = "UPDATE service_sched SET ScheduleDate = ? WHERE Services_id = ?";
                    $stmt_schedule = $conn->prepare($update_schedule_sql);
                    if ($stmt_schedule) {
                        $stmt_schedule->bind_param("si", $datetime, $source_row['Services_id']);
                        $stmt_schedule->execute();
                        $stmt_schedule->close();
                    }
                }
                $stmt_check->close();
            }
        }

        // Update Customer information - FIXED: Using correct column names
        if (!empty($client_name) && $orders_id) {
            // Get customer_id from order first
            $customer_sql = "SELECT customer_id FROM Orders WHERE Orders_id = ?";
            $stmt_customer = $conn->prepare($customer_sql);
            if ($stmt_customer) {
                $stmt_customer->bind_param("i", $orders_id);
                $stmt_customer->execute();
                $customer_result = $stmt_customer->get_result();
                if ($customer_row = $customer_result->fetch_assoc()) {
                    $update_customer = "UPDATE Customers SET Name = ?, PhoneNumber = ?, Email = ?, Address = ? 
                                       WHERE customer_id = ?";
                    $stmt_update_customer = $conn->prepare($update_customer);
                    if ($stmt_update_customer) {
                        $stmt_update_customer->bind_param("ssssi", $client_name, $client_contact, $client_email, $project_site, $customer_row['customer_id']);
                        $stmt_update_customer->execute();
                        $stmt_update_customer->close();
                    }
                }
                $stmt_customer->close();
            }
        }

        // Manage Staff Assignment
        $sql_delete_staff = "DELETE FROM ProjectStaff WHERE Project_id = ?";
        $stmt_delete = $conn->prepare($sql_delete_staff);
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $project_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        }
        
        if (!empty($assigned_staff_ids)) {
            $sql_insert_staff = "INSERT INTO ProjectStaff (Project_id, Staff_id) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert_staff);
            if ($stmt_insert) {
                foreach ($assigned_staff_ids as $staff_id) {
                    $staff_id = (int)$staff_id;
                    $stmt_insert->bind_param("ii", $project_id, $staff_id);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }
        }

        // Manage Materials - FIXED: Using orderitems table instead of non-existent ProjectMaterials
        if (!empty($materials_data)) {
            foreach ($materials_data as $material_id => $material) {
                if (!empty($material['quantity']) && $material['quantity'] > 0 && $orders_id) {
                    $product_id = (int)$material_id;
                    $quantity = (int)$material['quantity'];
                    $unit_price = floatval($material['unit_price'] ?? 0);
                    
                    // Check if material already exists in orderitems
                    $check_material_sql = "SELECT Items_id FROM orderitems WHERE Orders_id = ? AND product_id = ?";
                    $stmt_check_mat = $conn->prepare($check_material_sql);
                    if ($stmt_check_mat) {
                        $stmt_check_mat->bind_param("ii", $orders_id, $product_id);
                        $stmt_check_mat->execute();
                        $material_exists = $stmt_check_mat->get_result()->fetch_assoc();
                        $stmt_check_mat->close();
                        
                        if ($material_exists) {
                            // Update existing
                            $update_material_sql = "UPDATE orderitems SET quantity = ?, price = ? WHERE Orders_id = ? AND product_id = ?";
                            $stmt_update_mat = $conn->prepare($update_material_sql);
                            if ($stmt_update_mat) {
                                $stmt_update_mat->bind_param("idii", $quantity, $unit_price, $orders_id, $product_id);
                                $stmt_update_mat->execute();
                                $stmt_update_mat->close();
                            }
                        } else {
                            // Insert new
                            $insert_material_sql = "INSERT INTO orderitems (Orders_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                            $stmt_insert_mat = $conn->prepare($insert_material_sql);
                            if ($stmt_insert_mat) {
                                $stmt_insert_mat->bind_param("iiid", $orders_id, $product_id, $quantity, $unit_price);
                                $stmt_insert_mat->execute();
                                $stmt_insert_mat->close();
                            }
                        }
                    }
                }
            }
        }

        $conn->commit();
        $status = 'success';

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Operation failed: " . $e->getMessage();
        $status = 'error';
        error_log("Project Management Error: " . $e->getMessage());
    }

    // Redirect to prevent resubmission
    $redirect_url = "management_form.php" . ($project_id ? "?id=$project_id" : "");
    header("Location: $redirect_url&status=$status&msg=" . urlencode($message));
    exit();
}

// ===================================================================
// 2. Data Retrieval (For both New and Edit Modes)
// ===================================================================
$project_id_from_url = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$orders_id_from_url = filter_input(INPUT_GET, 'orders_id', FILTER_VALIDATE_INT);
$sched_id_from_url = filter_input(INPUT_GET, 'sched_id', FILTER_VALIDATE_INT);

if ($project_id_from_url) {
    // EDIT MODE: Fetch existing project - FIXED: Removed non-existent columns
    $sql_project = "
        SELECT 
            p.Project_id, p.Orders_id, p.Status, p.Notes, p.Total_Cost,
            p.StartDate,
            ss.ScheduleDate,
            c.Name AS ClientName, c.PhoneNumber AS Contact, c.Email, c.Address AS CustomerAddress,
            s.Name AS ServiceName
        FROM Projects p
        LEFT JOIN Orders o ON p.Orders_id = o.Orders_id
        LEFT JOIN Customers c ON o.customer_id = c.customer_id
        LEFT JOIN Services s ON o.Services_id = s.Services_id
        LEFT JOIN service_sched ss ON o.Services_id = ss.Services_id
        WHERE p.Project_id = ?
    ";
    
    $stmt_project = $conn->prepare($sql_project);
    if ($stmt_project) {
        $stmt_project->bind_param("i", $project_id_from_url);
        $stmt_project->execute();
        $result = $stmt_project->get_result();
        $project = $result->fetch_assoc();
        
        if ($project) {
            // Format schedule date/time from service_sched
            if ($project['ScheduleDate']) {
                $project['schedule_date'] = date('Y-m-d', strtotime($project['ScheduleDate']));
                $project['schedule_time'] = date('H:i', strtotime($project['ScheduleDate']));
            }
            
            // Generate project name from service name
            $project['project_name'] = ($project['ServiceName'] ?? 'Service') . ' Project';
        }
        $stmt_project->close();
    }
    
    // Fetch currently assigned staff
    if ($project_id_from_url) {
        $sql_current_staff = "SELECT Staff_id FROM ProjectStaff WHERE Project_id = ?";
        $stmt_current_staff = $conn->prepare($sql_current_staff);
        if ($stmt_current_staff) {
            $stmt_current_staff->bind_param("i", $project_id_from_url);
            $stmt_current_staff->execute();
            $result = $stmt_current_staff->get_result();
            while ($row = $result->fetch_assoc()) {
                $current_staff_ids[] = (string)$row['Staff_id'];
            }
            $stmt_current_staff->close();
        }

        // Fetch current materials from orderitems - FIXED: Using orderitems instead of ProjectMaterials
        if ($project['Orders_id']) {
            $sql_current_materials = "
                SELECT oi.product_id, oi.quantity, oi.price as unit_price, p.Name, p.Category_id 
                FROM orderitems oi 
                JOIN Product p ON oi.product_id = p.Product_id 
                WHERE oi.Orders_id = ? AND oi.product_id IS NOT NULL";
            $stmt_materials = $conn->prepare($sql_current_materials);
            if ($stmt_materials) {
                $stmt_materials->bind_param("i", $project['Orders_id']);
                $stmt_materials->execute();
                $result = $stmt_materials->get_result();
                while ($row = $result->fetch_assoc()) {
                    $current_materials[] = $row;
                }
                $stmt_materials->close();
            }
        }
    }
    
} else {
    // NEW PROJECT MODE
    $is_new_project = true;
    
    if ($sched_id_from_url) {
        // If creating from a service schedule
        $sql_schedule = "
            SELECT ss.sched_id, ss.ScheduleDate, ss.Services_id, ss.customer_id,
                   s.Name AS ServiceName, c.Name AS ClientName, c.Email, c.PhoneNumber AS Contact, c.Address
            FROM service_sched ss
            LEFT JOIN Services s ON ss.Services_id = s.Services_id
            LEFT JOIN Customers c ON ss.customer_id = c.customer_id
            WHERE ss.sched_id = ?
        ";
        $stmt_schedule = $conn->prepare($sql_schedule);
        if ($stmt_schedule) {
            $stmt_schedule->bind_param("i", $sched_id_from_url);
            $stmt_schedule->execute();
            $result = $stmt_schedule->get_result();
            $schedule_data = $result->fetch_assoc();
            
            if ($schedule_data) {
                $project = [
                    'ClientName' => $schedule_data['ClientName'],
                    'Contact' => $schedule_data['Contact'],
                    'Email' => $schedule_data['Email'],
                    'CustomerAddress' => $schedule_data['Address'],
                    'ServiceName' => $schedule_data['ServiceName'],
                    'schedule_date' => $schedule_data['ScheduleDate'] ? date('Y-m-d', strtotime($schedule_data['ScheduleDate'])) : date('Y-m-d'),
                    'schedule_time' => $schedule_data['ScheduleDate'] ? date('H:i', strtotime($schedule_data['ScheduleDate'])) : '09:00',
                    'project_name' => $schedule_data['ServiceName'] . ' Project',
                    'Status' => 'Pending',
                    'Total_Cost' => '0.00',
                    'payment_terms' => 'Partial'
                ];
            }
            $stmt_schedule->close();
        }
    } elseif ($orders_id_from_url) {
        // If creating from an order - FIXED: Using service_sched for dates
        $sql_order = "
            SELECT o.Orders_id, ss.ScheduleDate, c.Name AS ClientName, c.PhoneNumber AS Contact, c.Email, c.Address,
                   s.Name AS ServiceName
            FROM Orders o
            LEFT JOIN Customers c ON o.customer_id = c.customer_id
            LEFT JOIN Services s ON o.Services_id = s.Services_id
            LEFT JOIN service_sched ss ON o.Services_id = ss.Services_id
            WHERE o.Orders_id = ?
        ";
        $stmt_order = $conn->prepare($sql_order);
        if ($stmt_order) {
            $stmt_order->bind_param("i", $orders_id_from_url);
            $stmt_order->execute();
            $result = $stmt_order->get_result();
            $order_data = $result->fetch_assoc();
            
            if ($order_data) {
                $project = [
                    'Orders_id' => $order_data['Orders_id'],
                    'ClientName' => $order_data['ClientName'],
                    'Contact' => $order_data['Contact'],
                    'Email' => $order_data['Email'],
                    'CustomerAddress' => $order_data['Address'],
                    'ServiceName' => $order_data['ServiceName'],
                    'schedule_date' => $order_data['ScheduleDate'] ? date('Y-m-d', strtotime($order_data['ScheduleDate'])) : '',
                    'schedule_time' => $order_data['ScheduleDate'] ? date('H:i', strtotime($order_data['ScheduleDate'])) : '09:00',
                    'project_name' => $order_data['ServiceName'] . ' Project',
                    'Status' => 'Pending',
                    'Total_Cost' => '0.00'
                ];
            }
            $stmt_order->close();
        }
    }
}

// Fetch all staff for dropdown - FIXED: Removed Status column (doesn't exist)
$sql_all_staff = "SELECT Staff_id, Name, Position FROM Staff ORDER BY Name ASC";
$staff_result = $conn->query($sql_all_staff);
if ($staff_result) {
    $staff_list = $staff_result->fetch_all(MYSQLI_ASSOC);
} else {
    $staff_list = [];
}

// Fetch all materials/products
$sql_all_materials = "SELECT Product_id, Name, Category_id, Price, Stock FROM Product ORDER BY Category_id, Name ASC";
$materials_result = $conn->query($sql_all_materials);
if ($materials_result) {
    $materials_list = $materials_result->fetch_all(MYSQLI_ASSOC);
} else {
    $materials_list = [];
}

// Group materials by category - FIXED: Using Category_id instead of Category
$materials_by_category = [];
$category_names = [
    1 => 'Hardware',
    2 => 'Software Licenses', 
    3 => 'Cables & Accessories'
];

foreach ($materials_list as $material) {
    $category_id = $material['Category_id'] ?? 0;
    $category_name = $category_names[$category_id] ?? 'Uncategorized';
    if (!isset($materials_by_category[$category_name])) {
        $materials_by_category[$category_name] = [];
    }
    $materials_by_category[$category_name][] = $material;
}

// Handle messages from redirection
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $status = $_GET['status'];
    $message = htmlspecialchars(urldecode($_GET['msg']));
}

// Set default values if project is empty
if (empty($project)) {
    $project = [
        'Project_id' => '',
        'Orders_id' => $orders_id_from_url ?? '',
        'project_name' => '',
        'schedule_date' => date('Y-m-d'),
        'schedule_time' => '09:00',
        'ClientName' => '',
        'Contact' => '',
        'Email' => '',
        'CustomerAddress' => '',
        'ServiceName' => '',
        'Status' => 'Pending',
        'Notes' => '',
        'Total_Cost' => '0.00',
        'payment_terms' => 'Partial'
    ];
}

// Function to format Project ID
function formatProjectId($project_id, $schedule_date) {
    if (!$project_id) return 'New Project';
    $project_year = date('Y', strtotime($schedule_date ?? date('Y-m-d')));
    return "PRJ-" . $project_year . "-" . str_pad($project_id, 3, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALG | Project Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .form-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .section-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
        }
        .staff-list, .materials-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.5rem;
        }
        .material-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 0.5rem 0;
        }
        .material-item:last-child {
            border-bottom: none;
        }
        .assigned-staff {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            border-radius: 6px;
            padding: 0.75rem;
            margin-top: 0.5rem;
        }
        .staff-skill {
            font-size: 0.75rem;
            color: #718096;
            font-style: italic;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Header -->
    <header class="bg-gray-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="text-xl font-bold">ALG Enterprises</div>
                </div>
                <nav class="flex space-x-4">
                    <a href="home.php" class="hover:text-blue-300 transition">Home</a>
                    <a href="products.php" class="hover:text-blue-300 transition">Inventory</a>
                    <a href="projects.php" class="text-blue-300 font-semibold">Projects</a>
                    <a href="staff.php" class="hover:text-blue-300 transition">Staff</a>
                </nav>
                <div class="flex items-center space-x-3">
                    <span class="text-sm">Welcome, <?= htmlspecialchars($current_user) ?></span>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-6">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">
                <?= $is_new_project ? 'Create New Project' : 'Manage Project' ?>
            </h1>
            <div class="flex space-x-3">
                <button onclick="window.location.href='projects.php'" 
                        class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                    ← Back to Projects
                </button>
                <button onclick="window.location.href='appointing.php'" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Appointing
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?= $status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['Project_id']) ?>">
            <input type="hidden" name="orders_id" value="<?= htmlspecialchars($project['Orders_id']) ?>">

            <!-- Project Information Section -->
            <div class="form-section">
                <div class="section-title">
                    <h2>Project Information</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project ID</label>
                        <div class="p-3 bg-gray-100 rounded-lg font-mono">
                            <?= formatProjectId($project['Project_id'], $project['schedule_date']) ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project Type/Service</label>
                        <input type="text" name="project_name" value="<?= htmlspecialchars($project['project_name']) ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg" required
                               placeholder="Enter project name or service type">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Date</label>
                        <input type="date" name="schedule_date" value="<?= htmlspecialchars($project['schedule_date']) ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time</label>
                        <input type="time" name="schedule_time" value="<?= htmlspecialchars($project['schedule_time']) ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project Site/Location</label>
                        <input type="text" name="project_site" value="<?= htmlspecialchars($project['CustomerAddress']) ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg" 
                               placeholder="Enter project location">
                    </div>
                </div>
            </div>

            <!-- Client Information Section -->
            <div class="form-section">
                <div class="section-title">
                    <h2>Client Information</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client Name</label>
                        <input type="text" name="client_name" value="<?= htmlspecialchars($project['ClientName']) ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                        <input type="text" name="client_contact" value="<?= htmlspecialchars($project['Contact']) ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="client_email" value="<?= htmlspecialchars($project['Email']) ?>" 
                               class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Service Type</label>
                        <input type="text" value="<?= htmlspecialchars($project['ServiceName']) ?>" 
                               class="w-full p-3 bg-gray-100 rounded-lg" readonly>
                    </div>
                </div>
            </div>

            <!-- Staff Assignment Section -->
            <div class="form-section">
                <div class="section-title">
                    <h2>Staff Assignment</h2>
                </div>
                <div class="p-6">
                    <!-- Currently Assigned Staff -->
                    <?php if (!empty($current_staff_ids)): ?>
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Currently Assigned Staff</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php 
                            $assigned_staff = array_filter($staff_list, function($staff) use ($current_staff_ids) {
                                return in_array($staff['Staff_id'], $current_staff_ids);
                            });
                            foreach ($assigned_staff as $staff): ?>
                            <div class="assigned-staff">
                                <div class="font-medium"><?= htmlspecialchars($staff['Name']) ?></div>
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($staff['Position']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Staff Selection -->
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Assign New Staff</h3>
                        <div class="flex space-x-4 mb-3">
                            <input type="text" id="staffSearch" placeholder="Search staff by name..." 
                                   class="flex-1 p-2 border border-gray-300 rounded-lg">
                            <select id="positionFilter" class="p-2 border border-gray-300 rounded-lg">
                                <option value="">All Positions</option>
                                <option value="General Manager">General Manager</option>
                                <option value="Project Lead">Project Lead</option>
                                <option value="Technician">Technician</option>
                            </select>
                        </div>
                        <div class="staff-list">
                            <?php foreach ($staff_list as $staff): ?>
                            <div class="staff-item flex items-center p-2 border-b border-gray-200" 
                                 data-name="<?= strtolower(htmlspecialchars($staff['Name'])) ?>"
                                 data-position="<?= strtolower(htmlspecialchars($staff['Position'])) ?>">
                                <input type="checkbox" name="assigned_staff[]" value="<?= $staff['Staff_id'] ?>" 
                                       id="staff_<?= $staff['Staff_id'] ?>"
                                       <?= in_array($staff['Staff_id'], $current_staff_ids) ? 'checked' : '' ?>
                                       class="mr-3">
                                <label for="staff_<?= $staff['Staff_id'] ?>" class="flex-1 cursor-pointer">
                                    <div class="font-medium"><?= htmlspecialchars($staff['Name']) ?></div>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($staff['Position']) ?></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment & Materials Section -->
            <div class="form-section">
                <div class="section-title">
                    <h2>Payment & Materials</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Payment Information -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Information</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Project Cost (₱)</label>
                                <input type="number" name="total_cost" value="<?= htmlspecialchars($project['Total_Cost']) ?>" 
                                       step="0.01" min="0" class="w-full p-3 border border-gray-300 rounded-lg" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Project Status</label>
                                <select name="status" class="w-full p-3 border border-gray-300 rounded-lg">
                                    <option value="Pending" <?= ($project['Status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Ongoing" <?= ($project['Status'] ?? '') === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                    <option value="Completed" <?= ($project['Status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Materials Arrangement -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Materials Arrangement</h3>
                        <div class="mb-3">
                            <select id="materialCategory" class="w-full p-2 border border-gray-300 rounded-lg">
                                <option value="">All Categories</option>
                                <?php foreach (array_keys($materials_by_category) as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="materials-list mb-4">
                            <?php foreach ($materials_by_category as $category => $materials): ?>
                                <div class="category-section" data-category="<?= htmlspecialchars($category) ?>">
                                    <div class="font-semibold text-gray-700 mb-2 p-2 bg-gray-100"><?= htmlspecialchars($category) ?></div>
                                    <?php foreach ($materials as $material): 
                                        // Find current quantity for this material
                                        $current_qty = 0;
                                        foreach ($current_materials as $current_mat) {
                                            if ($current_mat['product_id'] == $material['Product_id']) {
                                                $current_qty = $current_mat['quantity'];
                                                break;
                                            }
                                        }
                                    ?>
                                    <div class="material-item" data-category="<?= htmlspecialchars($category) ?>">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <div class="font-medium"><?= htmlspecialchars($material['Name']) ?></div>
                                                <div class="text-sm text-gray-600">Stock: <?= $material['Stock'] ?> | ₱<?= number_format($material['Price'], 2) ?></div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <input type="number" 
                                                       name="materials[<?= $material['Product_id'] ?>][quantity]" 
                                                       value="<?= $current_qty ?>"
                                                       placeholder="Qty" 
                                                       min="0" 
                                                       max="<?= $material['Stock'] ?>"
                                                       class="w-16 p-1 border border-gray-300 rounded text-center">
                                                <input type="hidden" 
                                                       name="materials[<?= $material['Product_id'] ?>][unit_price]" 
                                                       value="<?= $material['Price'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Current Materials Summary -->
                        <?php if (!empty($current_materials)): ?>
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <h4 class="font-semibold text-blue-800 mb-2">Currently Assigned Materials</h4>
                            <?php foreach ($current_materials as $material): ?>
                            <div class="text-sm text-blue-700">
                                • <?= htmlspecialchars($material['Name']) ?> (<?= $material['quantity'] ?> units)
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notes & Submission Section -->
            <div class="form-section">
                <div class="section-title">
                    <h2>Notes & Approval</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project Notes</label>
                        <textarea name="notes" rows="4" class="w-full p-3 border border-gray-300 rounded-lg" 
                                  placeholder="Add any important notes about the project..."><?= htmlspecialchars($project['Notes']) ?></textarea>
                    </div>
                    <div class="space-y-4">
                        <div class="pt-4">
                            <button type="submit" class="w-full bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition font-semibold">
                                <?= $is_new_project ? 'Create Project' : 'Update Project' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Staff search and filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const staffSearch = document.getElementById('staffSearch');
            const positionFilter = document.getElementById('positionFilter');
            const staffItems = document.querySelectorAll('.staff-item');
            const materialCategory = document.getElementById('materialCategory');
            const categorySections = document.querySelectorAll('.category-section');

            // Staff search
            staffSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filterStaff();
            });

            // Position filter
            positionFilter.addEventListener('change', function() {
                filterStaff();
            });

            function filterStaff() {
                const searchTerm = staffSearch.value.toLowerCase();
                const position = positionFilter.value.toLowerCase();

                staffItems.forEach(item => {
                    const name = item.dataset.name;
                    const staffPosition = item.dataset.position;

                    const matchesSearch = name.includes(searchTerm);
                    const matchesPosition = !position || staffPosition.includes(position);

                    item.style.display = matchesSearch && matchesPosition ? 'flex' : 'none';
                });
            }

            // Material category filter
            materialCategory.addEventListener('change', function() {
                const selectedCategory = this.value.toLowerCase();
                
                categorySections.forEach(section => {
                    const category = section.dataset.category.toLowerCase();
                    
                    if (!selectedCategory || category === selectedCategory) {
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                });
            });

            // Auto-calculate total cost when materials are added
            document.querySelectorAll('input[name^="materials"]').forEach(input => {
                input.addEventListener('input', updateTotalCost);
            });

            function updateTotalCost() {
                let total = 0;
                document.querySelectorAll('input[name^="materials"]').forEach(input => {
                    if (input.name.includes('[quantity]') && input.value) {
                        const quantity = parseInt(input.value);
                        const productId = input.name.match(/\[(\d+)\]/)[1];
                        const priceInput = document.querySelector(`input[name="materials[${productId}][unit_price]"]`);
                        if (priceInput) {
                            const price = parseFloat(priceInput.value);
                            total += quantity * price;
                        }
                    }
                });
                
                // Update total cost field
                const totalCostField = document.querySelector('input[name="total_cost"]');
                if (totalCostField) {
                    totalCostField.value = total.toFixed(2);
                }
            }
        });
    </script>
</body>
</html>