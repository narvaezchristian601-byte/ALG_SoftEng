<?php
session_start();
if (!isset($_SESSION['Staff_id']) || $_SESSION['Role'] !== 'Admin') {
  header("Location: ../login.php");
  exit();
}
$name = $_SESSION['Name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - ALG</title>
  <style>
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: url("../images/aboutus-image1.png") center/cover no-repeat;
      height: 100vh;
      color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
    }
    .overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      z-index: 0;
    }
    .container {
      z-index: 1;
      background: rgba(17, 17, 17, 0.7);
      padding: 60px 80px;
      border-radius: 15px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    }
    a {
      display: inline-block;
      margin: 15px;
      padding: 14px 24px;
      background: #4f9bd2;
      color: white;
      border-radius: 8px;
      text-decoration: none;
      font-weight: bold;
    }
    a:hover {
      background: #3a84b8;
    }
  </style>
</head>
<body>
  <div class="overlay"></div>
  <div class="container">
    <h1>Welcome Admin, <?php echo htmlspecialchars($name); ?>!</h1>
    <p>Manage your system below:</p>
    <a href="../Staff/projects.php">View All Projects</a>
    <a href="../logout.php">Logout</a>
  </div>
</body>
</html>
