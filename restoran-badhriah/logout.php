<?php
session_start();

// If logout is confirmed, destroy session and redirect
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logout | Restoran Badhriah</title>
  <style>
    body {
      background: #111;
      color: #fff;
      font-family: 'Inter', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    .logout-box {
      background: #1a1a1a;
      padding: 2rem 3rem;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 0 15px rgba(255,255,255,0.05);
    }

    .logout-box h2 {
      margin-bottom: 1rem;
    }

    .btn {
      padding: 0.6rem 1.2rem;
      margin: 0.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
    }

    .btn-confirm {
      background: #fff;
      color: #000;
    }

    .btn-cancel {
      background: #444;
      color: #fff;
    }

    .btn:hover {
      opacity: 0.85;
    }
  </style>
</head>
<body>

<div class="logout-box">
  <h2>Confirm Logout</h2>
  <p>Are you sure you want to log out?</p>
  <button class="btn btn-confirm" onclick="confirmLogout()">Yes, Logout</button>
  <button class="btn btn-cancel" onclick="cancelLogout()">Cancel</button>
</div>

<script>
  function confirmLogout() {
    window.location.href = 'logout.php?confirm=yes';
  }

  function cancelLogout() {
    window.location.href = 'home.php'; // or replace with previous page
  }
</script>

</body>
</html>
