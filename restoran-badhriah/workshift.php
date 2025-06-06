
<?php
session_start();

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Get user role
$role = $_SESSION['Role'] ?? 'Staff';

// If user is Manager, redirect to master workshift
if ($role === 'Manager') {
    header("Location: workshift_master.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restoran_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get StaffID based on the logged-in username
if (!isset($_SESSION['StaffID'])) {
    $username = $_SESSION['Username'];
    $stmt = $conn->prepare("SELECT StaffID FROM staff WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['StaffID'] = $row['StaffID'];
        $staffID = $row['StaffID'];
    } else {
        die("Error: Staff record not found for username: " . htmlspecialchars($username));
    }
    $stmt->close();
} else {
    $staffID = $_SESSION['StaffID'];
}

$message = "";
$currentShift = null;

// Verify StaffID exists in staff table
$staffCheck = $conn->prepare("SELECT StaffID FROM staff WHERE StaffID = ?");
$staffCheck->bind_param("i", $staffID);
$staffCheck->execute();
$staffCheck->store_result();

if ($staffCheck->num_rows == 0) {
    die("Error: Invalid StaffID. You don't exist in our staff records.");
}
$staffCheck->close();

// Check if user has an active shift
$activeShiftQuery = $conn->prepare("SELECT * FROM workshift WHERE StaffID = ? AND EndDateTime IS NULL");
$activeShiftQuery->bind_param("i", $staffID);
$activeShiftQuery->execute();
$activeShiftResult = $activeShiftQuery->get_result();
$hasActiveShift = $activeShiftResult->num_rows > 0;

if ($hasActiveShift) {
    $currentShift = $activeShiftResult->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['clock_in'])) {
        // CLOCK IN with debug information
        $currentTime = date('Y-m-d H:i:s');
        $currentHour = (int)date('H');
        
        // Determine shift type based on current hour
        if ($currentHour < 12) {
            $shiftType = 'Morning';
        } elseif ($currentHour < 17) {
            $shiftType = 'Afternoon';
        } else {
            $shiftType = 'Evening';
        }
        
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
        
        // Debug information
        $debugInfo = "Hour: $currentHour, ShiftType: $shiftType, StaffID: $staffID";
        
        // Insert new shift record
        $stmt = $conn->prepare("INSERT INTO workshift (StaffID, StartDateTime, ShiftType, Notes) VALUES (?, ?, ?, ?)");
        
        if (!$stmt) {
            $message = "Prepare Error: " . $conn->error;
        } else {
            $stmt->bind_param("isss", $staffID, $currentTime, $shiftType, $notes);
            
            if ($stmt->execute()) {
                // Get the ID of the inserted record
                $insertedId = $conn->insert_id;
                
                // Verify what was actually inserted
                $checkQuery = $conn->prepare("SELECT * FROM workshift WHERE ShiftID = ?");
                $checkQuery->bind_param("i", $insertedId);
                $checkQuery->execute();
                $insertedData = $checkQuery->get_result()->fetch_assoc();
                
                $message = "Successfully clocked in at " . date('h:i A', strtotime($currentTime)) . 
                          " (Shift: " . $shiftType . ") | Verified in DB: '" . 
                          ($insertedData['ShiftType'] ?? 'NULL') . "'";
                
                $hasActiveShift = true;
                
                // Get the newly created shift
                $activeShiftQuery->execute();
                $activeShiftResult = $activeShiftQuery->get_result();
                $currentShift = $activeShiftResult->fetch_assoc();
                
                $checkQuery->close();
            } else {
                $message = "Error clocking in: " . $stmt->error . " | Debug: $debugInfo";
            }
            $stmt->close();
        }
        
    } elseif (isset($_POST['clock_out'])) {
        // CLOCK OUT
        // First check if user has an active shift
        if (!$hasActiveShift || !$currentShift) {
            $message = "Error: No active shift found to clock out.";
        } else {
            $currentTime = date('Y-m-d H:i:s');
            $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
            
            // Determine shift type based on the ORIGINAL clock-in time (not clock-out time)
            $clockInHour = (int)date('H', strtotime($currentShift['StartDateTime']));
            if ($clockInHour < 12) {
                $shiftType = 'Morning';
            } elseif ($clockInHour < 17) {
                $shiftType = 'Afternoon';
            } else {
                $shiftType = 'Evening';
            }
            
            // Update EndDateTime and Notes only (ShiftType should already be set from clock-in)
            $stmt = $conn->prepare("UPDATE workshift SET 
                EndDateTime = ?, 
                Notes = CONCAT(IFNULL(Notes,''), ?)
                WHERE ShiftID = ?");
            
            if (!$stmt) {
                $message = "Prepare Error: " . $conn->error;
            } else {
                $combinedNotes = ($notes ? "\n" . date('Y-m-d H:i') . ": " . $notes : "");
                $stmt->bind_param("ssi", $currentTime, $combinedNotes, $currentShift['ShiftID']);
                
                if ($stmt->execute()) {
                    // Verify the update
                    $verifyQuery = $conn->prepare("SELECT ShiftType FROM workshift WHERE ShiftID = ?");
                    $verifyQuery->bind_param("i", $currentShift['ShiftID']);
                    $verifyQuery->execute();
                    $verifyResult = $verifyQuery->get_result()->fetch_assoc();
                    
                    $message = "Successfully clocked out at " . date('h:i A', strtotime($currentTime)) . 
                              " | Shift Type: " . ($verifyResult['ShiftType'] ?? 'Not Set');
                    
                    $hasActiveShift = false;
                    $currentShift = null;
                    
                    $verifyQuery->close();
                } else {
                    $message = "Error clocking out: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
// Get shift history (last 7 days)
$historyQuery = $conn->prepare("
    SELECT * FROM workshift 
    WHERE StaffID = ? 
    AND StartDateTime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY StartDateTime DESC
");
$historyQuery->bind_param("i", $staffID);
$historyQuery->execute();
$historyResult = $historyQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Work Shift | Restoran Badhriah</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #111;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: 14px;
        }

        header {
            background-color: #000;
            padding: 1rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #333;
        }

        main {
            flex: 1;
            padding: 1.5rem;
            max-width: 1200px;
            margin: auto;
            width: 100%;
        }

        .container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .info-notice {
            background-color: #333;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .clock-section {
            background-color: #333;
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
        }

        .clock-display {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .clock-date {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: #ddd;
        }

        .clock-status {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            color: #4CAF50;
        }

        .clock-btn {
            border: none;
            padding: 0.8rem 2rem;
            font-size: 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin: 0 0.5rem;
            color: white;
        }

        .clock-btn.clock-in {
            background-color: #4CAF50;
        }

        .clock-btn.clock-in:hover {
            background-color: #45a049;
        }

        .clock-btn.clock-out {
            background-color: #f44336;
        }

        .clock-btn.clock-out:hover {
            background-color: #da190b;
        }

        .clock-btn:disabled {
            background-color: #666;
            cursor: not-allowed;
        }

        .notes-section {
            margin: 1.5rem 0;
        }

        .notes-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .notes-input {
            width: 100%;
            padding: 0.8rem;
            border-radius: 4px;
            border: 1px solid #555;
            background-color: #222;
            color: #fff;
            font-family: 'Inter', sans-serif;
            resize: vertical;
            min-height: 80px;
        }

        .history-section h2 {
            margin-bottom: 1rem;
            border-bottom: 1px solid #444;
            padding-bottom: 0.5rem;
        }

        .shift-table {
            width: 100%;
            border-collapse: collapse;
        }

        .shift-table th, .shift-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #444;
        }

        .shift-table th {
            background-color: #222;
        }

        .shift-table tr:hover {
            background-color: #333;
        }

        .message {
            padding: 0.8rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            text-align: center;
        }

        .success {
            background-color: #4CAF50;
        }

        .error {
            background-color: #f44336;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: auto;
        }

        .logo-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: contain;
            border: 1px solid #fff;
        }

        .header-buttons {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }

        .logout-btn, .profile-btn {
            background-color: #fff;
            color: #000;
            text-decoration: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
            font-size: 0.9rem;
        }

        .note-item {
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #444;
        }

        .note-time {
            font-size: 0.8rem;
            color: #aaa;
        }

        @media (max-width: 768px) {
            .shift-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <div class="logo-title">
            <img src="images/logo.png" alt="Logo" class="logo">
            <span>Work Shift Management</span>
        </div>
        <div class="header-buttons">
            <a href="profile.php" class="profile-btn">
                👤 Profile
            </a>
            <a href="javascript:history.back()" class="profile-btn">Dashboard</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>

<main>
    <div class="container">
        <div class="info-notice">
            📋 This is your personal timesheet. Only you can view and manage your clock in/out times.
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="clock-section">
            <div class="clock-date" id="current-date">
                <?php echo date('l, F j, Y'); ?>
            </div>
            <div class="clock-display" id="current-time">
                <?php echo date('h:i:s A'); ?>
            </div>
            
            <div class="clock-status">
                <?php if ($hasActiveShift): ?>
                    You are currently clocked in since <?php echo date('h:i A', strtotime($currentShift['StartDateTime'])); ?>
                <?php else: ?>
                    You are currently clocked out
                <?php endif; ?>
            </div>
            
            <form method="post">
                <div class="notes-section">
                    <label for="notes" class="notes-label">Add Note (Optional):</label>
                    <textarea id="notes" name="notes" class="notes-input" placeholder="Enter any notes about your shift..."></textarea>
                </div>
                
                <?php if (!$hasActiveShift): ?>
                    <button type="submit" name="clock_in" class="clock-btn clock-in">Clock In</button>
                <?php else: ?>
                    <button type="submit" name="clock_out" class="clock-btn clock-out">Clock Out</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="history-section">
            <h2>Your Recent Shifts (Last 7 Days)</h2>
            <table class="shift-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shift Type</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Duration</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($historyResult->num_rows > 0): ?>
                        <?php while ($shift = $historyResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($shift['StartDateTime'])); ?></td>
                                <td><?php echo htmlspecialchars($shift['ShiftType']); ?></td>
                                <td><?php echo date('h:i A', strtotime($shift['StartDateTime'])); ?></td>
                                <td>
                                    <?php echo $shift['EndDateTime'] ? date('h:i A', strtotime($shift['EndDateTime'])) : '--'; ?>
                                </td>
                                <td>
                                    <?php if ($shift['EndDateTime']): 
                                        $start = new DateTime($shift['StartDateTime']);
                                        $end = new DateTime($shift['EndDateTime']);
                                        $diff = $start->diff($end);
                                        echo $diff->format('%h h %i m');
                                    else: ?>
                                        Ongoing
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($shift['Notes'])): ?>
                                        <div class="notes-container">
                                            <?php 
                                            $notes = explode("\n", $shift['Notes']);
                                            foreach ($notes as $note):
                                                if (trim($note) !== ''):
                                            ?>
                                                <div class="note-item">
                                                    <?php echo htmlspecialchars($note); ?>
                                                </div>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No shift records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    // Update current time every second
    function updateClock() {
        const now = new Date();
        
        // Convert to Malaysia timezone
        const malaysiaTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Kuala_Lumpur"}));
        
        // Update date
        const dateStr = malaysiaTime.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        document.getElementById('current-date').textContent = dateStr;
        
        // Update time
        const timeStr = malaysiaTime.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
        document.getElementById('current-time').textContent = timeStr;
    }
    
    setInterval(updateClock, 1000);
    updateClock();
</script>

</body>
</html>
<?php
$conn->close();
?>