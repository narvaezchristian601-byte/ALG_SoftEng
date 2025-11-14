<?php
// project_calendar.php (Calendar View)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../../db.php";
session_start();

// Enhanced connection check
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'));
    die("Database connection failed. Please check ../../db.php.");
}

// 1. DATA SYNCHRONIZATION: Fetch events from Projects table with schedule data from appointment_sched
$events = [];
$sql = "
    SELECT 
        p.Project_id,
        p.Orders_id,
        p.Status,
        p.Total_Cost,
        ss.ScheduleDate as schedule_date,
        o.status as order_status,
        s.Name AS ServiceName,
        c.Name AS CustomerName
    FROM Projects p
    LEFT JOIN Orders o ON p.Orders_id = o.Orders_id
    LEFT JOIN Services s ON o.Services_id = s.Services_id
    LEFT JOIN Customers c ON o.customer_id = c.customer_id
    LEFT JOIN appointment_sched ss ON (o.Services_id = ss.Services_id OR p.Project_id = ss.project_id)
    WHERE ss.ScheduleDate IS NOT NULL
    ORDER BY ss.ScheduleDate ASC
";

error_log("Executing SQL: " . $sql);

$result = $conn->query($sql);

if ($result === false) {
    error_log("SQL Error: " . $conn->error);
    // Continue with empty events rather than dying
} else {
    while ($row = $result->fetch_assoc()) {
        // Determine status class for styling - use project status instead of order status
        $statusClass = strtolower($row['Status'] ?? 'pending');
        
        // Format date for FullCalendar
        $scheduleDate = $row['schedule_date'];
        
        $events[] = [
            "id" => (int)$row['Project_id'], // Use Project_id as the ID
            "title" => ($row['CustomerName'] ?? 'N/A') . ' - ' . ($row['ServiceName'] ?? 'Service') . ' (' . ($row['Status'] ?? 'N/A') . ')',
            "start" => $scheduleDate,
            "extendedProps" => [
                "project_id" => $row['Project_id'],
                "order_id" => $row['Orders_id'],
                "name" => $row['CustomerName'] ?? 'N/A',
                "service" => $row['ServiceName'] ?? 'Service',
                "status" => $row['Status'] ?? 'N/A',
                "total_cost" => $row['Total_Cost'] ?? '0.00'
            ],
            // FullCalendar uses 'className' for event styling
            "className" => 'status-' . $statusClass 
        ];
    }
}

error_log("Events found: " . count($events));

if (isset($conn)) $conn->close(); 

// Use JSON_HEX_TAG|JSON_HEX_AMP for security when echoing JSON into HTML/JS
$events_json = json_encode($events, JSON_HEX_TAG|JSON_HEX_AMP);
if ($events_json === false) {
    $events_json = '[]';
    error_log("JSON encode error: " . json_last_error_msg());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Calendar | ALG Enterprises</title>
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
        }

        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Header Section */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: bold;
            color: #1e40af;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #1e40af;
            color: white;
        }

        .btn-primary:hover {
            background-color: #1e3a8a;
        }

        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
        }

        .btn-outline {
            background-color: transparent;
            border: 2px solid #1e40af;
            color: #1e40af;
        }

        .btn-outline:hover {
            background-color: #1e40af;
            color: white;
        }

        /* Calendar Container */
        .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        /* Calendar Styles */
        #calendar {
            margin: 0 auto;
        }

        /* Status Styles for Calendar Events */
        .status-pending { 
            background-color: #f7e07e !important; 
            border-color: #f5d13d !important; 
            color: #8a6d3b !important;
        }
        .status-ongoing { 
            background-color: #7ed6f7 !important; 
            border-color: #3dcef5 !important; 
            color: #31708f !important;
        }
        .status-completed { 
            background-color: #7ef785 !important; 
            border-color: #3df545 !important; 
            color: #3c763d !important;
        }
        .status-cancelled { 
            background-color: #f77e7e !important; 
            border-color: #f53d3d !important; 
            color: #a94442 !important;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e40af;
        }

        .close-btn {
            color: #6b7280;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }

        .close-btn:hover {
            color: #374151;
        }

        .modal-body p {
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .modal-body strong {
            color: #374151;
            display: inline-block;
            width: 120px;
        }

        .no-events {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
            font-style: italic;
        }

    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar">
    <div class="nav-container">
        <div class="logo">ALG Enterprises</div>
        <ul class="nav-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="projects.php">Projects</a></li>
            <li><a href="staff.php">Staff</a></li>
        </ul>
    </div>
</nav>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <h1 class="page-title">Project Calendar</h1>
        <div class="action-buttons">
            <a href="projects.php" class="btn btn-secondary">
                ← Return to Projects
            </a>
        </div>
    </div>


    <!-- Calendar Container -->
    <div class="calendar-container">
        <div id='calendar'></div>
    </div>
</div>

<!-- Event Modal -->
<div id="eventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Project Details</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p><strong>Project ID:</strong> <span id="modalProjectId"></span></p>
            <p><strong>Order ID:</strong> <span id="modalOrderId"></span></p>
            <p><strong>Service:</strong> <span id="modalService"></span></p>
            <p><strong>Customer:</strong> <span id="modalCustomer"></span></p>
            <p><strong>Date/Time:</strong> <span id="modalDate"></span></p>
            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
            <p><strong>Total Cost:</strong> $<span id="modalTotalCost"></span></p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const calendarEl = document.getElementById('calendar');
  let currentEvent = null;

  console.log('Calendar events:', <?php echo $events_json; ?>);

  try {
    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      },
      firstDay: 1,
      navLinks: true,
      dayMaxEvents: true,
      nowIndicator: true,
      
      eventClick: function(info) {
        currentEvent = info.event;
        
        const projectId = info.event.id;
        const orderId = info.event.extendedProps.order_id;
        const customer = info.event.extendedProps.name;
        const service = info.event.extendedProps.service;
        const status = info.event.extendedProps.status;
        const totalCost = info.event.extendedProps.total_cost;
        const start = info.event.start;

        console.log('Event clicked:', info.event);

        // Populate Modal Fields
        document.getElementById('modalProjectId').textContent = projectId || "N/A";
        document.getElementById('modalOrderId').textContent = orderId || "N/A";
        document.getElementById('modalCustomer').textContent = customer || "N/A";
        document.getElementById('modalService').textContent = service || "N/A";
        
        // Format date nicely
        if (start) {
          const formattedDate = start.toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit', 
            hour12: true 
          });
          document.getElementById('modalDate').textContent = formattedDate;
        } else {
          document.getElementById('modalDate').textContent = 'N/A';
        }

        // Show the modal
        document.getElementById('eventModal').style.display = 'flex';
      },

      events: <?php echo $events_json; ?>,

      // Handle case when no events are found
      eventDidMount: function(info) {
        console.log('Event mounted:', info.event);
      }
    });

    calendar.render();

    // Style calendar buttons to match theme
    setTimeout(function() {
      document.querySelectorAll('.fc-button').forEach(btn => {
        btn.style.background = '#1e40af';
        btn.style.color = '#fff';
        btn.style.borderColor = '#1e40af';
        btn.style.fontWeight = '500';
        btn.style.borderRadius = '6px';
        btn.style.margin = '2px';
        
        // Hover effect
        btn.addEventListener('mouseenter', function() {
          this.style.background = '#1e3a8a';
        });
        btn.addEventListener('mouseleave', function() {
          this.style.background = '#1e40af';
        });
      });
    }, 100);

    // Show message if no events
    if (<?php echo count($events); ?> === 0) {
      const calendarContainer = document.querySelector('.calendar-container');
      const noEventsMsg = document.createElement('div');
      noEventsMsg.className = 'no-events';
      noEventsMsg.innerHTML = '<p>No scheduled projects found. Projects will appear here when they have scheduled dates in the system.</p>';
      calendarContainer.appendChild(noEventsMsg);
    }

  } catch (error) {
    console.error('Calendar initialization error:', error);
    const calendarContainer = document.querySelector('.calendar-container');
    calendarContainer.innerHTML = '<div class="no-events"><p>Error loading calendar. Please check the console for details.</p></div>';
  }
});

function closeModal() {
    document.getElementById('eventModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('eventModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

</body>
</html>