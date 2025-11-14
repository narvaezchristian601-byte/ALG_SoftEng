<?php
// management_form.php - Enhanced Project Management Form
include "../../db.php";
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'));
}

// Initialize variables
$project = [];
$staff_list = [];
$materials_list = [];
$message = '';
$status = '';
$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = !!$project_id;
$assigned_staff_ids = [];
$project_materials = [];
$selected_materials = [];

// Validation functions
function validateProjectData($data, $conn) {
    $errors = [];
    
    // Validate required fields
    if (empty(trim($data['project_name'] ?? ''))) {
        $errors[] = "Project name is required";
    }
    
    if (empty(trim($data['client_name'] ?? ''))) {
        $errors[] = "Client name is required";
    }
    
    // Validate email format if provided
    if (!empty($data['client_email']) && !filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Validate total cost
    $total_cost = floatval($data['total_cost'] ?? 0);
    if ($total_cost < 0) {
        $errors[] = "Total cost cannot be negative";
    }
    
    // Validate materials stock
    foreach (($data['materials'] ?? []) as $material_id => $material_data) {
        $quantity = intval($material_data['quantity'] ?? 0);
        if ($quantity > 0) {
            // Check if material exists and has sufficient stock
            $stock_stmt = $conn->prepare("SELECT Name, Stock FROM Product WHERE Product_id = ?");
            $stock_stmt->bind_param("i", $material_id);
            if ($stock_stmt->execute()) {
                $stock_result = $stock_stmt->get_result();
                if ($stock_row = $stock_result->fetch_assoc()) {
                    if ($quantity > $stock_row['Stock']) {
                        $errors[] = "Insufficient stock for {$stock_row['Name']}. Available: {$stock_row['Stock']}, Requested: {$quantity}";
                    }
                } else {
                    $errors[] = "Invalid product ID: $material_id";
                }
            }
            $stock_stmt->close();
        }
    }
    
    return $errors;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    $project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $orders_id = filter_input(INPUT_POST, 'orders_id', FILTER_VALIDATE_INT);
    $project_name = trim($_POST['project_name'] ?? '');
    $client_name = trim($_POST['client_name'] ?? '');
    $client_contact = trim($_POST['client_contact'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $client_address = trim($_POST['client_address'] ?? '');
    $project_status = $_POST['status'] ?? 'Pending';
    $notes = trim($_POST['notes'] ?? '');
    $total_cost = floatval($_POST['total_cost'] ?? 0);
    
    $assigned_staff = $_POST['assigned_staff'] ?? [];
    $materials = $_POST['materials'] ?? [];

    // Validate input data
    $validation_errors = validateProjectData([
        'project_name' => $project_name,
        'client_name' => $client_name,
        'client_email' => $client_email,
        'total_cost' => $total_cost,
        'materials' => $materials
    ], $conn);

    if (!empty($validation_errors)) {
        $message = "Validation errors: " . implode(", ", $validation_errors);
        $status = 'error';
    } else {
        try {
            // Start transaction
            if (!$conn->begin_transaction()) {
                throw new Exception("Failed to start transaction");
            }

            if (!$project_id) {
                // Validate orders_id for new projects
                if (!$orders_id) {
                    throw new Exception("Order reference is required for new projects");
                }
                
                // Verify orders_id exists and get customer_id
                $check_order_stmt = $conn->prepare("SELECT Orders_id, customer_id FROM Orders WHERE Orders_id = ?");
                $check_order_stmt->bind_param("i", $orders_id);
                if (!$check_order_stmt->execute()) {
                    throw new Exception("Failed to verify order");
                }
                $order_result = $check_order_stmt->get_result();
                $order_data = $order_result->fetch_assoc();
                if (!$order_data) {
                    throw new Exception("Invalid order reference provided");
                }
                $customer_id = $order_data['customer_id'];
                $check_order_stmt->close();

                // Create new project - FIXED: Removed client fields from Projects table
                $stmt = $conn->prepare("INSERT INTO Projects (Orders_id, Project_Name, status, notes, total_cost, StartDate) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("isssd", $orders_id, $project_name, $project_status, $notes, $total_cost);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create project: " . $stmt->error);
                }
                $project_id = $conn->insert_id;
                $message = "Project created successfully!";
                
                // Update customer information if provided
                if (!empty($client_name) || !empty($client_contact) || !empty($client_email) || !empty($client_address)) {
                    $update_customer_sql = "UPDATE Customers SET ";
                    $update_params = [];
                    $update_types = '';
                    
                    if (!empty($client_name)) {
                        $update_customer_sql .= "Name = ?, ";
                        $update_params[] = $client_name;
                        $update_types .= 's';
                    }
                    if (!empty($client_contact)) {
                        $update_customer_sql .= "PhoneNumber = ?, ";
                        $update_params[] = $client_contact;
                        $update_types .= 's';
                    }
                    if (!empty($client_email)) {
                        $update_customer_sql .= "Email = ?, ";
                        $update_params[] = $client_email;
                        $update_types .= 's';
                    }
                    if (!empty($client_address)) {
                        $update_customer_sql .= "Address = ?, ";
                        $update_params[] = $client_address;
                        $update_types .= 's';
                    }
                    
                    // Remove trailing comma and space
                    $update_customer_sql = rtrim($update_customer_sql, ', ');
                    $update_customer_sql .= " WHERE customer_id = ?";
                    $update_params[] = $customer_id;
                    $update_types .= 'i';
                    
                    $update_customer_stmt = $conn->prepare($update_customer_sql);
                    $update_customer_stmt->bind_param($update_types, ...$update_params);
                    if (!$update_customer_stmt->execute()) {
                        throw new Exception("Failed to update customer information: " . $update_customer_stmt->error);
                    }
                    $update_customer_stmt->close();
                }
                
                // Log the creation
                error_log("Project created: ID $project_id, Name: $project_name");
            } else {
                // Update existing project - FIXED: Removed client fields from Projects table
                $stmt = $conn->prepare("UPDATE Projects SET Project_Name=?, status=?, notes=?, total_cost=? WHERE Project_id=?");
                $stmt->bind_param("sssdi", $project_name, $project_status, $notes, $total_cost, $project_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update project: " . $stmt->error);
                }
                
                // Update customer information for existing project
                if (!empty($client_name) || !empty($client_contact) || !empty($client_email) || !empty($client_address)) {
                    // Get customer_id from the order
                    $customer_stmt = $conn->prepare("SELECT O.customer_id FROM Projects P JOIN Orders O ON P.Orders_id = O.Orders_id WHERE P.Project_id = ?");
                    $customer_stmt->bind_param("i", $project_id);
                    if ($customer_stmt->execute()) {
                        $customer_result = $customer_stmt->get_result();
                        if ($customer_data = $customer_result->fetch_assoc()) {
                            $customer_id = $customer_data['customer_id'];
                            
                            $update_customer_sql = "UPDATE Customers SET ";
                            $update_params = [];
                            $update_types = '';
                            
                            if (!empty($client_name)) {
                                $update_customer_sql .= "Name = ?, ";
                                $update_params[] = $client_name;
                                $update_types .= 's';
                            }
                            if (!empty($client_contact)) {
                                $update_customer_sql .= "PhoneNumber = ?, ";
                                $update_params[] = $client_contact;
                                $update_types .= 's';
                            }
                            if (!empty($client_email)) {
                                $update_customer_sql .= "Email = ?, ";
                                $update_params[] = $client_email;
                                $update_types .= 's';
                            }
                            if (!empty($client_address)) {
                                $update_customer_sql .= "Address = ?, ";
                                $update_params[] = $client_address;
                                $update_types .= 's';
                            }
                            
                            // Remove trailing comma and space
                            $update_customer_sql = rtrim($update_customer_sql, ', ');
                            $update_customer_sql .= " WHERE customer_id = ?";
                            $update_params[] = $customer_id;
                            $update_types .= 'i';
                            
                            $update_customer_stmt = $conn->prepare($update_customer_sql);
                            $update_customer_stmt->bind_param($update_types, ...$update_params);
                            if (!$update_customer_stmt->execute()) {
                                throw new Exception("Failed to update customer information: " . $update_customer_stmt->error);
                            }
                            $update_customer_stmt->close();
                        }
                    }
                    $customer_stmt->close();
                }
                
                $message = "Project updated successfully!";
            }

            // Update staff assignments
            $delete_stmt = $conn->prepare("DELETE FROM ProjectStaff WHERE Project_id = ?");
            $delete_stmt->bind_param("i", $project_id);
            if (!$delete_stmt->execute()) {
                throw new Exception("Failed to clear staff assignments: " . $delete_stmt->error);
            }

            if (!empty($assigned_staff)) {
                $staff_stmt = $conn->prepare("INSERT INTO ProjectStaff (Project_id, Staff_id) VALUES (?, ?)");
                $unique_staff = array_unique($assigned_staff);
                
                foreach ($unique_staff as $staff_id) {
                    $staff_id_clean = intval($staff_id);
                    // Verify staff exists
                    $check_staff = $conn->prepare("SELECT Staff_id FROM Staff WHERE Staff_id = ?");
                    $check_staff->bind_param("i", $staff_id_clean);
                    if ($check_staff->execute() && $check_staff->get_result()->fetch_assoc()) {
                        $staff_stmt->bind_param("ii", $project_id, $staff_id_clean);
                        if (!$staff_stmt->execute()) {
                            throw new Exception("Failed to assign staff ID $staff_id_clean: " . $staff_stmt->error);
                        }
                    }
                    $check_staff->close();
                }
                $staff_stmt->close();
            }

            // Update materials using orderitems table
            if ($orders_id) {
                // Remove existing materials for this order
                $delete_materials_stmt = $conn->prepare("DELETE FROM orderitems WHERE Orders_id = ? AND product_id IS NOT NULL");
                $delete_materials_stmt->bind_param("i", $orders_id);
                if (!$delete_materials_stmt->execute()) {
                    throw new Exception("Failed to clear materials: " . $delete_materials_stmt->error);
                }

                // Insert new materials
                foreach ($materials as $material_id => $data) {
                    $quantity = intval($data['quantity'] ?? 0);
                    if ($quantity > 0) {
                        $price = floatval($data['price'] ?? 0);
                        $material_id_clean = intval($material_id);
                        
                        // Verify product exists
                        $check_product = $conn->prepare("SELECT Product_id FROM Product WHERE Product_id = ?");
                        $check_product->bind_param("i", $material_id_clean);
                        if ($check_product->execute() && $check_product->get_result()->fetch_assoc()) {
                            $material_stmt = $conn->prepare("INSERT INTO orderitems (Orders_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                            $material_stmt->bind_param("iiid", $orders_id, $material_id_clean, $quantity, $price);
                            if (!$material_stmt->execute()) {
                                throw new Exception("Failed to add material ID $material_id_clean: " . $material_stmt->error);
                            }
                            $material_stmt->close();
                        }
                        $check_product->close();
                    }
                }
            }

            // Commit transaction
            if (!$conn->commit()) {
                throw new Exception("Failed to commit transaction");
            }
            
            $status = 'success';
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $status = 'error';
            error_log("Project form error: " . $e->getMessage());
        }
    }

    // Regenerate CSRF token after successful POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    header("Location: management_form.php?id=$project_id&status=$status&msg=" . urlencode($message));
    exit();
}

// Load existing project data - FIXED: Get client info from Customers table
if ($project_id) {
    $stmt = $conn->prepare("
        SELECT 
            p.Project_id,
            p.Project_Name,
            p.status,
            p.notes,
            p.total_cost,
            p.StartDate,
            p.Orders_id,
            c.Name as client_name,
            c.PhoneNumber as client_contact,
            c.Email as client_email,
            c.Address as client_address,
            c.customer_id
        FROM Projects p 
        LEFT JOIN Orders o ON p.Orders_id = o.Orders_id 
        LEFT JOIN Customers c ON o.customer_id = c.customer_id 
        WHERE p.Project_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $project_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $project = $result->fetch_assoc() ?? [];
            if (empty($project)) {
                $message = "Project not found";
                $status = 'error';
                $project_id = null;
                $is_edit = false;
            }
        } else {
            $message = "Failed to load project data";
            $status = 'error';
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
        $status = 'error';
    }
    
    if ($project_id) {
        // Get assigned staff
        $stmt = $conn->prepare("SELECT Staff_id FROM ProjectStaff WHERE Project_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assigned_staff_ids = [];
            while ($row = $result->fetch_assoc()) {
                $assigned_staff_ids[] = $row['Staff_id'];
            }
            $stmt->close();
        }
        
        // Get project materials from orderitems
        if (!empty($project['Orders_id'])) {
            $stmt = $conn->prepare("
                SELECT oi.product_id, oi.quantity, oi.price, p.Name 
                FROM orderitems oi 
                JOIN Product p ON oi.product_id = p.Product_id 
                WHERE oi.Orders_id = ? AND oi.product_id IS NOT NULL
            ");
            if ($stmt) {
                $stmt->bind_param("i", $project['Orders_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $project_materials = [];
                while ($row = $result->fetch_assoc()) {
                    $project_materials[$row['product_id']] = $row;
                }
                $stmt->close();
            }
        }
    }
}

// Load staff (excluding admins) and get unique positions
$staff_result = $conn->query("SELECT Staff_id, Name, Position FROM Staff WHERE Role != 'Admin' ORDER BY Position, Name");
if ($staff_result) {
    $staff_list = $staff_result->fetch_all(MYSQLI_ASSOC);
} else {
    $staff_list = [];
    error_log("Staff query failed: " . $conn->error);
}

// Get unique positions for filter dropdown
$positions_result = $conn->query("SELECT DISTINCT Position FROM Staff WHERE Role != 'Admin' ORDER BY Position");
if ($positions_result) {
    $unique_positions = $positions_result->fetch_all(MYSQLI_ASSOC);
} else {
    $unique_positions = [];
}

// Load materials with categories
$materials_result = $conn->query("
    SELECT p.Product_id, p.Name, p.Price, p.Stock, c.Name as category_name, c.Category_id 
    FROM Product p 
    LEFT JOIN category c ON p.Category_id = c.Category_id 
    ORDER BY c.Name, p.Name
");
if ($materials_result) {
    $materials_list = $materials_result->fetch_all(MYSQLI_ASSOC);
} else {
    $materials_list = [];
    error_log("Materials query failed: " . $conn->error);
}

// Get unique categories for filter dropdown
$categories_result = $conn->query("SELECT DISTINCT Category_id, Name FROM category ORDER BY Name");
if ($categories_result) {
    $unique_categories = $categories_result->fetch_all(MYSQLI_ASSOC);
} else {
    $unique_categories = [];
}

// Handle messages
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $status = $_GET['status'];
    $message = htmlspecialchars($_GET['msg']);
}

// Set defaults for new project with all required fields
$default_project = [
    'Project_Name' => '',
    'client_name' => '',
    'client_contact' => '',
    'client_email' => '',
    'client_address' => '',
    'status' => 'Pending',
    'notes' => '',
    'total_cost' => '0.00',
    'Orders_id' => ''
];

if (!$project || empty($project)) {
    $project = $default_project;
} else {
    $project = array_merge($default_project, $project);
}

// If creating from order, get order details
$orders_id_from_url = filter_input(INPUT_GET, 'orders_id', FILTER_VALIDATE_INT);
if (!$project_id && $orders_id_from_url) {
    $stmt = $conn->prepare("
        SELECT 
            o.Orders_id, 
            c.customer_id,
            c.Name as client_name, 
            c.PhoneNumber as client_contact, 
            c.Email as client_email, 
            c.Address as client_address, 
            s.Name as service_name
        FROM Orders o 
        LEFT JOIN Customers c ON o.customer_id = c.customer_id 
        LEFT JOIN Services s ON o.Services_id = s.Services_id 
        WHERE o.Orders_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $orders_id_from_url);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($order_data = $result->fetch_assoc()) {
                $project['Orders_id'] = $order_data['Orders_id'];
                $project['client_name'] = $order_data['client_name'];
                $project['client_contact'] = $order_data['client_contact'];
                $project['client_email'] = $order_data['client_email'];
                $project['client_address'] = $order_data['client_address'];
                $project['Project_Name'] = ($order_data['service_name'] ?? 'Service') . ' Project';
            }
        }
        $stmt->close();
    }
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
        .material-item {
            transition: all 0.2s ease-in-out;
        }
        .material-item:hover {
            background-color: #f9fafb;
        }
        .required-field::after {
            content: " *";
            color: #ef4444;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-gray-800 text-white shadow">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="text-xl font-bold">ALG Enterprises</div>
                <nav class="flex space-x-4">
                    <a href="home.php" class="hover:text-blue-300">Home</a>
                    <a href="projects.php" class="text-blue-300 font-semibold">Projects</a>
                    <a href="staff.php" class="hover:text-blue-300">Staff</a>
                </nav>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-6">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                <?= $is_edit ? 'Edit Project' : 'Create Project' ?>
            </h1>
            <a href="projects.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition duration-200">
                ← Back to Projects
            </a>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-4 rounded <?= $status === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
                <div class="flex items-center">
                    <?php if ($status === 'success'): ?>
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    <?php else: ?>
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    <?php endif; ?>
                    <?= $message ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6" id="projectForm">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <input type="hidden" name="orders_id" value="<?= htmlspecialchars($project['Orders_id'] ?? '') ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Project Information Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Project Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 required-field">Project Name</label>
                        <input type="text" name="project_name" value="<?= htmlspecialchars($project['Project_Name'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               required maxlength="255">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 required-field">Status</label>
                        <select name="status" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="Pending" <?= ($project['status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Ongoing" <?= ($project['status'] ?? '') === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                            <option value="Completed" <?= ($project['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Cancelled" <?= ($project['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Client Information Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Client Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 required-field">Client Name</label>
                        <input type="text" name="client_name" value="<?= htmlspecialchars($project['client_name'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               required maxlength="100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                        <input type="text" name="client_contact" value="<?= htmlspecialchars($project['client_contact'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               maxlength="20">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="client_email" value="<?= htmlspecialchars($project['client_email'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               maxlength="100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="client_address" value="<?= htmlspecialchars($project['client_address'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               maxlength="255">
                    </div>
                </div>
            </div>

            <!-- Staff Assignment Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Staff Assignment</h2>
                
                <!-- Position Filter -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Position</label>
                    <select id="positionFilter" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Positions</option>
                        <?php foreach ($unique_positions as $position): ?>
                            <option value="<?= htmlspecialchars(strtolower($position['Position'])) ?>">
                                <?= htmlspecialchars($position['Position']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-60 overflow-y-auto p-2 border border-gray-200 rounded" id="staffList">
                    <?php if (empty($staff_list)): ?>
                        <div class="col-span-2 text-center text-gray-500 py-4">
                            No staff members available for assignment.
                        </div>
                    <?php else: ?>
                        <?php foreach ($staff_list as $staff): ?>
                        <div class="staff-item flex items-center p-2 border rounded hover:bg-gray-50" 
                             data-position="<?= strtolower(htmlspecialchars($staff['Position'])) ?>">
                            <input type="checkbox" name="assigned_staff[]" value="<?= $staff['Staff_id'] ?>" 
                                   id="staff_<?= $staff['Staff_id'] ?>"
                                   <?= in_array($staff['Staff_id'], $assigned_staff_ids) ? 'checked' : '' ?>
                                   class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="staff_<?= $staff['Staff_id'] ?>" class="cursor-pointer flex-1">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($staff['Name']) ?></div>
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($staff['Position']) ?></div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Materials & Costs Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Materials & Costs</h2>
                
                <!-- Materials Filter -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Category</label>
                    <select id="categoryFilter" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Categories</option>
                        <?php foreach ($unique_categories as $category): ?>
                            <option value="<?= htmlspecialchars(strtolower($category['Category_id'])) ?>">
                                <?= htmlspecialchars($category['Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Materials Selection Table -->
                <div class="mb-6">
                    <h3 class="font-medium mb-3">Select Materials</h3>
                    <?php if (empty($materials_list)): ?>
                        <div class="text-center text-gray-500 py-4 border border-gray-200 rounded">
                            No materials available in inventory.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                                        <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                                        <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                        <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                        <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="materialsTableBody">
                                    <?php foreach ($materials_list as $material): 
                                        $current_qty = $project_materials[$material['Product_id']]['quantity'] ?? 0;
                                        $total_cost = $current_qty * $material['Price'];
                                    ?>
                                    <tr class="material-item hover:bg-gray-50" data-category="<?= htmlspecialchars(strtolower($material['Category_id'])) ?>">
                                        <td class="px-4 py-3 border-b">
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($material['Name']) ?></div>
                                        </td>
                                        <td class="px-4 py-3 border-b">
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs"><?= htmlspecialchars($material['category_name'] ?? 'Uncategorized') ?></span>
                                        </td>
                                        <td class="px-4 py-3 border-b">₱<?= number_format($material['Price'], 2) ?></td>
                                        <td class="px-4 py-3 border-b <?= $material['Stock'] <= 0 ? 'text-red-600 font-semibold' : '' ?>">
                                            <?= $material['Stock'] ?>
                                            <?php if ($material['Stock'] <= 0): ?>
                                                <span class="text-xs text-red-500">(Out of Stock)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 border-b">
                                            <input type="number" 
                                                   name="materials[<?= $material['Product_id'] ?>][quantity]" 
                                                   value="<?= $current_qty ?>" 
                                                   min="0" 
                                                   max="<?= $material['Stock'] ?>"
                                                   class="w-20 p-1 border rounded text-center quantity-input focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   data-price="<?= $material['Price'] ?>"
                                                   data-product-id="<?= $material['Product_id'] ?>"
                                                   data-product-name="<?= htmlspecialchars($material['Name']) ?>"
                                                   <?= $material['Stock'] <= 0 ? 'disabled' : '' ?>>
                                            <input type="hidden" 
                                                   name="materials[<?= $material['Product_id'] ?>][price]" 
                                                   value="<?= $material['Price'] ?>">
                                        </td>
                                        <td class="px-4 py-3 border-b total-cost font-semibold" id="total-cost-<?= $material['Product_id'] ?>">
                                            ₱<?= number_format($total_cost, 2) ?>
                                        </td>
                                        <td class="px-4 py-3 border-b">
                                            <button type="button" 
                                                    class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition duration-200 add-to-list-btn focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 <?= $material['Stock'] <= 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                    data-product-id="<?= $material['Product_id'] ?>"
                                                    data-product-name="<?= htmlspecialchars($material['Name']) ?>"
                                                    data-category="<?= htmlspecialchars($material['category_name'] ?? 'Uncategorized') ?>"
                                                    data-price="<?= $material['Price'] ?>"
                                                    data-stock="<?= $material['Stock'] ?>"
                                                    <?= $material['Stock'] <= 0 ? 'disabled' : '' ?>>
                                                Add to List
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Selected Materials List -->
                <div class="mb-6">
                    <h3 class="font-medium mb-3">Selected Materials List</h3>
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                                    <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                                    <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                    <th class="px-4 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody id="selectedMaterialsList">
                                <!-- Selected materials will be added here dynamically -->
                                <?php if (empty($project_materials)): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                            No materials selected yet. Use the "Add to List" button to add materials.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($project_materials as $material_id => $material): ?>
                                    <tr data-product-id="<?= $material_id ?>">
                                        <td class="px-4 py-3 border-b"><?= htmlspecialchars($material['Name']) ?></td>
                                        <td class="px-4 py-3 border-b">
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">Material</span>
                                        </td>
                                        <td class="px-4 py-3 border-b">₱<?= number_format($material['price'], 2) ?></td>
                                        <td class="px-4 py-3 border-b">
                                            <input type="number" 
                                                   class="w-20 p-1 border rounded text-center selected-quantity focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                                   value="<?= $material['quantity'] ?>" 
                                                   min="1" 
                                                   max="<?= $materials_list[array_search($material_id, array_column($materials_list, 'Product_id'))]['Stock'] ?? 0 ?>"
                                                   data-product-id="<?= $material_id ?>"
                                                   data-price="<?= $material['price'] ?>">
                                        </td>
                                        <td class="px-4 py-3 border-b selected-total-cost font-semibold">
                                            ₱<?= number_format($material['quantity'] * $material['price'], 2) ?>
                                        </td>
                                        <td class="px-4 py-3 border-b">
                                            <button type="button" 
                                                    class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition duration-200 remove-btn focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                                    data-product-id="<?= $material_id ?>">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cost Summary -->
                <div class="border-t pt-4">
                    <h3 class="font-medium mb-3">Cost Summary</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost (₱)</label>
                            <input type="number" name="total_cost" id="totalCost" 
                                   value="<?= htmlspecialchars($project['total_cost'] ?? '0.00') ?>" step="0.01" min="0"
                                   class="w-full p-2 border border-gray-300 rounded bg-gray-50 font-semibold" readonly>
                        </div>
                        <div class="text-sm text-gray-600 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>Cost updates automatically based on selected materials and quantities.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes & Submission Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Notes & Submission</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project Notes</label>
                        <textarea name="notes" rows="4" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                  placeholder="Any important notes about the project, requirements, or special instructions..."><?= htmlspecialchars($project['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-3 px-4 rounded hover:bg-blue-700 font-medium transition duration-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" id="submitBtn">
                            <?= $is_edit ? 'Update Project' : 'Create Project' ?>
                        </button>
                        <a href="projects.php" class="bg-gray-500 text-white py-3 px-6 rounded hover:bg-gray-600 font-medium transition duration-200 text-center">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Position-based staff filtering
        document.getElementById('positionFilter').addEventListener('change', function(e) {
            const selectedPosition = this.value.toLowerCase();
            const staffItems = document.querySelectorAll('.staff-item');
            
            staffItems.forEach(item => {
                const position = item.dataset.position;
                const matches = !selectedPosition || position === selectedPosition;
                item.style.display = matches ? 'flex' : 'none';
            });
        });

        // Category-based materials filtering
        document.getElementById('categoryFilter').addEventListener('change', function(e) {
            const selectedCategory = this.value.toLowerCase();
            const materialItems = document.querySelectorAll('.material-item');
            
            materialItems.forEach(item => {
                const category = item.dataset.category;
                const matches = !selectedCategory || category === selectedCategory;
                item.style.display = matches ? '' : 'none';
            });
        });

        // Calculate total cost for individual material row
        function calculateRowTotal(input) {
            const quantity = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price);
            const productId = input.dataset.productId;
            const total = quantity * price;
            
            document.getElementById(`total-cost-${productId}`).textContent = `₱${total.toFixed(2)}`;
            return total;
        }

        // Update overall total cost
        function calculateTotalCost() {
            let total = 0;
            const quantityInputs = document.querySelectorAll('.quantity-input, .selected-quantity');
            
            quantityInputs.forEach(input => {
                if (input.value && !isNaN(input.value)) {
                    const quantity = parseInt(input.value) || 0;
                    const price = parseFloat(input.dataset.price);
                    total += quantity * price;
                }
            });
            
            document.getElementById('totalCost').value = total.toFixed(2);
        }

        // Real-time validation for material quantities
        function validateQuantity(input) {
            const maxStock = parseInt(input.getAttribute('max'));
            const currentValue = parseInt(input.value) || 0;
            
            if (currentValue > maxStock) {
                alert(`Only ${maxStock} items available in stock for ${input.dataset.productName}`);
                input.value = maxStock;
                calculateRowTotal(input);
                calculateTotalCost();
            }
            
            if (currentValue < 0) {
                input.value = 0;
                calculateRowTotal(input);
                calculateTotalCost();
            }
        }

        // Form submission handler
        document.getElementById('projectForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
            
            // Add loading state to form
            this.classList.add('loading');
        });

        // Initialize functionality when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const selectedMaterialsList = document.getElementById('selectedMaterialsList');
            
            // Add event listeners to all "Add to List" buttons
            document.querySelectorAll('.add-to-list-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName;
                    const category = this.dataset.category;
                    const price = parseFloat(this.dataset.price);
                    const stock = parseInt(this.dataset.stock);
                    
                    const quantityInput = document.querySelector(`input[data-product-id="${productId}"]`);
                    const quantity = parseInt(quantityInput.value) || 0;
                    
                    if (quantity <= 0) {
                        alert('Please enter a quantity greater than 0');
                        return;
                    }
                    
                    if (quantity > stock) {
                        alert(`Only ${stock} items available in stock`);
                        quantityInput.value = stock;
                        calculateRowTotal(quantityInput);
                        calculateTotalCost();
                        return;
                    }
                    
                    const totalCost = quantity * price;
                    
                    // Check if item already exists in selected list
                    const existingItem = document.querySelector(`#selectedMaterialsList tr[data-product-id="${productId}"]`);
                    
                    if (existingItem) {
                        // Update existing item
                        const existingQtyInput = existingItem.querySelector('.selected-quantity');
                        const existingTotalCell = existingItem.querySelector('.selected-total-cost');
                        
                        existingQtyInput.value = quantity;
                        existingTotalCell.textContent = `₱${totalCost.toFixed(2)}`;
                    } else {
                        // Add new item to selected list
                        const row = document.createElement('tr');
                        row.dataset.productId = productId;
                        row.innerHTML = `
                            <td class="px-4 py-3 border-b">${productName}</td>
                            <td class="px-4 py-3 border-b">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">${category}</span>
                            </td>
                            <td class="px-4 py-3 border-b">₱${price.toFixed(2)}</td>
                            <td class="px-4 py-3 border-b">
                                <input type="number" 
                                       class="w-20 p-1 border rounded text-center selected-quantity focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${quantity}" 
                                       min="1" 
                                       max="${stock}"
                                       data-product-id="${productId}"
                                       data-price="${price}">
                            </td>
                            <td class="px-4 py-3 border-b selected-total-cost font-semibold">₱${totalCost.toFixed(2)}</td>
                            <td class="px-4 py-3 border-b">
                                <button type="button" 
                                        class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition duration-200 remove-btn focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                        data-product-id="${productId}">
                                    Remove
                                </button>
                            </td>
                        `;
                        selectedMaterialsList.appendChild(row);
                        
                        // Remove empty message if it exists
                        const emptyMessage = selectedMaterialsList.querySelector('td[colspan="6"]');
                        if (emptyMessage) {
                            emptyMessage.closest('tr').remove();
                        }
                        
                        // Add event listener to remove button
                        row.querySelector('.remove-btn').addEventListener('click', function() {
                            row.remove();
                            
                            // Reset the quantity in main table
                            const mainInput = document.querySelector(`input[data-product-id="${this.dataset.productId}"]`);
                            mainInput.value = 0;
                            calculateRowTotal(mainInput);
                            calculateTotalCost();
                            
                            // Show empty message if no items left
                            if (selectedMaterialsList.querySelectorAll('tr').length === 0) {
                                selectedMaterialsList.innerHTML = `
                                    <tr>
                                        <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                            No materials selected yet. Use the "Add to List" button to add materials.
                                        </td>
                                    </tr>
                                `;
                            }
                        });
                        
                        // Add event listener to quantity input in selected list
                        row.querySelector('.selected-quantity').addEventListener('change', function() {
                            const selectedQty = parseInt(this.value) || 0;
                            const selectedPrice = parseFloat(this.dataset.price);
                            const selectedTotal = selectedQty * selectedPrice;
                            
                            // Validate stock
                            if (selectedQty > parseInt(this.getAttribute('max'))) {
                                alert(`Only ${this.getAttribute('max')} items available in stock`);
                                this.value = this.getAttribute('max');
                                return;
                            }
                            
                            // Update the total cost display
                            this.closest('tr').querySelector('.selected-total-cost').textContent = `₱${selectedTotal.toFixed(2)}`;
                            
                            // Update the corresponding input in the main table
                            const mainInput = document.querySelector(`input[data-product-id="${this.dataset.productId}"]`);
                            mainInput.value = selectedQty;
                            
                            // Update the row total in main table
                            calculateRowTotal(mainInput);
                            calculateTotalCost();
                        });
                        
                        row.querySelector('.selected-quantity').addEventListener('input', function() {
                            calculateTotalCost();
                        });
                    }
                    
                    calculateTotalCost();
                });
            });
            
            // Add event listeners to all quantity inputs in main table
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    validateQuantity(this);
                    calculateRowTotal(this);
                    calculateTotalCost();
                });
                
                input.addEventListener('input', function() {
                    calculateRowTotal(this);
                    calculateTotalCost();
                });
            });
            
            // Add event listeners to existing selected quantity inputs
            document.querySelectorAll('.selected-quantity').forEach(input => {
                input.addEventListener('change', function() {
                    validateQuantity(this);
                    calculateTotalCost();
                });
                
                input.addEventListener('input', function() {
                    calculateTotalCost();
                });
            });
            
            // Add event listeners to existing remove buttons
            document.querySelectorAll('.remove-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const row = this.closest('tr');
                    const productId = this.dataset.productId;
                    
                    row.remove();
                    
                    // Reset the quantity in main table
                    const mainInput = document.querySelector(`input[data-product-id="${productId}"]`);
                    if (mainInput) {
                        mainInput.value = 0;
                        calculateRowTotal(mainInput);
                    }
                    calculateTotalCost();
                    
                    // Show empty message if no items left
                    if (selectedMaterialsList.querySelectorAll('tr').length === 0) {
                        selectedMaterialsList.innerHTML = `
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                    No materials selected yet. Use the "Add to List" button to add materials.
                                </td>
                            </tr>
                        `;
                    }
                });
            });
            
            // Initial calculation
            calculateTotalCost();
        });
    </script>
</body>
</html>