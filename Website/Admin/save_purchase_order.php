<?php
// This file handles the secure saving of a new Purchase Order (PO).
// It requires a successful database connection ($conn) and session management.

// Ensure db.php connects successfully and provides $conn object
// Assuming db.php is located one level up in the directory structure
include("../../db.php"); 
session_start();

// 1. Basic Request Validation
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // If accessed directly or not via POST, redirect back to the creation form.
    header("Location: create_purchase.php");
    exit();
}

// 2. Input Sanitization and Retrieval (Data Synchronization)
$po_number = $_POST['po_number'] ?? null;
$supplier_name = $_POST['supplier_name'] ?? null;
$est_ship_date = $_POST['est_ship_date'] ?? null;
$final_amount = (float)($_POST['grand_total'] ?? 0);
$discount = (float)($_POST['discount'] ?? 0);
$subtotal = (float)($_POST['subtotal'] ?? 0);

// Determine Status based on the submitted button action
$action = $_POST['action'] ?? 'Pending'; 
$status = ($action == 'approve') ? 'Approved' : 'Sent'; // Default status set by button

// Retrieve item arrays from the form
$item_names = $_POST['item_name'] ?? [];
$item_quantities = $_POST['item_qty'] ?? [];
$item_prices = $_POST['item_price'] ?? [];

// CRITICAL VALIDATION
if (!$po_number || !$supplier_name || $final_amount <= 0 || empty($item_names)) {
    die("Error: Missing critical Purchase Order data. Please check PO Number, Supplier, and total amount.");
}

// 3. Find or Validate Supplier ID
$supplier_id = null;
// Find the Supplier_id based on the submitted Company Name
$stmt_supplier = $conn->prepare("SELECT Supplier_id FROM Supplier WHERE Company_Name = ?");
$stmt_supplier->bind_param("s", $supplier_name);
$stmt_supplier->execute();
$result_supplier = $stmt_supplier->get_result();

if ($result_supplier->num_rows > 0) {
    $supplier_id = $result_supplier->fetch_assoc()['Supplier_id'];
} else {
    // Display an error if the supplier is not found, ensuring data integrity.
    die("Error: Supplier '$supplier_name' could not be found in the database. PO not created.");
}
$stmt_supplier->close();

// 4. Start Database Transaction
// This is critical for data integrity: if the header saves but any item fails, everything is rolled back.
$conn->begin_transaction();
$success = true;

try {
    // A. Insert into PurchaseOrders header table (Confirmed Schema)
    $query_po = "INSERT INTO PurchaseOrders (PO_Number, Supplier_id, Date_Created, Est_Ship_Date, Total_Amount, Discount, Final_Amount, Status) 
                 VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)";
                 
    $stmt_po = $conn->prepare($query_po);
    // Parameters: string, integer, string, decimal, decimal, decimal, string
    $stmt_po->bind_param(
        "sisddds", 
        $po_number, 
        $supplier_id, 
        $est_ship_date, 
        $subtotal, 
        $discount, 
        $final_amount, 
        $status
    );
    
    if (!$stmt_po->execute()) {
        throw new Exception("PO Header insertion failed: " . $stmt_po->error);
    }
    
    $po_id = $conn->insert_id; // Get the ID of the newly created PO
    $stmt_po->close();

    // B. Insert Purchase Order Items into ProductImports table (Item Details)
    // First, prepare the statement for looking up the Product_id (required foreign key)
    $query_product_id = "SELECT Product_id FROM Product WHERE Name = ? AND Supplier_id = ?";
    $stmt_product_id = $conn->prepare($query_product_id);
    
    // Second, prepare the statement for inserting into ProductImports (PO_id, Supplier_id, Product_id, Quantity, Price, ImportDate)
    $query_item = "INSERT INTO ProductImports (PO_id, Supplier_id, Product_id, Quantity, Price, ImportDate) 
                   VALUES (?, ?, ?, ?, ?, CURDATE())";
    $stmt_item = $conn->prepare($query_item);
    
    $items_processed = 0;
    
    for ($i = 0; $i < count($item_names); $i++) {
        $name = trim($item_names[$i]);
        $qty = (int)($item_quantities[$i] ?? 0);
        $price = (float)($item_prices[$i] ?? 0);

        // Only process valid line items
        if (!empty($name) && $qty > 0 && $price > 0) {
            
            // 1. Find the Product_id
            $stmt_product_id->bind_param("si", $name, $supplier_id);
            $stmt_product_id->execute();
            $result_product_id = $stmt_product_id->get_result();
            
            if ($result_product_id->num_rows == 0) {
                // Log and skip items not matching existing product names for this supplier
                error_log("Product not found in system for supplier $supplier_id: " . $name);
                continue; 
            }
            $product_id = $result_product_id->fetch_assoc()['Product_id'];

            // 2. Insert into ProductImports
            $stmt_item->bind_param(
                "iisid", // PO_id, Supplier_id, Product_id, Quantity, Price
                $po_id, 
                $supplier_id, 
                $product_id, 
                $qty, 
                $price
            );

            if (!$stmt_item->execute()) {
                throw new Exception("PO Item insertion failed: " . $stmt_item->error);
            }
            $items_processed++;
        }
    }
    
    // Close prepared statements
    $stmt_product_id->close();
    $stmt_item->close();

    // Ensure at least one line item was processed
    if ($items_processed === 0) {
         throw new Exception("No valid items were included in the purchase order. PO rejected.");
    }
    
    // C. Commit Transaction if everything succeeded
    $conn->commit();
    
} catch (Exception $e) {
    // D. Rollback on failure (undo all changes)
    $conn->rollback();
    $success = false;
    error_log("Purchase Order Save Error: " . $e->getMessage());
    // Redirect with error message (using custom message box logic instead of alert)
    header("Location: create_purchase.php?status=error&message=" . urlencode("Failed to save PO: " . $e->getMessage()));
    exit();
}

// 5. Success Redirection
if ($success) {
    // Redirect to a dedicated page for viewing purchase orders
    header("Location: purchase_orders.php?status=success&po_number=" . urlencode($po_number) . "&action=" . urlencode($action));
    exit();
}

?>
