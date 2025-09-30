    <?php 
    include("db.php");
    session_start();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');

        // Allowed statuses
        $allowedStatuses = ['Pending', 'Ongoing', 'Completed', 'Dismissed'];
        if (!in_array($newStatus, $allowedStatuses)) {
            echo "❌ Invalid status received: $newStatus";
            exit;
        }

        // Fetch order info
        $stmt = $conn->prepare("SELECT status, stock_adjusted FROM Orders WHERE Orders_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();

        if (!$order) {
            echo "❌ Order not found for ID: $orderId";
            exit;
        }

        $oldStatus = $order['status'];
        $stockAdjusted = $order['stock_adjusted'];

        echo "🔎 Current Order: ID=$orderId, OldStatus=$oldStatus, NewStatus=$newStatus, StockAdjusted=$stockAdjusted<br>";

        // Fetch order items
        $stmtItems = $conn->prepare("SELECT Product_id, quantity FROM OrderItems WHERE Orders_id = ?");
        $stmtItems->bind_param("i", $orderId);
        $stmtItems->execute();
        $itemsResult = $stmtItems->get_result();

        /**
         * STOCK HANDLING LOGIC
         */
        if ($oldStatus === 'Dismissed' && $newStatus !== 'Dismissed' && $stockAdjusted == 1) {
            echo "➡️ Deducting stock because order was Dismissed → $newStatus<br>";
            while ($item = $itemsResult->fetch_assoc()) {
                echo "   - Deducting {$item['quantity']} from Product {$item['Product_id']}<br>";
                $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock - ? WHERE Product_id = ?");
                $stmtUpdate->bind_param("ii", $item['quantity'], $item['Product_id']);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
            $stockAdjusted = 0;
        } elseif ($newStatus === 'Dismissed' && $stockAdjusted == 0) {
            echo "➡️ Restoring stock because order changed to Dismissed<br>";
            while ($item = $itemsResult->fetch_assoc()) {
                echo "   - Restoring {$item['quantity']} back to Product {$item['Product_id']}<br>";
                $stmtUpdate = $conn->prepare("UPDATE Product SET stock = stock + ? WHERE Product_id = ?");
                $stmtUpdate->bind_param("ii", $item['quantity'], $item['Product_id']);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
            $stockAdjusted = 1;
        } else {
            echo "ℹ️ No stock changes needed for this transition.<br>";
        }

        $stmtItems->close();

        // Update order status & stock flag
        $stmtUpdate = $conn->prepare("UPDATE Orders SET status = ?, stock_adjusted = ? WHERE Orders_id = ?");
        $stmtUpdate->bind_param("sii", $newStatus, $stockAdjusted, $orderId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        echo "✅ Order #$orderId updated: $oldStatus → $newStatus (StockAdjusted=$stockAdjusted)";
    }
    ?>
