<?php
// staff.php

// NOTE: Ensure your db.php file path is correct relative to this script.
include("../../db.php"); 

// Fetch staff with their assigned projects
$query = "
    SELECT 
        s.Staff_id,
        s.Name AS full_name,
        s.Position AS role_position,
        s.PhoneNum AS contact_number,
        COALESCE(GROUP_CONCAT(p.Project_id SEPARATOR ', '), 'None') AS assigned_task,
        CASE 
            WHEN COUNT(p.Project_id) = 0 THEN 'Yes'
            ELSE 'No'
        END AS availability
    FROM Staff s
    LEFT JOIN ProjectStaff ps ON s.Staff_id = ps.Staff_id
    LEFT JOIN Projects p ON ps.Project_id = p.Project_id
    GROUP BY s.Staff_id, s.Name, s.Position, s.PhoneNum
    ORDER BY s.Staff_id ASC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
  	<title>Staff | ALG Enterprises</title>

  	<link rel="stylesheet" href="./index.css" />
  	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" />

  	<style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }

        .header {
            background-color: #444;
            color: #fff;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 15px;
        }

        .header a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
        }

        .header a.active {
            background-color: #2d77ff;
            border-radius: 10px;
        }

        .staff-container {
            max-width: 1100px;
            background: white;
            margin: 40px auto;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #2d77ff;
            color: white;
        }

        tr:hover {
            background-color: #f2f2f2;
        }

        .action-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            background-color: #2d77ff;
            color: white;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn.secondary {
            background-color: #999;
        }

        .btn.view {
            background-color: #4CAF50;
            padding: 6px 12px;
            font-size: 14px;
        }

        .btn:hover {
            opacity: 0.9;
        }
  	</style>
</head>

<body>

    <div class="header">
        <a href="home.php">Home</a>
        <a href="inventory.php">Inventory</a>
        <a href="projects.php">Projects</a>
        <a href="staff.php" class="active">Staff</a>
    </div>

    <div class="staff-container">
        <div class="action-bar">
            <button class="btn" onclick="window.print()">Print</button>
            <button class="btn secondary" onclick="exportToExcel()">Export Excel</button>
            <a href="create_staff.php" class="btn">Add Staff</a> <!-- Added link to creation page -->
        </div>

        <table>
            <thead>
                <tr>
                    <th>Staff ID</th>
                    <th>Full Name</th>
                    <th>Role/Position</th>
                    <th>Contact Number</th>
                    <th>Assigned Project(s)</th>
                    <th>Availability</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['Staff_id']) ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['role_position']) ?></td>
                            <td><?= htmlspecialchars($row['contact_number']) ?></td>
                            <td><?= htmlspecialchars($row['assigned_task']) ?></td>
                            <td><?= htmlspecialchars($row['availability']) ?></td>
                            <td>
                                <!-- IMPORTANT: Pass the Staff_id to the viewStaff JavaScript function -->
                                <button class="btn view" onclick="viewStaff('<?= htmlspecialchars($row['Staff_id']) ?>')">View Info</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;">No staff data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function exportToExcel() {
            // Functionality to export data
            window.location.href = "export_staff_excel.php";
        }

        function viewStaff(staffId) {
            // This navigates to view_staff.php with the specific staff ID as a URL parameter
            window.location.href = "view_staff.php?id=" + staffId;
        }
    </script>

</body>
</html>
