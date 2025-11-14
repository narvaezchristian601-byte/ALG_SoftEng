<?php
include("../../db.php");
session_start();

// If form not submitted, block direct access
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: products.php");
    exit();
}

// Collect and sanitize input
$name           = trim($_POST['name'] ?? '');
$category_id    = intval($_POST['category_id'] ?? 0);
$supplier_id    = intval($_POST['supplier_id'] ?? 0);
$price          = floatval($_POST['price'] ?? 0);
$stock          = intval($_POST['stock'] ?? 0);
$reorder_level  = intval($_POST['reorder_level'] ?? 0);
$description    = trim($_POST['description'] ?? '');

if (empty($name) || $category_id <= 0 || $supplier_id <= 0 || $price <= 0) {
    echo "<script>alert('Please fill in all required fields properly.'); window.history.back();</script>";
    exit();
}

// Insert query
$stmt = $conn->prepare("
    INSERT INTO Product (Name, Category_id, Supplier_id, Price, Stock, Reorder_Level, Description)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("siidiss", $name, $category_id, $supplier_id, $price, $stock, $reorder_level, $description);

if ($stmt->execute()) {
    echo "<script>alert('Product added successfully!'); window.location.href='products.php';</script>";
} else {
    echo "<script>alert('Error adding product: " . htmlspecialchars($stmt->error) . "'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
?>
