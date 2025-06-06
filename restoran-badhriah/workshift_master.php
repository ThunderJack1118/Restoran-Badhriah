<?php
session_start();

// Set timezone for consistency
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Get user role
$role = $_SESSION['Role'] ?? 'Staff';

// Only managers can access this page
if ($role !== 'Manager') {
    header("Location: workshift.php");
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

// Initialize filter variables
$filter_staff = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filter_shift_type = isset($_GET['shift_type']) ? $_GET['shift_type'] : '';

// Build the base query - Updated to use FirstName and LastName instead of FullName
$query = "SELECT w.*, s.Username, s.FirstName, s.LastName 
          FROM workshift w
          JOIN staff s ON w.StaffID = s.StaffID
          WHERE w.StartDateTime BETWEEN ? AND ?";

$params = [$filter_date_from . ' 00:00:00', $filter_date_to . ' 23:59:59'];
$param_types = "ss";

// Add staff filter if specified
if (!empty($filter_staff)) {
    $query .= " AND w.StaffID = ?";
    $params[] = $filter_staff;
    $param_types .= "i";
}

// Add shift type filter if specified
if (!empty($filter_shift_type) && in_array($filter_shift_type, ['Morning', 'Afternoon', 'Evening'])) {
    $query .= " AND w.ShiftType = ?";
    $params[] = $filter_shift_type;
    $param_types .= "s";
}

$query .= " ORDER BY w.StartDateTime DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $shifts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Get all staff for filter dropdown - Updated to use FirstName and LastName
$staff_query = $conn->query("SELECT StaffID, Username, FirstName, LastName FROM staff ORDER BY FirstName, LastName");
$all_staff = $staff_query->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Shift Management | Restoran Badhriah</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
            border-bottom: 1px solid #333;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
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
            gap: 1rem;
            align-items: center;
        }

        .logout-btn, .dashboard-btn {
            background-color: #fff;
            color: #000;
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
            font-size: 0.9rem;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }

        .logout-btn:hover, .dashboard-btn:hover {
            background-color: #ddd;
        }

        main {
            flex: 1;
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .page-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .title-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .role-badge {
            background-color: #FF9800;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .access-info {
            background-color: #4CAF50;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 1rem;
            color: white;
            font-weight: 600;
        }

        .filters {
            background-color: #333;
            padding: 1.5rem;
            border-radius: 8px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .filter-input, .filter-select {
            padding: 0.7rem;
            border-radius: 4px;
            border: 1px solid #555;
            background-color: #222;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            grid-column: span 2;
            justify-content: flex-end;
        }

        .filter-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .filter-button:hover {
            background-color: #45a049;
        }

        .reset-button {
            background-color: #f44336;
        }

        .reset-button:hover {
            background-color: #d32f2f;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .summary-card {
            background-color: #333;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0.5rem 0;
            color: #4CAF50;
        }

        .summary-label {
            font-size: 0.9rem;
            color: #aaa;
        }

        .shift-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: #222;
            border-radius: 8px;
            overflow: hidden;
        }

        .shift-table th, .shift-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #444;
        }

        .shift-table th {
            background-color: #333;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .shift-table tr:hover {
            background-color: #2a2a2a;
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #4CAF50;
            color: white;
        }

        .status-completed {
            background-color: #2196F3;
            color: white;
        }

        .note-item {
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #444;
        }

        .note-item:last-child {
            border-bottom: none;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .info-footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
            padding: 1rem;
            background-color: #222;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .header-buttons {
                width: 100%;
                justify-content: center;
            }

            .page-title {
                flex-direction: column;
                align-items: flex-start;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                grid-column: span 1;
                flex-direction: column;
            }

            .summary-cards {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .shift-table th, .shift-table td {
                padding: 0.7rem 0.5rem;
                font-size: 0.8rem;
            }

            .logo-title {
                font-size: 1.2rem;
            }

            .logo {
                width: 40px;
                height: 40px;
            }
        }

        @media (max-width: 480px) {
            main {
                padding: 1rem;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .table-container, .table-container * {
                visibility: visible;
            }
            .table-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .shift-table {
                width: 100%;
                border-collapse: collapse;
            }
            .shift-table th, .shift-table td {
                padding: 8px;
                border: 1px solid #ddd;
            }
            .page-title, .access-info, .filters, .summary-cards, .info-footer {
                display: none;
            }
            header {
                display: none;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <div class="logo-title">
            <img src="images/logo.png" alt="Logo" class="logo">
            <span>Master Shift Management</span>
        </div>
        <div class="header-buttons">
            <a href="home.php" class="dashboard-btn">Dashboard</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>

<main>
    <div class="container">
        <div class="page-title">
            <div class="title-left">
                <h1>Shift Management Console</h1>
                <span class="role-badge">MANAGER ACCESS</span>
            </div>
            <button onclick="window.print()" class="filter-button" style="background-color: #2196F3;">Print Shifts</button>
        </div>
        
        <div class="access-info">
            👑 Welcome Manager <?= htmlspecialchars($_SESSION['Username']) ?>! You have full access to all staff shift records.
        </div>
        
        <!-- Filters Section -->
        <form method="get" class="filters">
            <div class="filter-group">
                <label for="staff_id" class="filter-label">Staff Member</label>
                <select id="staff_id" name="staff_id" class="filter-select">
                    <option value="">All Staff</option>
                    <?php foreach ($all_staff as $staff): 
                        $displayName = trim($staff['FirstName'] . ' ' . $staff['LastName']);
                        if (empty($displayName)) {
                            $displayName = $staff['Username'];
                        }
                    ?>
                        <option value="<?= $staff['StaffID'] ?>" <?= $filter_staff == $staff['StaffID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($displayName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_from" class="filter-label">From Date</label>
                <input type="date" id="date_from" name="date_from" class="filter-input" 
                       value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_to" class="filter-label">To Date</label>
                <input type="date" id="date_to" name="date_to" class="filter-input" 
                       value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            
            <div class="filter-group">
                <label for="shift_type" class="filter-label">Shift Type</label>
                <select id="shift_type" name="shift_type" class="filter-select">
                    <option value="">All Shifts</option>
                    <option value="Morning" <?= $filter_shift_type == 'Morning' ? 'selected' : '' ?>>Morning</option>
                    <option value="Afternoon" <?= $filter_shift_type == 'Afternoon' ? 'selected' : '' ?>>Afternoon</option>
                    <option value="Evening" <?= $filter_shift_type == 'Evening' ? 'selected' : '' ?>>Evening</option>
                </select>
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="filter-button">Apply Filters</button>
                <button type="button" class="filter-button reset-button" onclick="resetFilters()">Reset</button>
            </div>
        </form>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <?php
            // Calculate summary statistics
            $total_shifts = count($shifts);
            $active_shifts = 0;
            $total_hours = 0;
            $completed_shifts = 0;
            
            foreach ($shifts as $shift) {
                if (empty($shift['EndDateTime'])) {
                    $active_shifts++;
                } else {
                    $completed_shifts++;
                    $start = new DateTime($shift['StartDateTime']);
                    $end = new DateTime($shift['EndDateTime']);
                    $diff = $start->diff($end);
                    $total_hours += $diff->h + ($diff->i / 60);
                }
            }
            ?>
            
            <div class="summary-card">
                <div class="summary-label">Total Shifts</div>
                <div class="summary-value"><?= $total_shifts ?></div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Active Shifts</div>
                <div class="summary-value"><?= $active_shifts ?></div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Completed Shifts</div>
                <div class="summary-value"><?= $completed_shifts ?></div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Total Hours</div>
                <div class="summary-value"><?= number_format($total_hours, 1) ?>h</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Avg. Shift Length</div>
                <div class="summary-value">
                    <?= $completed_shifts > 0 ? number_format($total_hours / $completed_shifts, 1) : '0' ?>h
                </div>
            </div>
        </div>
        
        <!-- Shifts Table -->
        <div class="table-container">
            <table class="shift-table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Date</th>
                        <th>Shift Type</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($shifts)): ?>
                        <?php foreach ($shifts as $shift): 
                            $displayName = trim($shift['FirstName'] . ' ' . $shift['LastName']);
                            if (empty($displayName)) {
                                $displayName = $shift['Username'];
                            }
                        ?>
                            <tr>
                                <td>
                                    <div><strong><?= htmlspecialchars($displayName) ?></strong></div>
                                    <div style="font-size: 0.8rem; color: #aaa;">ID: <?= $shift['StaffID'] ?></div>
                                </td>
                                <td><?= date('M j, Y', strtotime($shift['StartDateTime'])) ?></td>
                                <td><?= htmlspecialchars($shift['ShiftType']) ?></td>
                                <td><?= date('h:i A', strtotime($shift['StartDateTime'])) ?></td>
                                <td>
                                    <?= $shift['EndDateTime'] ? date('h:i A', strtotime($shift['EndDateTime'])) : '--' ?>
                                </td>
                                <td>
                                    <?php if ($shift['EndDateTime']): 
                                        $start = new DateTime($shift['StartDateTime']);
                                        $end = new DateTime($shift['EndDateTime']);
                                        $diff = $start->diff($end);
                                        echo $diff->format('%h h %i m');
                                    else: ?>
                                        <span style="color: #4CAF50; font-weight: 600;">Ongoing</span>
                                    <?php endif; ?>
                                </td>
                                    <td>
                                        <?php 
                                        $isActive = empty($shift['EndDateTime']) || $shift['EndDateTime'] === null;
                                        $statusClass = $isActive ? 'status-active' : 'status-completed';
                                        $statusText = $isActive ? 'ACTIVE' : 'COMPLETED';
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                <td>
                                    <?php if (!empty($shift['Notes'])): ?>
                                        <div class="notes-container" style="max-height: 120px; overflow-y: auto;">
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
                                        <span style="color: #666;">--</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #aaa; padding: 3rem;">
                                📋 No shift records found for the selected filters
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="info-footer">
            Showing <?= count($shifts) ?> shift record(s) from <?= date('M j, Y', strtotime($filter_date_from)) ?> to <?= date('M j, Y', strtotime($filter_date_to)) ?>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Initialize date pickers
    flatpickr("#date_from", {
        dateFormat: "Y-m-d",
        defaultDate: "<?= $filter_date_from ?>",
        maxDate: "today"
    });
    
    flatpickr("#date_to", {
        dateFormat: "Y-m-d",
        defaultDate: "<?= $filter_date_to ?>",
        maxDate: "today"
    });
    
    // Auto-refresh active shifts every 30 seconds
    setInterval(function() {
        const activeElements = document.querySelectorAll('.status-active');
        if (activeElements.length > 0) {
            // Only refresh if there are active shifts
            console.log('Active shifts detected - consider refreshing');
        }
    }, 30000);

        // Function to check for active shifts and update status
    function checkActiveShifts() {
        const activeShifts = document.querySelectorAll('.status-active');
        
        if (activeShifts.length > 0) {
            fetch('check_shifts.php?last_check=' + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    data.updatedShifts.forEach(shift => {
                        const row = document.querySelector(`tr[data-shift-id="${shift.shiftId}"]`);
                        if (row) {
                            // Update clock out time
                            const clockOutCell = row.cells[4];
                            clockOutCell.textContent = shift.endTime ? 
                                new Date(shift.endTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '--';
                            
                            // Update duration
                            const durationCell = row.cells[5];
                            if (shift.endTime) {
                                const start = new Date(shift.startTime);
                                const end = new Date(shift.endTime);
                                const diff = new Date(end - start);
                                durationCell.textContent = `${diff.getUTCHours()} h ${diff.getUTCMinutes()} m`;
                            }
                            
                            // Update status badge
                            const statusCell = row.cells[6];
                            if (shift.endTime) {
                                statusCell.innerHTML = '<span class="status-badge status-completed">COMPLETED</span>';
                            }
                        }
                    });
                })
                .catch(error => console.error('Error checking shifts:', error));
        }
    }

    // Check every 30 seconds
    setInterval(checkActiveShifts, 30000);

    // Also add data-shift-id to each row in the table
    // Modify your PHP table generation to include:
    // <tr data-shift-id="<?= $shift['ShiftID'] ?>">

    function resetFilters() {
        // Get the current URL without query parameters
        const baseUrl = window.location.href.split('?')[0];
        
        // Redirect to the base URL to clear all filters
        window.location.href = baseUrl;
    }
</script>

</body>
</html>
<?php
$conn->close();
?>