<?php
// customer_seats.php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

// Check if table is empty
$result = $conn->query("SELECT COUNT(*) AS count FROM `tables`");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    // Insert sample data
    $conn->query("INSERT INTO `tables` (number, capacity, status, waiter) VALUES
        ('T1', 4, 'available', NULL),
        ('T2', 4, 'available', NULL),
        ('T3', 4, 'available', NULL),
        ('T4', 6, 'available', NULL),
        ('T5', 6, 'available', NULL),
        ('T6', 2, 'available', NULL),
        ('T7', 2, 'available', NULL),
        ('T8', 8, 'available', NULL),
        ('T9', 8, 'available', NULL),
        ('T10', 4, 'available', NULL)");
}

// Update table statuses based on active orders
$conn->query("UPDATE `tables` SET status = 'available', waiter = NULL");
$conn->query("UPDATE `tables` t 
              JOIN `order` o ON t.number = o.number 
              SET t.status = 'occupied', t.waiter = (SELECT Username FROM staff WHERE StaffID = o.StaffID)
              WHERE o.Status IN ('Pending', 'Preparing', 'Ready')");

// Get table statistics
$stats = [
    'available' => 0,
    'occupied' => 0,
    'guests' => 0
];

$result = $conn->query("SELECT status, COUNT(*) as count FROM `tables` GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
}

// Fetch tables from database
$tables = [];
$sql = "SELECT * FROM `tables`";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $tables[] = $row;
        if ($row['status'] === 'occupied') {
            $stats['guests'] += $row['capacity'] - rand(0, 2); // Random guests (capacity - 0-2)
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Seats | Restoran Badhriah</title>
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
      height: 100vh;
      overflow: hidden;
    }

    header {
      background-color: #000;
      padding: 0.8rem 1rem;
      text-align: center;
      font-size: 1.5rem;
      font-weight: 600;
      border-bottom: 1px solid #333;
      flex-shrink: 0;
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
      font-size: 1.3rem;
      font-weight: 600;
    }

    .logo {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #fff;
      color: #000;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 1rem;
    }

    .header-buttons {
      display: flex;
      gap: 0.6rem;
      align-items: center;
    }

    .logout-btn, .profile-btn, .back-btn {
      background-color: #fff;
      color: #000;
      text-decoration: none;
      padding: 0.3rem 0.7rem;
      border-radius: 6px;
      font-weight: 600;
      transition: background 0.3s;
      font-size: 0.85rem;
      white-space: nowrap;
    }

    .logout-btn:hover, .profile-btn:hover, .back-btn:hover {
      background-color: #ddd;
    }

    main {
      flex: 1;
      padding: 1rem;
      max-width: 1200px;
      margin: auto;
      width: 100%;
      display: flex;
      flex-direction: column;
      height: calc(100% - 120px);
      overflow: hidden;
    }

    /* Statistics Bar */
    .stats-bar {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.8rem;
      margin-bottom: 1rem;
    }

    .stat-card {
      background-color: #222;
      border-radius: 8px;
      padding: 0.8rem;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      display: flex;
      flex-direction: column;
      justify-content: center;
      border: 1px solid #333;
    }

    .stat-number {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 0.2rem;
    }

    .stat-label {
      font-size: 0.85rem;
      color: #aaa;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .page-title {
      font-size: 1.3rem;
      margin-bottom: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .status-legend {
      display: flex;
      gap: 0.8rem;
      margin-bottom: 0.8rem;
      flex-wrap: wrap;
      justify-content: center;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.85rem;
    }

    .legend-color {
      width: 16px;
      height: 16px;
      border-radius: 4px;
    }

    .available { background-color: #4CAF50; }
    .occupied { background-color: #f44336; }
    .reserved { background-color: #FF9800; }

    .seating-layout {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 2rem;
      flex: 1;
      overflow-y: auto;
      padding-bottom: 4px;
    }

    .table {
      background-color: #333;
      border-radius: 8px;
      padding: 0.8rem;
      text-align: center;
      transition: all 0.3s ease;
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: center;
      aspect-ratio: o.3/0.5; /* Square shape */
      min-height: 0;
    }

    .table:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }

    .table.available { background-color: #2E7D32; }
    .table.occupied { background-color: #C62828; }
    .table.reserved { background-color: #EF6C00; }

    .table-number {
      font-size: 1.5rem;
      font-weight: bold;
      margin-bottom: 0.3rem;
    }

    .table-info {
      font-size: 0.8rem;
      margin-bottom: 0.2rem;
      line-height: 1.2;
    }

    .table-waiter {
      font-style: italic;
      margin-top: 0.3rem;
      font-size: 0.8rem;
    }

    .action-btn {
      display: inline-block;
      background: rgba(255,255,255,0.2);
      color: white;
      padding: 0.3rem 0.6rem;
      border-radius: 4px;
      margin-top: 0.5rem;
      text-decoration: none;
      font-weight: 600;
      transition: background 0.3s;
      font-size: 0.8rem;
    }

    .action-btn:hover {
      background: rgba(255,255,255,0.3);
    }

    footer {
      text-align: center;
      padding: 0.6rem;
      color: #888;
      font-size: 0.9rem;
      background-color: #000;
      border-top: 1px solid #333;
      margin-top: auto;
      flex-shrink: 0;
    }

    /* Responsive layout */
    @media (max-width: 900px) {
      .seating-layout {
        grid-template-columns: repeat(4, 1fr);
      }
      
      .stats-bar {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.6rem;
      }
    }

    @media (max-width: 600px) {
      .seating-layout {
        grid-template-columns: repeat(3, 1fr);
      }
      
      .header-buttons {
        gap: 0.4rem;
      }
      
      .profile-btn, .logout-btn, .back-btn {
        padding: 0.2rem 0.5rem;
        font-size: 0.75rem;
      }
      
      .logo-title {
        font-size: 1rem;
      }
      
      .logo {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
      }
      
      .stat-card {
        padding: 0.6rem;
      }
      
      .stat-number {
        font-size: 1.5rem;
      }
    }
    
    @media (max-width: 400px) {
      .seating-layout {
        grid-template-columns: repeat(2, 1fr);
      }
    }
  </style>
</head>
<body>

<header>
  <div class="header-content">
    <div class="logo-title">
      <img src="images/logo.png" alt="Logo" class="logo">
      <span>Restoran Badhriah Dashboard</span>
    </div>
    <div class="header-buttons">
      <a href="waiter.php" class="back-btn"> Dashboard</a>
      <a href="profile.php" class="profile-btn">
        👤 Profile
      </a>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </div>
</header>

<main>
  <div class="page-title">
    <h1>Customer Seats</h1>
    <div>Total: <?= count($tables) ?> tables</div>
  </div>
  
  <!-- Statistics Bar -->
  <div class="stats-bar">
    <div class="stat-card">
      <div class="stat-number"><?= $stats['available'] ?></div>
      <div class="stat-label">Available Tables</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?= $stats['occupied'] ?></div>
      <div class="stat-label">Occupied Tables</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?= $stats['guests'] ?></div>
      <div class="stat-label">Current Guests</div>
    </div>
  </div>
  
  <div class="status-legend">
    <div class="legend-item">
      <div class="legend-color available"></div>
      <span>Available</span>
    </div>
    <div class="legend-item">
      <div class="legend-color occupied"></div>
      <span>Occupied</span>
    </div>
  </div>

  <div class="seating-layout">
    <?php foreach ($tables as $table): ?>
    <div class="table <?= $table['status'] ?>">
      <div class="table-number"><?= $table['number'] ?></div>
      <div class="table-info">Seats: <?= $table['capacity'] ?></div>
      <div class="table-info">Status: <strong><?= ucfirst($table['status']) ?></strong></div>
      
      <?php if (!empty($table['waiter'])): ?>
        <div class="table-waiter"><?= $table['waiter'] ?></div>
      <?php endif; ?>
      
      <?php if ($table['status'] === 'available'): ?>
        <a href="take_order.php?table=<?= $table['id'] ?>" class="action-btn">Assign</a>
      <?php elseif ($table['status'] === 'occupied'): ?>
        <a href="orders.php?table=<?= $table['id'] ?>" class="action-btn">View Order</a>
      <?php else: ?>
        <a href="#" class="action-btn">Details</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</main>

<footer>
  &copy; 2025 Restoran Badhriah. All rights reserved.
</footer>

</body>
</html>