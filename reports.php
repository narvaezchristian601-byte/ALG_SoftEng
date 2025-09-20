<?php
include("db.php");

// Defaults
$reportType = $_GET['report'] ?? 'imports';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get totals for the cards (static overview)
$sql = "SELECT
    (SELECT COUNT(*) FROM Supplier) AS total_suppliers,
    (SELECT COUNT(*) FROM Product) AS total_products,
    (SELECT COUNT(*) FROM Customers) AS total_customers
";
$result = $conn->query($sql);
$totals = $result->fetch_assoc();

// Reports
$data = [];
$totalSales = 0;

if ($reportType === 'imports') {
    $query = "SELECT pi.Import_id, p.Name AS ProductName, pi.Quantity, pi.ImportDate
              FROM ProductImports pi
              JOIN Product p ON pi.Product_id = p.Product_id
              WHERE MONTH(pi.ImportDate) = $month AND YEAR(pi.ImportDate) = $year";
    $data = $conn->query($query);
}
elseif ($reportType === 'sales') {
    $query = "SELECT o.Orders_id, c.Name AS CustomerName, o.order_date, o.total_amount
              FROM Orders o
              JOIN Customers c ON o.customer_id = c.customer_id
              WHERE o.status = 'Completed'
                AND MONTH(o.order_date) = $month AND YEAR(o.order_date) = $year";
    $data = $conn->query($query);

    // Calculate total sales
    $sumQuery = "SELECT SUM(total_amount) AS total_sales
                 FROM Orders
                 WHERE status = 'Completed'
                   AND MONTH(order_date) = $month AND YEAR(order_date) = $year";
    $totalSales = $conn->query($sumQuery)->fetch_assoc()['total_sales'] ?? 0;
}
/*elseif ($reportType === 'staff') {
    // Placeholder until StaffLog table is ready
    $query = "SELECT s.Name, s.Email, s.Password, s.Role, 
                     COUNT(l.LoginTime) AS total_logins, 
                     COUNT(l.LogoutTime) AS total_logouts
              FROM Staff s
              LEFT JOIN StaffLog l ON s.Staff_id = l.Staff_id
              GROUP BY s.Staff_id";
    $data = $conn->query($query);
}*/

if ($reportType === 'imports') {
    $query = "SELECT pi.Import_id, p.Name AS ProductName, pi.Quantity, pi.ImportDate, (pi.Quantity * p.Price) AS Total
              FROM ProductImports pi
              JOIN Product p ON pi.Product_id = p.Product_id
              WHERE MONTH(pi.ImportDate) = $month AND YEAR(pi.ImportDate) = $year";
    $data = $conn->query($query);

    // Calculate total imports value
    $sumQuery = "SELECT SUM(pi.Quantity * p.Price) AS total_imports
                 FROM ProductImports pi
                 JOIN Product p ON pi.Product_id = p.Product_id
                 WHERE MONTH(pi.ImportDate) = $month AND YEAR(pi.ImportDate) = $year";
    $totalImports = $conn->query($sumQuery)->fetch_assoc()['total_imports'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reports</title>
<style>
    body { margin:0; font-family:Arial, sans-serif; background:#f8f9fa; color:#333; }
    header { background:#007bff; color:white; padding:15px; text-align:center; }
    nav { background:#0056b3; padding:10px; text-align:center; }
    nav a { color:white; text-decoration:none; margin:0 15px; font-weight:bold; }
    nav a:hover { text-decoration:underline; }
    .container { display:flex; gap:20px; margin-top:40px; justify-content:center; }
    .card { background:#f4f4f4; padding:30px 40px; border-radius:10px;
            box-shadow:0 2px 8px rgba(0,0,0,0.08); text-align:center; min-width:180px; }
    .card h2 { margin:0 0 10px; font-size:1.2em; color:#333; }
    .card p { font-size:2em; margin:0; color:#007bff; }
    .filters { display:flex; justify-content:space-between; align-items:center;
               margin:30px auto; max-width:1000px; }
    .filters form select, .filters form button { padding:8px; margin:0 5px; }
    .report-table { max-width:1000px; margin:0 auto; background:white; padding:20px;
                    border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.15); }
    table { width:100%; border-collapse:collapse; margin-top:15px; }
    table th, table td { border:1px solid #ddd; padding:8px; text-align:left; }
    table th { background:#007bff; color:white; }
    .report-buttons { display:flex; gap:10px; }
    .report-buttons a { background:#007bff; color:white; padding:8px 12px;
                        border-radius:5px; text-decoration:none; }
    .report-buttons a.active { background:#0056b3; }
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
    </nav>

<main>
    <!-- Totals Overview -->
    <div class="container">
        <div class="card"><h2>Total Suppliers</h2><p><?php echo $totals['total_suppliers']; ?></p></div>
        <div class="card"><h2>Total Products</h2><p><?php echo $totals['total_products']; ?></p></div>
        <div class="card"><h2>Total Customers</h2><p><?php echo $totals['total_customers']; ?></p></div>
    </div>

    <!-- Report Switch -->
    <div class="filters">
        <form method="GET">
            <input type="hidden" name="report" value="<?php echo $reportType; ?>">
            <label>Month: 
                <select name="month">
                    <?php for ($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php if($m==$month) echo 'selected'; ?>>
                            <?php echo date("F", mktime(0,0,0,$m,1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>Year: 
                <select name="year">
                    <?php for ($y=date("Y"); $y>=2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($y==$year) echo 'selected'; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </label>
            <button type="submit">Filter</button>
        </form>
        <div class="report-buttons">
            <a href="?report=imports&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="<?php echo $reportType=='imports'?'active':''; ?>">Import Reports</a>
            <a href="?report=sales&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="<?php echo $reportType=='sales'?'active':''; ?>">Sales Reports</a>
            <a href="?report=staff&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="<?php echo $reportType=='staff'?'active':''; ?>">Staff Logins</a>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="report-table">
        <?php if ($reportType === 'imports'): ?>
            <h3>Product Import Reports (<?php echo date("F Y", mktime(0,0,0,$month,1,$year)); ?>)</h3>
            <table>
                <tr><th>Import ID</th><th>Product</th><th>Quantity</th><th>Date</th><th>Total</th></tr>
                <?php while($row = $data->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['Import_id']; ?></td>
                    <td><?php echo $row['ProductName']; ?></td>
                    <td><?php echo $row['Quantity']; ?></td>
                    <td><?php echo $row['ImportDate']; ?></td>
                    <td>₱ <?php echo number_format($row['Total'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
            <p><strong>Total Imports:</strong> ₱<?php echo number_format($totalImports, 2); ?></p>

        <?php elseif ($reportType === 'sales'): ?>
            <h3>Sales Reports (<?php echo date("F Y", mktime(0,0,0,$month,1,$year)); ?>)</h3>
            <table>
                <tr><th>Order ID</th><th>Customer</th><th>Order Date</th><th>Total Amount</th></tr>
                <?php while($row = $data->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['Orders_id']; ?></td>
                    <td><?php echo $row['CustomerName']; ?></td>
                    <td><?php echo $row['order_date']; ?></td>
                    <td>₱ <?php echo number_format($row['total_amount'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
            <p><strong>Total Sales:</strong> ₱<?php echo number_format($totalSales, 2); ?></p>

        <?php elseif ($reportType === 'staff'): ?>
            <h3>Staff Login Reports</h3>
            <table>
                <tr><th>Name</th><th>Email</th><th>Password</th><th>Role</th><th>Total Logins</th><th>Total Logouts</th></tr>
                <?php if ($data): while($row = $data->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['Name']; ?></td>
                    <td><?php echo $row['Email']; ?></td>
                    <td><?php echo $row['Password']; ?></td>
                    <td><?php echo $row['Role']; ?></td>
                    <td><?php echo $row['total_logins']; ?></td>
                    <td><?php echo $row['total_logouts']; ?></td>
                </tr>
                <?php endwhile; endif; ?>
            </table>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
