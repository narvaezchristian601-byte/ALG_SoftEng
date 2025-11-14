<?php
include("../../db.php");
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['product_id'])) {
    header("Location: products.php");
    exit();
}

$product_id = intval($_POST['product_id']);

// Validate if product exists
$check = $conn->prepare("SELECT Product_id FROM Product WHERE Product_id = ?");
$check->bind_param("i", $product_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows === 0) {
    echo "<script>alert('Product not found.'); window.location.href='products.php';</script>";
    exit();
}

$stmt = $conn->prepare("DELETE FROM Product WHERE Product_id = ?");
$stmt->bind_param("i", $product_id);

if ($stmt->execute()) {
    echo "<script>alert('Product removed successfully.'); window.location.href='products.php';</script>";
} else {
    echo "<script>alert('Error deleting product: " . htmlspecialchars($stmt->error) . "'); window.location.href='products.php';</script>";
}

$stmt->close();
$conn->close();
?>
