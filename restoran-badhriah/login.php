<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['Username']);
    $password = $_POST['Password'];

    // Fetch user by username only
    $stmt = $conn->prepare("SELECT * FROM staff WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify hashed password
        if (password_verify($password, $user['Password'])) {
            $_SESSION['Username'] = $user['Username'];
            $_SESSION['StaffID'] = $user['StaffID'];
            $_SESSION['Role'] = $user['Role'];
            
            // Redirect based on role
            switch ($user['Role']) {
                case 'Waiter':
                    header("Location: waiter.php");
                    break;
                case 'Cashier':
                    header("Location: cashier.php");
                    break;
                case 'Chef':
                    header("Location: chef.php");
                    break;
                case 'Manager':
                default:
                    header("Location: home.php");
                    break;
            }
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - Restoran Badhriah</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background-color: #111;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }

    .login-box {
      background: #1a1a1a;
      padding: 2rem;
      border-radius: 12px;
      width: 200%;
      max-width: 400px;
      box-shadow: 0 0 15px rgba(255, 255, 255, 0.05);
    }

    .login-box h2 {
      margin-bottom: 1.5rem;
      font-weight: 600;
      text-align: center;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      color: #ccc;
    }

    .form-group input {
      width: 94%;
      padding: 0.75rem;
      border: none;
      border-radius: 8px;
      background-color: #333;
      color: #fff;
    }

    .form-group input:focus {
      outline: 2px solid #555;
    }

    .btn {
      width: 100%;
      padding: 1rem;
      background-color: #fff;
      color: #000;
      border: none;
      border-radius: 8px;
      font-weight: 700;
      font-size: 18px;
      cursor: pointer;
      transition: background 0.3s;
      margin-top: 2rem;
    }

    .btn:hover {
      background-color: #ddd;
    }

    .error {
      color: #f44336;
      text-align: center;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>

<div class="login-box">
  <div style="text-align: center;">
   <img src="images/logo.png" alt="Restoren Badhriah Logo"
        style="width: 100px; height: 100px; border-radius: 50%; border: 2px solid #fff; object-fit: contain; margin-bottom: 1rem;">
  </div>

  <h2>Restoran Badhriah Login</h2>

  <?php if ($error): ?>
    <div class="error"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <div class="form-group">
      <label for="Username">Username</label>
      <input type="text" name="Username" required />
    </div>

    <div class="form-group">
      <label for="Password">Password</label>
      <input type="Password" name="Password" required />
    </div>

    <button class="btn" type="submit">Login</button>
  </form>

  <p style="text-align:center; margin-top:1rem;">
    Don’t have an account? <a href="register.php" style="color:#ccc;">Register</a>
  </p>
</div>


</body>
</html>
