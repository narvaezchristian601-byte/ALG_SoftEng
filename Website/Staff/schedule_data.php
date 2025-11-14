<?php
include '../../db.php';
$limit = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$query = "
    SELECT o.Orders_id, c.Name AS Customer, s.Name AS Service, o.schedule_date, o.status
    FROM Orders o
    JOIN Customers c ON o.customer_id = c.customer_id
    JOIN Services s ON o.Services_id = s.Services_id
    ORDER BY o.schedule_date ASC
    LIMIT $limit OFFSET $offset
";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo "<ul class='schedule-list'>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>
                <strong>{$row['Service']}</strong><br>
                Customer: {$row['Customer']}<br>
                Date: {$row['schedule_date']}<br>
                Status: {$row['status']}
              </li>";
    }
    echo "</ul>";
} else {
    echo "<p>No schedules found.</p>";
}
?>
