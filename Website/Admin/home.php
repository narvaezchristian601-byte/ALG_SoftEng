<?php
session_start();
include("../../db.php");

// ✅ Session check
if (!isset($_SESSION['Staff_id'])) {
   echo '<script>
      alert("Please login first.");
      window.location.href = "../login.php";
  </script>';
  exit();
}


// ✅ Fetch totals
$sql = "SELECT
    (SELECT COUNT(*) FROM supplier) AS total_suppliers,
    (SELECT COUNT(*) FROM product) AS total_products,
    (SELECT COUNT(*) FROM customers) AS total_customers";
$result = $conn->query($sql);
$totals = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff | Home</title>

  <style>
    body {
      margin: 0;
      font-family: "Archivo Black", sans-serif;
      background-color: #888;
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

    .nav-logo {
      display: flex;
      align-items: center;
    }

    .nav-logo .logo {
      height: 75px;
      width: auto;
      margin-right: 10px;
    }

    .nav-links a {
      font-size: 25px;
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

    @media (max-width: 768px) {
      .navbar {
        flex-direction: column;
        padding: 15px 20px;
      }

      .nav-links {
        margin-top: 10px;
      }

      .nav-links a {
        display: inline-block;
        margin: 5px;
      }
    }

    /* ======== MAIN CONTENT ======== */
    main {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-top: 40px;
      text-align: center;
    }

    /* Hero Section */
    .hero {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 10px;
      padding: 30px;
      max-width: 80%;
      color: #fff;
      font-size: 2em;
      font-weight: bold;
      letter-spacing: 1px;
    }

    /* Dashboard Summary Cards */
    .dashboard-cards {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 30px;
      margin-top: 50px;
    }

    .card {
      background: #f4f4f4;
      border-radius: 15px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
      padding: 25px 40px;
      text-align: center;
      min-width: 220px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 15px rgba(0,0,0,0.3);
    }

    .card h2 {
      font-size: 1.2rem;
      color: #222;
      margin-bottom: 10px;
    }

    .card p {
      font-size: 2rem;
      font-weight: bold;
      color: #007bff;
      margin: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .hero {
        font-size: 1.5em;
        padding: 20px;
      }

      .dashboard-cards {
        flex-direction: column;
        align-items: center;
      }

      .card {
        width: 80%;
      }
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <header class="navbar">
    <div class="nav-logo">
      <img src="../../images/alg-logo-black.png" alt="ALG Logo" class="logo">
    </div>
    <nav class="nav-links">
        <a href="home.php" class="active">Home</a>
        <a href="products.php">Inventory</a>
        <a href="projects.php">Projects</a>
        <a href="staff.php">Staff</a>
    </nav>
    <div class="nav-right">
      <a href="../login.php" class="logout">
        <img src="../../images/log-out.svg" alt="Logout" class="logout-icon">
      </a>
    </div>
  </header>

  <!-- Main -->
  <main>
    <div class="hero">
      Your shelter,<br>our responsibility
    </div>

    <section class="dashboard-cards">
      <div class="card">
        <h2>Total Suppliers</h2>
        <p><?php echo $totals['total_suppliers']; ?></p>
      </div>
      <div class="card">
        <h2>Total Products</h2>
        <p><?php echo $totals['total_products']; ?></p>
      </div>
      <div class="card">
        <h2>Total Customers</h2>
        <p><?php echo $totals['total_customers']; ?></p>
      </div>
    </section>
  </main>

</body>
</html>
