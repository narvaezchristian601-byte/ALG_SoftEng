<?php
// view_staff.php
session_start();
// NOTE: Ensure your db.php file path is correct relative to this script.
require_once "../../db.php"; 

$staff = []; // Array to hold staff data (core details)
$projects = []; // Array to hold project assignments
$error_message = null;

// --- 1. GET STAFF ID FROM URL ---
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $staff_id = $_GET['id'];
    
    // --- 2. QUERY 1: FETCH CORE STAFF DETAILS ---
    $sql_staff = "
        SELECT 
            Staff_id, 
            Name, 
            address, 
            PhoneNum, 
            Email, 
            Role, 
            Position 
        FROM Staff 
        WHERE Staff_id = ?
    ";

    if ($stmt = $conn->prepare($sql_staff)) {
        $stmt->bind_param("i", $staff_id); // 'i' for integer Staff_id
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $staff = $result->fetch_assoc();
            } else {
                $error_message = "Staff member with ID " . htmlspecialchars($staff_id) . " not found.";
            }
        } else {
            $error_message = "Database execution error (Staff Details): " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Database query preparation failed (Staff Details): " . $conn->error;
    }

    // --- 3. QUERY 2: FETCH ASSIGNED PROJECTS (only if staff was found) ---
    if (!empty($staff)) {
        $sql_projects = "
            SELECT 
                p.Project_id,
                p.Status,
                p.StartDate,
                p.EndDate,
                s.Name AS service_name,
                c.Name AS customer_name
            FROM ProjectStaff ps
            JOIN Projects p ON ps.Project_id = p.Project_id
            JOIN Orders o ON p.Orders_id = o.Orders_id
            JOIN Services s ON o.Services_id = s.Services_id
            JOIN Customers c ON o.customer_id = c.customer_id
            WHERE ps.Staff_id = ?
            ORDER BY p.StartDate DESC
        ";

        if ($stmt_proj = $conn->prepare($sql_projects)) {
            $stmt_proj->bind_param("i", $staff_id);
            if ($stmt_proj->execute()) {
                $result_proj = $stmt_proj->get_result();
                while ($row = $result_proj->fetch_assoc()) {
                    $projects[] = $row;
                }
            }
            $stmt_proj->close();
        }
    }

} else {
    // No ID provided
    $error_message = "No Staff ID provided. Please return to the Staff list.";
}

$conn->close();

// Mock Client Feedback (Since the new schema doesn't have a dedicated feedback table)
$comment_text = "Highly reliable and skilled. Alice handled the project delivery professionally and kept us informed every step of the way.";
if (isset($staff['Name'])) {
    $mock_comments = [
        "{$staff['Name']} was highly reliable and skilled. They handled the project delivery professionally and kept us informed every step of the way.",
        "Excellent technical work! {$staff['Name']} quickly resolved the issue and ensured the network was running smoothly.",
        "A true professional. {$staff['Name']} is a great communicator and a pleasure to work with on long-term projects.",
    ];
    $comment_text = $mock_comments[array_rand($mock_comments)];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile | ALG Enterprises</title>
    <style>
        /* FONTS */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        
        :root {
            --primary-bg: #f3f4f6; /* Light gray background */
            --navbar-bg: #1f2937; /* Dark blue/gray for header */
            --card-bg: #ffffff; /* White card background */
            --accent-blue: #3b82f6; /* Tailwind blue-500 equivalent */
            --text-color: #1f2937;
            --success-green: #10b981;
            --warning-yellow: #f59e0b;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--primary-bg);
            color: var(--text-color);
            min-height: 100vh;
        }

        /* -------------------------- */
        /* ======== NAVBAR ======== */
        /* -------------------------- */
        header {
            background-color: var(--navbar-bg); 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            color: #fff;
        }

        .logo {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff;
        }
        .logo span {
            font-size: 0.9rem;
            font-weight: 300;
        }
        
        nav a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
            padding: 8px 15px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        nav a.active {
            background-color: var(--accent-blue);
        }

        nav a:hover {
            background-color: #374151; /* Slightly lighter dark gray on hover */
        }
        
        /* --------------------------------- */
        /* ======== PROFILE LAYOUT ======== */
        /* --------------------------------- */
        main {
            padding: 30px;
            display: flex;
            justify-content: center;
        }

        .profile-container {
            width: 95%;
            max-width: 1200px;
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-header h1 {
            font-size: 2rem;
            margin: 0;
            color: var(--text-color);
        }
        
        .btn-action {
            background-color: var(--accent-blue);
            color: #fff;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: background-color 0.2s;
        }
        
        .btn-action:hover {
            background-color: #2563eb;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Staff details takes 2/3, Feedback 1/3 */
            gap: 20px;
        }

        .main-info-card, .feedback-card, .projects-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        /* --- DETAILS CARD --- */
        .staff-details {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .avatar-placeholder {
            flex-shrink: 0; 
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            justify-content: center;
            align-items: center;
            align-self: flex-start;
            border: 4px solid var(--accent-blue);
        }
        
        .avatar-placeholder img {
            width: 50px; 
            height: 50px;
            filter: invert(30%) sepia(0%) saturate(1000%) hue-rotate(0deg); 
        }

        .basic-info h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 5px 0;
        }

        .basic-info p {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--accent-blue);
            margin: 0;
        }

        .info-list {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-item span:first-child {
            font-weight: 600;
            color: #4b5563;
            font-size: 0.85rem;
            margin-bottom: 3px;
        }
        
        .info-item span:last-child {
            font-weight: 500;
            font-size: 1rem;
        }

        /* --- FEEDBACK CARD --- */
        .feedback-card h3, .projects-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 5px;
            color: #4b5563;
        }

        .feedback-box {
            background-color: #f9fafb;
            border-left: 4px solid var(--success-green);
            padding: 15px;
            border-radius: 5px;
            font-style: italic;
            color: #374151;
            line-height: 1.5;
        }

        /* --- PROJECTS CARD --- */
        .projects-card {
            grid-column: 1 / span 2; /* Span across both columns */
            margin-top: 20px;
        }

        .project-table {
            width: 100%;
            border-collapse: collapse;
        }

        .project-table th, .project-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .project-table th {
            background-color: #f9fafb;
            color: #4b5563;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .project-table tbody tr:hover {
            background-color: #eff6ff;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-Completed {
            background-color: #d1fae5;
            color: var(--success-green);
        }

        .status-Ongoing {
            background-color: #fef3c7;
            color: var(--warning-yellow);
        }

        .status-Pending {
            background-color: #fee2e2;
            color: #ef4444; /* Red */
        }

        /* Error/Info Box */
        .info-box {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }


        /* ------------------------------------ */
        /* ======== RESPONSIVE LAYOUT ======== */
        /* ------------------------------------ */
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr; /* Single column on tablet/mobile */
            }
            .projects-card {
                grid-column: auto; /* No longer need to span */
            }
        }
        
        @media (max-width: 600px) {
            header {
                flex-direction: column;
                padding: 10px 20px;
            }
            nav {
                margin-top: 10px;
            }
            main {
                padding: 15px;
            }
            .staff-details {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .info-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo">ALG <span style="font-weight: 300;">Enterprises</span></div>
        <nav>
            <a href="home.php">Home</a>
            <a href="inventory.php">Inventory</a>
            <a href="projects.php">Projects</a>
            <a href="staff.php" class="active">Staff</a>
        </nav>
    </header>

    <main>
        <div class="profile-container">
            <div class="profile-header">
                <h1>Staff Profile (ID: <?= htmlspecialchars($_GET['id'] ?? 'N/A') ?>)</h1>
                <a href="staff.php" class="btn-action">Back to Staff List</a>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="info-box">
                    **Error:** <?= htmlspecialchars($error_message) ?>
                </div>
            <?php else: ?>
                <div class="content-grid">
                    
                    <!-- 1. MAIN INFO CARD -->
                    <div class="main-info-card">
                        <div class="staff-details">
                            <div class="avatar-placeholder">
                                <!-- Placeholder icon for staff member -->
                                <img src="https://api.iconify.design/heroicons:user-circle-20-solid.svg?color=%231f2937" alt="User Avatar"> 
                            </div>

                            <div class="basic-info">
                                <h2><?= htmlspecialchars($staff['Name'] ?? 'N/A') ?></h2>
                                <p><?= htmlspecialchars($staff['Position'] ?? 'Role N/A') ?></p>
                                
                                <div class="info-list">
                                    <div class="info-item">
                                        <span>Staff ID</span>
                                        <span><?= htmlspecialchars($staff['Staff_id'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span>Role</span>
                                        <span><?= htmlspecialchars($staff['Role'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span>Email</span>
                                        <span><a href="mailto:<?= htmlspecialchars($staff['Email'] ?? '#') ?>"><?= htmlspecialchars($staff['Email'] ?? 'N/A') ?></a></span>
                                    </div>
                                    <div class="info-item">
                                        <span>Phone Number</span>
                                        <span><?= htmlspecialchars($staff['PhoneNum'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span>Address</span>
                                        <span style="grid-column: 1 / span 2;"><?= htmlspecialchars($staff['address'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. FEEDBACK CARD -->
                    <div class="feedback-card">
                        <h3>Latest Client Feedback</h3>
                        <div class="feedback-box">
                            “<?= htmlspecialchars($comment_text) ?>”
                        </div>
                    </div>
                </div>

                <!-- 3. PROJECTS HISTORY CARD (Full width below the grid) -->
                <div class="projects-card">
                    <h3>Assigned Project History</h3>
                    <?php if (!empty($projects)): ?>
                        <table class="project-table">
                            <thead>
                                <tr>
                                    <th>Project ID</th>
                                    <th>Service Type</th>
                                    <th>Customer</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($project['Project_id']) ?></td>
                                        <td><?= htmlspecialchars($project['service_name']) ?></td>
                                        <td><?= htmlspecialchars($project['customer_name']) ?></td>
                                        <td><?= htmlspecialchars($project['StartDate']) ?></td>
                                        <td><?= htmlspecialchars($project['EndDate'] ?? 'Ongoing') ?></td>
                                        <td>
                                            <span class="status-badge status-<?= htmlspecialchars($project['Status']) ?>">
                                                <?= htmlspecialchars($project['Status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-gray-500">This staff member is not currently assigned to any projects and has no recorded project history.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
