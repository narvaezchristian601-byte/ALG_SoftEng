<?php
session_start();
include("../../db.php");

if (!isset($_SESSION['Staff_id'])) {
  header("Location: ../login.php");
  exit();
}

$sql = "
SELECT 
  Orders.Orders_id,
  Customers.Name AS CustomerName,
  Services.Name AS ServiceName,
  Orders.total_amount,
  Orders.status,
  Orders.schedule_date
FROM Orders
JOIN Customers ON Orders.customer_id = Customers.customer_id
JOIN Services ON Orders.Services_id = Services.Services_id
ORDER BY Orders.schedule_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projects - ALG Staff</title>
  <style>
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: url("../images/aboutus-image1.png") center/cover no-repeat;
      height: 100vh;
      color: #fff;
      position: relative;
    }
    .overlay {
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.6);
      z-index: 0;
    }
    .container {
      position: relative;
      z-index: 1;
      background: rgba(17,17,17,0.75);
      margin: 60px auto;
      padding: 40px;
      width: 85%;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      padding: 12px 15px;
      border-bottom: 1px solid rgba(255,255,255,0.2);
      text-align: left;
    }
    th {
      background-color: rgba(79,155,210,0.5);
    }
    tr:hover {
      background: rgba(255,255,255,0.05);
    }
    .back-btn {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 20px;
      background: #4f9bd2;
      color: white;
      text-decoration: none;
      border-radius: 8px;
    }
    .back-btn:hover {
      background: #3a84b8;
    }
  </style>
</head>
<body>
  <?php include '../css/navbar.php'; ?>
  <div class="overlay"></div>
  <div class="container">
    <h1>Project Overview</h1>
    <table>
      <tr>
        <th>Order ID</th>
        <th>Customer</th>
        <th>Service</th>
        <th>Status</th>
        <th>Scheduled Date</th>
        <th>Total</th>
      </tr>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?php echo $row['Orders_id']; ?></td>
          <td><?php echo htmlspecialchars($row['CustomerName']); ?></td>
          <td><?php echo htmlspecialchars($row['ServiceName']); ?></td>
          <td><?php echo htmlspecialchars($row['status']); ?></td>
          <td><?php echo htmlspecialchars($row['schedule_date']); ?></td>
          <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
    <a href="home.php" class="back-btn">Back</a>
  </div>
</body>
</html>
