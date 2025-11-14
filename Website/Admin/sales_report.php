<?php
include "../../db.php";
session_start();

// Database credentials must be defined here if they are not in db.php
// Assuming db.php handles the connection, but defining here as per original
$db_host = 'localhost';
$db_name = 'algdb'; 
$user = 'root';
$pass = '';
$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
// ---------------------------------------------

$options = [
    PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES      => false,
];

// Initialize variables with default/empty values
$summary = [
    'suppliers' => 0,
    'products' => 0,
    'clients' => 0,
    'total_sales' => '0.00',
];
$salesData = [];
$db_error = null;

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Fetch all sales data for Excel export (not just last 5)
        $stmt = $pdo->prepare("
            SELECT
                DATE_FORMAT(o.order_date, '%Y-%m-%d') AS date,
                o.Orders_id AS invoice,
                cust.Name AS client,
                COALESCE(p.Name, s.Name) AS item,
                oi.quantity AS qty,
                (oi.quantity * oi.price) AS total,
                o.status AS status
            FROM Orders o
            JOIN Customers cust ON o.customer_id = cust.customer_id
            JOIN orderitems oi ON o.Orders_id = oi.Orders_id
            LEFT JOIN Product p ON oi.product_id = p.Product_id
            LEFT JOIN Services s ON oi.services_id = s.Services_id
            ORDER BY o.order_date DESC
        ");
        $stmt->execute();
        $excelData = $stmt->fetchAll();
        
        // Set headers for Excel download
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.xls"');
        
        // Excel content
        echo "Sales Report - " . date('F j, Y') . "\n\n";
        echo "Date\tInvoice #\tClient\tItem/Service\tQuantity\tLine Total\tStatus\n";
        
        foreach ($excelData as $row) {
            echo $row['date'] . "\t";
            echo $row['invoice'] . "\t";
            echo $row['client'] . "\t";
            echo $row['item'] . "\t";
            echo $row['qty'] . "\t";
            echo '₱' . number_format($row['total'], 2) . "\t";
            echo $row['status'] . "\n";
        }
        exit;
        
    } catch (PDOException $e) {
        // If export fails, continue with normal page load
        error_log("Excel Export Error: " . $e->getMessage());
    }
}

try {
    // This line (now around line 28) previously caused the error because $dsn, $user, and $pass were undefined.
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 1. Fetch Summary Metrics
    // Count Suppliers
    $summary['suppliers'] = $pdo->query("SELECT COUNT(Supplier_id) FROM Supplier")->fetchColumn();

    // Count Products
    $summary['products'] = $pdo->query("SELECT COUNT(Product_id) FROM Product")->fetchColumn();

    // Count Clients (Customers)
    $summary['clients'] = $pdo->query("SELECT COUNT(customer_id) FROM Customers")->fetchColumn();

    // Calculate Total Sales (sum of completed orders)
    $total_sales_raw = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM Orders WHERE status IN ('Completed')")->fetchColumn();
    // Format the total sales for display
    $summary['total_sales'] = number_format($total_sales_raw, 2);


    // 2. Fetch Sales Transactions (Last 5 transactions)
    // Joins Orders, Customers, OrderItems, and Product/Services to create the invoice line item report.
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(o.order_date, '%b %e, %y') AS date,
            o.Orders_id AS invoice,
            cust.Name AS client,
            COALESCE(p.Name, s.Name) AS item, -- Use Product name, fallback to Service name
            oi.quantity AS qty,
            (oi.quantity * oi.price) AS total,
            o.status AS status
        FROM Orders o
        JOIN Customers cust ON o.customer_id = cust.customer_id
        JOIN orderitems oi ON o.Orders_id = oi.Orders_id
        LEFT JOIN Product p ON oi.product_id = p.Product_id
        LEFT JOIN Services s ON oi.services_id = s.Services_id
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $salesData = $stmt->fetchAll();

} catch (PDOException $e) {
    // Log the error and set a user-friendly message
    $db_error = "Database Connection Error: Could not load report data. Please check your credentials and database setup.";
    // Log the actual error for debugging
    error_log("DB Error in sales_report.php: " . $e->getMessage());
}

/**
 * Helper function to determine Tailwind color classes based on sales status.
 * @param string $status
 * @return string
 */
function get_status_class(string $status): string {
    return match (trim($status)) {
        'Completed' => 'bg-green-100 text-green-700',
        'Ongoing' => 'bg-blue-100 text-blue-700',
        'Pending' => 'bg-yellow-100 text-yellow-700',
        'Dismissed' => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>ALG | Sales Report</title>
    <!-- Load Tailwind CSS CDN for styling of internal content (cards, tables, etc.) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles from products.php for the global layout */
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #e0e0e0; /* Light gray background */
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
        
        /* Main Navigation (Header) Styles */
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
        
        /* Layout Container for Sidebar and Content */
        .container {
            display: flex;
            height: calc(100vh - 60px); /* 60px assumed header height */
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: #d9d9d9;
            padding: 20px;
            box-shadow: 2px 0 6px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        .sidebar h3 { 
            margin-top: 0; 
            font-size: 18px; 
            margin-bottom: 20px;
        }
        .sidebar a {
            display: block;
            margin: 10px 0;
            padding: 6px 10px;
            color: #333;
            text-decoration: none;
            font-weight: 600;
            border-radius: 6px;
            transition: background 0.1s;
        }
        /* Active link in sidebar (Sales Report) */
        .sidebar a.active {
            background: #ffffff; /* Use white for better visual pop in the sidebar */
            color: #000;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        /* Sidebar hover style as requested by user */
        .sidebar a:hover {
            background: #c0c0c0; /* Slightly darker than sidebar background for hover */
            color: #000;
            text-decoration: none;
        }
        
        /* Main Content Area */
        .content {
            flex: 1;
            padding: 30px;
            background: #f5f5f5;
            overflow-y: auto;
        }

        /* Retaining essential component styles that rely on Tailwind structure but need basic definition */
        .sales-card {
            background-color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            border-radius: 0.75rem; /* rounded-xl from Tailwind */
        }

        /* Print-specific styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .content, .content * {
                visibility: visible;
            }
            .content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                background: white;
            }
            .sidebar, header, .flex.justify-between.items-start.mb-6.border-b.pb-4 {
                display: none !important;
            }
            .sales-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            button {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Global Header/Navbar (Copied from products.php) -->
<header>
    <img src="../images/alg-logo-black.png" alt="ALG Logo">
    <nav>
        <a href="home.php">Home</a>
        <!-- Inventory is active as Sales Report is a sub-page of Inventory -->
        <a href="products.php" class="active">Inventory</a>
        <a href="projects.php">Projects</a>
        <a href="staff.php">Staff</a>
    </nav>
</header>

<div class="container">
    <!-- Left Sidebar (Copied from products.php structure) -->
    <aside class="sidebar">
        <h3>Products</h3>
        <a href="products.php">Products</a>
        <a href="purchase.php">Purchase</a>
        <a href="supplier.php">Supplier</a>
        <!-- Sales Report is the active link in the sidebar -->
        <a href="sales_report.php" class="active">Sales Report</a>
    </aside>

    <!-- Main Content Area (Replaced <main> with <section class="content">) -->
    <section class="content"> 

        <?php if ($db_error): ?>
            <!-- Alert Box (Using original Tailwind classes) -->
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Database Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($db_error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Summary Cards Row (Retained original Tailwind classes) -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Total Sales Card (Added for prominence) -->
            <div class="sales-card p-6 rounded-xl shadow-lg text-center bg-blue-50 border-t-4 border-blue-500">
                <h3 class="text-lg font-medium text-blue-600">Total Sales Value</h3>
                <p class="text-4xl font-extrabold text-blue-900 mt-2">₱<?php echo htmlspecialchars($summary['total_sales']); ?></p>
                <p class="text-xs text-gray-500 mt-1">(Completed Orders)</p>
            </div>
            
            <!-- Total of Suppliers Card -->
            <div class="sales-card p-6 rounded-xl shadow-lg text-center">
                <h3 class="text-lg font-medium text-gray-500">Total Suppliers</h3>
                <p class="text-4xl font-extrabold text-gray-900 mt-2"><?php echo htmlspecialchars($summary['suppliers']); ?></p>
            </div>

            <!-- Total of Products Card -->
            <div class="sales-card p-6 rounded-xl shadow-lg text-center">
                <h3 class="text-lg font-medium text-gray-500">Total Products</h3>
                <p class="text-4xl font-extrabold text-gray-900 mt-2"><?php echo htmlspecialchars($summary['products']); ?></p>
            </div>

            <!-- Total of Clients Card -->
            <div class="sales-card p-6 rounded-xl shadow-lg text-center">
                <h3 class="text-lg font-medium text-gray-500">Total Clients</h3>
                <p class="text-4xl font-extrabold text-gray-900 mt-2"><?php echo htmlspecialchars($summary['clients']); ?></p>
            </div>
        </div>

        <!-- Sales Report / Invoice Table (Retained original Tailwind classes) -->
        <div class="sales-card p-6 rounded-xl shadow-lg">
            <!-- Report Header and Actions -->
            <div class="flex justify-between items-start mb-6 border-b pb-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Latest Sales Transactions</h1>
                    <p class="text-xs text-gray-500 mt-1">Detailed Line Items from Recent Orders</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="window.print()" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Print
                    </button>
                    <a href="?export=excel" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-lg shadow-sm hover:bg-green-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export to Excel
                    </a>
                </div>
            </div>

            <!-- Table Structure -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 bg-gray-50">
                            <th class="px-4 py-3">Billing Date</th>
                            <th class="px-4 py-3">Invoice #</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Item/Service</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Line Total</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        <?php foreach ($salesData as $sale): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars($sale['date']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-blue-600 font-medium cursor-pointer hover:underline"><?php echo htmlspecialchars($sale['invoice']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars($sale['client']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars($sale['item']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-right"><?php echo htmlspecialchars($sale['qty']); ?></td>
                            <!-- Format item total with two decimal places -->
                            <td class="px-4 py-3 whitespace-nowrap text-right font-semibold">₱<?php echo number_format($sale['total'], 2); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo get_status_class($sale['status']); ?>">
                                    <?php echo htmlspecialchars($sale['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($salesData)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">No recent sales transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Footer: Pagination (Retained original Tailwind classes) -->
            <div class="flex justify-end items-center pt-4 mt-4 border-t">
                <div class="flex items-center space-x-4 text-sm font-medium text-gray-600">
                    <button class="flex items-center p-2 rounded-full hover:bg-gray-100 transition-colors disabled:opacity-50" disabled>
                        &larr; Previous
                    </button>
                    <button class="flex items-center p-2 rounded-full hover:bg-gray-100 transition-colors">
                        Next &rarr;
                    </button>
                </div>
            </div>
        </div>
    </section>

</div>

<script>
// Additional print enhancement
document.addEventListener('DOMContentLoaded', function() {
    // Add print functionality with better formatting
    window.printReport = function() {
        window.print();
    };
    
    // You can add more print customization here if needed
});
</script>

</body>
</html>