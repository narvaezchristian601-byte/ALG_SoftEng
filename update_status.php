<?php
include("db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    $allowedStatuses = ['Pending', 'Completed', 'Ongoing', 'Dismissed'];
    if ($orderId > 0 && in_array($newStatus, $allowedStatuses)) {

        $stmtOld = $conn->prepare("SELECT status FROM Orders WHERE Orders_id = ?");
        $stmtOld->bind_param("i", $orderId);
        $stmtOld->execute();
        $stmtOld->bind_result($oldStatus);
        $stmtOld->fetch();
        $stmtOld->close();

        $stmt = $conn->prepare("UPDATE Orders SET status = ? WHERE Orders_id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);

        if ($stmt->execute()) {
            echo "Order #$orderId status updated from $oldStatus to $newStatus.";
            // Deducts stock from product if status changed to Completed
            if ($newStatus === "Completed" && $oldStatus !== "Completed") {
                $stmtItems = $conn->prepare("SELECT Product_id, quantity FROM OrderItems WHERE Orders_id = ?");
                $stmtItems->bind_param("i", $orderId);
                $stmtItems->execute();
                $result = $stmtItems->get_result();

                while ($row = $result->fetch_assoc()) {
                    $productId = $row['Product_id'];
                    $quantity = $row['quantity'];

                    $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock - ? WHERE Product_id = ?");
                    $stmtUpdate->bind_param("ii", $quantity, $productId);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }

                $stmtItems->close();
                echo " Stock reduced.";
                // Adds stock back to product if status changed from Completed to something else
            } elseif ($oldStatus === "Completed" && $newStatus !== "Completed") {
                $stmtItems = $conn->prepare("SELECT Product_id, quantity FROM OrderItems WHERE Orders_id = ?");
                $stmtItems->bind_param("i", $orderId);
                $stmtItems->execute();
                $result = $stmtItems->get_result();

                while ($row = $result->fetch_assoc()) {
                    $productId = $row['Product_id'];
                    $quantity = $row['quantity'];

                    $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock + ? WHERE Product_id = ?");
                    $stmtUpdate->bind_param("ii", $quantity, $productId);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }

                $stmtItems->close();
                echo " Stock restored.";
            }

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
