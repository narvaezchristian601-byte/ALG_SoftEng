<?php
// calendar.php
include("db.php");

// Fetch events from Orders table
$events = [];
$sql = "
    SELECT 
        o.Orders_id,
        o.schedule_date,
        o.status,
        s.Name AS ServiceName,
        c.Name AS CustomerName
    FROM Orders o
    LEFT JOIN Services s ON o.Services_id = s.Services_id
    LEFT JOIN Customers c ON o.Customer_id = c.Customer_id
    WHERE o.schedule_date IS NOT NULL
    ORDER BY o.schedule_date ASC
";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $statusClass = strtolower($row['status'] ?? 'pending');
    
    $events[] = [
        "id" => (int)$row['Orders_id'],
        "title" => ($row['CustomerName'] ?? 'N/A') . ' - ' . ($row['ServiceName'] ?? 'Service') . ' (' . ($row['status'] ?? 'N/A') . ')',
        "start" => $row['schedule_date'],
        "extendedProps" => [
            "name" => $row['CustomerName'] ?? 'N/A',
            "service" => $row['ServiceName'] ?? 'Service',
            "status" => $row['status'] ?? 'N/A'
        ],
        "className" => 'status-' . $statusClass
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scheduling Calendar</title>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<style>
  body { font-family: Arial, sans-serif; margin:0; padding:0; background:#f4f6f9; overflow-x:hidden; }
  header { background:#007bff; color:white; padding:15px; text-align:center; }
  nav { background:#0056b3; padding:10px; text-align:center; }
  nav a { color:white; text-decoration:none; margin:0 15px; font-weight:bold; }
  nav a:hover { text-decoration:underline; }
  footer { background:#007bff; color:white; text-align:center; padding:10px; position:relative; bottom:0; width:100%; }
  #calendar { max-width:1000px; margin:20px auto; padding:15px; background:white; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.15); }

  /* Modal */
  #eventModal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); justify-content:center; align-items:center; z-index:9999; }
  #eventModal .modal-content { background:#fff; padding:20px; border-radius:8px; width:360px; box-shadow:0 4px 10px rgba(0,0,0,.2); }
  #eventModal h3 { margin-top:0; color:#2c3e50; }
  #eventModal button { background:#007bff; color:white; padding:8px; margin-top:10px; border:none; border-radius:5px; cursor:pointer;}
  #eventModal button:hover{ background:#0451a3; }
</style>
</head>
<body>
  <header><h1>ALG Roofing Products & Services System</h1></header>

  <nav>
      <a href="index.php">Home</a>
      <a href="orders.php">Orders</a>
      <a href="products.php">Product Search</a>
      <a href="supplier_list.php">Supplier List</a>
      <a href="price_list.php">Price List</a>
      <a href="orderform.php">Order Form</a>
      <a href="calendar.php">Schedule Calendar</a>
      <a href="reports.php">Reports</a>
  </nav>

  <main>
    <div id="calendar"></div>

    <!-- Modal for event details -->
    <div id="eventModal">
      <div class="modal-content">
        <h3>Schedule Details</h3>
        <p><strong>Customer:</strong> <span id="modalCustomer"></span></p>
        <p><strong>Service:</strong> <span id="modalService"></span></p>
        <p><strong>Date:</strong> <span id="modalDate"></span></p>
        <p><strong>Status:</strong> <span id="modalStatus"></span></p>
        <div style="display:flex; gap:8px;">
          <button type="button" onclick="removeEvent()">Remove</button>
          <button type="button" onclick="closeModal()" style="background:#888;">Close</button>
        </div>
      </div>
    </div>
  </main>

  <footer><p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p></footer>

<script>
let calendar;              
let currentEvent = null;   

document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('calendar');

  calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    selectable: true,
    editable: false,
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek'
    },

    eventClick: function(info) {
      currentEvent = info.event;
      document.getElementById('modalCustomer').textContent = info.event.extendedProps.name || "N/A";
      document.getElementById('modalService').textContent = info.event.title || "N/A";
      const start = info.event.start;
      document.getElementById('modalDate').textContent = start ? start.toLocaleString() : 'N/A';
      document.getElementById('eventModal').style.display = 'flex';
    },

    events: <?php echo json_encode($events, JSON_HEX_TAG|JSON_HEX_AMP); ?>
  });

  calendar.render();

  setTimeout(function() {
    document.querySelectorAll('.fc-button').forEach(btn=>{
      btn.style.background = '#007bff';
      btn.style.color = '#fff';
      btn.style.borderColor = '#007bff';
    });
  }, 100);
});

function removeEvent() {
  if (!currentEvent) {
    alert('No event selected.');
    return;
  }
  if (!confirm('Delete this schedule?')) return;

  const id = currentEvent.id;
  fetch('delete_event.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=' + encodeURIComponent(id)
  })
  .then(r => r.text())
  .then(txt => {
    const res = txt.trim();
    if (res === 'success') {
      currentEvent.remove();
      closeModal();
      alert('Schedule deleted.');
    } else {
      alert('Failed to delete schedule: ' + res);
    }
  })
  .catch(err => {
    console.error(err);
    alert('Network / server error.');
  });
}

function closeModal() {
  document.getElementById('eventModal').style.display = 'none';
  currentEvent = null;
}
</script>
</body>
</html>
