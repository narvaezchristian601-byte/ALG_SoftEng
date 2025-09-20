<?php include("db.php"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Price List</title>
    <style>
        body {
            margin:0;
            font-family:Arial,sans-serif;
            background:#f8f9fa;
        }
        header {
            background:#007bff;
            color:white;padding:15px;
            text-align:center;
        }
        nav {
            background:#0056b3;
            padding:10px;
            text-align:center;
        }
        nav a {
            color:white;
            text-decoration:none;
            margin:0 15px;
            font-weight:bold;
        }
        nav a:hover {
            text-decoration:underline;
        }
        main {
            min-height:70vh;
            display:flex;
            justify-content:center;
            align-items:center;
            flex-direction:column;
            padding:20px;}
        footer {
            background:#007bff;
            color:white;
            text-align:center;
            padding:10px;
            width:100%;
        }
        .card {
            background:white;
            padding:25px;
            border-radius:8px;
            box-shadow:0px 4px 8px rgba(0,0,0,0.1);
            max-width:700px;width:90%;
        }
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:20px;
        }
        th, td {
            border:1px solid #ddd;
            padding:8px;
            text-align:center;
        }
        th {
            background:#007bff;
            color:white;
        }
    </style>
</head>
<body>
    <header>
        <h1>ALG Roofing System</h1>
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
        <div class="card">
            <h2>Product Price List</h2>
            <table>
                <tr><th>Product</th><th>Category</th><th>Price</th></tr>
                <?php
                $sql = "SELECT p.Name, c.Name AS Category, p.Price 
                        FROM Product p
                        JOIN Category c ON p.Category_id = c.Category_id";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>".$row["Name"]."</td>
                                <td>".$row["Category"]."</td>
                                <td>".$row["Price"]."</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No products available.</td></tr>";
                }
                ?>
            </table>
        </div>
    </main>
    <footer>
        <p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p>
    </footer>
</body>
</html>
