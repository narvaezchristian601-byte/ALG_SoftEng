<?php
include '../../db.php';
$limit = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$query = "
    SELECT p.Project_id, s.Name AS Service, c.Name AS Customer, o.total_amount, o.status
    FROM Projects p
    JOIN Orders o ON p.Orders_id = o.Orders_id
    JOIN Services s ON o.Services_id = s.Services_id
    JOIN Customers c ON o.customer_id = c.customer_id
    ORDER BY p.start_date DESC
    LIMIT $limit OFFSET $offset
";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo "<ul class='log-list'>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>
                <strong>{$row['Service']}</strong><br>
                Customer: {$row['Customer']}<br>
                Amount: ₱{$row['total_amount']}<br>
                Status: {$row['status']}
              </li>";
    }
    echo "</ul>";
} else {
    echo "<p>No project logs found.</p>";
}
?>
