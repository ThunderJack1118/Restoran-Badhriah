<?php
session_start();
if (!isset($_SESSION['Username']) || $_SESSION['Role'] !== 'Manager') {
    die("❌ Access denied.");
}

require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $role = $_POST['role'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $icPassport = $_POST['icPassport'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = password_hash($_POST['password'] ?? 'default123', PASSWORD_DEFAULT);
    $hireDate = $_POST['hireDate'] ?? date('Y-m-d');
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    $address = $_POST['address'] ?? '';

    // Validate inputs
    if (empty($firstName) || empty($lastName) || empty($role) || empty($email) || 
        empty($phone) || empty($icPassport) || empty($username) || empty($hireDate)) {
        $error = 'All fields except password and address are required!';
    } else {
        // Check if username or email already exists
        $checkQuery = "SELECT StaffID FROM Staff WHERE Username = ? OR Email = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username or Email already exists!';
        } else {
            // Insert new staff
            $insertQuery = "INSERT INTO Staff (FirstName, LastName, Role, Email, Phone, HireDate, IsActive, Address, ICPassportNo, Username, Password) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("ssssssissss", $firstName, $lastName, $role, $email, $phone, $hireDate, $isActive, $address, $icPassport, $username, $password);
            
            if ($stmt->execute()) {
                $success = 'Staff added successfully!';
                // Clear form
                $firstName = $lastName = $role = $email = $phone = $icPassport = $username = $address = '';
                $hireDate = date('Y-m-d');
                $isActive = 1;
            } else {
                $error = 'Error adding staff: ' . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Staff | Restoran Badhriah</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #111;
      color: #fff;
      min-height: 100vh;
      padding: 2rem;
    }
    h1 {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .form-container {
      max-width: 800px;
      margin: 0 auto;
      background: #1a1a1a;
      padding: 2rem;
      border-radius: 8px;
      border: 1px solid #333;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
    }
    input, select, textarea {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #333;
      border-radius: 4px;
      background: #222;
      color: #fff;
    }
    .form-row {
      display: flex;
      gap: 1rem;
    }
    .form-row .form-group {
      flex: 1;
    }
    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .checkbox-group input {
      width: auto;
    }
    .btn {
      padding: 0.75rem 1.5rem;
      background: #fff;
      color: #000;
      border: none;
      border-radius: 4px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 1rem;
    }
    .btn:hover {
      background: #ddd;
    }
    .error {
      color: #f44336;
      margin-bottom: 1rem;
    }
    .success {
      color: #4CAF50;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>

<div class="main">
  <h1>Add New Staff</h1>
  
  <div class="form-container">
    <?php if ($error): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="success"><?= $success ?></div>
    <?php endif; ?>
    
    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label for="firstName">First Name</label>
          <input type="text" id="firstName" name="firstName" value="<?= htmlspecialchars($firstName ?? '') ?>" required>
        </div>
        
        <div class="form-group">
          <label for="lastName">Last Name</label>
          <input type="text" id="lastName" name="lastName" value="<?= htmlspecialchars($lastName ?? '') ?>" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="role">Role</label>
          <select id="role" name="role" required>
            <option value="">Select Role</option>
            <option value="Manager" <?= ($role ?? '') === 'Manager' ? 'selected' : '' ?>>Manager</option>
            <option value="Chef" <?= ($role ?? '') === 'Chef' ? 'selected' : '' ?>>Chef</option>
            <option value="Waiter" <?= ($role ?? '') === 'Waiter' ? 'selected' : '' ?>>Waiter</option>
            <option value="Cashier" <?= ($role ?? '') === 'Cashier' ? 'selected' : '' ?>>Cashier</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="hireDate">Hire Date</label>
          <input type="date" id="hireDate" name="hireDate" value="<?= htmlspecialchars($hireDate ?? date('Y-m-d')) ?>" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="icPassport">IC/Passport Number</label>
          <input type="text" id="icPassport" name="icPassport" value="<?= htmlspecialchars($icPassport ?? '') ?>" required>
        </div>
        
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
        </div>
        
        <div class="form-group">
          <label for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" required>
        </div>
      </div>
      
      <div class="form-group">
        <label for="address">Address</label>
        <textarea id="address" name="address" rows="3"><?= htmlspecialchars($address ?? '') ?></textarea>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Leave blank for default password">
        </div>
        
        <div class="form-group">
          <label>&nbsp;</label>
          <div class="checkbox-group">
            <input type="checkbox" id="isActive" name="isActive" <?= ($isActive ?? 1) ? 'checked' : '' ?>>
            <label for="isActive">Active Staff Member</label>
          </div>
        </div>
      </div>
      
      <div class="form-group">
        <button type="submit" class="btn">Add Staff</button>
        <a href="staff.php" class="btn" style="background: #333; color: #fff; margin-left: 1rem;">Cancel</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>