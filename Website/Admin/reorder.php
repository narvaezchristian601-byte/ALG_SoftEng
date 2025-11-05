<?php
include("../../db.php"); // Assuming this includes your database connection ($conn)
session_start();

// --- SQL Query to fetch Reorder List ---
// Selects products where current stock is less than the Reorder Level.
// NOTE: I am assuming a 'Reorder_Level' column exists in your 'Product' table.
// If not, replace `p.Reorder_Level` with a hardcoded number like '10' in the WHERE clause.

$query = "
    SELECT 
        p.Product_id,
        p.Name AS Product,
        p.Stock AS CurrentStock,
        p.Reorder_Level,
        s.Company_Name AS Supplier,
        p.Price AS UnitPrice
    FROM Product p
    LEFT JOIN Supplier s ON p.Supplier_id = s.Supplier_id
    WHERE p.Stock <= p.Reorder_Level
    ORDER BY p.Stock ASC
";

$result = $conn->query($query);

// Helper function to determine and render stock status
function renderStockStatus($current, $reorder) {
    if ($current <= 0) {
        return "<span class='status red'>Out of Stock</span>";
    } elseif ($current <= ($reorder / 2)) {
        return "<span class='status red'>Critical</span>";
    } elseif ($current <= $reorder) {
        return "<span class='status yellow'>Low Stock</span>";
    }
    return "<span class='status green'>Sufficient</span>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ALG | Inventory Reorder List</title>
    <style>
        /* ================================================= */
        /* === Global & Layout Styles === */
        /* ================================================= */
        body {
            margin: 0;
            font-family: sans-serif;
            background: #e0e0e0;
            color: #333;
        }

        .container {
            display: flex;
            height: calc(100vh - 60px);
        }

        .content {
            flex: 1;
            padding: 30px;
            background: #f5f5f5;
            overflow-y: auto;
        }

        /* ================================================= */
        /* === Header & Navigation Styles === */
        /* ================================================= */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #3e3e3e;
            color: white;
            padding: 10px 30px;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
        }

        nav a.active, nav a:hover {
            background: #d9d9d9;
            color: #000;
        }

        /* ================================================= */
        /* === Sidebar Styles === */
        /* ================================================= */
        .sidebar {
            width: 250px;
            background: #d9d9d9;
            padding: 20px;
            box-shadow: 2px 0 6px rgba(0,0,0,0.1);
        }

        .sidebar h3 {
            margin-top: 0;
            font-size: 18px;
        }

        .sidebar a {
            display: block;
            margin: 10px 0;
            color: #333;
            text-decoration: none;
            font-weight: 600;
        }

        /* ================================================= */
        /* === Top Bar & Button Styles (Reorder/Create PO) === */
        /* ================================================= */
        .top-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .top-bar button {
            background: #4f9bd2; /* Blue button */
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .top-bar button.active {
            background: #3a84b9; /* Darker blue for active button */
        }

        /* ================================================= */
        /* === Table Styles === */
        /* ================================================= */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        th, td {
            padding: 12px 16px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #efefef;
            font-weight: bold;
            color: #555;
        }

        /* ================================================= */
        /* === Status Badges & Action Buttons === */
        /* ================================================= */
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 600;
            color: #fff;
            text-align: center;
            min-width: 90px;
            font-size: 0.85em;
        }
        
        .status.yellow {
            background: #ffc107;
            color: #000;
        }
        
        .status.red {
            background: #dc3545;
        }
        
        .status.green {
            background: #28a745;
        }

        .action-button {
            background: #28a745; /* Green for Reorder button */
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9em;
        }
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
        <a href="orders.php">Orders</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <aside class="sidebar">
        <h3>Inventory</h3>
        <a href="products.php" class="active">Products</a>
        <a href="purchase.php">Purchased</a>
        <a href="supplier.php">Supplier</a>
        <a href="sales_report.php">Sales Report</a>
    </aside>

    <section class="content">
        <div class="top-bar">
            <button onclick="window.location.href='Purchase.php'">Return</button>
            <button class="active">View Re Order</button>
            <button onclick="window.location.href='create_purchase.php'">Create Purchase Order</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Current Stock</th>
                    <th>Reorder Level</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php 
                            $current = (int)$row['CurrentStock'];
                            // Use a fallback of 10 if Reorder_Level is somehow null or 0 in the database
                            $reorder = (int)($row['Reorder_Level'] ?? 10); 
                            $status_html = renderStockStatus($current, $reorder);
                            
                            // Calculate the suggested reorder quantity (e.g., 2x the difference)
                            $reorder_qty = max(0, ($reorder * 2) - $current); 
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['Product'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($current) ?></td>
                            <td><?= htmlspecialchars($reorder) ?></td>
                            <td><?= htmlspecialchars($row['Supplier'] ?? 'N/A') ?></td>
                            <td><?= $status_html ?></td>
                            <td>
                                <button 
                                    class="action-button"
                                    onclick="window.location.href='create_purchase.php?product_id=<?= $row['Product_id'] ?>&qty=<?= $reorder_qty ?>'"
                                >
                                    Reorder
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="no-records" style="text-align:center; color:#555; padding: 15px;">No products currently need reordering.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

</body>
</html>