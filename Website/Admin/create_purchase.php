<?php
include("../../db.php");
session_start();

// Function to update inventory from purchase order
function updateInventoryFromPO($conn, $po_id, $po_number) {
    // Get all products from this PO
    $items_query = "
        SELECT pi.Product_id, pi.Quantity, p.Name as product_name, p.Stock as previous_stock
        FROM productimports pi
        JOIN product p ON pi.Product_id = p.Product_id
        WHERE pi.PO_id = ?
    ";
    
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        $product_id = $item['Product_id'];
        $quantity = $item['Quantity'];
        $previous_stock = $item['previous_stock'];
        $new_stock = $previous_stock + $quantity;
        
        // Update product stock
        $update_stmt = $conn->prepare("UPDATE product SET Stock = Stock + ? WHERE Product_id = ?");
        $update_stmt->bind_param("ii", $quantity, $product_id);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows > 0) {
            // Log the inventory change
            try {
                $log_stmt = $conn->prepare("INSERT INTO inventory_log (Product_id, Change_Amount, Previous_Stock, New_Stock, Change_Type, Reference, Changed_By) VALUES (?, ?, ?, ?, 'PO_RECEIVED', ?, ?)");
                $reference = "PO: " . $po_number;
                $changed_by = $_SESSION['staff_name'] ?? 'System';
                $log_stmt->bind_param("iiiiss", $product_id, $quantity, $previous_stock, $new_stock, $reference, $changed_by);
                $log_stmt->execute();
                $log_stmt->close();
            } catch (Exception $e) {
                // Log table might not exist, continue without logging
            }
        }
        $update_stmt->close();
    }
    $stmt->close();
}

// Initialize variables with default values
$is_reorder = false;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$reorder_qty = isset($_GET['quantity']) ? (int)$_GET['quantity'] : null;
$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : null;
$complete_id = isset($_GET['complete_id']) ? (int)$_GET['complete_id'] : null;
$reorder_po_id = isset($_GET['reorder_po_id']) ? (int)$_GET['reorder_po_id'] : null;
$reorder_import_id = isset($_GET['reorder_import_id']) ? (int)$_GET['reorder_import_id'] : null;

// Initialize default po_data to prevent undefined variable errors
$po_data = [
    'po_number' => 'PO-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
    'date_created' => date('Y-m-d'),
    'supplier_name' => '',
    'supplier_email' => '',
    'supplier_id' => null,
    'product_id' => $product_id,
    'product_name' => '',
    'product_description' => '',
    'quantity' => $reorder_qty,
    'unit_price' => 0.00,
    'total' => 0.00,
    'estimated_date' => date('Y-m-d', strtotime('+7 days')),
    'contact_name' => '',
    'address' => '',
    'payment_terms' => 'Net 30',
    'currency' => 'PHP',
    'est_ship_date' => date('Y-m-d', strtotime('+7 days')),
    'mode' => 'Truck/LCL',
    'carrier' => '',
    'subtotal' => 0.00,
    'discount' => 0.00,
    'grand_total' => 0.00,
    'status' => 'Pending'
];
$po_items = [];

// Fetch all products for autocomplete
$products_result = $conn->query("SELECT Product_id as id, Name as name, Description as description, Price as price FROM product ORDER BY Name ASC");
$products_data = [];
while ($product = $products_result->fetch_assoc()) {
    $products_data[] = $product;
}
$products_json = json_encode($products_data);

// Handle complete action from reorder.php
if ($complete_id) {
    $conn->begin_transaction();
    try {
        // Update PO status to Received
        $update_stmt = $conn->prepare("UPDATE purchaseorders SET Status = 'Received' WHERE PO_id = ?");
        $update_stmt->bind_param("i", $complete_id);
        $update_stmt->execute();
        
        // Get PO number for reference
        $po_stmt = $conn->prepare("SELECT PO_Number FROM purchaseorders WHERE PO_id = ?");
        $po_stmt->bind_param("i", $complete_id);
        $po_stmt->execute();
        $po_result = $po_stmt->get_result();
        $po_data_db = $po_result->fetch_assoc();
        $po_number = $po_data_db['PO_Number'];
        $po_stmt->close();
        
        // Update inventory
        updateInventoryFromPO($conn, $complete_id, $po_number);
        
        $conn->commit();
        $_SESSION['success_message'] = "Purchase order marked as received and inventory updated!";
        header("Location: reorder.php?status=success&po_number=" . urlencode($po_number) . "&action=complete");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error completing purchase order: " . $e->getMessage();
    }
}

// Fetch all suppliers for the dropdown
$suppliers_result = $conn->query("SELECT Supplier_id, Company_Name FROM supplier ORDER BY Company_Name ASC");
$suppliers = [];
while ($supplier = $suppliers_result->fetch_assoc()) {
    $suppliers[] = $supplier;
}

// --- HANDLE FORM SUBMISSION (Save Purchase Order) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $po_number = $_POST['po_number'] ?? '';
    $edit_id = $_POST['edit_id'] ?? null;
    
    // Debug: Check if supplier_id is set
    if (!isset($_POST['supplier_id']) || empty($_POST['supplier_id'])) {
        $error_message = "Supplier is required!";
    } else {
        $supplier_id = (int)$_POST['supplier_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Enhanced status handling
            $current_status = 'Pending';
            if ($edit_id) {
                $status_stmt = $conn->prepare("SELECT Status FROM purchaseorders WHERE PO_id = ?");
                $status_stmt->bind_param("i", $edit_id);
                $status_stmt->execute();
                $status_result = $status_stmt->get_result();
                if ($status_result->num_rows > 0) {
                    $status_row = $status_result->fetch_assoc();
                    $current_status = $status_row['Status'];
                }
                $status_stmt->close();
            }
            
            $new_status = $current_status;
            if ($action === 'approve' && in_array($current_status, ['Pending'])) {
                $new_status = 'Approved';
            } elseif ($action === 'send' && in_array($current_status, ['Approved', 'Pending'])) {
                $new_status = 'Sent';
            } elseif ($action === 'receive' && in_array($current_status, ['Sent', 'Approved'])) {
                $new_status = 'Received';
            } elseif ($action === 'draft') {
                $new_status = 'Pending';
            }

            // Calculate totals from items
            $subtotal = 0;
            if (isset($_POST['item_name'])) {
                foreach ($_POST['item_name'] as $index => $item_name) {
                    if (!empty($item_name) && !empty($_POST['item_qty'][$index]) && !empty($_POST['item_price'][$index])) {
                        $quantity = (float)$_POST['item_qty'][$index];
                        $price = (float)$_POST['item_price'][$index];
                        $subtotal += $quantity * $price;
                    }
                }
            }
            
            $discount = (float)($_POST['discount'] ?? 0);
            $grand_total = max(0, $subtotal - $discount);

            if ($edit_id) {
                // Update existing purchase order
                $stmt = $conn->prepare("
                    UPDATE purchaseorders SET 
                    Supplier_id = ?, 
                    Est_Ship_Date = ?,
                    Total_Amount = ?,
                    Discount = ?,
                    Final_Amount = ?,
                    Status = ?
                    WHERE PO_id = ?
                ");
                
                $est_ship_date = $_POST['est_ship_date'];
                
                $stmt->bind_param(
                    "isdddss",
                    $supplier_id,
                    $est_ship_date,
                    $subtotal,
                    $discount,
                    $grand_total,
                    $new_status,
                    $edit_id
                );
                
                $stmt->execute();
                $stmt->close();
                
                // Delete old items from productimports
                $delete_stmt = $conn->prepare("DELETE FROM productimports WHERE PO_id = ?");
                $delete_stmt->bind_param("i", $edit_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
            } else {
                // Create new purchase order
                $stmt = $conn->prepare("
                    INSERT INTO purchaseorders 
                    (PO_Number, Supplier_id, Date_Created, Est_Ship_Date, Total_Amount, Discount, Final_Amount, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $date_created = date('Y-m-d');
                $est_ship_date = $_POST['est_ship_date'];
                
                $stmt->bind_param(
                    "sisdddss",
                    $po_number,
                    $supplier_id,
                    $date_created,
                    $est_ship_date,
                    $subtotal,
                    $discount,
                    $grand_total,
                    $new_status
                );
                
                $stmt->execute();
                $po_id = $conn->insert_id;
                $stmt->close();
            }
            
            // Get the PO ID (either from edit or new insert)
            $current_po_id = $edit_id ? $edit_id : $po_id;
            
            // Save items to productimports table
            if (isset($_POST['item_name'])) {
                foreach ($_POST['item_name'] as $index => $item_name) {
                    if (!empty($item_name) && !empty($_POST['item_qty'][$index]) && !empty($_POST['item_price'][$index])) {
                        // Try to find product ID by name
                        $product_stmt = $conn->prepare("SELECT Product_id FROM product WHERE Name = ?");
                        $product_stmt->bind_param("s", $item_name);
                        $product_stmt->execute();
                        $product_result = $product_stmt->get_result();
                        
                        $product_id = null;
                        if ($product_result->num_rows > 0) {
                            $product_row = $product_result->fetch_assoc();
                            $product_id = $product_row['Product_id'];
                        }
                        $product_stmt->close();
                        
                        // Insert into productimports
                        $item_stmt = $conn->prepare("
                            INSERT INTO productimports 
                            (PO_id, Supplier_id, Product_id, Quantity, Price, ImportDate) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $import_date = date('Y-m-d');
                        $quantity = $_POST['item_qty'][$index] ?? 0;
                        $price = $_POST['item_price'][$index] ?? 0;
                        
                        $item_stmt->bind_param(
                            "iiiids", 
                            $current_po_id,
                            $supplier_id,
                            $product_id,
                            $quantity,
                            $price,
                            $import_date
                        );
                        $item_stmt->execute();
                        $item_stmt->close();
                    }
                }
            }
            
            // Update inventory if status is Received
            if ($new_status === 'Received' && $current_status !== 'Received') {
                updateInventoryFromPO($conn, $current_po_id, $po_number);
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Purchase order {$new_status} successfully!";
            header("Location: reorder.php?status=success&po_number=" . urlencode($po_number) . "&action=" . urlencode($action));
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error saving purchase order: " . $e->getMessage();
        }
    }
}

// Handle edit mode (for displaying existing data)
if ($edit_id && !isset($_POST['action'])) {
    // Fetch existing purchase order
    $stmt = $conn->prepare("SELECT * FROM purchaseorders WHERE PO_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $existing_po = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing_po) {
        $po_data = [
            'po_number' => $existing_po['PO_Number'],
            'date_created' => $existing_po['Date_Created'],
            'supplier_id' => $existing_po['Supplier_id'],
            'estimated_date' => $existing_po['Est_Ship_Date'],
            'subtotal' => $existing_po['Total_Amount'],
            'discount' => $existing_po['Discount'],
            'grand_total' => $existing_po['Final_Amount'],
            'est_ship_date' => $existing_po['Est_Ship_Date'],
            'supplier_name' => '',
            'supplier_email' => '',
            'contact_name' => '',
            'address' => '',
            'payment_terms' => 'Net 30',
            'currency' => 'PHP',
            'mode' => 'Truck/LCL',
            'carrier' => '',
            'status' => $existing_po['Status']
        ];
        
        // Get supplier info
        $supplier_stmt = $conn->prepare("SELECT Company_Name, Email, Contact_Person, Address FROM supplier WHERE Supplier_id = ?");
        $supplier_stmt->bind_param("i", $existing_po['Supplier_id']);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();
        if ($supplier_result->num_rows > 0) {
            $supplier = $supplier_result->fetch_assoc();
            $po_data['supplier_name'] = $supplier['Company_Name'];
            $po_data['supplier_email'] = $supplier['Email'];
            $po_data['contact_name'] = $supplier['Contact_Person'];
            $po_data['address'] = $supplier['Address'];
        }
        $supplier_stmt->close();
        
        // Fetch items from productimports
        $items_stmt = $conn->prepare("
            SELECT pi.*, p.Name as product_name, p.Description as product_description 
            FROM productimports pi 
            LEFT JOIN product p ON pi.Product_id = p.Product_id 
            WHERE pi.PO_id = ?
        ");
        $items_stmt->bind_param("i", $edit_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $po_items = [];
        while ($item = $items_result->fetch_assoc()) {
            $po_items[] = [
                'item_name' => $item['product_name'],
                'description' => $item['product_description'],
                'quantity' => $item['Quantity'],
                'unit_price' => $item['Price']
            ];
        }
        $items_stmt->close();
    }
} 
// Handle reorder from PO
elseif ($reorder_po_id && !isset($_POST['action'])) {
    $is_reorder = true;
    
    // Fetch the PO to reorder
    $stmt = $conn->prepare("SELECT * FROM purchaseorders WHERE PO_id = ?");
    $stmt->bind_param("i", $reorder_po_id);
    $stmt->execute();
    $existing_po = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing_po) {
        $po_data = [
            'po_number' => 'PO-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
            'date_created' => date('Y-m-d'),
            'supplier_id' => $existing_po['Supplier_id'],
            'estimated_date' => date('Y-m-d', strtotime('+7 days')),
            'subtotal' => $existing_po['Total_Amount'],
            'discount' => $existing_po['Discount'],
            'grand_total' => $existing_po['Final_Amount'],
            'est_ship_date' => date('Y-m-d', strtotime('+7 days')),
            'supplier_name' => '',
            'supplier_email' => '',
            'contact_name' => '',
            'address' => '',
            'payment_terms' => 'Net 30',
            'currency' => 'PHP',
            'mode' => 'Truck/LCL',
            'carrier' => '',
            'status' => 'Pending'
        ];
        
        // Get supplier info
        $supplier_stmt = $conn->prepare("SELECT Company_Name, Email, Contact_Person, Address FROM supplier WHERE Supplier_id = ?");
        $supplier_stmt->bind_param("i", $existing_po['Supplier_id']);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();
        if ($supplier_result->num_rows > 0) {
            $supplier = $supplier_result->fetch_assoc();
            $po_data['supplier_name'] = $supplier['Company_Name'];
            $po_data['supplier_email'] = $supplier['Email'];
            $po_data['contact_name'] = $supplier['Contact_Person'];
            $po_data['address'] = $supplier['Address'];
        }
        $supplier_stmt->close();
        
        // Fetch items from the original PO
        $items_stmt = $conn->prepare("
            SELECT pi.*, p.Name as product_name, p.Description as product_description 
            FROM productimports pi 
            LEFT JOIN product p ON pi.Product_id = p.Product_id 
            WHERE pi.PO_id = ?
        ");
        $items_stmt->bind_param("i", $reorder_po_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $po_items = [];
        while ($item = $items_result->fetch_assoc()) {
            $po_items[] = [
                'item_name' => $item['product_name'],
                'description' => $item['product_description'],
                'quantity' => $item['Quantity'],
                'unit_price' => $item['Price']
            ];
        }
        $items_stmt->close();
    }
}
// Handle reorder from import
elseif ($reorder_import_id && !isset($_POST['action'])) {
    $is_reorder = true;
    
    // Fetch the import to reorder
    $stmt = $conn->prepare("
        SELECT pi.*, p.Name as product_name, p.Description, s.Supplier_id, s.Company_Name, s.Email, s.Contact_Person, s.Address
        FROM productimports pi
        JOIN product p ON pi.Product_id = p.Product_id
        JOIN supplier s ON pi.Supplier_id = s.Supplier_id
        WHERE pi.Import_id = ?
    ");
    $stmt->bind_param("i", $reorder_import_id);
    $stmt->execute();
    $import_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($import_data) {
        $po_data = [
            'po_number' => 'PO-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
            'date_created' => date('Y-m-d'),
            'supplier_id' => $import_data['Supplier_id'],
            'supplier_name' => $import_data['Company_Name'],
            'supplier_email' => $import_data['Email'],
            'contact_name' => $import_data['Contact_Person'],
            'address' => $import_data['Address'],
            'estimated_date' => date('Y-m-d', strtotime('+7 days')),
            'subtotal' => $import_data['Quantity'] * $import_data['Price'],
            'discount' => 0.00,
            'grand_total' => $import_data['Quantity'] * $import_data['Price'],
            'est_ship_date' => date('Y-m-d', strtotime('+7 days')),
            'payment_terms' => 'Net 30',
            'currency' => 'PHP',
            'mode' => 'Truck/LCL',
            'carrier' => '',
            'status' => 'Pending'
        ];
        
        $po_items = [[
            'item_name' => $import_data['product_name'],
            'description' => $import_data['Description'],
            'quantity' => $import_data['Quantity'],
            'unit_price' => $import_data['Price']
        ]];
    }
}

// Helper to format currency
function formatCurrency($amount) {
    return number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALG | <?= $edit_id ? 'Edit' : 'Create' ?> Purchase Order</title>
    <style>
        /* Your existing CSS styles remain the same */
        /* Print Styles */
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .invoice-container { box-shadow: none; padding: 0; }
            .sidebar, header { display: none; }
            .container { display: block; }
            .content { padding: 0; }
        }

        body { margin: 0; font-family: sans-serif; background: #e0e0e0; color: #333; }
        header { display: flex; align-items: center; justify-content: space-between; background: #3e3e3e; color: white; padding: 10px 30px; }
        header img { height: 40px; }
        nav { display: flex; gap: 25px; }
        nav a { color: white; text-decoration: none; font-weight: 600; padding: 6px 14px; border-radius: 20px; }
        nav a.active, nav a:hover { background: #d9d9d9; color: #000; }
        .container { display: flex; height: calc(100vh - 60px); }
        .sidebar { width: 250px; background: #d9d9d9; padding: 20px; box-shadow: 2px 0 6px rgba(0,0,0,0.1); }
        .sidebar h3 { margin-top: 0; font-size: 18px; }
        .sidebar a { display: block; margin: 10px 0; color: #333; text-decoration: none; font-weight: 600; }
        .content { flex: 1; padding: 30px; background: #f5f5f5; overflow-y: auto; }
        
        .invoice-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 1200px; margin: 0 auto; }
        
        .header-section { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header-section h2 { margin: 0; font-size: 2em; }
        
        .bill-ship-section { display: flex; gap: 40px; margin-bottom: 30px; }
        .bill-to, .shipment-info { flex: 1; border: 1px solid #ccc; padding: 15px; border-radius: 5px; }
        .bill-to h3, .shipment-info h3 { margin-top: 0; color: #444; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px; }
        
        .field-group { display: flex; align-items: center; margin-bottom: 8px; font-size: 0.9em; }
        .field-group label { width: 120px; font-weight: 600; }
        .field-group input, .field-group select { flex-grow: 1; padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .items-table th { background: #f7f7f7; font-weight: bold; }
        .items-table td input { width: 95%; border: none; padding: 0; }
        .items-table .total-col { text-align: right; font-weight: 600; }
        
        .summary-totals { width: 300px; float: right; margin-top: 20px; }
        .summary-totals div { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee; }
        .summary-totals .grand-total { font-weight: bold; border-top: 2px solid #333; margin-top: 5px; padding-top: 10px; }
        
        .actions-bar { text-align: center; margin-top: 50px; }
        .actions-bar button { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; margin: 0 10px; }
        .btn-send { background: #4f9bd2; color: white; }
        .btn-approve { background: #77c66c; color: white; }
        .btn-receive { background: #28a745; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .top-buttons button { background: #555; color: white; padding: 5px 10px; font-size: 0.9em; margin-left: 5px; }

        .reorder-notice { background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 20px; border: 1px solid #ffeeba; border-radius: 5px; text-align: center; }
        .error-notice { background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 20px; border: 1px solid #f5c6cb; border-radius: 5px; text-align: center; }
        .success-notice { background-color: #d4edda; color: #155724; padding: 10px; margin-bottom: 20px; border: 1px solid #c3e6cb; border-radius: 5px; text-align: center; }
        
        .current-status { 
            background: #6c757d; 
            color: white; 
            padding: 8px 15px; 
            border-radius: 20px; 
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }
        .status-pending { background: #6c757d; }
        .status-approved { background: #17a2b8; }
        .status-sent { background: #ffc107; color: black; }
        .status-received { background: #28a745; }
        
        /* Autocomplete styles */
        .autocomplete-container {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background-color: white;
        }
        
        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4;
        }
        
        .autocomplete-items div:hover {
            background-color: #e9e9e9;
        }
        
        .autocomplete-active {
            background-color: #4f9bd2 !important;
            color: white;
        }
        
        .btn-add-row {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .btn-remove-row {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>

<header class="no-print">
    <img src="../../images/alg-logo-black.png" alt="ALG Logo" height="40">
    <nav>
        <a href="home.php">Home</a>
        <a href="products.php" class="active">Inventory</a>
        <a href="projects.php">Projects</a>
        <a href="staff.php">Staff</a>
        <a href="reorder.php">Purchase Orders</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <aside class="sidebar no-print">
        <h3>Inventory</h3>
        <a href="products.php">Products</a>
        <a href="purchases.php">Purchased</a>
        <a href="supplier.php">Supplier</a>
        <a href="reorder.php" class="active">Purchase Orders</a>
        <a href="sales_report.php">Sales Report</a>
    </aside>

    <section class="content">
        <div class="top-bar no-print" style="justify-content: flex-end;">
            <div class="top-buttons">
                <button onclick="window.location.href='reorder.php'">Return to Orders</button>
                <button onclick="window.print()">Print/Export PDF</button>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="error-notice">
                ❌ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-notice">
                ✅ <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if ($is_reorder): ?>
            <div class="reorder-notice no-print">
                ⚠️ **REORDER:** This Purchase Order has been pre-filled from a previous order. Please review details before sending.
            </div>
        <?php endif; ?>

        <div class="invoice-container">
            <div class="header-section">
                <div>
                    <img src="../../images/alg-logo-black.png" alt="ALG" height="30"><br>
                    <small>123 Business Street</small><br>
                    <small>Manila, 1000</small><br>
                    <small>Phone: (02) 1234-5678</small><br>
                    <small>Website: AlgRoofing.com</small>
                </div>
                <h2>PURCHASE ORDER</h2>
                <div style="text-align: right; font-size: 0.9em;">
                    <div style="font-weight: bold;">Date Created: <?= htmlspecialchars($po_data['date_created']) ?></div>
                    <div>PO No.: <strong><?= htmlspecialchars($po_data['po_number']) ?></strong></div>
                    <div>Supplier ID: <?= htmlspecialchars($po_data['supplier_id'] ?? 'N/A') ?></div>
                    <div>Estimated Date: <?= htmlspecialchars($po_data['estimated_date']) ?></div>
                    <?php if ($edit_id): ?>
                        <div class="current-status status-<?= strtolower($po_data['status']) ?>">
                            Current Status: <?= strtoupper($po_data['status']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" action="" id="poForm">
                <input type="hidden" name="po_number" value="<?= htmlspecialchars($po_data['po_number']) ?>">
                <input type="hidden" name="product_id" value="<?= htmlspecialchars($po_data['product_id'] ?? '') ?>">
                <input type="hidden" name="is_reorder" value="<?= $is_reorder ? '1' : '0' ?>">
                <?php if ($edit_id): ?>
                    <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
                <?php endif; ?>
                
                <div class="bill-ship-section">
                    <div class="bill-to">
                        <h3>BILL TO (Supplier)</h3>
                        <div class="field-group">
                            <label>PO Number:</label>
                            <span style="font-weight: bold;"><?= htmlspecialchars($po_data['po_number']) ?></span>
                        </div>
                        <div class="field-group">
                            <label for="supplier_select">Company Name:</label>
                            <select id="supplier_select" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['Supplier_id'] ?>" 
                                        <?= (isset($po_data['supplier_id']) && $po_data['supplier_id'] == $supplier['Supplier_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['Company_Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="supplier_email">Email:</label>
                            <input type="email" id="supplier_email" name="supplier_email" value="<?= htmlspecialchars($po_data['supplier_email']) ?>" <?= $is_reorder && !$edit_id ? 'readonly' : '' ?> required>
                        </div>
                        <div class="field-group">
                            <label for="contact_name">Contact Name:</label>
                            <input type="text" id="contact_name" name="contact_name" value="<?= htmlspecialchars($po_data['contact_name']) ?>" <?= $is_reorder && !$edit_id ? 'readonly' : '' ?> required>
                        </div>
                        <div class="field-group">
                            <label for="address">Address:</label>
                            <input type="text" id="address" name="address" value="<?= htmlspecialchars($po_data['address']) ?>" <?= $is_reorder && !$edit_id ? 'readonly' : '' ?> required>
                        </div>
                    </div>

                    <div class="shipment-info">
                        <h3>SHIPMENT INFORMATION</h3>
                        <div class="field-group">
                            <label for="payment_terms">Payment Terms:</label>
                            <input type="text" id="payment_terms" name="payment_terms" value="<?= htmlspecialchars($po_data['payment_terms']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label for="currency">Currency:</label>
                            <input type="text" id="currency" name="currency" value="<?= htmlspecialchars($po_data['currency']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label for="est_ship_date">Est. Ship Date:</label>
                            <input type="date" id="est_ship_date" name="est_ship_date" value="<?= htmlspecialchars($po_data['est_ship_date']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label for="mode">Mode of Trans.:</label>
                            <input type="text" id="mode" name="mode" value="<?= htmlspecialchars($po_data['mode']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label for="carrier">Carrier Company:</label>
                            <select id="carrier" name="carrier" required>
                                <option value="">Select Carrier Company</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= htmlspecialchars($supplier['Company_Name']) ?>" 
                                        <?= (isset($po_data['carrier']) && $po_data['carrier'] === $supplier['Company_Name']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['Company_Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 20%;">Item</th>
                            <th style="width: 35%;">Description</th>
                            <th style="width: 10%;">Quantity</th>
                            <th style="width: 15%;">Unit Price</th>
                            <th style="width: 15%;">Total</th>
                            <th style="width: 5%;" class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <?php 
                        $initial_rows = max(6, count($po_items));
                        for ($i = 0; $i < $initial_rows; $i++): 
                            $item = $po_items[$i] ?? null;
                            $is_reorder_row = ($i === 0 && $is_reorder && !$edit_id);
                        ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <div class="autocomplete-container">
                                        <input type="text" name="item_name[]" 
                                               value="<?= $is_reorder_row ? (isset($po_items[$i]['item_name']) ? htmlspecialchars($po_items[$i]['item_name']) : '') : (isset($item['item_name']) ? htmlspecialchars($item['item_name']) : '') ?>" 
                                               <?= $is_reorder_row ? 'readonly' : '' ?>
                                               class="product-autocomplete"
                                               data-row-index="<?= $i ?>"
                                               autocomplete="off">
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="item_desc[]" 
                                           value="<?= $is_reorder_row ? (isset($po_items[$i]['description']) ? htmlspecialchars($po_items[$i]['description']) : '') : (isset($item['description']) ? htmlspecialchars($item['description']) : '') ?>" 
                                           <?= $is_reorder_row ? 'readonly' : '' ?>
                                           class="product-description"
                                           data-row-index="<?= $i ?>">
                                </td>
                                <td>
                                    <input type="number" name="item_qty[]" 
                                           value="<?= $is_reorder_row ? (isset($po_items[$i]['quantity']) ? htmlspecialchars($po_items[$i]['quantity']) : '') : (isset($item['quantity']) ? htmlspecialchars($item['quantity']) : '') ?>" 
                                           min="1" onchange="calculateTotal(this)" 
                                           <?php if ($is_reorder_row && isset($po_items[$i]['unit_price'])): ?>
                                               data-base-price="<?= $po_items[$i]['unit_price'] ?>"
                                           <?php endif; ?>>
                                </td>
                                <td>
                                    <input type="number" name="item_price[]" 
                                           value="<?= $is_reorder_row ? (isset($po_items[$i]['unit_price']) ? htmlspecialchars($po_items[$i]['unit_price']) : '') : (isset($item['unit_price']) ? htmlspecialchars($item['unit_price']) : '') ?>" 
                                           step="0.01" 
                                           <?= $is_reorder_row ? 'readonly' : 'onchange="calculateTotal(this)"' ?> 
                                           class="price-input product-price"
                                           data-row-index="<?= $i ?>">
                                </td>
                                <td class="total-col" data-total-id="total_<?= $i + 1 ?>">
                                    <?php
                                    if ($is_reorder_row && isset($po_items[$i])) {
                                        echo formatCurrency($po_items[$i]['quantity'] * $po_items[$i]['unit_price']);
                                    } elseif ($item) {
                                        echo formatCurrency($item['quantity'] * $item['unit_price']);
                                    } else {
                                        echo '0.00';
                                    }
                                    ?>
                                </td>
                                <td class="no-print">
                                    <?php if (!$is_reorder_row): ?>
                                        <button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                
                <div class="no-print" style="text-align: right; margin-top: 10px;">
                    <button type="button" class="btn-add-row" onclick="addNewRow()">+ Add Another Item</button>
                </div>
                
                <div class="summary-totals">
                    <div>
                        <span>SUBTOTAL:</span>
                        <span id="subtotal_display">₱<?= formatCurrency($po_data['subtotal']) ?></span>
                        <input type="hidden" id="subtotal_input" name="subtotal" value="<?= $po_data['subtotal'] ?>">
                    </div>
                    <div>
                        <span>DISCOUNT:</span>
                        <input type="number" id="discount_input" name="discount" value="<?= $po_data['discount'] ?>" step="0.01" min="0" style="width: 100px; text-align: right;" onchange="updateGrandTotal()">
                    </div>
                    <div class="grand-total">
                        <span>TOTAL:</span>
                        <span id="grand_total_display">₱<?= formatCurrency($po_data['grand_total']) ?></span>
                        <input type="hidden" id="grand_total_input" name="grand_total" value="<?= $po_data['grand_total'] ?>">
                    </div>
                </div>
                
                <div style="clear: both;"></div>

                <div class="actions-bar no-print">
                    <?php if ($edit_id && isset($po_data['status']) && $po_data['status'] !== 'received'): ?>
                        <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                        <button type="submit" name="action" value="send" class="btn-send">Send to Supplier</button>
                        <button type="submit" name="action" value="receive" class="btn-receive">Mark as Received</button>
                    <?php elseif (!$edit_id): ?>
                        <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                        <button type="submit" name="action" value="send" class="btn-send">Send to Supplier</button>
                        <button type="submit" name="action" value="receive" class="btn-receive">Mark as Received</button>
                    <?php else: ?>
                        <div style="color: #28a745; font-weight: bold; padding: 10px;">
                            ✅ This purchase order has been received and inventory has been updated.
                        </div>
                    <?php endif; ?>
                    <button type="submit" name="action" value="draft" class="btn-draft" style="background: #6c757d; color: white;">Save Draft</button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='reorder.php'">Cancel</button>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    // Product data for autocomplete - embedded directly from PHP
    const products = <?= $products_json ?>;
    let rowCounter = <?= $initial_rows ?>;
    
    // Initialize autocomplete for all product input fields
    function initAutocomplete() {
        const productInputs = document.querySelectorAll('.product-autocomplete');
        
        productInputs.forEach(input => {
            // Only initialize if not readonly
            if (!input.readOnly) {
                input.addEventListener('input', function(e) {
                    const rowIndex = this.getAttribute('data-row-index');
                    const val = this.value;
                    
                    // Close any existing autocomplete lists
                    closeAllLists();
                    
                    if (!val) return false;
                    
                    // Create autocomplete container
                    const list = document.createElement("DIV");
                    list.setAttribute("id", "autocomplete-list-" + rowIndex);
                    list.setAttribute("class", "autocomplete-items");
                    
                    // Append to the autocomplete container
                    this.parentNode.appendChild(list);
                    
                    // Filter products
                    const filteredProducts = products.filter(product => 
                        product.name.toLowerCase().includes(val.toLowerCase())
                    );
                    
                    // Create list items
                    filteredProducts.forEach(product => {
                        const item = document.createElement("DIV");
                        item.innerHTML = `<strong>${product.name}</strong> - ${product.description}`;
                        item.innerHTML += `<input type="hidden" value="${product.name}">`;
                        
                        item.addEventListener("click", function() {
                            // Set the product name
                            input.value = product.name;
                            
                            // Find the description and price inputs for this row
                            const descInput = document.querySelector(`.product-description[data-row-index="${rowIndex}"]`);
                            const priceInput = document.querySelector(`.product-price[data-row-index="${rowIndex}"]`);
                            
                            // Set the description and price
                            if (descInput) descInput.value = product.description;
                            if (priceInput) priceInput.value = product.price;
                            
                            // Trigger calculation
                            if (priceInput) calculateTotal(priceInput);
                            
                            // Close the autocomplete list
                            closeAllLists();
                        });
                        
                        list.appendChild(item);
                    });
                    
                    // If no results found
                    if (filteredProducts.length === 0) {
                        const noResults = document.createElement("DIV");
                        noResults.innerHTML = "No products found";
                        list.appendChild(noResults);
                    }
                });
                
                // Handle keyboard navigation
                input.addEventListener("keydown", function(e) {
                    const list = document.getElementById("autocomplete-list-" + rowIndex);
                    let items = list ? list.getElementsByTagName("div") : [];
                    
                    if (e.keyCode == 40) { // Down arrow
                        e.preventDefault();
                        currentFocus++;
                        addActive(items);
                    } else if (e.keyCode == 38) { // Up arrow
                        e.preventDefault();
                        currentFocus--;
                        addActive(items);
                    } else if (e.keyCode == 13) { // Enter
                        e.preventDefault();
                        if (currentFocus > -1) {
                            if (items) items[currentFocus].click();
                        }
                    }
                });
                
                let currentFocus = -1;
                
                function addActive(items) {
                    if (!items) return false;
                    removeActive(items);
                    if (currentFocus >= items.length) currentFocus = 0;
                    if (currentFocus < 0) currentFocus = (items.length - 1);
                    items[currentFocus].classList.add("autocomplete-active");
                }
                
                function removeActive(items) {
                    for (let i = 0; i < items.length; i++) {
                        items[i].classList.remove("autocomplete-active");
                    }
                }
            }
        });
        
        // Close autocomplete when clicking elsewhere
        document.addEventListener("click", function(e) {
            closeAllLists(e.target);
        });
    }
    
    // Close all autocomplete lists
    function closeAllLists(elmnt) {
        const items = document.getElementsByClassName("autocomplete-items");
        for (let i = 0; i < items.length; i++) {
            if (elmnt !== items[i] && elmnt !== document.querySelector('.product-autocomplete')) {
                items[i].parentNode.removeChild(items[i]);
            }
        }
        currentFocus = -1;
    }
    
    // Add new row to the items table
    function addNewRow() {
        const tbody = document.getElementById('itemsTableBody');
        const newRow = document.createElement('tr');
        rowCounter++;
        
        newRow.innerHTML = `
            <td>${rowCounter}</td>
            <td>
                <div class="autocomplete-container">
                    <input type="text" name="item_name[]" class="product-autocomplete" data-row-index="${rowCounter}" autocomplete="off">
                </div>
            </td>
            <td>
                <input type="text" name="item_desc[]" class="product-description" data-row-index="${rowCounter}">
            </td>
            <td>
                <input type="number" name="item_qty[]" min="1" onchange="calculateTotal(this)">
            </td>
            <td>
                <input type="number" name="item_price[]" step="0.01" onchange="calculateTotal(this)" class="price-input product-price" data-row-index="${rowCounter}">
            </td>
            <td class="total-col">0.00</td>
            <td class="no-print">
                <button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>
            </td>
        `;
        
        tbody.appendChild(newRow);
        initAutocomplete(); // Reinitialize autocomplete for new inputs
    }
    
    // Remove row from the items table
    function removeRow(button) {
        const row = button.closest('tr');
        if (document.querySelectorAll('#itemsTableBody tr').length > 1) {
            row.remove();
            updateRowNumbers();
            updateGrandTotal();
        } else {
            alert('You must have at least one item in the purchase order.');
        }
    }
    
    // Update row numbers after removal
    function updateRowNumbers() {
        const rows = document.querySelectorAll('#itemsTableBody tr');
        rows.forEach((row, index) => {
            row.cells[0].textContent = index + 1;
        });
        rowCounter = rows.length;
    }
    
    function calculateTotal(inputElement) {
        const row = inputElement.closest('tr');
        const qtyInput = row.querySelector('input[name^="item_qty"]');
        const priceInput = row.querySelector('input[name^="item_price"]');
        const totalCell = row.querySelector('.total-col');

        let qty = parseFloat(qtyInput.value) || 0;
        let price = parseFloat(priceInput.value) || 0;
        
        let rowTotal = price * qty;
        totalCell.innerText = '₱' + rowTotal.toFixed(2);
        updateGrandTotal();
    }

    function updateGrandTotal() {
        let subtotal = 0;
        document.querySelectorAll('.total-col').forEach(cell => {
            let value = cell.innerText.replace('₱', '');
            subtotal += parseFloat(value) || 0;
        });

        let discount = parseFloat(document.getElementById('discount_input').value) || 0;
        let grandTotal = Math.max(0, subtotal - discount);

        document.getElementById('subtotal_display').innerText = '₱' + subtotal.toFixed(2);
        document.getElementById('grand_total_display').innerText = '₱' + grandTotal.toFixed(2);

        document.getElementById('subtotal_input').value = subtotal.toFixed(2);
        document.getElementById('grand_total_input').value = grandTotal.toFixed(2);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize autocomplete
        initAutocomplete();
        
        // Initialize calculations
        document.querySelectorAll('input[name^="item_qty"], input[name^="item_price"]').forEach(input => {
            if (input.value) {
                calculateTotal(input);
            }
        });
        updateGrandTotal();
    });
</script>
</body>
</html>