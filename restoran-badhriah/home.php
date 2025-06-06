<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Example role — normally retrieved from DB/login logic
$role = $_SESSION['Role'] ?? 'Manager';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | Restoran Badhriah</title>
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
      font-size: 14px; /* Base font size reduced */
    }

    header {
      background-color: #000;
      padding: 1rem; /* Reduced padding */
      text-align: center;
      font-size: 1.5rem; /* Reduced font size */
      font-weight: 600;
      border-bottom: 1px solid #333;
    }

    main {
      flex: 1;
      padding: 1.5rem; /* Reduced padding */
      max-width: 1200px;
      margin: auto;
    }

    .welcome {
      font-size: 1.5rem; /* Reduced font size */
      margin-bottom: 1.5rem; /* Reduced margin */
      text-align: center;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem; /* Reduced gap */
      margin-top: 1.5rem; /* Reduced margin */
    }

    .grid-row-center {
      display: flex;
      justify-content: center;
      gap: 1rem; /* Reduced gap */
      margin-bottom: 1.5rem; /* Reduced margin */
      flex-wrap: wrap;
    }
    
    .wide-card {
      width: 350px; /* Reduced width */
    }

    .card {
      background-color:rgb(49, 49, 49);
      padding: 1rem; /* Reduced padding */
      border-radius: 8px; /* Reduced radius */
      text-align: center;
      box-shadow: 0 4px 12px rgba(255, 255, 255, 0.03);
      transition: all 0.25s ease;
      border: 1px solid transparent;
    }

    .card:hover {
      transform: translateY(-3px); /* Reduced hover effect */
      border-color: #444;
    }

    .card a {
      text-decoration: none;
      color: #fff;
      font-weight: 600;
      display: block;
      font-size: 1.2rem; /* Reduced font size */
      margin-top: 0.5rem;
    }

    footer {
      text-align: center;
      padding: 0.8rem; /* Reduced padding */
      color: #888;
      font-size: 1rem; /* Reduced font size */
      background-color: #000;
      border-top: 1px solid #333;
      margin-top: auto;
    }

    @media (max-width: 600px) {
      .welcome {
        font-size: 1.2rem; /* Adjusted for mobile */
      }
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
      gap: 10px; /* Reduced gap */
      font-size: 1.5rem; /* Reduced font size */
      font-weight: 600;
    }

    .logo {
      width: 50px; /* Reduced size */
      height: 50px; /* Reduced size */
      border-radius: 50%;
      object-fit: contain;
      border: 1px solid #fff; /* Thinner border */
    }

    .header-buttons {
      display: flex;
      gap: 0.8rem; /* Reduced gap */
      align-items: center;
    }

    .logout-btn, .profile-btn {
      background-color: #fff;
      color: #000;
      text-decoration: none;
      padding: 0.4rem 0.8rem; /* Reduced padding */
      border-radius: 6px; /* Reduced radius */
      font-weight: 600;
      transition: background 0.3s;
      font-size: 0.9rem; /* Reduced font size */
    }

    .logout-btn:hover,.profile-btn:hover {
      background-color: #ddd;
    }

    .card-icon {
      width: 80px; /* Reduced size */
      height: 80px; /* Reduced size */
      margin-bottom: 0.8rem; /* Reduced margin */
      object-fit: contain;
      border-radius: 8px; /* Reduced radius */
      background-color: #fff;
    }
    
    .card-label {
          font-weight: 600;
          color: #fff;
          text-decoration: none;
          display: block;
          font-size: 1.2rem;
          margin-top: 0.5rem;
      }

    .not-authorized {
        color: #f44336;
      }

    @media (max-width: 768px) {
      .header-buttons {
        flex-direction: column;
        gap: 0.5rem;
      }
      
      .profile-btn, .logout-btn {
        padding: 0.3rem 0.6rem;
        font-size: 0.8rem;
      }
      
      .logo-title {
        font-size: 1.2rem;
      }
      
      .logo {
        width: 40px;
        height: 40px;
      }
      
      .grid {
        grid-template-columns: repeat(2, 1fr);
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
      <a href="profile.php" class="profile-btn">
        👤 Profile
      </a>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </div>
</header>

<main>
  <div class="welcome">
    👋 Welcome, <strong><?= htmlspecialchars($_SESSION['Username']) ?></strong> (<?= htmlspecialchars($role) ?>)
  </div>
  <div class="grid-row-center">
      <!-- Clock In/Out Card - now matches Financial Report style -->
    <div class="card wide-card">
      <img src="images/clock.png" alt="Clock In/Out" class="card-icon">
      <a href="workshift.php">Clock In/Out</a>
    </div>
    
    <!-- Financial Reports -->
    <div class="card wide-card">
      <img src="images/report.png" alt="Overall Reports" class="card-icon">
      <?php if ($role === 'Manager'): ?>
        <a href="reports.php">Overall Reports</a>
      <?php else: ?>
        <div class="card-label  not-authorized">Not Authorized</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <img src="images/orders.png" alt="Orders" class="card-icon">
      <a href="take_order.php">Manage Orders</a>
    </div>

    <div class="card">
      <img src="images/menu.png" alt="Menu Items" class="card-icon">
      <a href="menu.php">Menu Items</a>
    </div>

    <div class="card">
      <img src="images/inventory.png" alt="Inventory" class="card-icon">
      <a href="inventory_management.php">Inventory</a>
    </div>

    <div class="card">
      <img src="images/payment.png" alt="Payments" class="card-icon">
      <a href="payments.php">Payments</a>
    </div>

    <!-- Staff Management -->
    <div class="card">
      <img src="images/staff.png" alt="Staff Management" class="card-icon">
      <?php if ($role === 'Manager'): ?>
        <a href="staff.php">Staff Management</a>
      <?php else: ?>
        <div class="card-label not-authorized">Not Authorized</div>
      <?php endif; ?>
    </div>

    <!-- Salary & Expenses -->
    <div class="card">
      <img src="images/salary.png" alt="Salary and Expenses" class="card-icon">
      <?php if ($role === 'Manager'): ?>
        <a href="salaries.php">Salary & Expenses</a>
      <?php else: ?>
        <div class="card-label not-authorized">Not Authorized</div>
      <?php endif; ?>
    </div>
  </div>
</main>

<footer>
  &copy; <?= date("Y") ?> Restoran Badhriah. All rights reserved.
</footer>

</body>
</html>