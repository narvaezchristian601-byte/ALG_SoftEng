<?php
session_start();
require_once "../../db.php";

// Redirect if not logged in
if (!isset($_SESSION['Staff_id'])) {
  echo '<script>
      alert("Please login first.");
      window.location.href = "../login.php";
  </script>';
  exit();
}

$staff_id = $_SESSION['Staff_id'];

// Fetch current logged-in staff info
$query = "SELECT * FROM staff WHERE Staff_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $staff = $result->fetch_assoc();
} else {
  echo "<p style='color:red; text-align:center;'>Error: Staff record not found.</p>";
  exit();
}

// Prevent undefined index error
$staff['Skills'] = isset($staff['Skills']) ? $staff['Skills'] : 'N/A';

// // ✅ Fetch latest customer comment from successful project (using projects table)
// $commentQuery = "
//   SELECT client_feedback AS comment_text, completion_date AS date_posted, project_name
//   FROM projects
//   WHERE status = 'Successful' AND staff_id = ?
//   ORDER BY completion_date DESC
//   LIMIT 1
// ";
// $commentStmt = $conn->prepare($commentQuery);
// $commentStmt->bind_param("i", $staff_id);
// $commentStmt->execute();
// $commentResult = $commentStmt->get_result();
// $comment = $commentResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile | ALG Staff</title>
  <style>
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: #a6a6a6;
      color: #000;
    }

    /* ======== NAVBAR ======== */
    header {
      background-color: #615e5e;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 40px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }

    .logo img {
      height: 45px;
    }

    nav a {
      color: #fff;
      text-decoration: none;
      font-weight: 600;
      margin: 0 20px;
      padding: 6px 14px;
      border-radius: 20px;
      transition: 0.3s;
    }

    nav a.active {
      background-color: #d9d9d9;
      color: #000;
    }

    nav a:hover {
      background-color: #bfbfbf;
      color: #000;
    }

    .logout img {
      width: 28px;
      height: 28px;
      filter: invert(1);
      cursor: pointer;
    }

    /* ======== PROFILE CONTAINER ======== */
    main {
      padding: 40px;
      display: flex;
      justify-content: center;
    }

    .profile-container {
      background: #f4f4f4;
      width: 90%;
      max-width: 1000px;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
      padding: 30px 40px;
    }

    .profile-header {
      text-align: center;
      margin-bottom: 25px;
    }

    .profile-header h1 {
      font-size: 2rem;
      margin: 0;
      color: #111;
    }

    /* ======== PROFILE CARD LAYOUT ======== */
    .profile-card {
      display: flex;
      background: #fff;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      justify-content: space-between;
      flex-wrap: wrap;
    }

    /* ======== LEFT SIDE (IMAGE + INFO) ======== */
    .profile-left {
      flex: 0 0 60%;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .profile-image {
      margin-bottom: 20px;
    }

    .profile-image img {
      width: 130px;
      height: 130px;
      border-radius: 50%;
      background: #d9d9d9;
      padding: 10px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.2);
    }

    .profile-info {
      width: 100%;
      text-align: left;
    }

    .profile-info p {
      margin: 8px 0;
      line-height: 1.6;
      font-size: 1rem;
    }

    .profile-info p span {
      font-weight: 600;
      margin-right: 5px;
      color: #333;
    }

    /* ======== RIGHT SIDE (CUSTOMER COMMENT) ======== */
    .customer-comment {
      flex: 0 0 35%;
      background: #ffffff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 15px 18px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      font-size: 0.95rem;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      height: fit-content;
    }

    .customer-comment h3 {
      margin-top: 0;
      margin-bottom: 10px;
      font-size: 1.1rem;
      color: #333;
      border-bottom: 1px solid #ccc;
      padding-bottom: 5px;
    }

    .comment-text {
      font-style: italic;
      color: #444;
      margin-bottom: 12px;
      line-height: 1.5;
    }

    .comment-meta {
      font-size: 0.85rem;
      color: #666;
    }

    /* ======== RESPONSIVE ======== */
    @media (max-width: 768px) {
      .profile-card {
        flex-direction: column;
      }
      .profile-left, .customer-comment {
        flex: 1 1 100%;
      }
      .customer-comment {
        margin-top: 20px;
      }
    }
  </style>
</head>
<body>

  <!-- ======== NAVBAR ======== -->
  <header>
    <div class="logo">
      <img src="../../images/alg-logo-black.png" alt="ALG Logo">
    </div>
    <nav>
      <a href="home.php">Home</a>
      <a href="projects.php">Projects</a>
      <a href="profile.php" class="active">Staff</a>
    </nav>
    <div class="logout">
      <a href="../Login.php"><img src="../../images/log-out.svg" alt="Logout"></a>
    </div>
  </header>

  <!-- ======== PROFILE SECTION ======== -->
  <main>
    <div class="profile-container">
      <div class="profile-header">
        <h1>Staff Profile</h1>
      </div>

      <!-- PROFILE AND COMMENT SECTION -->
      <div class="profile-card">
        <!-- LEFT SIDE -->
        <div class="profile-left">
          <div class="profile-image">
            <img src="<?= !empty($staff['image']) ? '../../uploads/' . htmlspecialchars($staff['image']) : '../../images/avatar-placeholder.png' ?>" alt="Staff Image">
          </div>

          <div class="profile-info">
            <p><span>Name:</span> <?= htmlspecialchars($staff['Name']) ?></p>
            <p><span>Address:</span> <?= htmlspecialchars($staff['address']) ?></p>
            <p><span>Phone Number:</span> <?= htmlspecialchars($staff['PhoneNum']) ?></p>
            <p><span>Position:</span> <?= htmlspecialchars($staff['Position']) ?></p>
            <p><span>Skills:</span> <?= htmlspecialchars($staff['Skills']) ?></p>
          </div>
        </div>

        <!-- RIGHT SIDE -->
        <div class="customer-comment">
          <h3>Recent Customer Comment</h3>
          <?php if (!empty($comment)): ?>
            <div class="comment-text">“<?= htmlspecialchars($comment['comment_text']) ?>”</div>
            <div class="comment-meta">
              <strong><?= htmlspecialchars($comment['project_name']) ?></strong><br>
              <em><?= date("F j, Y", strtotime($comment['date_posted'])) ?></em>
            </div>
          <?php else: ?>
            <div class="comment-text">No recent customer comments available.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

</body>
</html>
