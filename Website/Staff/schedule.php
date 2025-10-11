<?php
include '../../db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Schedule | ALG Enterprise</title>
    <link rel="stylesheet" href="css/staff-schedule.css">
    <script src="js/jquery.min.js"></script>
</head>
<body>
  <?php include '../css/navbar.php'; ?>

    <section class="schedule-container">
        <h1>Project Schedule</h1>
        <div id="schedule-list"></div>
        <div class="pagination">
            <button id="prevPage">Previous</button>
            <button id="nextPage">Next</button>
        </div>
    </section>

<script>
let currentPage = 1;
function loadSchedule(page) {
    $.ajax({
        url: 'schedule_data.php',
        type: 'GET',
        data: { page: page },
        success: function(data) {
            $('#schedule-list').html(data);
        }
    });
}
$('#nextPage').on('click', () => loadSchedule(++currentPage));
$('#prevPage').on('click', () => { if (currentPage > 1) loadSchedule(--currentPage); });
$(document).ready(() => loadSchedule(currentPage));
</script>
</body>
</html>
