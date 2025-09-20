<?php
include("db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    $allowedStatuses = ['Pending', 'Completed', 'Ongoing', 'Dismissed'];
    if ($orderId > 0 && in_array($newStatus, $allowedStatuses)) {
        
        // Get the current (old) status of the order.
        $stmtOld = $conn->prepare("SELECT status FROM Orders WHERE Orders_id = ?");
        $stmtOld->bind_param("i", $orderId);
        $stmtOld->execute();
        $stmtOld->bind_result($oldStatus);
        $stmtOld->fetch();
        $stmtOld->close();

        // Check if a change in status has actually occurred.
        if ($oldStatus === $newStatus) {
            echo "Status is already $newStatus. No changes made.";
            exit;
        }

        // Start a database transaction.
        $conn->begin_transaction();
        $success = false;
        $message = "Order #$orderId status updated from $oldStatus to $newStatus.";

        try {
            // Update the status of the order first.
            $stmt = $conn->prepare("UPDATE Orders SET status = ? WHERE Orders_id = ?");
            $stmt->bind_param("si", $newStatus, $orderId);
            $stmt->execute();
            $stmt->close();

            // Get product and quantity data for the order.
            $stmtItems = $conn->prepare("SELECT Product_id, quantity FROM OrderItems WHERE Orders_id = ?");
            $stmtItems->bind_param("i", $orderId);
            $stmtItems->execute();
            $result = $stmtItems->get_result();

            // === Stock adjustment logic ===
            // Deduct stock if the order is now completed and wasn't before.
            if ($newStatus === 'Completed' && $oldStatus !== 'Completed') {
                while ($row = $result->fetch_assoc()) {
                    $productId = $row['Product_id'];
                    $quantity = $row['quantity'];

                    $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock - ? WHERE Product_id = ?");
                    $stmtUpdate->bind_param("ii", $quantity, $productId);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }
                $message .= " Stock reduced.";
            } 
            // Restore stock if the order is no longer completed, but was before.
            elseif ($oldStatus === 'Completed' && $newStatus !== 'Completed') {
                while ($row = $result->fetch_assoc()) {
                    $productId = $row['Product_id'];
                    $quantity = $row['quantity'];

                    $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock + ? WHERE Product_id = ?");
                    $stmtUpdate->bind_param("ii", $quantity, $productId);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }
                $message .= " Stock restored.";
            }

            $stmtItems->close();
            
            // Commit the transaction if everything succeeded.
            $conn->commit();
            $success = true;

        } catch (Exception $e) {
            // Rollback the transaction on failure.
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
        }
        
        if ($success) {
            echo $message;
        } else {
            http_response_code(500);
            echo "Failed to update status. " . $message;
        }
    } else {
        http_response_code(400);
        echo "Invalid order or status.";
    }
} else {
    http_response_code(405);
    echo "Invalid request method.";
}
?>