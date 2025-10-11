<?php
include("db.php");
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALG Roofing System</title>
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
        main {
            min-height: 70vh;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }
        footer {
            background: #007bff;
            color: white;
            text-align: center;
            padding: 10px;
            position: relative;
            bottom: 0;
            width: 100%;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
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
        <a href="import_products.php">Import Products</a>
        <a href="mobile_schedule.php">Mobile Schedule</a>
    </nav>


    <main>
        <div class="card">
            <h2>Welcome to ALG System</h2>
            <p>Select a feature from the navigation bar to get started.</p>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p>
    </footer>
</body>
</html>
