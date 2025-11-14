<?php
// management_form.php - Enhanced Project Management Form
include "../../db.php";
session_start();

// Check connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed.");
}

$project = [];
$staff_list = [];
$materials_list = [];
$message = '';
$status = '';
$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = !!$project_id;
$assigned_staff_ids = [];
$project_materials = [];
$selected_materials = []; // For the materials list

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    try {
        $conn->autocommit(FALSE); // Start transaction
        
        if (!$project_id) {
            // Create new project - Fixed field name to match database
            $stmt = $conn->prepare("INSERT INTO Projects (Orders_id, Project_Name, client_name, client_contact, client_email, client_address, status, notes, total_cost, StartDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssssssd", $orders_id, $project_name, $client_name, $client_contact, $client_email, $client_address, $project_status, $notes, $total_cost);
            if (!$stmt->execute()) {
                throw new Exception("Failed to create project: " . $stmt->error);
            }
            $project_id = $conn->insert_id;
            $message = "Project created successfully!";
        } else {
            // Update existing project - Fixed field name
            $stmt = $conn->prepare("UPDATE Projects SET Project_Name=?, client_name=?, client_contact=?, client_email=?, client_address=?, status=?, notes=?, total_cost=? WHERE Project_id=?");
            $stmt->bind_param("sssssssdi", $project_name, $client_name, $client_contact, $client_email, $client_address, $project_status, $notes, $total_cost, $project_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update project: " . $stmt->error);
            }
            $message = "Project updated successfully!";
        }

        // Update staff assignments - Fixed SQL injection
        $delete_stmt = $conn->prepare("DELETE FROM ProjectStaff WHERE Project_id = ?");
        $delete_stmt->bind_param("i", $project_id);
        if (!$delete_stmt->execute()) {
            throw new Exception("Failed to clear staff assignments: " . $delete_stmt->error);
        }

        if (!empty($assigned_staff)) {
            $staff_stmt = $conn->prepare("INSERT INTO ProjectStaff (Project_id, Staff_id) VALUES (?, ?)");
            foreach ($assigned_staff as $staff_id) {
                $staff_id_clean = intval($staff_id);
                $staff_stmt->bind_param("ii", $project_id, $staff_id_clean);
                if (!$staff_stmt->execute()) {
                    throw new Exception("Failed to assign staff: " . $staff_stmt->error);
                }
            }
            $staff_stmt->close();
        }

        // Update materials using orderitems table
        if ($orders_id) {
            // Remove existing materials for this order - Fixed SQL injection
            $delete_materials_stmt = $conn->prepare("DELETE FROM orderitems WHERE Orders_id = ? AND product_id IS NOT NULL");
            $delete_materials_stmt->bind_param("i", $orders_id);
            if (!$delete_materials_stmt->execute()) {
                throw new Exception("Failed to clear materials: " . $delete_materials_stmt->error);
            }

            // Insert new materials - Fixed SQL injection
            foreach ($materials as $material_id => $data) {
                if (!empty($data['quantity']) && $data['quantity'] > 0) {
                    $quantity = intval($data['quantity']);
                    $price = floatval($data['price'] ?? 0);
                    $material_id_clean = intval($material_id);
                    
                    $material_stmt = $conn->prepare("INSERT INTO orderitems (Orders_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $material_stmt->bind_param("iiid", $orders_id, $material_id_clean, $quantity, $price);
                    if (!$material_stmt->execute()) {
                        throw new Exception("Failed to add materials: " . $material_stmt->error);
                    }
                    $material_stmt->close();
                }
            }
        }

        $conn->commit();
        $status = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $status = 'error';
    } finally {
        $conn->autocommit(TRUE);
    }

    header("Location: management_form.php?id=$project_id&status=$status&msg=" . urlencode($message));
    exit();
}

// Load existing project data - Fixed SQL injection
if ($project_id) {
    $stmt = $conn->prepare("
        SELECT p.*, o.Orders_id, c.Name as client_name, c.PhoneNumber as client_contact, 
               c.Email as client_email, c.Address as client_address 
        FROM Projects p 
        LEFT JOIN Orders o ON p.Orders_id = o.Orders_id 
        LEFT JOIN Customers c ON o.customer_id = c.customer_id 
        WHERE p.Project_id = ?
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc() ?? [];
    $stmt->close();
    
    // Get assigned staff - Fixed SQL injection
    $stmt = $conn->prepare("SELECT Staff_id FROM ProjectStaff WHERE Project_id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned_staff_ids = [];
    while ($row = $result->fetch_assoc()) {
        $assigned_staff_ids[] = $row['Staff_id'];
    }
    $stmt->close();
    
    // Get project materials from orderitems - Fixed SQL injection
    if (!empty($project['Orders_id'])) {
        $stmt = $conn->prepare("
            SELECT oi.product_id, oi.quantity, oi.price, p.Name 
            FROM orderitems oi 
            JOIN Product p ON oi.product_id = p.Product_id 
            WHERE oi.Orders_id = ? AND oi.product_id IS NOT NULL
        ");
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

// Load staff (excluding admins) and get unique positions
$staff_result = $conn->query("SELECT Staff_id, Name, Position FROM Staff WHERE Role != 'Admin' ORDER BY Position, Name");
if ($staff_result) {
    $staff_list = $staff_result->fetch_all(MYSQLI_ASSOC);
} else {
    $staff_list = [];
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
if (!$project || empty($project)) {
    $project = [
        'Project_Name' => '',
        'client_name' => '',
        'client_contact' => '',
        'client_email' => '',
        'client_address' => '',
        'status' => 'Pending',
        'notes' => '', // Added this field
        'total_cost' => '0.00',
        'Orders_id' => ''
    ];
} else {
    // Ensure all keys exist even for existing projects
    $defaults = [
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
    $project = array_merge($defaults, $project);
}

// If creating from order, get order details - Fixed SQL injection
$orders_id_from_url = filter_input(INPUT_GET, 'orders_id', FILTER_VALIDATE_INT);
if (!$project_id && $orders_id_from_url) {
    $stmt = $conn->prepare("
        SELECT o.Orders_id, c.Name as client_name, c.PhoneNumber as client_contact, 
               c.Email as client_email, c.Address as client_address, s.Name as service_name
        FROM Orders o 
        LEFT JOIN Customers c ON o.customer_id = c.customer_id 
        LEFT JOIN Services s ON o.Services_id = s.Services_id 
        WHERE o.Orders_id = ?
    ");
    $stmt->bind_param("i", $orders_id_from_url);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($order_data = $result->fetch_assoc()) {
        $project['Orders_id'] = $order_data['Orders_id'];
        $project['client_name'] = $order_data['client_name'];
        $project['client_contact'] = $order_data['client_contact'];
        $project['client_email'] = $order_data['client_email'];
        $project['client_address'] = $order_data['client_address'];
        $project['Project_Name'] = $order_data['service_name'] . ' Project';
        // Initialize notes if not set
        if (!isset($project['notes'])) {
            $project['notes'] = '';
        }
    }
    $stmt->close();
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
            <a href="projects.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                ← Back to Projects
            </a>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 rounded <?= $status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6" id="projectForm">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <input type="hidden" name="orders_id" value="<?= htmlspecialchars($project['Orders_id'] ?? '') ?>">

            <!-- Project Information Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Project Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project Name</label>
                        <input type="text" name="project_name" value="<?= htmlspecialchars($project['Project_Name'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full p-2 border border-gray-300 rounded">
                            <option value="Pending" <?= ($project['status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Ongoing" <?= ($project['status'] ?? '') === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                            <option value="Completed" <?= ($project['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Client Information Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Client Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client Name</label>
                        <input type="text" name="client_name" value="<?= htmlspecialchars($project['client_name'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                        <input type="text" name="client_contact" value="<?= htmlspecialchars($project['client_contact'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="client_email" value="<?= htmlspecialchars($project['client_email'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="client_address" value="<?= htmlspecialchars($project['client_address'] ?? '') ?>" 
                               class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
            </div>

            <!-- Staff Assignment Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Staff Assignment</h2>
                
                <!-- Position Filter -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Position</label>
                    <select id="positionFilter" class="w-full p-2 border border-gray-300 rounded">
                        <option value="">All Positions</option>
                        <?php foreach ($unique_positions as $position): ?>
                            <option value="<?= htmlspecialchars(strtolower($position['Position'])) ?>">
                                <?= htmlspecialchars($position['Position']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-60 overflow-y-auto" id="staffList">
                    <?php foreach ($staff_list as $staff): ?>
                    <div class="staff-item flex items-center p-2 border rounded" 
                         data-position="<?= strtolower(htmlspecialchars($staff['Position'])) ?>">
                        <input type="checkbox" name="assigned_staff[]" value="<?= $staff['Staff_id'] ?>" 
                               id="staff_<?= $staff['Staff_id'] ?>"
                               <?= in_array($staff['Staff_id'], $assigned_staff_ids) ? 'checked' : '' ?>
                               class="mr-2">
                        <label for="staff_<?= $staff['Staff_id'] ?>" class="cursor-pointer flex-1">
                            <div class="font-medium"><?= htmlspecialchars($staff['Name']) ?></div>
                            <div class="text-sm text-gray-600"><?= htmlspecialchars($staff['Position']) ?></div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Materials & Costs Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Materials & Costs</h2>
                
                <!-- Materials Filter -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Category</label>
                    <select id="categoryFilter" class="w-full p-2 border border-gray-300 rounded">
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
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody id="materialsTableBody">
                                <?php foreach ($materials_list as $material): 
                                    $current_qty = $project_materials[$material['Product_id']]['quantity'] ?? 0;
                                    $total_cost = $current_qty * $material['Price'];
                                ?>
                                <tr class="material-item hover:bg-gray-50" data-category="<?= htmlspecialchars(strtolower($material['Category_id'])) ?>">
                                    <td class="px-4 py-2 border-b">
                                        <div class="font-medium"><?= htmlspecialchars($material['Name']) ?></div>
                                    </td>
                                    <td class="px-4 py-2 border-b">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs"><?= htmlspecialchars($material['category_name']) ?></span>
                                    </td>
                                    <td class="px-4 py-2 border-b">₱<?= number_format($material['Price'], 2) ?></td>
                                    <td class="px-4 py-2 border-b"><?= $material['Stock'] ?></td>
                                    <td class="px-4 py-2 border-b">
                                        <input type="number" 
                                               name="materials[<?= $material['Product_id'] ?>][quantity]" 
                                               value="<?= $current_qty ?>" 
                                               min="0" 
                                               max="<?= $material['Stock'] ?>"
                                               class="w-20 p-1 border rounded text-center quantity-input"
                                               data-price="<?= $material['Price'] ?>"
                                               data-product-id="<?= $material['Product_id'] ?>">
                                        <input type="hidden" 
                                               name="materials[<?= $material['Product_id'] ?>][price]" 
                                               value="<?= $material['Price'] ?>">
                                    </td>
                                    <td class="px-4 py-2 border-b total-cost" id="total-cost-<?= $material['Product_id'] ?>">
                                        ₱<?= number_format($total_cost, 2) ?>
                                    </td>
                                    <td class="px-4 py-2 border-b">
                                        <button type="button" 
                                                class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition duration-200 add-to-list-btn"
                                                data-product-id="<?= $material['Product_id'] ?>"
                                                data-product-name="<?= htmlspecialchars($material['Name']) ?>"
                                                data-category="<?= htmlspecialchars($material['category_name']) ?>"
                                                data-price="<?= $material['Price'] ?>"
                                                data-stock="<?= $material['Stock'] ?>">
                                            Add to List
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Selected Materials List -->
                <div class="mb-6">
                    <h3 class="font-medium mb-3">Selected Materials List</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                    <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody id="selectedMaterialsList">
                                <!-- Selected materials will be added here dynamically -->
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
                                   class="w-full p-2 border border-gray-300 rounded" readonly>
                        </div>
                        <div class="text-sm text-gray-600">
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
                        <textarea name="notes" rows="3" class="w-full p-2 border border-gray-300 rounded" 
                                  placeholder="Any important notes about the project, requirements, or special instructions..."><?= htmlspecialchars($project['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 font-medium transition duration-200">
                        <?= $is_edit ? 'Update Project' : 'Create Project' ?>
                    </button>
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
            const quantityInputs = document.querySelectorAll('.quantity-input');
            
            quantityInputs.forEach(input => {
                if (input.value && !isNaN(input.value)) {
                    const quantity = parseInt(input.value) || 0;
                    const price = parseFloat(input.dataset.price);
                    total += quantity * price;
                }
            });
            
            document.getElementById('totalCost').value = total.toFixed(2);
        }

        // Add to list functionality
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
                            <td class="px-4 py-2 border-b">${productName}</td>
                            <td class="px-4 py-2 border-b">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">${category}</span>
                            </td>
                            <td class="px-4 py-2 border-b">₱${price.toFixed(2)}</td>
                            <td class="px-4 py-2 border-b">
                                <input type="number" 
                                       class="w-20 p-1 border rounded text-center selected-quantity" 
                                       value="${quantity}" 
                                       min="1" 
                                       max="${stock}"
                                       data-product-id="${productId}"
                                       data-price="${price}">
                            </td>
                            <td class="px-4 py-2 border-b selected-total-cost">₱${totalCost.toFixed(2)}</td>
                            <td class="px-4 py-2 border-b">
                                <button type="button" 
                                        class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition duration-200 remove-btn"
                                        data-product-id="${productId}">
                                    Remove
                                </button>
                            </td>
                        `;
                        selectedMaterialsList.appendChild(row);
                        
                        // Add event listener to remove button
                        row.querySelector('.remove-btn').addEventListener('click', function() {
                            row.remove();
                            calculateTotalCost();
                        });
                        
                        // Add event listener to quantity input in selected list
                        row.querySelector('.selected-quantity').addEventListener('change', function() {
                            const selectedQty = parseInt(this.value) || 0;
                            const selectedPrice = parseFloat(this.dataset.price);
                            const selectedTotal = selectedQty * selectedPrice;
                            
                            // Update the total cost display
                            this.closest('tr').querySelector('.selected-total-cost').textContent = `₱${selectedTotal.toFixed(2)}`;
                            
                            // Update the corresponding input in the main table
                            const mainInput = document.querySelector(`input[data-product-id="${this.dataset.productId}"]`);
                            mainInput.value = selectedQty;
                            
                            // Update the row total in main table
                            calculateRowTotal(mainInput);
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
            
            // Initial calculation
            calculateTotalCost();
        });

        // Real-time validation for material quantities
        function validateQuantity(input) {
            const maxStock = parseInt(input.getAttribute('max'));
            const currentValue = parseInt(input.value) || 0;
            
            if (currentValue > maxStock) {
                alert(`Only ${maxStock} items available in stock`);
                input.value = maxStock;
            }
            
            if (currentValue < 0) {
                input.value = 0;
            }
        }
    </script>
</body>
</html>