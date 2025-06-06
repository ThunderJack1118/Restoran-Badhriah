<?php
session_start();
if (!isset($_SESSION['Username']) || $_SESSION['Role'] !== 'Manager') {
    die("❌ Access denied.");
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restoran_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch suppliers for dropdown
$suppliers = [];
$supplierResult = $conn->query("SELECT SupplierID, Name FROM supplier ORDER BY SupplierID");
if ($supplierResult && $supplierResult->num_rows > 0) {
    while ($row = $supplierResult->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Fetch staff for dropdown
$staff = [];
$staffResult = $conn->query("SELECT StaffID, FirstName, LastName FROM staff ORDER BY StaffID");
if ($staffResult && $staffResult->num_rows > 0) {
    while ($row = $staffResult->fetch_assoc()) {
        $staff[] = $row;
    }
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $supplierId = $_POST['supplier_id'] ?? 0;
    $staffId = $_POST['staff_id'] ?? 0;
    $description = $_POST['description'] ?? '';

    // Validate input
    if (empty($category) || $amount <= 0 || empty($expenseDate)) {
        $message = '<div class="error">❌ Please fill all required fields correctly.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO expense (Category, Amount, ExpenseDate, SupplierID, StaffID, Description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsiis", $category, $amount, $expenseDate, $supplierId, $staffId, $description);
        
        if ($stmt->execute()) {
            $message = '<div class="success">✔️ Expense record added successfully!</div>';
            // Clear POST data to avoid resubmission
            unset($_POST);
            header("Refresh: 2; url=expenses.php");
        } else {
            $message = '<div class="error">❌ Error adding expense: ' . $conn->error . '</div>';
        }
        $stmt->close();
    }
}

// Handle filter
$filterDate = $_GET['filter_date'] ?? '';
$filterClause = '';
if (!empty($filterDate)) {
    $filterClause = "WHERE DATE_FORMAT(ExpenseDate, '%Y-%m') = '" . $conn->real_escape_string($filterDate) . "'";
}

// Fetch expenses from DB with staff names
$sql = "SELECT e.ExpenseID, e.Category, e.Amount, e.ExpenseDate, e.Description, 
               e.SupplierID, e.StaffID, CONCAT(s.FirstName, ' ', s.LastName) as StaffName, 
               sup.Name as SupplierName
        FROM expense e
        LEFT JOIN staff s ON e.StaffID = s.StaffID
        LEFT JOIN supplier sup ON e.SupplierID = sup.SupplierID
        $filterClause 
        ORDER BY e.ExpenseDate DESC";
$result = $conn->query($sql);

$filteredExpenses = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $filteredExpenses[] = [
            'ExpenseID' => 'EP' . str_pad($row['ExpenseID'], 3, '0', STR_PAD_LEFT),
            'Category' => $row['Category'],
            'Amount' => $row['Amount'],
            'Date' => $row['ExpenseDate'],
            'SupplierID' => 'SP' . str_pad($row['SupplierID'], 3, '0', STR_PAD_LEFT),
            'SupplierName' => $row['SupplierName'] ?? 'N/A',
            'StaffID' => 'RB'. str_pad($row['StaffID'], 3, '0', STR_PAD_LEFT),
            'StaffName' => $row['StaffName'] ?? 'N/A',
            'Description' => $row['Description'],
            // Store original IDs for database operations
            'rawExpenseID' => $row['ExpenseID'],
            'rawSupplierID' => $row['SupplierID']
        ];
    }
} else {
    $filteredExpenses = []; // No data or query error
}

$shouldPrint = $_GET['print'] ?? false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Expenses | Restoran Badhriah</title>
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
    
    /* New styles */
    .add-expense-form { 
        background: #222; 
        padding: 1.5rem; 
        border-radius: 8px; 
        margin-bottom: 2rem;
        border: 1px solid #333;
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border-radius: 6px;
        border: 1px solid #444;
        background: #333;
        color: #fff;
    }
    .form-group textarea {
        min-height: 80px;
    }
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
    }
    .success {
        color: #4CAF50;
        background: #1a1a1a;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        border: 1px solid #4CAF50;
    }
    .error {
        color: #f44336;
        background: #1a1a1a;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        border: 1px solid #f44336;
    }
    .staff-name {
        font-weight: 600;
    }
    .staff-id {
        font-size: 0.8rem;
        color: #aaa;
    }
  </style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <div style="text-align: center;">
    <img src="images/logo.png" alt="Restoran Badhriah Logo"
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
  <a href="salaries.php" class="active">💰 Salaries and Expenses</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main" id="main">
  <button id="toggleBtn" class="toggle-btn">☰ Menu</button>

  <h1>Business Expenses | Restoran Badhriah</h1>

  <?php echo $message; ?>

  <div class="add-expense-form">
      <h2 style="margin-bottom: 1.5rem;">➕ Add New Expense</h2>
      <form method="POST" action="">
          <div class="form-row">
              <div class="form-group">
                  <label for="category">Category *</label>
                  <select name="category" id="category" required class="form-control">
                      <option value="">Select Category</option>
                      <option value="Ingredient Purchase">Ingredient Purchase</option>
                      <option value="Salary">Salary</option>
                      <option value="Utility">Utility</option>
                      <option value="Other">Other</option>
                  </select>
              </div>
              
              <div class="form-group">
                  <label for="amount">Amount (RM) *</label>
                  <input type="number" name="amount" id="amount" step="0.01" min="0" required 
                        class="form-control" placeholder="0.00">
              </div>
          </div>
          
          <div class="form-row">
              <div class="form-group">
                  <label for="expense_date">Date *</label>
                  <input type="date" name="expense_date" id="expense_date" required
                        class="form-control">
              </div>
              
              <div class="form-group">
                  <label for="supplier_id">Supplier</label>
                  <select name="supplier_id" id="supplier_id" class="form-control">
                      <option value="0">-- Select Supplier --</option>
                      <?php foreach ($suppliers as $supplier): ?>
                          <option value="<?= $supplier['SupplierID'] ?>">
                              <?= htmlspecialchars($supplier['Name']) ?>
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>
          </div>
          
          <div class="form-row">
              <div class="form-group">
                  <label for="staff_id">Staff Responsible</label>
                  <select name="staff_id" id="staff_id" class="form-control">
                      <option value="0">-- Select Staff --</option>
                      <?php foreach ($staff as $staffMember): ?>
                          <option value="<?= $staffMember['StaffID'] ?>">
                              <?= htmlspecialchars($staffMember['FirstName'] . ' ' . $staffMember['LastName']) ?>
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>
              
              <div class="form-group">
                  <label for="payment_method">Payment Method</label>
                  <select name="payment_method" id="payment_method" class="form-control">
                      <option value="Cash">Cash</option>
                      <option value="Bank Transfer">Bank Transfer</option>
                      <option value="Credit Card">Credit Card</option>
                      <option value="Other">Other</option>
                  </select>
              </div>
          </div>
          
          <div class="form-group full-width">
              <label for="description">Description</label>
              <textarea name="description" id="description" class="form-control" 
                        rows="3" placeholder="Enter expense details"></textarea>
          </div>
          
          <div class="form-actions">
              <button type="reset" class="btn btn-secondary">Clear Form</button>
              <button type="submit" class="btn btn-primary">Save Expense</button>
          </div>
      </form>
  </div>

  <table id="expenseTable">
    <thead>
      <tr>
        <th>ExpenseID</th>
        <th>Category</th>
        <th>Amount (RM)</th>
        <th>Date</th>
        <th>Supplier</th>
        <th>Staff</th>
        <th>Description</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
        <?php foreach ($filteredExpenses as $expense): ?>
        <tr>
            <td><?= $expense['ExpenseID'] ?></td>
            <td><?= htmlspecialchars($expense['Category']) ?></td>
            <td><?= number_format($expense['Amount'], 2) ?></td>
            <td><?= htmlspecialchars($expense['Date']) ?></td>
            <td>
                <?= htmlspecialchars($expense['SupplierName']) ?>
                <div class="staff-id"><?= $expense['SupplierID'] ?></div>
            </td>
            <td>
                    <div class="staff-name"><?= htmlspecialchars($expense['StaffName']) ?></div>
                    <div class="staff-id"><?= $expense['StaffID'] ?></div>
            </td>
            <td><details><summary><?= htmlspecialchars(substr($expense['Description'], 0, 30)) ?><?= strlen($expense['Description']) > 30 ? '...' : '' ?></summary><?= htmlspecialchars($expense['Description']) ?></details></td>
            <td class="actions">
                <a href="#" class="btn btn-small" onclick="printRow(this)">🖶️ Print</a>
                <a href="delete_expense.php?id=<?= $expense['rawExpenseID'] ?>" class="btn btn-small" onclick="return confirm('Are you sure you want to delete this expense record?')">🗑️ Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredExpenses)): ?>
        <tr>
            <td colspan="8" style="text-align: center;">No expenses found</td>
        </tr>
        <?php endif; ?>
    </tbody>
  </table>

  <div class="footer">
    &copy; <?= date('Y') ?> Restoran Badhriah. All rights reserved.
  </div>
</div>

<script>
  const toggleBtn = document.getElementById('toggleBtn');
  const sidebar = document.getElementById('sidebar');
  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('hidden');
  });

  // Set today's date as default
  document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('expense_date').value) {
      document.getElementById('expense_date').valueAsDate = new Date();
    }
  });

  function printRow(el) {
    const row = el.closest('tr').cloneNode(true);
    // Remove action buttons for printing
    row.querySelector('td.actions').innerHTML = '';
    const html = `<html><head><title>Print</title></head><body><table border="1">${row.outerHTML}</table></body></html>`;
    const w = window.open('', '', 'width=800,height=600');
    w.document.write(html);
    w.document.close();
    w.print();
  }

  function printThisMonth() {
    const now = new Date();
    const month = now.toISOString().slice(0, 7);
    window.location.href = 'expenses.php?filter_date=' + month + '&print=true';
  }

  function printThisYear() {
    const table = document.getElementById('expenseTable').outerHTML;
    const html = `<html><head><title>Annual Expenses Report</title>
                 <style>table { border-collapse: collapse; width: 100%; }
                 th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                 th { background-color: #f2f2f2; }</style></head>
                 <body><h1>Annual Expenses Report - Restoran Badhriah</h1>
                 <p>Generated on: ${new Date().toLocaleDateString()}</p>
                 ${table}</body></html>`;
    const w = window.open('', '', 'width=1000,height=600');
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