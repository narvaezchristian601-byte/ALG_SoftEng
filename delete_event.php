<?php 
// delete_event.php - Handles Schedule Deletion (Changes Order Status to 'Dismissed')

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = intval($_POST['id'] ?? 0);

    if ($orderId > 0) {
        
        // CORE CHANGE: Using cURL to call update_status.php
        // This executes the safe stock reversal logic built previously.
        
        $postData = http_build_query([
            'order_id' => $orderId,
            'new_status' => 'Dismissed'
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'update_status.php'); 
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && strpos($response, '✅') !== false) {
            echo "success";
        } else {
            // Echo the full response for debugging if it failed
            echo "Stock Reversal Failed: " . $response;
        }

    } else {
        echo "Invalid Order ID for deletion.";
    }
} else {
    echo "Invalid request method.";
}
?>