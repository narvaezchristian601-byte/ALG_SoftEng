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
            margin: 20px auto;
        }
        .card a {
            color: black;
            text-decoration: none;
            margin: 0 15px;
            font-weight: bold;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
            display: inline-block;
            background: white;
            margin: 10px;

        }
        .card a:hover {
            background: #f1f1f1;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <header>
        <h1>ALG Roofing Products & Services System</h1>
    </header>



    <main>
        <div class="card">
            <a href="Website/Customer/Loadingscreen.html">Customer</a>
            <a href="Website/Login.php">Staff</a>
            <a href="Website/Login.php">Admin</a>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p>
    </footer>
</body>
</html>
