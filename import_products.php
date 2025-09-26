<?php
// add_inbound_delivery.php
include("db.php");
session_start();

$message = "";

// --- SECURITY CHECK (REQUIRED FOR DEPARTMENTAL ROLES) ---
// Uncomment and adjust this section once you implement login.php and roles
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Logistics') {
    // You might want to redirect to a login page or an unauthorized access page
    header("Location: index.php"); 
    exit;
}
*/
// --------------------------------------------------------


// 1. Handle form submission (Inbound Delivery)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $supplierId = intval($_POST['supplier_id'] ?? 0);
    $importDate = date('Y-m-d H:i:s'); // Record current time

    if ($productId > 0 && $quantity > 0) {
        
        // Start transaction for safety: both inserts/updates must succeed or fail together.
        $conn->begin_transaction();
        
        try {
            // A. Insert the Inbound Delivery record into the ProductImports table
            // This logs the activity (which is read by reports.php)
            $stmt_import = $conn->prepare(
                "INSERT INTO ProductImports (Product_id, Quantity, Supplier_id, ImportDate) VALUES (?, ?, ?, ?)"
            );
            $stmt_import->bind_param("iiis", $productId, $quantity, $supplierId, $importDate);
            $stmt_import->execute();
            $stmt_import->close();

            // B. Update the current stock in the Product table (The 'On Hand Stock')
            $stmt_stock = $conn->prepare(
                "UPDATE Product SET Stock = Stock + ? WHERE Product_id = ?"
            );
            $stmt_stock->bind_param("ii", $quantity, $productId);
            $stmt_stock->execute();
            $stmt_stock->close();

            // Commit the transaction
            $conn->commit();
            $message = "✅ Successfully recorded inbound delivery of $quantity units for Product ID: $productId. Stock updated.";
            
        } catch (Exception $e) {
            // Rollback the transaction if any query failed
            $conn->rollback();
            $message = "❌ Error recording delivery. Please try again. Error: " . $e->getMessage();
        }
    } else {
        $message = "❌ Please select a product and enter a positive quantity.";
    }
}

// 2. Fetch all products for the dropdown menu
$products = $conn->query("SELECT Product_id, Name, Stock FROM Product ORDER BY Name ASC");

// 3. Fetch all suppliers for the dropdown menu
$suppliers = $conn->query("SELECT Supplier_id, Company_Name FROM Supplier ORDER BY Company_Name ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inbound Delivery</title>
    <style>
        body { margin:0; font-family:Arial,sans-serif; background:#f8f9fa; }
        header { background:#007bff; color:white; padding:15px; text-align:center; }
        nav { background:#0056b3; padding:10px; text-align:center; }
        nav a { color:white; text-decoration:none; margin:0 15px; font-weight:bold; }
        nav a:hover { text-decoration:underline; }
        main { min-height:70vh; display:flex; justify-content:center; align-items:center; flex-direction:column; padding:20px; }
        .card { background:white; padding:25px; border-radius:8px; box-shadow:0px 4px 8px rgba(0,0,0,0.1); max-width:600px; width:90%; }
        form div { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="number"], select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button[type="submit"] { background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button[type="submit"]:hover { background: #218838; }
        .message-success { color: green; font-weight: bold; }
        .message-error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <h1>ALG Roofing Products & Services System</h1>
    </header>

    <nav>
        <a href="index.php">Home</a>
        <a href="orders.php">Orders</a>
        <a href="products.php">Product Search</a>
        <a href="supplier_list.php">Supplier List</a>
        <a href="price_list.php">Price List</a>
        <a href="orderform.php">Order Form</a>
        <a href="calendar.php">Schedule Calendar</a>
        <a href="reports.php">Reports</a>
        <a href="import_products.php">Import Products</a>
    </nav>
    
    <main>
        <div class="card">
            <h2>Record Inbound Delivery (Logistics)</h2>
            
            <?php if ($message): ?>
                <p class="<?php echo strpos($message, '✅') !== false ? 'message-success' : 'message-error'; ?>">
                    <?php echo $message; ?>
                </p>
            <?php endif; ?>
            
            <form method="POST">
                <div>
                    <label for="product_id">Product:</label>
                    <select name="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php while($row = $products->fetch_assoc()): ?>
                            <option value="<?php echo $row['Product_id']; ?>">
                                <?php echo htmlspecialchars($row['Name']) . " (Current Stock: " . $row['Stock'] . ")"; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div>
                    <label for="supplier_id">Supplier (Optional):</label>
                    <select name="supplier_id">
                        <option value="0">-- Select Supplier --</option>
                        <?php while($row = $suppliers->fetch_assoc()): ?>
                            <option value="<?php echo $row['Supplier_id']; ?>">
                                <?php echo htmlspecialchars($row['Company_Name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label for="quantity">Quantity Received (Inbound):</label>
                    <input type="number" name="quantity" min="1" required>
                </div>
                
                <button type="submit">Record Delivery</button>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p>
    </footer>
</body>
</html>