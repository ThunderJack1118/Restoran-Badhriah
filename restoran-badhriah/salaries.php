<?php
session_start();
if (!isset($_SESSION['Username']) || $_SESSION['Role'] !== 'Manager') {
    die("❌ Access denied.");
}

// Only redirect to modal if coming from external page AND no explicit noPrompt flag
$shouldRedirect = !isset($_GET['noPrompt']) && 
                 !isset($_GET['print']) && 
                 !isset($_POST['add_salary']) && 
                 (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], 'salaries.php') === false);

if ($shouldRedirect && !isset($_GET['show_modal'])) {
    header("Location: salaries.php?show_modal=1");
    exit();
}

// Sample data (replace with DB in production)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restoran_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission for adding new salary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_salary'])) {
    $staffID = $_POST['staff_id'];
    $amount = $_POST['amount'];
    $paymentDate = $_POST['payment_date'];
    $paymentType = $_POST['payment_type'];
    $description = $_POST['description'];
    $shiftHour = $_POST['shift_hour'];
    
    $stmt = $conn->prepare("INSERT INTO salary (StaffID, Amount, PaymentDate, PaymentType, Description, ShiftHour) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idsssi", $staffID, $amount, $paymentDate, $paymentType, $description, $shiftHour);
    
    if ($stmt->execute()) {
        header("Location: salaries.php?success=1&noPrompt=1");
        exit();
    } else {
        $error = "Error adding salary record: " . $stmt->error;
    }
}

// Get staff list for dropdown
$staffList = [];
$staffResult = $conn->query("SELECT StaffID, FirstName, LastName FROM staff ORDER BY LastName, FirstName");
if ($staffResult && $staffResult->num_rows > 0) {
    while ($row = $staffResult->fetch_assoc()) {
        $staffList[] = $row;
    }
}

$filterDate = $_GET['filter_date'] ?? '';
$filterClause = '';

if (!empty($filterDate)) {
    $filterClause = "WHERE PaymentDate LIKE '" . $conn->real_escape_string($filterDate) . "%'";
}

$sql = "SELECT s.SalaryID, s.StaffID, st.FirstName, st.LastName, s.Amount, s.PaymentDate, s.PaymentType, s.Description, s.ShiftHour 
        FROM salary s
        JOIN staff st ON s.StaffID = st.StaffID
        $filterClause 
        ORDER BY s.PaymentDate DESC";
$result = $conn->query($sql);

$filteredSalaries = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $filteredSalaries[] = [
            'SalaryID' => 'SL' . str_pad($row['SalaryID'], 3, '0', STR_PAD_LEFT),
            'StaffID' => 'RB' . str_pad($row['StaffID'], 3, '0', STR_PAD_LEFT),
            'FullName' => htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']),
            'Amount' => $row['Amount'],
            'PaymentDate' => $row['PaymentDate'],
            'PaymentType' => $row['PaymentType'],
            'Description' => $row['Description'],
            'ShiftHour' => $row['ShiftHour'],
            'rawSalaryID' => $row['SalaryID'],
            'rawStaffID' => $row['StaffID']
        ];
    }
} else {
    $filteredSalaries = []; 
}

$shouldPrint = $_GET['print'] ?? false;
$successMessage = isset($_GET['success']) ? "Salary record added successfully!" : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Salaries | Restoran Badhriah</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #111; color: #fff; min-height: 100vh; }
    .sidebar { width: 240px; background: #000; padding: 2rem 1rem; border-right: 1px solid #333; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; transition: transform 0.3s ease; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar h2 { color: #fff; font-size: 1.2rem; margin-bottom: 2rem; text-align: center; }
    .sidebar a { display: block; color: #ccc; text-decoration: none; padding: 0.75rem 1rem; border-radius: 8px;margin-bottom: 0.5rem; font-weight: 500; transition: background 0.3s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 10px;}
    .sidebar a:hover, .sidebar a.active { background: #222; color: #fff; }
    .main { margin-left: 220px; padding: 2rem; transition: margin-left 0.3s ease; }
    .sidebar.hidden + .main { margin-left: 0; }
    .toggle-btn { position: fixed; top: 1rem; left: 1rem; padding: 0.5rem 1rem; background: #fff; color: #000; border: none; border-radius: 8px; font-size: 1.1rem; cursor: pointer; z-index: 1100; }
    .sidebar:not(.hidden) ~ .main #toggleBtn { left: 260px; }
    h1 { text-align: center; margin-top: 3rem; margin-bottom: 1.5rem; font-size: 2rem; }
    .btn { padding: 0.5rem 1rem; background: #fff; color: #000; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.3s; text-decoration: none; }
    .btn:hover { background: #ddd; }
    .top-bar, form { display: flex; justify-content: flex-end; margin-bottom: 1.5rem; gap: 1rem; }
    table { width: 100%; border-collapse: collapse; background-color: #1a1a1a; border: 1px solid #333; }
    th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #333; }
    th { background-color: #000; }
    td.actions { display: flex; gap: 0.5rem; }
    .btn-small { padding: 0.4rem 0.7rem; font-size: 0.9rem; }
    details { background: #222; padding: 0.5rem; border-radius: 6px; margin-top: 0.5rem; }
    details summary { cursor: pointer; font-weight: bold; }
    .footer { text-align: center; margin-top: 3rem; color: #777; font-size: 0.9rem; }
    
    /* Add salary form styles */
    .add-salary-form {
      background: #222;
      padding: 1.5rem;
      border-radius: 8px;
      margin-bottom: 2rem;
      border: 1px solid #333;
    }
    .add-salary-form h2 {
      margin-bottom: 1rem;
      color: #fff;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      color: #ccc;
    }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%;
      padding: 0.5rem;
      border-radius: 4px;
      border: 1px solid #444;
      background: #333;
      color: #fff;
    }
    .form-row {
      display: flex;
      gap: 1rem;
    }
    .form-row .form-group {
      flex: 1;
    }
    .success-message {
      background: #4CAF50;
      color: white;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
      text-align: center;
    }
    .error-message {
      background: #f44336;
      color: white;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
      text-align: center;
    }
  </style>
</head>
<body>

<div id="pageChoiceModal" style="
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 9999;
">
  <div style="
    background: #fff;
    color: #000;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    width: 300px;
  ">
    <h2>Select Page</h2>
    <p>Where do you want to go?</p>
    <button onclick="goToPage('salaries.php')" style="margin: 1rem; padding: 0.5rem 1rem;">💰 Salary Page</button>
    <button onclick="goToPage('expenses.php')" style="margin: 1rem; padding: 0.5rem 1rem;">📄 Expenses Page</button>
  </div>
</div>

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
  <a href="staff.php">💼 Staff Management</a>
  <a href="salaries.php?noPrompt=1" class="active">💰 Salaries and Expenses</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main" id="main">
  <button id="toggleBtn" class="toggle-btn">☰ Menu</button>

  <h1>Salary Payments | Restoran Badhriah</h1>

  <?php if ($successMessage): ?>
    <div class="success-message"><?= $successMessage ?></div>
  <?php endif; ?>
  
  <?php if (isset($error)): ?>
    <div class="error-message"><?= $error ?></div>
  <?php endif; ?>

  <!-- Add Salary Form -->
  <div class="add-salary-form">
    <h2>➕ Add New Salary Record</h2>
    <form method="POST" action="salaries.php">
      <div class="form-row">
        <div class="form-group">
          <label for="staff_id">Staff Member</label>
          <select name="staff_id" id="staff_id" required>
              <option value="">Select Staff</option>
              <?php foreach ($staffList as $staff): ?>
                  <option value="<?= $staff['StaffID'] ?>">
                      RB<?= str_pad($staff['StaffID'], 3, '0', STR_PAD_LEFT) ?> - 
                      <?= htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']) ?>
                  </option>
              <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="amount">Amount (RM)</label>
          <input type="number" name="amount" id="amount" step="0.01" min="0" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="payment_date">Payment Date</label>
          <input type="date" name="payment_date" id="payment_date" required>
        </div>
        <div class="form-group">
          <label for="payment_type">Payment Type</label>
          <select name="payment_type" id="payment_type" required>
            <option value="">Select Type</option>
            <option value="Monthly">Monthly</option>
            <option value="Bonus">Bonus</option>
            <option value="OverTime">OverTime</option>
          </select>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="shift_hour">Total Shift Hours</label>
          <input type="number" name="shift_hour" id="shift_hour" min="0" step="0.5" required>
        </div>
        <div class="form-group">
          <label for="description">Description</label>
          <input type="text" name="description" id="description" required>
        </div>
      </div>
      
      <button type="submit" name="add_salary" class="btn">💾 Save Salary Record</button>
    </form>
  </div>

  <form method="GET">
    <input type="hidden" name="noPrompt" value="1">
    <label for="filter_date">📅 Filter by Month:</label>
    <input type="month" name="filter_date" id="filter_date" value="<?= htmlspecialchars($filterDate) ?>">
    <button type="submit" class="btn">🔍 Filter</button>
    <a href="salaries.php?noPrompt=1" class="btn">🔄 Reset</a>
  </form>

  <div class="top-bar">
    <a href="#" onclick="printThisYear()" class="btn">🖶️ Print This Year</a>
    <a href="#" onclick="printThisMonth()" class="btn">🖶️ Print This Month</a>
  </div>

  <table id="salaryTable">
    <thead>
      <tr>
        <th>SalaryID</th>
        <th>Staff Name</th>
        <th>Amount (RM)</th>
        <th>Payment Date</th>
        <th>Payment Type</th>
        <th>Description</th>
        <th>Actions</th>
      </tr>
    </thead>
      <tbody>
          <?php foreach ($filteredSalaries as $salary): ?>
          <tr>
              <td><?= $salary['SalaryID'] ?></td>
              <td>
                  <?= $salary['FullName'] ?>
                  <div style="font-size: 0.8em; color: #aaa;"><?= $salary['StaffID'] ?></div>
              </td>
              <td><?= number_format($salary['Amount'], 2) ?></td>
              <td><?= htmlspecialchars($salary['PaymentDate']) ?></td>
              <td><?= htmlspecialchars($salary['PaymentType']) ?></td>
              <td>
                  <details>
                      <summary><?= htmlspecialchars($salary['Description']) ?></summary>
                      <div>Total Shift Hours: <?= htmlspecialchars($salary['ShiftHour']) ?> hrs</div>
                  </details>
              </td>
              <td class="actions">
                  <a href="#" class="btn btn-small" onclick="printRow(this)">🖶️ Print</a>
                  <a href="delete_salary.php?id=<?= $salary['rawSalaryID'] ?>" class="btn btn-small" onclick="return confirm('Are you sure you want to delete this salary record?')">🗑️ Delete</a>
              </td>
          </tr>
          <?php endforeach; ?>
      </tbody>
  </table>

  <div class="footer">
    &copy; <?= date('Y') ?> Restoran Badhriah. All rights reserved.
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Sidebar toggle logic
  const toggleBtn = document.getElementById('toggleBtn');
  const sidebar = document.getElementById('sidebar');
  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('hidden');
  });

  // Modal control logic
  const urlParams = new URLSearchParams(window.location.search);
  const shouldShowModal = urlParams.get('show_modal');
  
  if (shouldShowModal === '1') {
    document.getElementById('pageChoiceModal').style.display = 'flex';
    // Clean the URL without reloading
    history.replaceState({}, document.title, window.location.pathname);
  }
});

function goToPage(page) {
  // Close the modal
  document.getElementById('pageChoiceModal').style.display = 'none';
  
  if (page === 'salaries.php') {
    // Navigate to salaries with noPrompt flag
    window.location.href = 'salaries.php?noPrompt=1';
  } else {
    // For other pages, navigate normally
    window.location.href = page;
  }
}

function printRow(el) {
  const row = el.closest('tr').outerHTML;
  const html = `<html><head><title>Print</title></head><body><table border="1">${row}</table></body></html>`;
  const w = window.open('', '', 'width=800,height=600');
  w.document.write(html);
  w.document.close();
  w.print();
}

function printThisMonth() {
  const now = new Date();
  const month = now.toISOString().slice(0, 7);
  window.location.href = 'salaries.php?filter_date=' + month + '&print=true&noPrompt=1';
}

function printThisYear() {
  const table = document.getElementById('salaryTable').outerHTML;
  const html = `<html><head><title>Print</title></head><body>${table}</body></html>`;
  const w = window.open('', '', 'width=800,height=600');
  w.document.write(html);
  w.document.close();
  w.print();
}

<?php if ($shouldPrint): ?>
  window.onload = function() {
    window.print();
  };
<?php endif; ?>
</script>

</body>
</html>