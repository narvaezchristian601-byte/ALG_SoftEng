<?php
session_start();
include("../../db.php");

if (!isset($_SESSION['Staff_id'])) {
  echo '<script>
      alert("Please login first.");
      window.location.href = "../login.php";
  </script>';
  exit();
}

// ======== HANDLE AJAX PAGINATION REQUEST ========
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
  $limit = 5;
  $offset = ($page - 1) * $limit;

  $query = "
  SELECT 
    Orders.Orders_id,
    Customers.Name AS ClientName,
    Services.Name AS RoofingService,
    Orders.schedule_date AS CompletionDate,
    Orders.status AS ClientFeedback
  FROM Orders
  JOIN Customers ON Orders.customer_id = Customers.customer_id
  JOIN Services ON Orders.Services_id = Services.Services_id
  ORDER BY Orders.schedule_date DESC
  LIMIT $limit OFFSET $offset";

  $result = $conn->query($query);
  $projects = [];
  while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
  }

  echo json_encode($projects);
  exit();
}

// ======== HANDLE EXCEL EXPORT ========
if (isset($_GET['export']) && $_GET['export'] == '1') {
  header("Content-Type: application/vnd.ms-excel; charset=utf-8");
  header("Content-Disposition: attachment; filename=ALG_Projects_Export.xls");
  header("Pragma: no-cache");
  header("Expires: 0");

  // UTF-8 BOM for Excel
  echo "\xEF\xBB\xBF";

  $query = "
  SELECT 
    Orders.Orders_id AS 'Project ID',
    Customers.Name AS 'Client Name',
    Services.Name AS 'Roofing Service',
    Orders.schedule_date AS 'Completion Date',
    Orders.status AS 'Client Feedback'
  FROM Orders
  JOIN Customers ON Orders.customer_id = Customers.customer_id
  JOIN Services ON Orders.Services_id = Services.Services_id
  ORDER BY Orders.schedule_date DESC";

  $result = $conn->query($query);

  echo "<table border='1'>";
  echo "<tr style='background-color:#d9d9d9;font-weight:bold;'>
          <th>Project ID</th>
          <th>Client Name</th>
          <th>Roofing Service</th>
          <th>Completion Date</th>
          <th>Client Feedback</th>
        </tr>";

  while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['Project ID']}</td>
            <td>{$row['Client Name']}</td>
            <td>{$row['Roofing Service']}</td>
            <td>{$row['Completion Date']}</td>
            <td>{$row['Client Feedback']}</td>
          </tr>";
  }

  echo "</table>";
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projects | ALG Staff</title>
  <style>
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: #a6a6a6;
      overflow-x: hidden;
    }

    /* ======== NAVBAR ======== */
    .navbar {
      width: 100%;
      background: #615e5e;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 40px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      box-sizing: border-box;
    }

    .nav-logo img {
      height: 55px;
      width: auto;
    }

    .nav-links a {
      color: white;
      margin: 0 15px;
      text-decoration: none;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 20px;
      transition: 0.3s;
    }

    .nav-links a.active {
      background: #d9d9d9;
      color: black;
    }

    .nav-links a:hover {
      background: rgba(255,255,255,0.2);
    }

    .nav-right img {
      width: 28px;
      height: 28px;
      filter: invert(1);
      cursor: pointer;
    }

    main {
      padding: 40px 0;
      display: flex;
      justify-content: center;
    }

    .project-container {
      background: #f4f4f4;
      width: 90%;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      padding: 30px 40px;
    }

    h1 {
      color: #111;
      font-size: 2rem;
      margin-bottom: 15px;
    }

    /* ======== PROJECT CONTROLS ======== */
    .project-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .left-controls button {
      background: none;
      border: none;
      font-size: 1rem;
      color: #333;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: color 0.2s;
    }

    .left-controls button:hover {
      color: #007bff;
    }

    .right-controls {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .project-btn, .print-btn {
      padding: 8px 16px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .project-btn {
      background: #d9d9d9;
      color: #000;
      text-decoration: none;
    }

    .project-btn:hover {
      background: #c6c6c6;
    }

    .print-btn {
      background: #4f9bd2;
      color: white;
    }

    .print-btn:hover {
      background: #3f8bc0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
    }

    th, td {
      padding: 12px 16px;
      border-bottom: 1px solid #ddd;
      text-align: left;
      color: #333;
    }

    th {
      background-color: #f9f9f9;
      color: #111;
      font-weight: bold;
    }

    /* ======== PAGINATION ======== */
    .pagination {
      display: flex;
      justify-content: flex-end;
      padding: 10px 0;
      color: #555;
      font-size: 0.9rem;
      gap: 15px;
    }

    .pagination button {
      background: none;
      border: none;
      color: #007bff;
      cursor: pointer;
      font-weight: 600;
    }

    .pagination button:disabled {
      color: #aaa;
      cursor: default;
    }
  </style>
</head>
<body>
  <!-- ======== NAVBAR ======== -->
  <header class="navbar">
    <div class="nav-logo">
      <img src="../../images/alg-logo-black.png" alt="ALG Logo">
    </div>

    <nav class="nav-links">
      <a href="home.php">Home</a>
      <a href="projects.php" class="active">Projects</a>
      <a href="profile.php">Staff</a>
    </nav>

    <div class="nav-right">
      <a href="../login.php" title="Logout">
        <img src="../../images/log-out.svg" alt="Logout">
      </a>
    </div>
  </header>

  <!-- ======== MAIN CONTENT ======== -->
  <main>
    <div class="project-container">
      <h1>Schedule</h1>

      <div class="project-controls">
        <div class="left-controls">
          <button><img src="../../images/calendar.svg" alt="Calendar" style="height:18px;"> View Calendar</button>
        </div>
        <div class="right-controls">
          <a href="projects-logs.php" class="project-btn">Project Logs</a>
          <button class="print-btn" onclick="window.open('?export=1', '_blank')">
            <img src="../../images/print.svg" alt="Print" style="height:18px;"> Print
          </button>
        </div>
      </div>

      <table id="projectTable">
        <thead>
          <tr>
            <th>Project ID</th>
            <th>Client Name</th>
            <th>Roofing Service</th>
            <th>Completion Date</th>
            <th>Client Feedback</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <!-- Rows loaded by AJAX -->
        </tbody>
      </table>

      <div class="pagination">
        <button id="prevBtn" disabled>← Previous</button>
        <button id="nextBtn">Next →</button>
      </div>
    </div>
  </main>

  <script>
    let currentPage = 1;

    function loadProjects(page = 1) {
      fetch(`projects.php?ajax=1&page=${page}`)
        .then(res => res.json())
        .then(data => {
          const tableBody = document.getElementById('tableBody');
          tableBody.innerHTML = '';

          if (data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No data available</td></tr>`;
          } else {
            data.forEach(item => {
              tableBody.innerHTML += `
                <tr>
                  <td>${item.Orders_id}</td>
                  <td>${item.ClientName}</td>
                  <td>${item.RoofingService}</td>
                  <td>${item.CompletionDate}</td>
                  <td>${item.ClientFeedback}</td>
                </tr>`;
            });
          }

          document.getElementById('prevBtn').disabled = (page === 1);
          document.getElementById('nextBtn').disabled = (data.length < 5);
        });
    }

    document.getElementById('prevBtn').addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        loadProjects(currentPage);
      }
    });

    document.getElementById('nextBtn').addEventListener('click', () => {
      currentPage++;
      loadProjects(currentPage);
    });

    // Load first page
    loadProjects();
  </script>
</body>
</html>
