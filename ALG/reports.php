<?php
include("db.php");

$sql = "SELECT
    (SELECT COUNT(*) FROM Supplier) AS total_suppliers,
    (SELECT COUNT(*) FROM Product) AS total_products,
    (SELECT COUNT(*) FROM Customers) AS total_customers
";

$result = $conn->query($sql);
$totals = $result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reports</title>
<style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f8f9fa;
        color: #333;
    }
    header {
        background: #007bff;
        color: white;
        padding: 15px;
        text-align: center;
    }
    nav {
        background: #0056b3;
        padding: 10px;
        text-align: center;
    }
    nav a {
        color: white;
        text-decoration: none;
        margin: 0 15px;
        font-weight: bold;
    }
    nav a:hover {
        text-decoration: underline;
    }
.container {
    display: flex;
    gap: 20px;
    margin-top: 40px;
    justify-content: center;
}
.card {
    background: #f4f4f4;
    padding: 30px 40px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
    min-width: 180px;
}
.card h2 {
    margin: 0 0 10px 0;
    font-size: 1.2em;
    color: #333;
}
.card p {
    font-size: 2em;
    margin: 0;
    color: #007bff;
}
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
        <div class="container">
            <div class="card">
                <h2>Total Suppliers</h2>
                <p><?php echo $totals['total_suppliers']; ?></p>
            </div>
            <div class="card">
                <h2>Total Products</h2>
                <p><?php echo $totals['total_products']; ?></p>
            </div>
            <div class="card">
                <h2>Total Customers</h2>
                <p><?php echo $totals['total_customers']; ?></p>
            </div>
        </div>
        <div class="report-table">
            <h2 style="text-align:center; margin-top:40px;">Supplier List</h2>
            <table border="1" cellpadding="10" cellspacing="0" style="width:80%; margin:20px auto; border-collapse:collapse;">
                <thead style="background:#007bff; color:white;">
                    <tr>
                        <th>Supplier ID</th>
                        <th>Supplier Name</th>
                        <th>Contact Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $supplier_sql = "SELECT supplier_id, supplier_name, contact_name, phone, email FROM Supplier";
                    $supplier_result = $conn->query($supplier_sql);
                    if ($supplier_result->num_rows > 0) {
                        while ($row = $supplier_result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['supplier_id']}</td>
                                <td>{$row['supplier_name']}</td>
                                <td>{$row['contact_name']}</td>
                                <td>{$row['phone']}</td>
                                <td>{$row['email']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center;'>No suppliers found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
