<?php
include '../../db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Logs | ALG Enterprise</title>
    <link rel="stylesheet" href="css/staff-projectlogs.css">
    <script src="js/jquery.min.js"></script>
</head>
<body>
    <?php include '../css/navbar.php'; ?>

    <section class="logs-container">
        <h1>Project Logs</h1>
        <div id="log-list"></div>
        <div class="pagination">
            <button id="prevLog">Previous</button>
            <button id="nextLog">Next</button>
        </div>
    </section>

<script>
let logPage = 1;
function loadLogs(page) {
    $.ajax({
        url: 'logs_data.php',
        type: 'GET',
        data: { page: page },
        success: function(data) {
            $('#log-list').html(data);
        }
    });
}
$('#nextLog').on('click', () => loadLogs(++logPage));
$('#prevLog').on('click', () => { if (logPage > 1) loadLogs(--logPage); });
$(document).ready(() => loadLogs(logPage));
</script>
</body>
</html>
