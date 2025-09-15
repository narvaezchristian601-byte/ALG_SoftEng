<?php
include("db.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['eventName'] ?? '';
    $service = $_POST['eventTitle'] ?? '';
    $date = $_POST['eventDate'] ?? '';

    if (!empty($name) && !empty($service) && !empty($date)) {
        $stmt = $conn->prepare("INSERT INTO service_sched (name, service, date) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $service, $date);

        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error";
        }
    } else {
        echo "missing_fields";
    }
}
?>
