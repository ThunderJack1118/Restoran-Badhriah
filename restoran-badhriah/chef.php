<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Example role — normally retrieved from DB/login logic
$role = $_SESSION['Role'] ?? 'Chef';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chef Dashboard - Restoran Badriah</title>
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    html, body {
        height: 100%;
        font-family: Arial, sans-serif;
        background: #000;
        color: #fff;
    }
    body {
        display: flex;
        flex-direction: column;
    }
    header {
      background-color: #000;
      padding: 1rem;
      text-align: center;
      font-size: 1.5rem;
      font-weight: 600;
      border-bottom: 1px solid #333;
    }
    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 1200px;
      margin: auto;
      width: 90%;
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

    .logout-btn:hover, .profile-btn:hover {
      background-color: #ddd;
    }
    .content {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px;
        max-width: 1200px;
        width: 90%;
        margin: 0 auto;
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
    .logo-title {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 1.3rem;
      font-weight: 600;
    }
    .welcome {
      font-size: 1.3rem;
      margin: 1rem 0 2rem;
      text-align: center;
      width: 100%;
    }
    .clock-in-out {
        background: #1a1a1a;
        width: 100%;
        max-width: 600px;
        border-radius: 10px;
        padding: 25px 20px;
        margin-bottom: 2rem;
        text-align: center;
        cursor: pointer;
        transition: background 0.3s;
    }
    .clock-in-out:hover {
        background: #333;
    }
    .clock-in-out img {
        width: 50px;
        height: 50px;
        margin-bottom: 15px;
        border-radius: 20%; /* Added for clock icon */
    }
    .clock-in-out .title {
        font-size: 1.3rem;
        font-weight: bold;
        margin-bottom: 8px;
    }
    .clock-in-out .subtitle {
        font-size: 1rem;
        color: #ccc;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1.2rem;
        width: 100%;
        flex: 1;
        place-items: center;
        max-width: 800px;
    }
    .card {
        background: #1a1a1a;
        border-radius: 10px;
        text-align: center;
        padding: 1.8rem 1rem;
        cursor: pointer;
        transition: background 0.3s;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 180px;
    }
    .card:hover {
        background: #333;
    }
    .card img {
        width: 60px;
        height: 60px;
        margin-bottom: 20px;
        border-radius: 30%; /* Increased border radius for card icons */
        object-fit: contain; /* Ensures icons maintain aspect ratio */
        padding: 5px; /* Gives icons some breathing room */
        background: rgba(255, 255, 255, 0.1); /* Subtle background for icons */
    }
    .card-title {
        font-size: 1.2rem;
        font-weight: bold;
        width: 100%;
        word-break: break-word;
    }
    footer {
        background: #111;
        text-align: center;
        padding: 15px;
        font-size: 0.8rem;
        color: #777;
        margin-top: 2rem;
    }
    @media (max-width: 600px) {
      .welcome {
        font-size: 1.1rem;
        margin: 0.8rem 0 1.5rem;
      }
      .clock-in-out {
        padding: 20px 15px;
        margin-bottom: 1.5rem;
      }
      .clock-in-out .title {
        font-size: 1.2rem;
      }
      .grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
      }
      .card {
        padding: 1.5rem 0.8rem;
        min-height: 160px;
      }
      .card img {
        width: 50px;
        height: 50px;
        margin-bottom: 15px;
        border-radius: 30%; /* Consistent on mobile */
      }
      .card-title {
        font-size: 1.1rem;
      }
    }
    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        gap: 1rem;
      }
      .header-buttons {
        width: 100%;
        justify-content: center;
      }
      .logo-title {
        font-size: 1.1rem;
      }
      .logo {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
      }
    }
    @media (min-width: 1200px) {
      .grid {
        grid-template-columns: repeat(3, 1fr);
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

<div class="content">
    <div class="welcome">
        👋 Welcome, <strong><?= htmlspecialchars($_SESSION['Username']) ?></strong> (<?= htmlspecialchars($role) ?>)
    </div>

    <div class="clock-in-out" onclick="window.location.href='workshift.php'">
        <img src="images/clock.png" alt="Clock In / Clock Out">
        <div class="title">Clock In / Clock Out</div>
        <div class="subtitle">Access your shift attendance</div>
    </div>

    <div class="grid">
        <div class="card" onclick="window.location.href='orders.php'">
            <img src="images/orders.png" alt="Order Management">
            <div class="card-title">Order Management</div>
        </div>
        <div class="card" onclick="window.location.href='supplier_management.php'">
            <img src="images/supplier.png" alt="Supplier Management">
            <div class="card-title">Supplier Management</div>
        </div>
        <div class="card" onclick="window.location.href='inventory_management.php'">
            <img src="images/inventory.png" alt="Inventory Management">
            <div class="card-title">Inventory Management</div>
        </div>
    </div>
</div>

<footer>
    &copy; 2025 Restoran Badriah. All rights reserved.
</footer>

</body>
</html>