<?php
include("../../db.php");
session_start();

// --- Filters ---
// Array of months for the dropdown
$months = [
    'Recent' => 'Recent', // Default value
    '01' => 'January',
    '02' => 'February',
    '03' => 'March',
    '04' => 'April',
    '05' => 'May',
    '06' => 'June',
    '07' => 'July',
    '08' => 'August',
    '09' => 'September',
    '10' => 'October',
    '11' => 'November',
    '12' => 'December',
];

// Get selected month from URL, default to 'Recent'
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : 'Recent';

// --- Base Query ---
$query = "
    SELECT 
        pi.Import_id,
        s.Company_Name AS Supplier,
        p.Name AS Product,
        pi.Quantity,
        pi.Price,
        pi.Total,
        pi.ImportDate,
        o.status AS OrderStatus
    FROM ProductImports pi
    LEFT JOIN Supplier s ON pi.Supplier_id = s.Supplier_id
    LEFT JOIN Product p ON pi.Product_id = p.Product_id
    LEFT JOIN orderitems oi ON pi.Product_id = oi.product_id
    LEFT JOIN orders o ON oi.Orders_id = o.Orders_id
    WHERE 1
";

// --- Apply Month Filter (Data Synchronization & Storage) ---
if ($selectedMonth === 'Recent') {
    // Show last 30 days of data for "Recent" view
    // Note: Using NOW() or CURDATE() assumes your server clock is in the same timezone as your data entry.
    // Assuming MySQL:
    $query .= " AND pi.ImportDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif (array_key_exists($selectedMonth, $months)) {
    // Filter by the selected month number (e.g., '10' for October) for the current year.
    // For a production system, you should also include a year filter.
    $currentYear = date('Y');
    
    // Use MySQL's MONTH() and YEAR() functions for filtering
    $query .= " AND MONTH(pi.ImportDate) = '$selectedMonth' AND YEAR(pi.ImportDate) = '$currentYear'";
}

$query .= " GROUP BY pi.Import_id ORDER BY pi.ImportDate DESC";
$result = $conn->query($query);

// Helper function for colored badges
function renderStatus($status) {
    if (!$status) return "<span class='status gray'>N/A</span>";

    switch (strtolower($status)) {
        case 'pending':
            return "<span class='status yellow'>Pending</span>";
        case 'ongoing':
            return "<span class='status blue'>Ongoing</span>";
        case 'completed':
            return "<span class='status green'>Completed</span>";
        default:
            return "<span class='status gray'>" . htmlspecialchars($status) . "</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ALG | Purchase Inventory</title>
<style>
    /* ... (CSS styles remain the same) ... */
    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background: #e0e0e0;
        color: #333;
    }
    header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #3e3e3e;
        color: white;
        padding: 10px 30px;
    }
    header img { height: 40px; }
    nav {
        display: flex;
        gap: 25px;
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

    .container {
        display: flex;
        height: calc(100vh - 60px);
    }
    .sidebar {
        width: 250px;
        background: #d9d9d9;
        padding: 20px;
        box-shadow: 2px 0 6px rgba(0,0,0,0.1);
    }
    .sidebar h3 { margin-top: 0; font-size: 18px; }
    .sidebar a {
        display: block;
        margin: 10px 0;
        color: #333;
        text-decoration: none;
        font-weight: 600;
    }
    .sidebar a:hover, .sidebar a.active { text-decoration: underline; color: #000; }

    .content {
        flex: 1;
        padding: 30px;
        background: #f5f5f5;
        overflow-y: auto;
    }

    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        gap: 10px;
    }
    .top-bar form {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .top-bar select, .top-bar input { /* Added select element style */
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    .top-bar button {
        background: #4f9bd2;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 8px 14px;
        font-weight: 600;
        cursor: pointer;
    }
    .top-bar button:hover { background: #3a84b9; }

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
    th { background: #efefef; font-weight: bold; }

    .no-records {
        text-align: center;
        padding: 15px;
        color: #555;
    }

    /* --- Status Badges --- */
    .status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 6px;
        font-weight: 600;
        color: #fff;
        text-align: center;
        min-width: 90px;
    }
    .status.yellow { background: #ffc107; color: #000; }
    .status.blue { background: #2196f3; }
    .status.green { background: #4caf50; }
    .status.gray { background: #9e9e9e; }
</style>
</head>
<body>

<header>
    <img src="../../images/alg-logo-black.png" alt="ALG Logo">
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
        <a href="Purchase.php" class="active">Purchase</a>
        <a href="supplier.php">Supplier</a>
        <a href="sales_report.php">Purchase Report</a>
    </aside>

    <section class="content">
        <div class="top-bar">
            <form method="GET" action="Purchase.php">
                <label for="month_filter">Filter by Month:</label>
                <select name="month" id="month_filter">
                    <?php 
                    // Populate the dropdown with months
                    foreach ($months as $month_number => $month_name) {
                        $selected = ($selectedMonth == $month_number) ? 'selected' : '';
                        echo "<option value=\"$month_number\" $selected>$month_name</option>";
                    }
                    ?>
                </select>
                <button type="submit">Filter</button>
            </form>

            <div style="display:flex; gap:10px;">
                <button onclick="window.location.href='reorder.php'">View Re Order</button>
                <button onclick="window.location.href='create_purchase.php'">Create Purchase Order</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Import ID</th>
                    <th>Supplier</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Unit Price (₱)</th>
                    <th>Total (₱)</th>
                    <th>Import Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['Import_id']) ?></td>
                            <td><?= htmlspecialchars($row['Supplier'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['Product'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['Quantity']) ?></td>
                            <td><?= number_format($row['Price'], 2) ?></td>
                            <td><?= number_format($row['Total'], 2) ?></td>
                            <td><?= htmlspecialchars(date("M d, Y", strtotime($row['ImportDate']))) ?></td>
                            <td><?= renderStatus($row['OrderStatus']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="no-records">No Import records found for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

</body>
</html>