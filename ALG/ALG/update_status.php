<?php
include("db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    // Allowed statuses only (safety)
    $allowedStatuses = ['Pending', 'Completed', 'Ongoing', 'Dismissed'];
    if ($orderId > 0 && in_array($newStatus, $allowedStatuses)) {
        $stmt = $conn->prepare("UPDATE Orders SET status = ? WHERE Orders_id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);

        if ($stmt->execute()) {
            echo "Order #$orderId status updated to $newStatus.";
        } else {
            echo "Failed to update status.";
        }

        $stmt->close();
    } else {
        echo "Invalid order or status.";
    }
} else {
    echo "Invalid request.";
}
?>
