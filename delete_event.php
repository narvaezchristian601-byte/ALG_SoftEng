<?php 
// delete_event.php
include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        // Status to Dismissed
        $stmt = $conn->prepare("UPDATE Orders SET status = 'Dismissed' WHERE Orders_id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "DB error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Invalid ID";
    }
} else {
    echo "Invalid request";
}
?>
