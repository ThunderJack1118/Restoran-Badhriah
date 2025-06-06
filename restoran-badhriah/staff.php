<?php
session_start();
if (!isset($_SESSION['Username']) || $_SESSION['Role'] !== 'Manager') {
    die("❌ Access denied.");
}

// Use your existing MySQLi database connection file
require_once 'db.php';

// Fetch staff data from database
$staff = [];
$query = "SELECT StaffID, FirstName, LastName, Role, Email, Phone, HireDate, IsActive, ICPassportNo, Username FROM Staff";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    $result->free();
} else {
    die("Database error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Management | Restoran Badhriah</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #111;
      color: #fff;
      min-height: 100vh;
    }

    /* Sidebar styles */
    .sidebar {
      width: 240px;
      background: #000;
      padding: 2rem 1rem;
      border-right: 1px solid #333;
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      overflow-y: auto;
      transition: transform 0.3s ease;
      z-index: 1000;
    }

    .sidebar.hidden {
      transform: translateX(-100%);
    }

    .sidebar h2 {
      color: #fff;
      font-size: 1.2rem;
      margin-bottom: 2rem;
      text-align: center;
    }

    .sidebar a {
      display: block;
      color: #ccc;
      text-decoration: none;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      margin-bottom: 0.5rem;
      font-weight: 500;
      transition: background 0.3s;
      white-space: nowrap;       /* Prevent text wrapping */
      overflow: hidden;          /* Hide overflow */
      text-overflow: ellipsis;   /* Show ... if text is too long */
      padding-right: 10px;       /* Add some right padding */
    }

    .sidebar a:hover,
    .sidebar a.active {
      background: #222;
      color: #fff;
    }

    /* Main content */
    .main {
      margin-left: 220px;
      padding: 2rem;
      transition: margin-left 0.3s ease;
    }

    .sidebar.hidden + .main {
      margin-left: 0;
    }

    /* Base toggle button */
    .toggle-btn {
      position: fixed;
      top: 1rem;
      left: 1rem;
      padding: 0.5rem 1rem;
      background: #fff;
      color: #000;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      cursor: pointer;
      z-index: 1100;
      transition: left 0.3s ease;
    }

    /* When sidebar is open, move the button over */
    .sidebar:not(.hidden) ~ .main #toggleBtn {
      left: 260px; /* width of sidebar + some margin */
    }


    .toggle-btn {
      position: fixed;
      top: 1rem;
      left: 1rem;
      padding: 0.5rem 1rem;
      background: #fff;
      color: #000;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      cursor: pointer;
      z-index: 1100;
    }

    h1 {
      text-align: center;
      margin-top: 3rem;
      margin-bottom: 1.5rem;
      font-size: 2rem;
    }

    .btn {
      padding: 0.5rem 1rem;
      background: #fff;
      color: #000;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.3s;
      text-decoration: none;
    }

    .btn:hover {
      background: #ddd;
    }

    .top-bar {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 1.5rem;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #1a1a1a;
      border: 1px solid #333;
    }

    th, td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid #333;
    }

    th {
      background-color: #000;
    }

    td.actions {
      display: flex;
      gap: 0.5rem;
    }

    .btn-small {
      padding: 0.4rem 0.7rem;
      font-size: 0.9rem;
    }

    .footer {
      text-align: center;
      margin-top: 3rem;
      color: #777;
      font-size: 0.9rem;
    }
    
    .active-status {
      color: #4CAF50;
      font-weight: bold;
    }
    
    .inactive-status {
      color: #F44336;
      font-weight: bold;
    }
    
    .password-mask {
      font-family: monospace;
      letter-spacing: 2px;
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div style="text-align: center;">
   <img src="images/logo.png" alt="Restoren Badhriah Logo"
        style="width: 100px; height: 100px; border-radius: 50%; border: 2px solid #fff; object-fit: contain; margin-bottom: 1rem;">
  </div>
  <h2>Restoran Badhriah</h2>
  <a href="home.php">🏠 Dashboard</a>
  <a href="workshift.php">⏱️ Clock In/Out</a>
  <a href="reports.php">📊 Overall Reports</a>
  <a href="orders.php">🍽️ Orders</a>
  <a href="menu.php">📋 Menu Items</a>
  <a href="inventory_management.php">📦 Inventory</a>
  <a href="payments.php">💳 Payments</a>
  <a href="staff.php" class="active">💼 Staff Management</a>
  <a href="salaries.php">💰 Salaries and Expenses</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="main" id="main">
  <button id="toggleBtn" class="toggle-btn">☰ Menu</button>

  <h1>Staff Management | Restoran Badhriah</h1>

  <div class="top-bar">
    <a href="add_staff.php" class="btn">➕ Add New Staff</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Role</th>
        <th>IC/Passport No</th>
        <th>Username</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Hire Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($staff as $member): ?>
        <tr>
          <td><?= $member['StaffID'] ?></td>
          <td><?= htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']) ?></td>
          <td><?= htmlspecialchars($member['Role']) ?></td>
          <td><?= htmlspecialchars($member['ICPassportNo']) ?></td>
          <td><?= htmlspecialchars($member['Username']) ?></td>
          <td><?= htmlspecialchars($member['Email']) ?></td>
          <td><?= htmlspecialchars($member['Phone']) ?></td>
          <td><?= htmlspecialchars($member['HireDate']) ?></td>
          <td class="<?= $member['IsActive'] ? 'active-status' : 'inactive-status' ?>">
            <?= $member['IsActive'] ? 'Active' : 'Inactive' ?>
          </td>
          <td class="actions">
            <a href="edit_staff.php?id=<?= $member['StaffID'] ?>" class="btn btn-small">✏️ Edit</a>
            <a href="delete_staff.php?id=<?= $member['StaffID'] ?>" class="btn btn-small" onclick="return confirm('Are you sure you want to delete this staff member?')">
              <?= $member['IsActive'] ? '🗑️ Deactivate' : '♻️ Activate' ?>
            </a>
            <a href="reset_password.php?id=<?= $member['StaffID'] ?>" class="btn btn-small" onclick="return confirm('Reset password to default?')">🔄 Reset PW</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="footer">
    &copy; <?= date('Y') ?> Restoran Badhriah. All rights reserved.
  </div>
</div>

<!-- JavaScript to toggle sidebar -->
<script>
  const toggleBtn = document.getElementById('toggleBtn');
  const sidebar = document.getElementById('sidebar');

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('hidden');
  });
</script>

</body>
</html>