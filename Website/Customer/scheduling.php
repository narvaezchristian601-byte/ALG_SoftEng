<?php
// scheduling.php - Updated to match your database structure
include("../../db.php");

// Fetch scheduled appointments - only show basic info
$events = [];
$sql = "
    SELECT 
        ss.sched_id,
        ss.ScheduleDate
    FROM service_sched ss
    WHERE ss.ScheduleDate IS NOT NULL
    ORDER BY ss.ScheduleDate ASC
";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            "id" => (int)$row['sched_id'],
            "title" => 'Scheduled',
            "start" => $row['ScheduleDate'],
            "className" => 'scheduled-time'
        ];
    }
}

// Handle form submission for scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_service'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $appointment_type = $_POST['appointment_type'];
    $schedule_date = $_POST['schedule_date'];

    try {
        $conn->begin_transaction();
        
        // Validate required fields
        if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($appointment_type) || empty($schedule_date)) {
            throw new Exception("All fields are required.");
        }

        // Check if customer already exists (by email)
        $check_customer_sql = "SELECT customer_id FROM Customers WHERE Email = ?";
        $check_stmt = $conn->prepare($check_customer_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Customer exists, get their ID
            $customer_row = $check_result->fetch_assoc();
            $customerId = $customer_row['customer_id'];
            
            // Update customer info
            $update_customer_sql = "UPDATE Customers SET Name = ?, PhoneNumber = ?, Address = ? WHERE customer_id = ?";
            $update_stmt = $conn->prepare($update_customer_sql);
            $update_stmt->bind_param("sssi", $name, $phone, $address, $customerId);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Insert new customer
            $stmt_cust = $conn->prepare("INSERT INTO Customers (Name, Email, PhoneNumber, Address) VALUES (?, ?, ?, ?)");
            $stmt_cust->bind_param("ssss", $name, $email, $phone, $address);
            if (!$stmt_cust->execute()) {
                throw new Exception("Failed to create customer: " . $stmt_cust->error);
            }
            $customerId = $stmt_cust->insert_id;
            $stmt_cust->close();
        }
        $check_stmt->close();

        // Check if the time slot is already booked
        $check_schedule_sql = "SELECT sched_id FROM service_sched WHERE ScheduleDate = ?";
        $check_schedule_stmt = $conn->prepare($check_schedule_sql);
        $check_schedule_stmt->bind_param("s", $schedule_date);
        $check_schedule_stmt->execute();
        $schedule_result = $check_schedule_stmt->get_result();
        
        if ($schedule_result->num_rows > 0) {
            throw new Exception("This time slot is already booked. Please choose another time.");
        }
        $check_schedule_stmt->close();

        // Insert into service_sched table with appointment_type
        $stmt_sched = $conn->prepare("INSERT INTO service_sched (ScheduleDate, customer_id, appointment_type) VALUES (?, ?, ?)");
        $stmt_sched->bind_param("sis", $schedule_date, $customerId, $appointment_type);
        if (!$stmt_sched->execute()) {
            throw new Exception("Failed to schedule appointment: " . $stmt_sched->error);
        }
        $schedId = $stmt_sched->insert_id;
        $stmt_sched->close();

        $conn->commit();
        $success_message = "Appointment scheduled successfully! Your schedule ID is: " . $schedId;
        
        // Refresh the page to show new appointment
        echo "<script>setTimeout(function() { window.location.href = window.location.href; }, 2000);</script>";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Get appointment types from ENUM definition
$appointment_types = ['Service', 'Consultation', 'Purchase Materials', 'N/A'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scheduling Calendar | ALG Enterprises</title>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
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
    margin: 0;
    padding: 0;
  }

  /* ===== NAVBAR ===== */
  nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #fff;
    padding: 10px 60px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 10;
  }

  nav img {
    height: 70px;
  }

  .nav-links {
    display: flex;
    gap: 60px;
    justify-content: center;
    flex: 1;
  }

  .nav-links a {
    text-decoration: none;
    color: black;
    font-weight: bold;
    font-size: 18px;
    transition: 0.3s;
  }

  .nav-links a:hover,
  .nav-links a.active {
    color: #4f9bd2;
  }

  @media (max-width: 900px) {
    nav {
      flex-direction: column;
      padding: 20px;
    }
    .nav-links {
      flex-wrap: wrap;
      justify-content: center;
      gap: 20px;
      padding-top: 10px;
    }
  }

  /* Main Container */
  .container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 2rem;
  }

  /* Calendar Header */
  .calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .calendar-title {
    font-size: 2rem;
    font-weight: bold;
    color: #1e40af;
  }

  .legend-container {
    display: flex;
    align-items: center;
    gap: 2rem;
  }

  .legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
  }

  .scheduled-color {
    background-color: #dc2626;
  }

  /* Action Buttons */
  .action-buttons {
    display: flex;
    gap: 1rem;
  }

  .btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
    font-size: 14px;
  }

  .btn-primary {
    background-color: #1e40af;
    color: white;
  }

  .btn-primary:hover {
    background-color: #1e3a8a;
  }

  /* Calendar Container */
  .calendar-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    margin-bottom: 2rem;
  }

  #calendar {
    margin: 0 auto;
  }

  /* Scheduled Time Style */
  .scheduled-time {
    background-color: #dc2626 !important;
    border-color: #b91c1c !important;
    color: white !important;
    font-weight: 500;
  }

  .scheduled-time .fc-event-title {
    color: white !important;
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
    max-width: 600px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    max-height: 90vh;
    overflow-y: auto;
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

  /* Form Styles */
  .form-section {
    margin-bottom: 1.5rem;
  }

  .form-section h3 {
    margin-bottom: 1rem;
    color: #1e40af;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 0.5rem;
  }

  .form-group {
    margin-bottom: 1rem;
  }

  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
  }

  .form-group input,
  .form-group select,
  .form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.3s;
  }

  .form-group input:focus,
  .form-group select:focus,
  .form-group textarea:focus {
    outline: none;
    border-color: #1e40af;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
  }

  .form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
  }

  .btn-cancel {
    background-color: #6b7280;
    color: white;
  }

  .btn-cancel:hover {
    background-color: #4b5563;
  }

  .message {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-weight: 500;
  }

  .message.success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #34d399;
  }

  .message.error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #f87171;
  }

  small {
    color: #6b7280;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: block;
  }
</style>
</head>
<body>

<!-- Navigation Bar -->
<nav>
  <div class="navbar-left">
    <img src="../../images/alg-logo-black.png" alt="ALG Enterprises Logo">
  </div>
  <div class="nav-links">
    <a href="home.html">Home</a>
    <a href="products.html">Products</a>
    <a href="services.html">Services</a>
    <a href="projects.html">Projects</a>
    <a href="about.html">About Us</a>
    <a href="contact.html">Contact Us</a>
  </div>
</nav>

<div class="container">
  <!-- Calendar Header -->
  <div class="calendar-header">
    <h1 class="calendar-title">Service Schedule</h1>
    <div class="legend-container">
      <div class="legend-item">
        <div class="legend-color scheduled-color"></div>
        <span>Scheduled (Red)</span>
      </div>
    </div>
    <div class="action-buttons">
      <button class="btn btn-primary" onclick="openScheduleModal()">
        Schedule Appointment
      </button>
    </div>
  </div>

  <!-- Calendar Container -->
  <div class="calendar-container">
    <div id="calendar"></div>
  </div>
</div>

<!-- Schedule Modal -->
<div id="scheduleModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Create New Schedule</h2>
      <button class="close-btn" onclick="closeScheduleModal()">&times;</button>
    </div>
    
    <?php if (isset($success_message)): ?>
      <div class="message success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
      <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" id="scheduleForm">
      <input type="hidden" name="schedule_service" value="1">
      
      <div class="form-section">
        <h3>Customer Info</h3>
        
        <div class="form-group">
          <label for="name">Name:</label>
          <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
        </div>
        
        <div class="form-group">
          <label for="email">Email:</label>
          <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        </div>
        
        <div class="form-group">
          <label for="phone">Phone:</label>
          <input type="text" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
        </div>
        
        <div class="form-group">
          <label for="address">Address:</label>
          <input type="text" id="address" name="address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
        </div>
      </div>

      <div class="form-section">
        <h3>Appointment Details</h3>
        
        <div class="form-group">
          <label for="appointment_type">Appointment Type:</label>
          <select id="appointment_type" name="appointment_type" required>
            <option value="">- Select Appointment Type -</option>
            <?php foreach ($appointment_types as $type): ?>
              <?php if ($type !== 'N/A'): ?>
                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (isset($_POST['appointment_type']) && $_POST['appointment_type'] == $type) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($type); ?>
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="schedule_date">Preferred Schedule Date:</label>
          <input type="datetime-local" 
                 id="schedule_date" 
                 name="schedule_date" 
                 value="<?php echo isset($_POST['schedule_date']) ? htmlspecialchars($_POST['schedule_date']) : date('Y-m-d').'T08:00'; ?>"
                 required
                 step="1800"
                 min="<?php echo date('Y-m-d').'T06:00'; ?>" 
                 max="<?php echo date('Y-m-d', strtotime('+90 days')).'T18:00'; ?>">
          <small>Allowed time: 6:00 AM to 6:00 PM</small>
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-cancel" onclick="closeScheduleModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Schedule Appointment</button>
      </div>
    </form>
  </div>
</div>

<!-- Event Details Modal -->
<div id="eventModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Scheduled Time</h2>
      <button class="close-btn" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <p><strong>Time:</strong> <span id="modalDate"></span></p>
      <p><em>This time slot is currently booked</em></p>
    </div>
  </div>
</div>

<script>
let calendar;              
let currentEvent = null;   

document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('calendar');

  calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    selectable: false,
    editable: false,
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek'
    },
    firstDay: 1,

    eventClick: function(info) {
      currentEvent = info.event;
      const start = info.event.start;
      
      document.getElementById('modalDate').textContent = start ? start.toLocaleString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit', 
        hour12: true 
      }) : 'N/A';
      
      document.getElementById('eventModal').style.display = 'flex';
    },

    events: <?php echo json_encode($events, JSON_HEX_TAG|JSON_HEX_AMP); ?>
  });

  calendar.render();

  // Style calendar buttons to match theme
  setTimeout(function() {
    document.querySelectorAll('.fc-button').forEach(btn => {
      btn.style.background = '#1e40af';
      btn.style.color = '#fff';
      btn.style.borderColor = '#1e40af';
      btn.style.fontWeight = '500';
    });
  }, 100);

  // Date validation
  document.getElementById('schedule_date').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const hour = selectedDate.getHours();
    if (hour < 6 || hour > 18) {
      alert('Please select a time between 6:00 AM and 6:00 PM.');
      this.value = '';
    }
  });
});

function openScheduleModal() {
  document.getElementById('scheduleModal').style.display = 'flex';
}

function closeScheduleModal() {
  document.getElementById('scheduleModal').style.display = 'none';
  // Reset form on close if no success message
  <?php if (!isset($success_message)): ?>
    document.getElementById('scheduleForm').reset();
  <?php endif; ?>
}

function closeModal() {
  document.getElementById('eventModal').style.display = 'none';
  currentEvent = null;
}

// Close modals when clicking outside
window.onclick = function(event) {
  const scheduleModal = document.getElementById('scheduleModal');
  const eventModal = document.getElementById('eventModal');
  
  if (event.target === scheduleModal) {
    closeScheduleModal();
  }
  if (event.target === eventModal) {
    closeModal();
  }
}
</script>

</body>
</html>