<?php
// update_mobile_status.php - Handles Mobile Status Updates (3.3 - 3.4)

include("db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    // Allowed statuses for *this* mobile script
    $allowedStatuses = ['Ongoing', 'Completed'];
    if (!in_array($newStatus, $allowedStatuses)) {
        echo "  Invalid status received: $newStatus";
        exit;
    }

    // 1. Fetch current order info
    $stmt = $conn->prepare("SELECT status, stock_adjusted FROM Orders WHERE Orders_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo "  Order not found for ID: $orderId";
        exit;
    }

    $oldStatus = $order['status'];
    $stockAdjusted = $order['stock_adjusted'];

    // 2. Determine if the transition is valid
    if ($newStatus === 'Ongoing' && $oldStatus !== 'Pending') {
        echo "  Cannot move from $oldStatus to Ongoing. Must be Pending.";
        exit;
    }
    if ($newStatus === 'Completed' && $oldStatus !== 'Ongoing') {
        echo "  Cannot move from $oldStatus to Completed. Must be Ongoing first.";
        exit;
    }
    
    // Default to the existing flag unless changed below
    $newStockAdjusted = $stockAdjusted;

    /**
     * STOCK DEDUCTION LOGIC (Only runs when moving to Completed, and only if stock was NOT already adjusted)
     */
    if ($newStatus === 'Completed' && $stockAdjusted == 0) {
        $conn->begin_transaction();
        try {
            // Fetch order items only if stock deduction is needed
            $stmtItems = $conn->prepare("SELECT Product_id, quantity FROM OrderItems WHERE Orders_id = ?");
            $stmtItems->bind_param("i", $orderId);
            $stmtItems->execute();
            $itemsResult = $stmtItems->get_result();
            
            // Deduct stock for each item
            while ($item = $itemsResult->fetch_assoc()) {
                // Check stock availability (optional, but safer)
                $stmtCheck = $conn->prepare("SELECT Stock FROM Product WHERE Product_id = ?");
                $stmtCheck->bind_param("i", $item['Product_id']);
                $stmtCheck->execute();
                $stmtCheck->bind_result($currentStock);
                $stmtCheck->fetch();
                $stmtCheck->close();

                if ($currentStock < $item['quantity']) {
                     throw new Exception("Insufficient stock for Product ID " . $item['Product_id']);
                }

                // Deduct stock
                $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock - ? WHERE Product_id = ?");
                $stmtUpdate->bind_param("ii", $item['quantity'], $item['Product_id']);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
            $itemsResult->free();
            $stmtItems->close();
            
            $newStockAdjusted = 1; // Mark as adjusted/deducted
            $message = "Stock reduced.";

            // 3. Update order status & stock flag
            $stmtUpdate = $conn->prepare("UPDATE Orders SET status = ?, stock_adjusted = ? WHERE Orders_id = ?");
            $stmtUpdate->bind_param("sii", $newStatus, $newStockAdjusted, $orderId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            
            $conn->commit();
            echo "  Order #$orderId updated: $oldStatus → $newStatus ($message)";
            
        } catch (Exception $e) {
            $conn->rollback();
            echo "  Transaction Failed: " . $e->getMessage();
            exit;
        }

    } 
    // This case handles moving from Pending to Ongoing or moving to Completed if stock was already adjusted 
    // (though in a typical flow stockAdjusted=1 only happens on moving to Completed)
    else {
        // Just update the status, no stock changes needed
        $stmtUpdate = $conn->prepare("UPDATE Orders SET status = ? WHERE Orders_id = ?");
        $stmtUpdate->bind_param("si", $newStatus, $orderId);
        
        if ($stmtUpdate->execute()) {
            echo "  Order #$orderId status updated: $oldStatus → $newStatus";
        } else {
            echo "  Database Update Failed: " . $stmtUpdate->error;
        }
        $stmtUpdate->close();
    }

} else {
    echo "  Invalid request method.";
}
?>