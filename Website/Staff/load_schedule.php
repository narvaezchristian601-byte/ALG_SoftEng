<?php
include("../db.php");
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 6;
$offset = ($page - 1) * $limit;

$sql = "
  SELECT o.Orders_id, c.Name AS cust, s.Name AS service, o.schedule_date, o.status, o.total_amount
  FROM Orders o
  JOIN Customers c ON o.customer_id = c.customer_id
  JOIN Services s ON o.Services_id = s.Services_id
  WHERE o.schedule_date >= NOW()
  ORDER BY o.schedule_date ASC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  echo "<tr>";
  echo "<td>{$row['Orders_id']}</td>";
  echo "<td>" . htmlspecialchars($row['cust']) . "</td>";
  echo "<td>" . htmlspecialchars($row['service']) . "</td>";
  echo "<td>{$row['schedule_date']}</td>";
  echo "<td>{$row['status']}</td>";
  echo "<td>{$row['total_amount']}</td>";
  echo "</tr>";
}
?>
