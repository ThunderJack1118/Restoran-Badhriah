<?php
session_start();
if (!isset($_SESSION['Username'])) {
    die("Access denied.");
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restoran_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user role from session
$userRole = $_SESSION['Role'] ?? 'Staff';

// Get active tab from query or default to financial
$activeTab = $_GET['tab'] ?? 'financial';

// Get filter month from query or use current
$filterDate = $_GET['filter_date'] ?? date('Y-m');

// Initialize variables
$report = [];
$customerData = [];
$averageRating = 0;
$feedbackCount = 0;

// Financial Report Data
if ($activeTab === 'financial') {
    // First check if the view exists
    $viewCheck = $conn->query("SHOW FULL TABLES IN `$dbname` WHERE TABLE_TYPE LIKE 'VIEW'");
    $viewExists = false;
    $viewName = '';

    while ($row = $viewCheck->fetch_array()) {
        if (strtolower($row[0]) === 'financial_summary' || strtolower($row[0]) === 'financial_summary') {
            $viewName = $row[0];
            $viewExists = true;
            break;
        }
    }

    if ($viewExists) {
        // Use the view if it exists
        $sql = "
        SELECT 
            SUM(TotalIncome) AS total_sales,
            SUM(TotalOutcome) AS total_expenses,
            (SELECT SUM(Amount) FROM salary WHERE DATE_FORMAT(PaymentDate, '%Y-%m') = ?) AS total_salaries,
            (SUM(TotalIncome) - SUM(TotalOutcome) - (SELECT SUM(Amount) FROM salary WHERE DATE_FORMAT(PaymentDate, '%Y-%m') = ?)) AS net_profit
        FROM `$viewName`
        WHERE DATE_FORMAT(Date, '%Y-%m') = ?
        ";
    } else {
        // Fallback to original query if view doesn't exist
        $sql = "
        SELECT 
            (SELECT SUM(Amount) FROM payment WHERE DATE_FORMAT(PaymentDateTime, '%Y-%m') = ?) AS total_sales,
            (SELECT SUM(Amount) FROM expense WHERE DATE_FORMAT(ExpenseDate, '%Y-%m') = ?) AS total_expenses,
            (SELECT SUM(Amount) FROM salary WHERE DATE_FORMAT(PaymentDate, '%Y-%m') = ?) AS total_salaries,
            (
                (SELECT SUM(Amount) FROM payment WHERE DATE_FORMAT(PaymentDateTime, '%Y-%m') = ?) -
                (SELECT SUM(Amount) FROM expense WHERE DATE_FORMAT(ExpenseDate, '%Y-%m') = ?) -
                (SELECT SUM(Amount) FROM salary WHERE DATE_FORMAT(PaymentDate, '%Y-%m') = ?)
            ) AS net_profit
        ";
    }

    $stmt = $conn->prepare($sql);
    if ($viewExists) {
        $stmt->bind_param("sss", $filterDate, $filterDate, $filterDate);
    } else {
        $stmt->bind_param("ssssss", $filterDate, $filterDate, $filterDate, $filterDate, $filterDate, $filterDate);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();

    // Handle case where no data is found
    if (!$report) {
        $report = [
            'total_sales' => 0,
            'total_expenses' => 0,
            'total_salaries' => 0,
            'net_profit' => 0
        ];
    }

    // Only managers can insert reports
    if ($userRole === 'Manager') {
        $insert = $conn->prepare("INSERT INTO report (ReportMonth, ReportPeriod, TotalSales, TotalExpenses, TotalSalaries) VALUES (?, ?, ?, ?, ?)");
        $reportYear = substr($filterDate, 0, 4);
        $insert->bind_param("issdd", $reportYear, $filterDate, $report['total_sales'], $report['total_expenses'], $report['total_salaries']);
        $insert->execute();
    }
}

// Customer Data Analysis
// Customer Data Analysis - FIXED VERSION
if ($activeTab === 'customer') {
    // Get average rating and feedback count
    $ratingQuery = "SELECT AVG(Rating) as avg_rating, COUNT(*) as feedback_count 
                    FROM feedback 
                    WHERE DATE_FORMAT(FeedbackDate, '%Y-%m') = ?";
    $stmt = $conn->prepare($ratingQuery);
    $stmt->bind_param("s", $filterDate);
    $stmt->execute();
    $ratingResult = $stmt->get_result()->fetch_assoc();
    
    $averageRating = $ratingResult['avg_rating'] ?? 0;
    $feedbackCount = $ratingResult['feedback_count'] ?? 0;
    
    // Get recent feedback
    $feedbackQuery = "SELECT f.*, c.Name, c.Email, c.Phone, o.number AS table_number
                     FROM feedback f
                     JOIN customers c ON f.CustomerID = c.CustomerID
                     JOIN `order` o ON f.OrderID = o.OrderID
                     WHERE DATE_FORMAT(f.FeedbackDate, '%Y-%m') = ?
                     ORDER BY f.FeedbackDate DESC
                     LIMIT 10";
    $stmt = $conn->prepare($feedbackQuery);
    $stmt->bind_param("s", $filterDate);
    $stmt->execute();
    $feedbackResult = $stmt->get_result();
    
    while ($row = $feedbackResult->fetch_assoc()) {
        $customerData[] = $row;
    }
    
    // FIXED: Get customer metrics with proper database structure
    // Since order table doesn't have CustomerID, we connect through feedback table
    $customerMetricsQuery = "SELECT 
                            COUNT(DISTINCT c.CustomerID) as total_customers,
                            COUNT(DISTINCT CASE WHEN f.FeedbackID IS NOT NULL THEN c.CustomerID END) as feedback_customers,
                            COUNT(DISTINCT CASE WHEN f.OrderID IS NOT NULL THEN f.OrderID END) as total_orders_with_feedback
                            FROM customers c
                            LEFT JOIN feedback f ON c.CustomerID = f.CustomerID 
                                AND DATE_FORMAT(f.FeedbackDate, '%Y-%m') = ?";
    
    $stmt = $conn->prepare($customerMetricsQuery);
    $stmt->bind_param("s", $filterDate);
    $stmt->execute();
    $customerMetrics = $stmt->get_result()->fetch_assoc();
    
    // Get total orders for the month (all orders, not just those with customer feedback)
    $ordersQuery = "SELECT COUNT(DISTINCT OrderID) as total_orders 
                   FROM `order` 
                   WHERE DATE_FORMAT(OrderDateTime, '%Y-%m') = ?";
    $stmt = $conn->prepare($ordersQuery);
    $stmt->bind_param("s", $filterDate);
    $stmt->execute();
    $ordersResult = $stmt->get_result()->fetch_assoc();
    
    // Use total orders from separate query since not all orders have customer feedback
    $customerMetrics['total_orders'] = $ordersResult['total_orders'] ?? 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports | Restoran Badhriah</title>
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
    h1 { text-align: center; margin-top: 3rem; margin-bottom: 2rem; font-size: 2rem; }
    .btn { padding: 0.5rem 1rem; background: #fff; color: #000; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.3s; text-decoration: none; }
    .btn:hover { background: #ddd; }
    .top-bar, form { display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 2rem; }
    .report-box { background: #1a1a1a; padding: 2rem; border-radius: 12px; max-width: 800px; margin: auto; box-shadow: 0 0 10px rgba(255, 255, 255, 0.05); }
    .report-item { display: flex; justify-content: space-between; margin: 1rem 0; font-size: 1.2rem; }
    .report-item span:last-child { font-weight: 600; }
    .footer { text-align: center; margin-top: 3rem; color: #777; font-size: 0.9rem; }
    
    /* Tab System */
    .tabs { display: flex; justify-content: center; margin-bottom: 2rem; }
    .tab { padding: 0.75rem 1.5rem; background: #333; color: #ccc; border: none; border-radius: 8px 8px 0 0; font-weight: 600; cursor: pointer; transition: all 0.3s; }
    .tab:hover { background: #444; color: #fff; }
    .tab.active { background: #1a1a1a; color: #fff; }
    
    /* Customer Data Styles */
    .customer-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .metric-card { background: #222; padding: 1.5rem; border-radius: 8px; text-align: center; }
    .metric-value { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
    .metric-label { color: #aaa; font-size: 0.9rem; }
    
    .rating-display { text-align: center; margin: 2rem 0; }
    .stars { font-size: 2.5rem; color: #ffc107; letter-spacing: 5px; }
    .rating-text { font-size: 1.2rem; margin-top: 0.5rem; }
    
    .feedback-table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
    .feedback-table th, .feedback-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #333; }
    .feedback-table th { background: #222; }
    .feedback-table tr:hover { background: #252525; }
    
    .feedback-rating { color: #ffc107; }
    
    /* Print Styles */
    @media print {
        .sidebar, .toggle-btn, .top-bar, form { display: none; }
        .main { margin-left: 0; padding: 0; }
        .report-box { max-width: 100%; box-shadow: none; }
    }
  </style>
</head>
<body class="<?= strtolower($userRole) ?>">

<div class="sidebar" id="sidebar">
  <div style="text-align: center;">
    <img src="images/logo.png" alt="Restoran Badhriah Logo"
         style="width: 100px; height: 100px; border-radius: 50%; border: 2px solid #fff; object-fit: contain; margin-bottom: 1rem;">
  </div>
  <h2>Restoran Badhriah</h2>

  <?php if ($userRole === 'Manager'): ?>
    <!-- Manager Options -->
    <a href="home.php">🏠 Dashboard</a>
    <a href="workshift.php">⏱️ Clock In/Out</a>
    <a href="reports.php" class="active">📊 Overall Reports</a>
    <a href="orders.php">🍽️ Orders</a>
    <a href="menu.php">📋 Menu Items</a>
    <a href="inventory_management.php">📦 Inventory</a>
    <a href="payments.php">💳 Payments</a>
    <a href="staff.php">💼 Staff Management</a>
    <a href="salaries.php">💰 Salaries and Expenses</a>
  <?php else: ?>
    <!-- Cashier Options -->
    <a href="cashier.php">🏠 Dashboard</a>
    <a href="cashier_payment.php">📇 Process Payment</a>
    <a href="workshift.php">⏱️ Clock In/Out</a>
    <a href="orders.php">🍽️ View Orders</a>
    <a href="payments.php">💳 Payment History</a>
    <a href="menu.php">📋 Menu Items</a>
    <a href="refunds.php">🛒 Process Refunds</a>
    <a href="reports.php" class="active">📊 Overall Reports</a>
  <?php endif; ?>
  
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main" id="main">
  <button id="toggleBtn" class="toggle-btn">☰ Menu</button>

  <h1>📊 Overall Reports — <?= date('F Y', strtotime($filterDate . '-01')) ?></h1>

  <!-- Tab Navigation -->
  <div class="tabs">
    <button class="tab <?= $activeTab === 'financial' ? 'active' : '' ?>" 
            onclick="window.location.href='reports.php?tab=financial&filter_date=<?= $filterDate ?>'">
      💰 Financial Summary
    </button>
    <button class="tab <?= $activeTab === 'customer' ? 'active' : '' ?>" 
            onclick="window.location.href='reports.php?tab=customer&filter_date=<?= $filterDate ?>'">
      👥 Customer Data Analysis
    </button>
  </div>

  <form method="GET" action="reports.php">
    <input type="hidden" name="tab" value="<?= $activeTab ?>">
    <label for="filter_date">📅 Filter by Month:</label>
    <input type="month" name="filter_date" id="filter_date" value="<?= htmlspecialchars($filterDate) ?>">
    <button type="submit" class="btn">🔍 Filter</button>
    <a href="reports.php?tab=<?= $activeTab ?>" class="btn">🔄 Reset</a>
  </form>

  <?php if ($userRole === 'Manager'): ?>
    <div class="top-bar">
      <a href="#" onclick="window.print()" class="btn">🖶️ Print Report</a>
    </div>
  <?php endif; ?>

  <?php if ($activeTab === 'financial'): ?>
    <!-- Financial Summary Tab -->
    <div class="report-box">
      <div class="report-item"><span>Total Sales:</span> <span>RM <?= number_format($report['total_sales'], 2) ?></span></div>
      <div class="report-item"><span>Total Expenses:</span> <span>RM <?= number_format($report['total_expenses'], 2) ?></span></div>
      <div class="report-item"><span>Total Salaries:</span> <span>RM <?= number_format($report['total_salaries'], 2) ?></span></div>
      <hr style="border-color: #333;">
      <div class="report-item"><strong>Net Profit:</strong> <strong style="color: <?= $report['net_profit'] >= 0 ? 'lightgreen' : 'red' ?>">RM <?= number_format($report['net_profit'], 2) ?></strong></div>
    </div>
  <?php else: ?>
    <!-- Customer Data Analysis Tab -->
    <div class="report-box">
      <div class="customer-metrics">
        <div class="metric-card">
          <div class="metric-value"><?= $customerMetrics['total_customers'] ?? 0 ?></div>
          <div class="metric-label">Total Customers</div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?= $customerMetrics['feedback_customers'] ?? 0 ?></div>
          <div class="metric-label">Customers Who Gave Feedback</div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?= $customerMetrics['total_orders'] ?? 0 ?></div>
          <div class="metric-label">Total Orders</div>
        </div>
      </div>
      
      <div class="rating-display">
        <div class="stars">
          <?php
          $fullStars = floor($averageRating);
          $halfStar = ($averageRating - $fullStars) >= 0.5;
          $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
          
          echo str_repeat('★', $fullStars);
          echo $halfStar ? '½' : '';
          echo str_repeat('☆', $emptyStars);
          ?>
        </div>
        <div class="rating-text">
          Average Rating: <?= number_format($averageRating, 1) ?> (from <?= $feedbackCount ?> reviews)
        </div>
      </div>
      
      <?php if (!empty($customerData)): ?>
        <h3>Recent Feedback</h3>
        <table class="feedback-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Customer</th>
              <th>Table</th>
              <th>Rating</th>
              <th>Comments</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($customerData as $feedback): ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime($feedback['FeedbackDate'])) ?></td>
                <td><?= htmlspecialchars($feedback['Name']) ?></td>
                <td><?= $feedback['table_number'] ?></td>
                <td class="feedback-rating">
                  <?= str_repeat('★', $feedback['Rating']) ?><?= str_repeat('☆', 5 - $feedback['Rating']) ?>
                </td>
                <td><?= htmlspecialchars($feedback['Comments']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="text-align: center; color: #aaa; margin: 2rem 0;">No customer feedback available for this period.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="footer">
    &copy; <?= date('Y') ?> Restoran Badhriah. All rights reserved.
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('toggleBtn');
    const sidebar = document.getElementById('sidebar');
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('hidden');
    });
  });
</script>

</body>
</html>