<?php
include '../../db.php';
$staff_id = 2; // Sample logged-in staff

$query = "SELECT * FROM Staff WHERE Staff_id = $staff_id";
$result = $conn->query($query);
$staff = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile | ALG Enterprise</title>
    <link rel="stylesheet" href="css/staff-profile.css">
</head>
<body>
    <?php include '../css/navbar.php'; ?>

    <section class="profile-container">
        <h1>Profile</h1>
        <div class="profile-card">
            <h2><?php echo $staff['Name']; ?></h2>
            <p><strong>Email:</strong> <?php echo $staff['Email']; ?></p>
            <p><strong>Phone:</strong> <?php echo $staff['PhoneNum']; ?></p>
            <p><strong>Address:</strong> <?php echo $staff['address']; ?></p>
            <p><strong>Position:</strong> <?php echo $staff['Position']; ?></p>
            <a href="../../logout.php" class="btn">Logout</a>
        </div>
    </section>
</body>
</html>
