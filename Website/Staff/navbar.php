<?php
session_start();
include('../../db.php'); // Adjusted to your structure

// Check if staff is logged in
/*if (!isset($_SESSION['staff_id'])) {
    echo '<script>
        alert("Please login first.");
        window.location.href = "../login.php";
    </script>';
    exit();
}*/

// Fetch staff info
$staff_id = $_SESSION['staff_id'];
$query = "SELECT Name, Position FROM Staff WHERE Staff_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Navbar</title>
    <script src="../js/jquery.min.js"></script> <!-- Local jQuery -->
    <style>
        /* === NAVBAR GENERAL === */
    .navbar {
        background-color: #1e293b; /* dark slate blue */
        color: #ffffff;
        padding: 10px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .navbar-container {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* === LOGO === */
    .navbar-logo a {
        font-family: 'Archivo Black', sans-serif;
        color: #f1f5f9;
        text-decoration: none;
        font-size: 22px;
        letter-spacing: 1px;
    }

    /* === MENU === */
    .navbar-menu {
        list-style: none;
        display: flex;
        gap: 25px;
    }

    .navbar-menu li {
        display: inline-block;
    }

    .nav-link {
        color: #e2e8f0;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .nav-link:hover {
        color: #38bdf8; /* light blue highlight */
    }

    .logout {
        color: #f87171; /* red */
    }

    .logout:hover {
        color: #ef4444;
    }

    /* === USER INFO === */
    .navbar-user {
        text-align: right;
        font-size: 14px;
        color: #f1f5f9;
    }

    .navbar-user .position {
        display: block;
        font-size: 12px;
        color: #94a3b8;
    }

    /* === MOBILE MENU === */
    .navbar-toggle {
        display: none;
        flex-direction: column;
        cursor: pointer;
    }

    .bar {
        background-color: #ffffff;
        height: 3px;
        width: 25px;
        margin: 4px 0;
        transition: 0.4s;
    }

    @media (max-width: 768px) {
        .navbar-menu {
            display: none;
            flex-direction: column;
            position: absolute;
            top: 65px;
            right: 0;
            width: 100%;
            background-color: #1e293b;
            padding: 15px 0;
        }

        .navbar-menu.active {
            display: flex;
        }

        .navbar-toggle {
            display: flex;
        }

        .navbar-user {
            display: none;
        }

        .navbar-container {
            flex-wrap: wrap;
        }
    }

    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-logo">
                <a href="home.php">ALG Construction</a>
            </div>

            <div class="navbar-toggle" id="mobile-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>

            <ul class="navbar-menu">
                <li><a href="home.php" class="nav-link">Home</a></li>
                <li><a href="schedule.php" class="nav-link">Schedule List</a></li>
                <li><a href="project_logs.php" class="nav-link">Project Logs</a></li>
                <li><a href="profile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link logout">Logout</a></li>
            </ul>

            <div class="navbar-user">
                <p>Welcome, <strong><?php echo htmlspecialchars($staff['Name']); ?></strong></p>
                <span class="position"><?php echo htmlspecialchars($staff['Position']); ?></span>
            </div>
        </div>
    </nav>

    <script>
    $(document).ready(function() {
        $('#mobile-menu').click(function() {
            $('.navbar-menu').toggleClass('active');
            $('#mobile-menu').toggleClass('is-active');
        });
    });
    </script>
</body>
</html>
