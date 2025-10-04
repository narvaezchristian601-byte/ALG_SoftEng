<?php
require_once("db.php");
session_start();

// Update stock if order is deleted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order_id'])) {
    $orderId = intval($_POST['delete_order_id']);
// restore stock first
    $stmtItems = $conn->prepare("SELECT Product_id, quantity FROM OrderItems WHERE Orders_id = ?");
    $stmtItems->bind_param("i", $orderId);
    $stmtItems->execute();
    $itemsResult = $stmtItems->get_result();

    while ($item = $itemsResult->fetch_assoc()) {
        $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock + ? WHERE Product_id = ?");
        $stmtUpdate->bind_param("ii", $item['quantity'], $item['Product_id']);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
    $stmtItems->close();

// get customer id
    $stmtGetCustomer = $conn->prepare("SELECT customer_id FROM Orders WHERE Orders_id = ?");
    $stmtGetCustomer->bind_param("i", $orderId);
    $stmtGetCustomer->execute();
    $stmtGetCustomer->bind_result($customerId);
    $stmtGetCustomer->fetch();
    $stmtGetCustomer->close();

// delete order items
    $stmtDelItems = $conn->prepare("DELETE FROM OrderItems WHERE Orders_id = ?");
    $stmtDelItems->bind_param("i", $orderId);
    $stmtDelItems->execute();
    $stmtDelItems->close();

// delete order record
    $stmtDelOrder = $conn->prepare("DELETE FROM Orders WHERE Orders_id = ?");
    $stmtDelOrder->bind_param("i", $orderId);
    $stmtDelOrder->execute();
    $stmtDelOrder->close();

// delete the customer
    $success = true;
    if ($customerId) {
        $stmtDelCustomer = $conn->prepare("DELETE FROM Customers WHERE customer_id = ?");
        $stmtDelCustomer->bind_param("i", $customerId);
        $success = $stmtDelCustomer->execute();
        $stmtDelCustomer->close();
    }

    if ($success) {
        echo "<script>alert('Order & Customer deleted successfully.'); window.location='orders.php';</script>";
        exit;
    } else {
        echo "<script>alert('Failed to delete order or customer.');</script>";
    }
}



// Search handling
$searchTerm = "";
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
}
$like = "%{$searchTerm}%";

// Valid sort columns
$validColumns = [
    "Name"  => "c.Name",
    "total_amount" => "o.total_amount",
    "status"       => "o.status",
    "order_date"   => "o.order_date"
];

$requestedCol = $_GET['sort'] ?? '';
$requestedDir = strtoupper($_GET['dir'] ?? '');

$orderBy = "o.order_date";
$orderDir = "DESC"; // default newest first

if ($requestedCol && array_key_exists($requestedCol, $validColumns)) {
    $orderBy = $validColumns[$requestedCol];
    $currentCol = $requestedCol;
} else {
    $currentCol = 'order_date';
}

if ($requestedDir === 'ASC') {
    $orderDir = 'ASC';
    $currentDir = 'ASC';
} else {
    $orderDir = 'DESC';
    $currentDir = 'DESC';
}
// Get orders with customer names and items
$stmt = $conn->prepare(
    "SELECT o.Orders_id, c.Name, o.total_amount, o.status, o.order_date,
            oi.Product_id, oi.quantity, oi.price, p.Name AS ProductName
     FROM Orders o
     LEFT JOIN OrderItems oi ON o.Orders_id = oi.Orders_id
     LEFT JOIN Product p ON oi.Product_id = p.Product_id
     LEFT JOIN Customers c ON o.Customer_id = c.Customer_id
     WHERE o.Orders_id LIKE ? 
        OR c.Name LIKE ? 
        OR o.status LIKE ? 
        OR o.order_date LIKE ?
     ORDER BY $orderBy $orderDir"
);
$stmt->bind_param("ssss", $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

// Group by Orders_id
$orders = [];
while ($row = $result->fetch_assoc()) {
    // Initialize order if not exists
    $orders[$row['Orders_id']]['details'] = [
        'Name'        => $row['Name'],
        'total_amount'=> $row['total_amount'],
        'status'      => $row['status'],
        'order_date'  => $row['order_date'],
    ];
    // Append item
    $orders[$row['Orders_id']]['items'][] = [
        'ProductName' => $row['ProductName'],
        'quantity' => $row['quantity'],
        'price' => $row['price']
    ];
}

// Toggle order
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
<title>ALG Roofing System - Orders</title>
<style>


html, body {
    margin: 0;
    padding: 0;
    font-family: 'Arial', sans-serif;
    background: #f8f9fa;
    color: #333;
}


header, footer {
    background: #007bff;
    color: white;
    text-align: center;
    padding: 12px 0;
}


nav {
    background: #0056b3;
    text-align: center;
    padding: 10px 0;
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
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
}


.card {
    max-width: 900px;
    width: 100%;
    margin: 20px auto;
    padding: 25px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}


.search-box input[type=text] {
    width: 70%;
    padding: 8px;
    margin-right: 8px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.search-box button {
    padding: 8px 15px;
    border-radius: 5px;
    border: none;
    background: #007bff;
    color: white;
    cursor: pointer;
}

.search-box button:hover {
    background: #0056b3;
}

.delete-order {
    padding: 8px 15px;
    border-radius: 5px;
    border: none;
    background: #007bff;
    color: white;
    cursor: pointer;
}

.delete-order:hover {
    background: #0056b3;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    border: 1px solid #ccc;
    padding: 10px;
    text-align: left;
    font-size: 0.95rem;
}

th {
    background: #007bff;
    color: white;
}


.status-select {
    padding: 5px 10px;
    font-weight: bold;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    color: white;

  
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}
a {
    color: white;
    text-decoration: none;
}
.status-pending   { background: orange; color: black; }
.status-ongoing   { background: blue;   color: white; }
.status-completed { background: green;  color: white; }
.status-dismissed { background: red;    color: white; }



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
        <h2>Orders</h2>
        <div class="search-box">
            <form method="get" action="orders.php">
                <input type="text" name="search" placeholder="Search by order id, customer, status, or date..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($currentCol); ?>">
                <input type="hidden" name="dir" value="<?php echo htmlspecialchars($currentDir); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <table>
            <tr>
                <th><?php echo sortLink("Orders_id", "Order ID", $currentCol, $currentDir, $searchTerm); ?></th>
                <th>Name</th>
                <th>Products</th>
                <th><?php echo sortLink("total_amount", "Total Amount", $currentCol, $currentDir, $searchTerm); ?></th>
                <th><?php echo sortLink("status", "Status", $currentCol, $currentDir, $searchTerm); ?></th>
                <th>Order Date</th>
                <th>Action</th>
            </tr>

            <?php foreach ($orders as $order_id => $data): ?>
            <tr>
                <td><?php echo $order_id; ?></td>
                <td><?php echo htmlspecialchars($data['details']['Name']); ?></td>
                <td>
                    <ul>
                        <?php foreach ($data['items'] as $item): ?>
                        <li><?php echo htmlspecialchars($item['ProductName']); ?> (x<?php echo (int)$item['quantity']; ?> @ ₱<?php echo number_format($item['price'],2); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </td>
                <td>₱<?php echo number_format($data['details']['total_amount'], 2); ?></td>
                <td>
                    <select class="status-dropdown <?php echo 'status-' . strtolower($data['details']['status']); ?>" data-id="<?php echo $order_id; ?>">
                        <option value="Pending"   <?php if ($data['details']['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                        <option value="Ongoing"   <?php if ($data['details']['status'] == 'Ongoing') echo 'selected'; ?>>Ongoing</option>
                        <option value="Completed" <?php if ($data['details']['status'] == 'Completed') echo 'selected'; ?>>Completed</option>
                        <option value="Dismissed" <?php if ($data['details']['status'] == 'Dismissed') echo 'selected'; ?>>Dismissed</option>
                    </select>

                </td>
                <td><?php echo htmlspecialchars($data['details']['order_date']); ?></td>
                <td><button class="delete-order" data-id="<?php echo $order_id; ?>">Delete</button></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</main>

<footer>
    <p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p>
</footer>

<script>
document.querySelectorAll('.status-dropdown').forEach(select => {
    // Apply correct color on page load
    select.className = "status-dropdown status-" + select.value.toLowerCase();

    select.addEventListener("change", function () {
        const newStatus = this.value;
        const orderId = this.dataset.id;

        if (confirm(`Are you sure you want to change this order to "${newStatus}"?`)) {
            fetch("update_status.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "order_id=" + orderId + "&new_status=" + newStatus
            })
            .then(res => res.text())
            .then(data => {
                alert(data);
                // Update dropdown class instantly
                this.className = "status-dropdown status-" + newStatus.toLowerCase();
            })
            .catch(err => {
                alert("Error updating status");
                console.error(err);
            });
        } else {
            // If canceled, reset back
            this.value = this.dataset.current;
        }
    });

    // Save current status for cancel revert
    select.dataset.current = select.value;
});
 // For the delete buttons
document.querySelectorAll('.delete-order').forEach(button => {
    button.addEventListener('click', function() {
        const orderId = this.dataset.id;
        if (confirm(`Are you sure you want to delete this order (ID: ${orderId})? This action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'orders.php'; // backend script handles deletion & stock restore

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_order_id';
            input.value = orderId;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });
});

</script>
</body>
</html>

