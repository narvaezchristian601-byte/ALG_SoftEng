<?php
// create_staff.php

// Note: Assuming 'db.php' handles the database connection using $conn (mysqli object)
include("../../db.php");

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize and collect form data
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phoneNum = trim($_POST['phoneNum']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Will be hashed immediately
    $role = $_POST['role'];
    $position = trim($_POST['position']);

    // 2. Simple Validation
    if (empty($name) || empty($email) || empty($password) || empty($role) || empty($position)) {
        $message = "Error: Name, Email, Password, Role, and Position are required fields.";
        $message_type = 'error';
    } else {
        // 3. Hash the password for secure storage
        // Note: For real-world security, always use password_hash()!
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 4. Prepare the INSERT statement
        // Staff_id is auto-incremented and omitted from the column list.
        $stmt = $conn->prepare("INSERT INTO Staff (Name, address, PhoneNum, Email, Password, Role, Position) VALUES (?, ?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            $message = "Database prepare error: " . $conn->error;
            $message_type = 'error';
        } else {
            // Bind parameters (s = string)
            $stmt->bind_param("sssssss", $name, $address, $phoneNum, $email, $hashed_password, $role, $position);

            if ($stmt->execute()) {
                $message = "Success! Staff member **" . htmlspecialchars($name) . "** created successfully.";
                $message_type = 'success';
                // Clear inputs after successful submission
                $_POST = []; 
            } else {
                // Check for duplicate email error (usually error code 1062)
                if ($conn->errno == 1062) {
                    $message = "Error: The email address is already registered. Please use a unique email.";
                } else {
                    $message = "Error creating staff member: " . $stmt->error;
                }
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Staff | ALG Enterprises</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
    <style>
        :root {
            --primary-color: #5cb8e4;
            --secondary-color: #4a4a4a;
            --success-color: #4CAF50;
            --error-color: #f44336;
            --bg-light: #f9f9f9;
            --bg-dark: #d9d9d9;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 600px;
            background-color: var(--bg-light);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 14px;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input:focus, select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(92, 184, 228, 0.2);
            outline: none;
        }
        .btn-submit {
            width: 100%;
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.1s;
        }
        .btn-submit:hover {
            background-color: #479ecb;
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
        }
        .message.success {
            background-color: #E8F5E9;
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        .message.error {
            background-color: #FFEBEE;
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add New Staff Member</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="create_staff.php">

            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Email * (Unique)</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="position">Position *</label>
                <input type="text" id="position" name="position" required value="<?= htmlspecialchars($_POST['position'] ?? '') ?>" placeholder="e.g., Technician, Project Lead, Sales Executive">
            </div>

            <div class="form-group">
                <label for="role">Role *</label>
                <select id="role" name="role" required>
                    <option value="" disabled selected>Select Role</option>
                    <option value="Admin" <?= (($_POST['role'] ?? '') === 'Admin') ? 'selected' : '' ?>>Admin</option>
                    <option value="Staff" <?= (($_POST['role'] ?? '') === 'Staff') ? 'selected' : '' ?>>Staff</option>
                </select>
            </div>

            <div class="form-group">
                <label for="phoneNum">Phone Number</label>
                <input type="text" id="phoneNum" name="phoneNum" value="<?= htmlspecialchars($_POST['phoneNum'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>

            <button type="submit" class="btn-submit">Add Staff Member</button>
        </form>
    </div>
</body>
</html>