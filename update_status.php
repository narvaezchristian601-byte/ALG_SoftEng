<?php
include("db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    $allowedStatuses = ['Pending', 'Completed', 'Ongoing', 'Dismissed'];
    if ($orderId > 0 && in_array($newStatus, $allowedStatuses)) {

        // Get old status AND the current stock_adjusted flag
        $stmtOld = $conn->prepare("SELECT status, stock_adjusted FROM Orders WHERE Orders_id = ?");
        $stmtOld->bind_param("i", $orderId);
        $stmtOld->execute();
        $stmtOld->bind_result($oldStatus, $oldStockAdjusted);
        $stmtOld->fetch();
        $stmtOld->close();

        if ($oldStatus === $newStatus) {
            echo "Status is already $newStatus. No changes made.";
            exit;
        }

        $conn->begin_transaction();
        $success = false;
        $message = "Order #$orderId status updated from $oldStatus to $newStatus.";

        try {
            // Assume no stock change is needed initially
            $newStockAdjusted = $oldStockAdjusted;

            // Check if stock should be deducted
            if ($newStatus === "Completed" && $oldStockAdjusted == 0) {
                // Deduct stock and set flag
                $message .= " Stock reduced.";
                $newStockAdjusted = 1;
            } 
            // Check if stock should be restored
            elseif (($newStatus !== "Completed" || $newStatus === "Dismissed") && $oldStockAdjusted == 1) {
                // Restore stock and clear flag
                $message .= " Stock restored.";
                $newStockAdjusted = 0;
            }

            // If a stock adjustment is required, get order items and apply the change
            if ($oldStockAdjusted !== $newStockAdjusted) {
                $stmtItems = $conn->prepare("SELECT Product_id, quantity FROM OrderItems WHERE Orders_id = ?");
                $stmtItems->bind_param("i", $orderId);
                $stmtItems->execute();
                $result = $stmtItems->get_result();
                
                $operator = ($newStockAdjusted == 1) ? '-' : '+';
                
                while ($row = $result->fetch_assoc()) {
                    $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock {$operator} ? WHERE Product_id = ?");
                    $stmtUpdate->bind_param("ii", $row['quantity'], $row['Product_id']);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }
                $stmtItems->close();
            }

            // Update order status and the stock_adjusted flag
            $stmt = $conn->prepare("UPDATE Orders SET status = ?, stock_adjusted = ? WHERE Orders_id = ?");
            $stmt->bind_param("sii", $newStatus, $newStockAdjusted, $orderId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $success = true;

        } catch (Exception $e) {
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