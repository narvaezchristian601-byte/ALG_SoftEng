<?php
include("../../db.php");
session_start();

// Handle filters
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$change_type = isset($_GET['change_type']) ? $_GET['change_type'] : '';

// Build query with filters
$query = "
    SELECT 
        il.*,
        p.Name as product_name
    FROM inventory_log il
    JOIN product p ON il.Product_id = p.Product_id
    WHERE 1
";

$params = [];
$types = '';

if ($product_id) {
    $query .= " AND il.Product_id = ?";
    $params[] = $product_id;
    $types .= 'i';
}

if ($date_from) {
    $query .= " AND DATE(il.Date_Changed) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $query .= " AND DATE(il.Date_Changed) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($change_type && $change_type !== 'all') {
    $query .= " AND il.Change_Type = ?";
    $params[] = $change_type;
    $types .= 's';
}

$query .= " ORDER BY il.Date_Changed DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch products for filter dropdown
$products_result = $conn->query("SELECT Product_id, Name FROM product ORDER BY Name");
$products = [];
while ($product = $products_result->fetch_assoc()) {
    $products[] = $product;
}

// Change types for filter
$change_types = [
    'all' => 'All Types',
    'PO_RECEIVED' => 'PO Received',
    'SALE' => 'Sale',
    'RETURN' => 'Return',
    'ADJUSTMENT' => 'Adjustment',
    'DAMAGED' => 'Damaged',
    'OTHER' => 'Other'
];

function formatChangeType($type) {
    $types = [
        'PO_RECEIVED' => ['label' => 'PO Received', 'class' => 'po-received'],
        'SALE' => ['label' => 'Sale', 'class' => 'sale'],
        'RETURN' => ['label' => 'Return', 'class' => 'return'],
        'ADJUSTMENT' => ['label' => 'Adjustment', 'class' => 'adjustment'],
        'DAMAGED' => ['label' => 'Damaged', 'class' => 'damaged'],
        'OTHER' => ['label' => 'Other', 'class' => 'other']
    ];
    
    $info = $types[$type] ?? ['label' => $type, 'class' => 'other'];
    return "<span class='change-type {$info['class']}'>{$info['label']}</span>";
}

function formatChangeAmount($amount) {
    if ($amount > 0) {
        return "<span class='change-positive'>+{$amount}</span>";
    } else {
        return "<span class='change-negative'>{$amount}</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ALG | Inventory Log</title>
<style>
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
    .sidebar a:hover { text-decoration: underline; }
    .sidebar a.active, .sidebar a:hover {
        background: #d9d9d9;
        color: #000;
        text-decoration: underline;
    }

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
        margin-bottom: 20px;
    }

    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-group label {
        font-weight: 600;
        margin-bottom: 5px;
        font-size: 0.9em;
    }

    .filter-group select,
    .filter-group input {
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        align-items: end;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background: #4f9bd2;
        color: white;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

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

    .change-type {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8em;
        font-weight: 600;
    }

    .change-type.po-received { background: #d4edda; color: #155724; }
    .change-type.sale { background: #f8d7da; color: #721c24; }
    .change-type.return { background: #fff3cd; color: #856404; }
    .change-type.adjustment { background: #d1ecf1; color: #0c5460; }
    .change-type.damaged { background: #e2e3e5; color: #383d41; }
    .change-type.other { background: #f8f9fa; color: #6c757d; }

    .change-positive {
        color: #28a745;
        font-weight: 600;
    }

    .change-negative {
        color: #dc3545;
        font-weight: 600;
    }

    .no-records {
        text-align: center;
        padding: 40px;
        color: #6c757d;
        font-style: italic;
    }

    .summary-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .summary-card h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
    }

    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .stat-item {
        text-align: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .stat-value {
        font-size: 1.5em;
        font-weight: bold;
        color: #4f9bd2;
    }

    .stat-label {
        font-size: 0.9em;
        color: #6c757d;
        margin-top: 5px;
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
    </nav>
</header>

<div class="container">
    <aside class="sidebar">
        <h3>Products</h3>
        <a href="products.php">Products</a>
        <a href="purchase.php">Purchase</a>
        <a href="supplier.php">Supplier</a>
        <a href="sales_report.php">Sales Report</a>
    </aside>

    <section class="content">
        <div class="top-bar">
            <h2>Inventory Movement Log</h2>
            <div>
                <button onclick="window.location.href='products.php'" class="btn btn-secondary">
                    Back to Products
                </button>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="summary-card">
            <h3>Quick Overview</h3>
            <div class="summary-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= $result->num_rows ?></div>
                    <div class="stat-label">Total Log Entries</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php
                        $po_count = 0;
                        $result->data_seek(0); // Reset pointer
                        while ($row = $result->fetch_assoc()) {
                            if ($row['Change_Type'] === 'PO_RECEIVED') $po_count++;
                        }
                        $result->data_seek(0); // Reset pointer again
                        echo $po_count;
                        ?>
                    </div>
                    <div class="stat-label">PO Receivals</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php
                        $sale_count = 0;
                        $result->data_seek(0);
                        while ($row = $result->fetch_assoc()) {
                            if ($row['Change_Type'] === 'SALE') $sale_count++;
                        }
                        $result->data_seek(0);
                        echo $sale_count;
                        ?>
                    </div>
                    <div class="stat-label">Sales</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="inventory_log.php" class="filter-form">
                <div class="filter-group">
                    <label for="product_filter">Product</label>
                    <select id="product_filter" name="product_id">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['Product_id'] ?>" 
                                <?= $product_id == $product['Product_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="filter-group">
                    <label for="change_type">Change Type</label>
                    <select id="change_type" name="change_type">
                        <?php foreach ($change_types as $value => $label): ?>
                            <option value="<?= $value ?>" 
                                <?= $change_type == $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <button type="button" onclick="window.location.href='inventory_log.php'" class="btn btn-secondary">
                        Clear Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Log Table -->
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Product</th>
                    <th>Change Type</th>
                    <th>Change Amount</th>
                    <th>Previous Stock</th>
                    <th>New Stock</th>
                    <th>Reference</th>
                    <th>Changed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('M j, Y g:i A', strtotime($row['Date_Changed'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['product_name']) ?></strong><br>
                                <small>ID: <?= $row['Product_id'] ?></small>
                            </td>
                            <td><?= formatChangeType($row['Change_Type']) ?></td>
                            <td><?= formatChangeAmount($row['Change_Amount']) ?></td>
                            <td><?= $row['Previous_Stock'] ?></td>
                            <td><strong><?= $row['New_Stock'] ?></strong></td>
                            <td><?= htmlspecialchars($row['Reference'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['Changed_By'] ?? 'System') ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="no-records">
                            No inventory log entries found for the selected filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<script>
// Set default date range to last 30 days if no dates selected
document.addEventListener('DOMContentLoaded', function() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (!dateFrom.value && !dateTo.value) {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        dateTo.value = today.toISOString().split('T')[0];
        dateFrom.value = thirtyDaysAgo.toISOString().split('T')[0];
    }
});
</script>

</body>
</html>