<?php
// delete_event.php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'invalid_method';
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo 'missing_id';
    exit;
}

$id = (int) $_POST['id'];

$stmt = $conn->prepare("DELETE FROM service_sched WHERE sched_id = ?");
if (!$stmt) {
    echo 'prepare_error';
    exit;
}
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo 'success';
} else {
    echo 'error: ' . $stmt->error;
}
$stmt->close();
?>
