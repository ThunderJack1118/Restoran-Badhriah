<?php
require 'db.php';
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = trim($_POST['FirstName']);
    $lastName = trim($_POST['LastName']);
    $role = $_POST['Role'];
    $phone = trim($_POST['Phone']);
    $email = trim($_POST['Email']);
    $hireDate = $_POST['HireDate'];
    $isActive = 1; // Automatically active
    $address = trim($_POST['Address']);
    $icPassport = trim($_POST['ICPassportNo']);
    $user = trim($_POST['Username']);
    $pass = password_hash($_POST['Password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO staff 
        (FirstName, LastName, Role, Phone, Email, HireDate, IsActive, Address, ICPassportNo, Username, Password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssissss", $firstName, $lastName, $role, $phone, $email, $hireDate, $isActive, $address, $icPassport, $user, $pass);

    if ($stmt->execute()) {
        $success = "Registration successful! <a href='login.php'>Login now</a>";
    } else {
        $error = "An error occurred or username already exists.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Register</title>
  <style>
      body {
        background: #111;
        color: #fff;
        font-family: Inter, sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        min-height: 100vh;
        overflow-y: auto;
        font-size: 0.95rem;
      }

      .box {
        background: #1a1a1a;
        padding: 1.5rem 2rem;
        border-radius: 10px;
        width: 100%;
        max-width: 420px;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.05);
      }

      label {
        display: block;
        margin-top: 0.75rem;
        font-weight: 600;
        font-size: 0.95rem;
      }

      input,
      select,
      textarea {
        display: block;
        width: 100%;
        margin-top: 0.3rem;
        padding: 0.6rem 0.75rem;
        background: #333;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 0.95rem;
        box-sizing: border-box;
        appearance: none;
        -webkit-appearance: none;
      }

      select {
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='white' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 12px;
        padding-right: 2rem;
      }

      .btn {
        width: 100%;
        background: #fff;
        color: #000;
        padding: 0.6rem;
        border: none;
        border-radius: 6px;
        font-weight: bold;
        margin-top: 1.2rem;
        font-size: 0.95rem;
      }

      .msg {
        text-align: center;
        margin-top: 1rem;
      }

  </style>

</head>

<body>
  <div class="box">
    <h2>Register Account</h2>
    <form method="POST">
      <label for="FirstName">First Name</label>
      <input type="text" id="FirstName" name="FirstName" required>

      <label for="LastName">Last Name</label>
      <input type="text" id="LastName" name="LastName" required>

      <label for="Role">Role</label>
      <select id="Role" name="Role" required>
        <option value="">Select Role</option>
        <option value="Waiter">Waiter</option>
        <option value="Cashier">Cashier</option>
        <option value="Chef">Chef</option>
        <option value="Manager">Manager</option>
      </select>

      <label for="Phone">Phone Number</label>
      <input type="tel" id="Phone" name="Phone" placeholder="e.g. 012-3456789" required>

      <label for="Email">Email</label>
      <input type="email" id="Email" name="Email" required>

      <label for="hire_date">Hire Date</label>
      <input type="date" id="HireDate" name="HireDate" required>

      <label for="Address">Address</label>
      <textarea id="Address" name="Address" required></textarea>

      <label for="ICPassportNo">IC/Passport No</label>
      <input type="text" id="ICPassportNo" name="ICPassportNo" required>

      <label for="Username">Username</label>
      <input type="text" id="Username" name="Username" required>

      <label for="Password">Password</label>
      <input type="password" id="Password" name="Password" required>

      <button class="btn" type="submit">Register</button>
    </form>

    <div class="msg">
      <?php if ($error) echo "<p style='color:#f44336;'>$error</p>"; ?>
      <?php if ($success) echo "<p style='color:#4caf50;'>$success</p>"; ?>
    </div>
  </div>
  <script>
    document.getElementById("Phone").addEventListener("input", function (e) {
      let x = e.target.value.replace(/\D/g, ''); // remove non-digits
      if (x.length >= 3 && x.length <= 11) {
        e.target.value = x.slice(0, 3) + '-' + x.slice(3);
      } else {
        e.target.value = x;
      }
    });
  </script>
</body>
</html>


