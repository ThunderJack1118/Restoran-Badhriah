<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Example role — normally retrieved from DB/login logic
$role = $_SESSION['Role'] ?? 'Cashier';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cashier Dashboard | Restoran Badhriah</title>
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
    }

    .welcome {
      font-size: 1.5rem;
      margin-bottom: 1.5rem;
      text-align: center;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin-top: 1.5rem;
    }

    .grid-row-center {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;

      
    }
    
    .wide-card {
      width: 350px;
      position: relative;
    }

    .card {
      background-color:rgb(49, 49, 49);
      padding: 1rem;
      border-radius: 8px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(255, 255, 255, 0.03);
      transition: all 0.25s ease;
      border: 1px solid transparent;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .card:hover {
      transform: translateY(-3px);
      border-color: #444;
    }

    .card a {
      text-decoration: none;
      color: #fff;
      font-weight: 600;
      display: block;
      font-size: 1.2rem;
      margin-top: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      transition: background 0.3s;
    }

    .card a:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }

    footer {
      text-align: center;
      padding: 0.8rem;
      color: #888;
      font-size: 1rem;
      background-color: #000;
      border-top: 1px solid #333;
      margin-top: auto;
    }

    @media (max-width: 600px) {
      .welcome {
        font-size: 1.2rem;
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

    .logout-btn:hover,.profile-btn:hover {
      background-color: #ddd;
    }

    .card-icon {
      width: 80px;
      height: 80px;
      margin-bottom: 0.8rem;
      object-fit: contain;
      border-radius: 8px;
      background-color: #fff;
    }

    .card-desc {
      color: #aaa;
      font-size: 0.9rem;
      margin-top: 0.5rem;
      flex-grow: 1;
      display: flex;
      align-items: center;
    }

    .action-card {
    background: linear-gradient(135deg, #4CAF50, #45a049);
      border: none;
      padding: 1.5rem;
      border-radius: 12px;
      color: white;
      cursor: pointer;
      transition: all 0.25s ease;
    }

    .action-title {
      font-size: 1.4rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    .action-subtitle {
      font-size: 1rem;
      opacity: 0.9;
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
    <!-- Start New Order Card -->
         <a href="cashier_payment.php" class="action-card neworder-card" style="text-decoration: none; color: white;">
      <div class="action-title">🛒 Start New Order</div>
      <div class="action-subtitle">Process customer orders and payments</div>

          <a href="take_order.php" class="action-card" style="text-decoration: none; color: white;">
      <div class="action-title">📝 Take Customer Order</div>
      <div class="action-subtitle">Start new order for customers</div>
    </a>
    </a>
    </div>
  </div>

    <div class="grid">
    <!-- Clock In/Out Card -->
    <div class="card">
      <img src="images/clock.png" alt="Clock In/Out" class="card-icon">
      <a href="workshift.php">Clock In/Out</a>
    </div>
    <!-- View Orders -->
    <div class="card">
      <img src="images/orders.png" alt="View Orders" class="card-icon">
      <a href="orders.php">View Orders</a>
    </div>

    <!-- Menu & Prices -->
    <div class="card">
      <img src="images/menu.png" alt="Menu Items" class="card-icon">
      <a href="menu_cashier.php">Menu Items</a>
    </div>

    <!-- Print Receipts -->
    <div class="card">
      <img src="images/payment.png" alt="Payment History" class="card-icon">
      <a href="payments.php">Payment History</a>
    </div>

    <!-- Process Refunds -->
    <div class="card">
      <img src="images/refund.png" alt="Process Refunds" class="card-icon">
      <a href="refunds.php">Process Refunds</a>
    </div>

    <!-- Daily Sales -->
    <div class="card">
      <img src="images/report.png" alt="Overall Report" class="card-icon">
      <a href="reports.php">Overall Report</a>
    </div>
  </div>
</main>

<footer>
  &copy; <?= date("Y") ?> Restoran Badhriah. All rights reserved.
</footer>

</body>
</html>