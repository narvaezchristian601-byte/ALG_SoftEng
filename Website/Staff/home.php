<?php
session_start();
include('../db.ph'); // must provide $conn
// session check
if (!isset($_SESSION['Staff_id'])) {
  echo '<script>alert("Please login first."); window.location.href="../login.php";</script>';
  exit();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Staff Home - ALG</title>
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/staff-style.css">
  <style>
    /* small hero tweaks to mimic your design */
    .hero {
      background-image: url('../images/alg-background.png');
      background-size: cover;
      background-position: center;
      color: #fff;
      min-height: 160px;
      border-radius: 8px;
    }
    .hero .hero-box {
      background: rgba(0,0,0,0.45);
      color:#fff;
      font-size: 32px;
      padding: 22px;
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="staff-container">
  <div class="hero card">
    <div class="hero-box">
      <strong>Your shelter,</strong><br/>our responsibility
    </div>
  </div>

  <div style="height:18px"></div>

  <div class="card">
    <h3>Quick Links</h3>
    <div style="display:flex;gap:12px;margin-top:8px;">
      <a class="btn" href="schedule.php">View Schedule</a>
      <a class="btn" href="project_logs.php">Project Logs</a>
      <a class="btn" href="profile.php">Your Profile</a>
    </div>
  </div>
</div>

</body>
</html>
