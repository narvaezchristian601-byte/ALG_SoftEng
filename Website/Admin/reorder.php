<?php
include("../../db.php");
session_start();

// Show success messages
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $po_number = $_GET['po_number'] ?? '';
    $action = $_GET['action'] ?? '';
    $action_text = ucfirst($action);
    $_SESSION['success_message'] = "Purchase order {$po_number} {$action_text} successfully!";
}

// Fetch purchase orders with item count
$query = "
    SELECT po.*, s.Company_Name, COUNT(pi.Import_id) as item_count 
    FROM purchaseorders po 
    LEFT JOIN supplier s ON po.Supplier_id = s.Supplier_id 
    LEFT JOIN productimports pi ON po.PO_id = pi.PO_id 
    GROUP BY po.PO_id 
    ORDER BY po.Date_Created DESC
";
$result = $conn->query($query);

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $conn->begin_transaction();
    
    try {
        $delete_items = $conn->prepare("DELETE FROM productimports WHERE PO_id = ?");
        $delete_items->bind_param("i", $delete_id);
        $delete_items->execute();
        $delete_items->close();
        
        $delete_po = $conn->prepare("DELETE FROM purchaseorders WHERE PO_id = ?");
        $delete_po->bind_param("i", $delete_id);
        $delete_po->execute();
        $delete_po->close();
        
        $conn->commit();
        $_SESSION['success_message'] = "Purchase order deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting purchase order: " . $e->getMessage();
    }
    
    header("Location: reorder.php");
    exit;
}

// Handle reorder PO action
if (isset($_GET['reorder_po_id'])) {
    $reorder_po_id = (int)$_GET['reorder_po_id'];
    
    // Get the PO details to pre-fill a new PO
    $reorder_query = "
        SELECT po.*, s.Company_Name, s.Email, s.Contact_Person, s.Address
        FROM purchaseorders po
        JOIN supplier s ON po.Supplier_id = s.Supplier_id
        WHERE po.PO_id = ?
    ";
    
    $stmt = $conn->prepare($reorder_query);
    $stmt->bind_param("i", $reorder_po_id);
    $stmt->execute();
    $reorder_result = $stmt->get_result();
    
    if ($reorder_result->num_rows > 0) {
        $po_data = $reorder_result->fetch_assoc();
        
        // Redirect to create_purchase.php with reorder parameters
        header("Location: create_purchase.php?reorder_po_id=" . $reorder_po_id);
        exit;
    }
}

// Display messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ALG | Purchase Orders</title>
    <style>
        body { margin: 0; font-family: sans-serif; background: #e0e0e0; color: #333; }
        header { display: flex; align-items: center; justify-content: space-between; background: #3e3e3e; color: white; padding: 10px 30px; }
        header img { height: 40px; }
        nav a { color: white; text-decoration: none; font-weight: 600; padding: 6px 14px; border-radius: 20px; }
        nav a.active, nav a:hover { background: #d9d9d9; color: #000; }
        .container { display: flex; height: calc(100vh - 60px); }
        .sidebar { width: 250px; background: #d9d9d9; padding: 20px; box-shadow: 2px 0 6px rgba(0,0,0,0.1); }
        .sidebar h3 { margin-top: 0; font-size: 18px; }
        .sidebar a { display: block; margin: 10px 0; color: #333; text-decoration: none; font-weight: 600; }
        .content { flex: 1; padding: 30px; background: #f5f5f5; overflow-y: auto; }
        
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .add-btn { background: #4f9bd2; color: white; border: none; border-radius: 6px; padding: 8px 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 3px 8px rgba(0,0,0,0.1); }
        th, td { padding: 12px 16px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #efefef; font-weight: bold; }
        
        .status { padding: 6px 10px; border-radius: 6px; font-weight: 600; display: inline-block; text-align: center; min-width: 80px; }
        .status.pending { background: #6c757d; color: white; }
        .status.approved { background: #17a2b8; color: white; }
        .status.sent { background: #ffc107; color: black; }
        .status.received { background: #28a745; color: white; }
        .status.cancelled { background: #dc3545; color: white; }
        
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; margin: 2px; }
        .btn-view { background: #17a2b8; color: white; }
        .btn-edit { background: #ffc107; color: black; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-reorder { background: #28a745; color: white; }
        .btn-receive { background: #28a745; color: white; }
        
        .alert { padding: 12px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<header>
    <img src="../../images/alg-logo-black.png" alt="ALG Logo" height="40">
    <nav>
        <a href="home.php">Home</a>
        <a href="products.php" class="active">Inventory</a>
        <a href="projects.php">Projects</a>
        <a href="staff.php">Staff</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <aside class="sidebar">
        <h3>Inventory</h3>
        <a href="products.php">Products</a>
        <a href="Purchase.php">Purchased</a>
        <a href="supplier.php">Supplier</a>
        <a href="reorder.php" class="active">Purchase Orders</a>
        <a href="sales_report.php">Sales Report</a>
    </aside>

    <section class="content">
        <div class="top-bar">
            <div>
                <button onclick="window.location.href='Purchase.php'" style="background: #555; color: white; border: none; border-radius: 6px; padding: 8px 14px; font-weight: 600; cursor: pointer; margin-right: 10px;">
                    Return to Purchased
                </button>
            </div>
            <div>
                <a href="create_purchase.php" class="add-btn">+ Create Purchase Order</a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Items</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['PO_Number']) ?></td>
                            <td><?= htmlspecialchars($row['Company_Name']) ?></td>
                            <td><?= $row['item_count'] ?> items</td>
                            <td>₱<?= number_format($row['Final_Amount'], 2) ?></td>
                            <td>
                                <span class="status <?= strtolower($row['Status']) ?>">
                                    <?= strtoupper($row['Status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($row['Date_Created'])) ?></td>
                            <td>
                                <a href="create_purchase.php?edit_id=<?= $row['PO_id'] ?>" class="btn btn-edit">Edit</a>
                                <a href="reorder.php?reorder_po_id=<?= $row['PO_id'] ?>" class="btn btn-reorder">Reorder</a>
                                <?php if ($row['Status'] !== 'received'): ?>
                                    <a href="create_purchase.php?complete_id=<?= $row['PO_id'] ?>" class="btn btn-receive" onclick="return confirm('Mark this purchase order as received? This will update inventory stock.')">Receive</a>
                                <?php endif; ?>
                                <a href="reorder.php?delete_id=<?= $row['PO_id'] ?>" 
                                   class="btn btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this purchase order?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No purchase orders found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>