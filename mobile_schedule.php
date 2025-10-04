<?php
// mobile_schedule.php - Mobile Interface for Technicians (3.1 & 3.2)

include("db.php");
session_start();

// 1. Fetch orders scheduled for TODAY
$today = date('Y-m-d');

$sql = "
    SELECT 
        o.Orders_id,
        o.schedule_date,
        o.status,
        c.Name AS CustomerName,
        c.Address AS CustomerAddress,
        c.PhoneNumber AS CustomerPhone,
        s.Name AS ServiceName
    FROM Orders o
    LEFT JOIN Customers c ON o.customer_id = c.customer_id
    LEFT JOIN Services s ON o.Services_id = s.Services_id
    WHERE DATE(o.schedule_date) = ?
    AND o.status IN ('Pending', 'Ongoing') -- Only show jobs that need action today
    ORDER BY o.schedule_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$jobs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Technician Mobile Schedule</title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; padding:10px; background:#f4f6f9; }
        h2 { color:#333; text-align:center; margin-bottom: 20px; }
        .job-card { 
            background:white; border-radius:8px; padding:15px; margin-bottom:15px; 
            box-shadow:0 2px 4px rgba(0,0,0,.1); border-left: 5px solid #007bff;
        }
        .job-card p { margin: 5px 0; font-size: 0.9em; }
        .job-card strong { color:#007bff; }
        .status-pending { border-left-color: orange; }
        .status-ongoing { border-left-color: green; }
        
        .update-form { margin-top: 10px; text-align: center; }
        .update-form button { 
            padding: 10px 15px; border:none; border-radius:5px; cursor:pointer; 
            color:white; font-weight:bold; width: 100%; box-sizing: border-box; margin-top: 5px;
        }
        .start-btn { background:#28a745; } /* Green */
        .complete-btn { background:#007bff; } /* Blue */
        .no-jobs { text-align: center; padding: 20px; color: #888; }
        
        .modal { 
            display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
            background:rgba(0,0,0,.6); justify-content:center; align-items:center; z-index:1000;
        }
        .modal-content { 
            background:#fff; padding:20px; border-radius:10px; width:80%; max-width:400px; 
            text-align: center;
        }
        .modal-content button { margin-top: 10px; width: 100%; }
    </style>
</head>
<body>

    <h2>Today's Jobs (<?php echo $today; ?>)</h2>

    <?php if (count($jobs) > 0): ?>
        <?php foreach ($jobs as $job): ?>
            <div class="job-card status-<?php echo strtolower($job['status']); ?>">
                <p><strong>Order ID:</strong> #<?php echo $job['Orders_id']; ?></p>
                <p><strong>Service:</strong> <?php echo $job['ServiceName']; ?></p>
                <p><strong>Customer:</strong> <?php echo $job['CustomerName']; ?></p>
                <p><strong>Address:</strong> <?php echo $job['CustomerAddress']; ?></p>
                <p><strong>Phone:</strong> <a href="tel:<?php echo $job['CustomerPhone']; ?>"><?php echo $job['CustomerPhone']; ?></a></p>
                <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($job['schedule_date'])); ?></p>
                <p><strong>Status:</strong> <span style="font-weight:bold;"><?php echo $job['status']; ?></span></p>

                <div class="update-form">
                    <?php if ($job['status'] === 'Pending'): ?>
                        <button class="start-btn" onclick="openModal(<?php echo $job['Orders_id']; ?>, 'Ongoing')">Start Job</button>
                    <?php elseif ($job['status'] === 'Ongoing'): ?>
                        <button class="complete-btn" onclick="openModal(<?php echo $job['Orders_id']; ?>, 'Completed')">Mark Complete</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-jobs">
            <p>No jobs scheduled for today or all jobs are completed/dismissed.</p>
        </div>
    <?php endif; ?>

    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Status Change</h3>
            <p>Are you sure you want to change the status to <strong id="modalNewStatus"></strong>?</p>
            <button id="confirmBtn" onclick="updateStatus()">Confirm</button>
            <button onclick="closeModal()" style="background:#888;">Cancel</button>
            <p id="modalMessage" style="color:red; margin-top: 10px;"></p>
        </div>
    </div>

    <script>
        let currentOrderId = null;
        let targetStatus = null;

        function openModal(orderId, newStatus) {
            currentOrderId = orderId;
            targetStatus = newStatus;
            document.getElementById('modalNewStatus').textContent = targetStatus;
            document.getElementById('statusModal').style.display = 'flex';
            document.getElementById('modalMessage').textContent = '';
        }

        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        function updateStatus() {
            const messageElement = document.getElementById('modalMessage');
            messageElement.textContent = 'Updating...';
            
            fetch('update_mobile_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `order_id=${currentOrderId}&new_status=${targetStatus}`
            })
            .then(r => r.text())
            .then(res => {
                if (res.trim().startsWith(' ')) {
                    alert('Status updated successfully!');
                    window.location.reload(); // Reload the page to refresh job list
                } else {
                    messageElement.textContent = 'Update failed: ' + res.trim();
                }
            })
            .catch(err => {
                console.error(err);
                messageElement.textContent = 'A network error occurred.';
            });
        }
    </script>

</body>
</html>