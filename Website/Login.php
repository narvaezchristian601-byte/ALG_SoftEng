<?php
session_start();
include("../db.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = $_POST['email'];
  $password = $_POST['password'];

  $sql = "SELECT * FROM Staff WHERE Email = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // For now, direct plain password check (if you haven't hashed yet)
    if ($password === $user['Password']) {
      $_SESSION['Staff_id'] = $user['Staff_id'];
      $_SESSION['Name'] = $user['Name'];
      $_SESSION['Email'] = $user['Email'];
      $_SESSION['Role'] = $user['Role'];

      if ($user['Role'] === 'Admin') {
        header("Location: ../Website/Admin/home.php");
      } else {
        header("Location: ../Website/Staff/home.php");
      }
      exit();
    } else {
      echo "<script>alert('Incorrect password.'); window.history.back();</script>";
    }
  } else {
    echo "<script>alert('No account found with that email.'); window.history.back();</script>";
  }

  $stmt->close();
  $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ALG Enterprises - Login</title>

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: url("../images/alg-background.png") center/cover no-repeat;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      overflow: hidden;
    }

    .overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.55);
      z-index: 0;
    }

    .login-container {
      position: relative;
      z-index: 1;
      background: rgba(17, 17, 17, 0.75);
      padding: 60px 80px;
      border-radius: 20px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 400px;
      max-width: 90%;
    }

    .avatar {
      background-color: #fff;
      width: 120px;
      height: 120px;
      border-radius: 50%;
      margin-bottom: 30px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .avatar img {
      width: 70px;
      height: 70px;
      object-fit: contain;
    }

    form {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
      width: 100%;
    }

    label {
      align-self: flex-start;
      font-weight: bold;
      color: #fff;
      margin-left: 10px;
      font-size: 16px;
    }

    input {
      width: 100%;
      padding: 14px;
      border: none;
      border-radius: 5px;
      background: #d9d9d9;
      font-size: 16px;
      color: #000;
    }

    input:focus {
      outline: 2px solid #4f9bd2;
    }

    .password-wrapper {
      position: relative;
      width: 100%;
    }

    .password-wrapper img {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      width: 24px;
      height: 24px;
      cursor: pointer;
      opacity: 0.8;
      transition: opacity 0.2s ease;
    }

    .password-wrapper img:hover {
      opacity: 1;
    }

    .login-btn {
      width: 200px;
      padding: 14px;
      border: none;
      border-radius: 50px;
      background: #4f9bd2;
      color: #fff;
      font-size: 18px;
      cursor: pointer;
      font-weight: bold;
      transition: 0.3s ease;
    }

    .login-btn:hover {
      background: #3a84b8;
    }
  </style>
</head>

<body>
  <div class="overlay"></div>

  <div class="login-container">
    <div class="avatar">
      <img src="../images/generic-avatar.svg" alt="User Icon">
    </div>

    <form action="login.php" method="POST">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required>

      <label for="password">Password</label>
      <div class="password-wrapper">
        <input type="password" id="password" name="password" required>
        <!-- Default eye icon (hidden state) -->
        <img src="../images/eye.svg" alt="Toggle Password" id="togglePassword">
      </div>
      <button type="submit" class="login-btn">Login</button>
    </form>
  </div>

  <script>
    // Password visibility toggle with icon swap
    const togglePassword = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    togglePassword.addEventListener("click", () => {
      const isHidden = password.getAttribute("type") === "password";
      password.setAttribute("type", isHidden ? "text" : "password");
      togglePassword.src = isHidden ? "../images/eye-off.svg" : "../images/eye.svg";
    });
  </script>
</body>
</html>
