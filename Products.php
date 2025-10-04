    <?php
    include("db.php");
    session_start();

    $searchTerm = "";
        if (isset($_GET['search'])) {
            $searchTerm = trim($_GET['search']);
    }
    $like = "%{$searchTerm}%";
    // For Sorting 
    $validColumns = [
        "Name" => "p.Name",
        "Description" => "p.Description",
        "CategoryName" => "c.Name",
        "Company_Name" => "s.Company_Name",
        "Price" => "p.Price",
        "Stock" => "p.Stock"
    ];

    // Read requested sort column and direction
    $requestedCol = $_GET['sort'] ?? '';
    $requestedDir = strtoupper($_GET['dir'] ?? '');

    // Defaults
    $orderBy = "p.Name";
    $orderDir = "ASC";

    // Validate requested column
    if ($requestedCol && array_key_exists($requestedCol, $validColumns)) {
        $orderBy = $validColumns[$requestedCol];
        $currentCol = $requestedCol;
    } else {
        $currentCol = 'Name';
    }

    // Validate direction
    if ($requestedDir === 'DESC') {
        $orderDir = 'DESC';
        $currentDir = 'DESC';
    } else {
        $orderDir = 'ASC';
        $currentDir = 'ASC';
    }

    // Prepare query (search terms are bound safely)
    $stmt = $conn->prepare(
        "SELECT p.Product_id, p.Name, p.Description, p.Price, p.Stock,
                s.Company_Name,
                c.Name AS CategoryName
        FROM Product p
        JOIN Supplier s ON p.Supplier_id = s.Supplier_id
        LEFT JOIN Category c ON p.Category_id = c.Category_id
        WHERE p.Name LIKE ? 
            OR p.Description LIKE ? 
            OR s.Company_Name LIKE ? 
            OR c.Name LIKE ?
        ORDER BY $orderBy $orderDir"
    );
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    // Toggle order for header links
    $toggleOrder = $currentDir === 'ASC' ? 'DESC' : 'ASC';

    // Safe sort link builder
    function sortLink($col, $label, $currentCol, $currentDir, $searchTerm) {
        $dir = "ASC";
        $arrow = "";
        if ($col === $currentCol) {
            if ($currentDir === "ASC") {
                $dir = "DESC";
                $arrow = " ↑";
            } else {
                $dir = "ASC";
                $arrow = " ↓";
            }
        }
        $url = '?search=' . urlencode($searchTerm) . '&sort=' . urlencode($col) . '&dir=' . urlencode($dir);
        return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label . $arrow) . '</a>';
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ALG Roofing System - Products</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        header {
            background: #007bff;
            color: white;
            text-align: center;
            padding: 10px;
            width: 100%;
        }
        header h1 { margin: 0; font-size: 24px; }

        nav {
            background: #0056b3;
            color: white;
            text-align: center;
            padding: 10px;
            width: 100%;
        }
        nav a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-weight: bold;
        }
        nav a:hover { text-decoration: underline; }

        main {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding: 20px;
            height: calc(100vh - 140px);
            box-sizing: border-box;
        }

        footer {
            background: #007bff;
            color: white;
            text-align: center;
            padding: 10px;
            width: 100%;
        }

        .card {
            max-width: 1200px;
            width: 100%;
            box-sizing: border-box;
            height: 100%;
            display: flex;
            flex-direction: column;
            margin: 20px auto;
        }

        .search-box { margin-bottom: 20px; }
        .search-box input {
            padding: 8px;
            width: 250px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .search-box button {
            padding: 8px 12px;
            border: none;
            background: #007bff;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-box button:hover { 
            background: #0056b3; 
        }

        .table-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: auto;
            margin-top: 15px;
            border: 2px solid #dcdcdc;
            border-radius: 6px;
            padding: 8px;
            box-sizing: border-box;
            background: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            word-wrap: break-word;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }

        th {
            background: #007bff;
            color: #ffffff;
        }

        th a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
        }
        th a:hover {
            text-decoration: underline;
            color: #e6f2ff; 
        }

        .status { font-weight: bold; }
        .no-stock { color: red; }
        .low-stock { color: orange; }
        .on-stock { color: green; }
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
            <h2>Product List</h2>

            <div class="search-box">
                <form method="get" action="products.php">
                    <input type="text" name="search" placeholder="Search product, description, company, or category..."
                        value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <!-- Sort saved when searching -->
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($currentCol); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars($currentDir); ?>">
                    <button type="submit">Search</button>
                </form>
            </div>

            <div class="table-container">
                <table>
                    <tr>
                        <th><?php echo sortLink("Name", "Product", $currentCol, $currentDir, $searchTerm); ?></th>
                        <th><?php echo sortLink("Description", "Description", $currentCol, $currentDir, $searchTerm); ?></th>
                        <th><?php echo sortLink("CategoryName", "Category", $currentCol, $currentDir, $searchTerm); ?></th>
                        <th><?php echo sortLink("Company_Name", "Supplier", $currentCol, $currentDir, $searchTerm); ?></th>
                        <th><?php echo sortLink("Price", "Price", $currentCol, $currentDir, $searchTerm); ?></th>
                        <th><?php echo sortLink("Stock", "Stock", $currentCol, $currentDir, $searchTerm); ?></th>
                        <th>Status</th>
                    </tr>

                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr> <!-- Fetch data from database -->
                            <td><?php echo htmlspecialchars($row['Name']); ?></td>
                            <td><?php echo htmlspecialchars($row['Description']); ?></td>
                            <td><?php echo htmlspecialchars($row['CategoryName']); ?></td>
                            <td><?php echo htmlspecialchars($row['Company_Name']); ?></td>
                            <td>₱<?php echo number_format($row['Price'], 2); ?></td>
                            <td><?php echo (int)$row['Stock']; ?></td>
                            <td class="status 
                            <!-- Stock Controls -->
                            <?php // Add classes based on stock level
                                    if ($row['Stock'] == 0) echo 'no-stock';
                                    elseif ($row['Stock'] <= 25) echo 'low-stock';
                                    else echo 'on-stock';
                                ?>">    
                                <?php // Display status text
                                    if ($row['Stock'] == 0) echo 'No stock';
                                    elseif ($row['Stock'] <= 25) echo 'Low on Stock';
                                    else echo 'On Stock';
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p>
    </footer>
    </body>
    </html>
