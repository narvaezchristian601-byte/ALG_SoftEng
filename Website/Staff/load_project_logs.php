<?php
include("../db.php");
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 6;
$offset = ($page - 1) * $limit;

$sql = "
  SELECT pl.Log_id, pl.Activity, pl.LogDate, p.Project_id
  FROM ProjectLogs pl
  JOIN Projects p ON pl.Project_id = p.Project_id
  ORDER BY pl.LogDate DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  echo "<tr>";
  echo "<td>{$row['Log_id']}</td>";
  echo "<td>{$row['Project_id']}</td>";
  echo "<td>" . htmlspecialchars($row['Activity']) . "</td>";
  echo "<td>{$row['LogDate']}</td>";
  echo "</tr>";
}
?>
