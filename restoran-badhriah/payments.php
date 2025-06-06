<?php
session_start();
// Allow both Manager and Cashier roles to access
if (!isset($_SESSION['Username']) || ($_SESSION['Role'] !== 'Manager' && $_SESSION['Role'] !== 'Cashier')) {
    die("❌ Access denied.");
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restoran_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$filterDate = $_GET['filter_date'] ?? '';
$filterClause = '';
if (!empty($filterDate)) {
    $filterClause = "WHERE DATE_FORMAT(PaymentDateTime, '%Y-%m') = '" . $conn->real_escape_string($filterDate) . "'";
}

// Fetch payments from DB
$sql = "SELECT PaymentID, OrderID, Amount, PaymentMethod, PaymentDateTime, Status FROM payment $filterClause ORDER BY PaymentDateTime DESC";
$result = $conn->query($sql);

$filteredPayments = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $filteredPayments[] = [
            'PaymentID' => $row['PaymentID'],
            'OrderID' => $row['OrderID'],
            'Amount' => $row['Amount'],
            'PaymentMethod' => $row['PaymentMethod'],
            'PaymentDateTime' => $row['PaymentDateTime'],
            'Status' => $row['Status'],
        ];
    }
}

$shouldPrint = isset($_GET['print']) ? true : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments | Restoran Badhriah</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #111; color: #fff; min-height: 100vh; }
        .sidebar { width: 240px; background: #000; padding: 2rem 1rem; border-right: 1px solid #333; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; transition: transform 0.3s ease; z-index: 1000; }
        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar h2 { color: #fff; font-size: 1.2rem; margin-bottom: 2rem; text-align: center; }
        .sidebar a { display: block; color: #ccc; text-decoration: none; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; font-weight: 500; transition: background 0.3s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 10px; }
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
        .footer { text-align: center; margin-top: 3rem; color: #777; font-size: 0.9rem; }
        @media print {
            .sidebar, .toggle-btn, .top-bar, form, .footer, .btn { display: none; }
            .main { margin-left: 0; padding: 0; }
            table { width: 100%; border: 1px solid #000; }
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
    <?php if ($_SESSION['Role'] === 'Manager'): ?>
        <!-- Manager Options -->
        <a href="home.php">🏠 Dashboard</a>
        <a href="workshift.php">⏱️ Clock In/Out</a>
        <a href="reports.php">📊 Overall Reports</a>
        <a href="orders.php">🍽️ Orders</a>
        <a href="menu.php">📋 Menu Items</a>
        <a href="inventory_management.php">📦 Inventory</a>
        <a href="payments.php" class="active">💳 Payments</a>
        <a href="staff.php">💼 Staff Management</a>
        <a href="salaries.php">💰 Salaries and Expenses</a>
    <?php else: ?>
        <!-- Cashier Options -->
        <a href="cashier.php">🏠 Dashboard</a>
        <a href="cashier_payment.php" >📇 Process Payment</a>
        <a href="workshift.php">⏱️ Clock In/Out</a>
        <a href="orders.php">🍽️ View Orders</a>
        <a href="payments.php" class="active">💳 Payment History</a>
        <a href="menu_cashier.php">📋 Menu Items</a>
        <a href="refunds.php">🛒 Process Refunds</a>
        <a href="reports.php">📊 Overall Reports</a>
    <?php endif; ?>

    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main" id="main">
    <button id="toggleBtn" class="toggle-btn">☰ Menu</button>

    <h1>Customer Payments | Restoran Badhriah</h1>

    <form method="GET">
        <label for="filter_date">📅 Filter by Month:</label>
        <input type="month" name="filter_date" id="filter_date" value="<?= htmlspecialchars($filterDate) ?>">
        <button type="submit" class="btn">🔍 Filter</button>
        <a href="payments.php" class="btn">🔄 Reset</a>
    </form>

    <div class="top-bar">
        <a href="#" onclick="printThisYear()" class="btn">🖶️ Print This Year</a>
        <a href="#" onclick="printThisMonth()" class="btn">🖶️ Print This Month</a>
    </div>

    <table id="paymentTable">
        <thead>
            <tr>
                <th>Payment ID</th>
                <th>Order ID</th>
                <th>Amount (RM)</th>
                <th>Payment Method</th>
                <th>Date/Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filteredPayments as $payment): ?>
                <tr>
                    <td><?= $payment['PaymentID'] ?></td>
                    <td><?= htmlspecialchars($payment['OrderID']) ?></td>
                    <td><?= number_format($payment['Amount'], 2) ?></td>
                    <td><?= htmlspecialchars($payment['PaymentMethod']) ?></td>
                    <td><?= htmlspecialchars($payment['PaymentDateTime']) ?></td>
                    <td><?= htmlspecialchars($payment['Status']) ?></td>
                    <td class="actions">
                        <a href="#" class="btn btn-small" onclick="printRow(this)">🖶️ Print</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filteredPayments)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No payment records found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

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

    function printRow(el) {
        const row = el.closest('tr').outerHTML;
        const html = `
            <html>
                <head>
                    <title>Payment Receipt</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                        th { background-color: #f2f2f2; }
                    </style>
                </head>
                <body>
                    <h2>Payment Receipt - Restoran Badhriah</h2>
                    <table border="1">${row}</table>
                    <p style="margin-top: 20px; text-align: right;">Printed on: ${new Date().toLocaleString()}</p>
                </body>
            </html>`;
        const w = window.open('', '', 'width=800,height=600');
        w.document.write(html);
        w.document.close();
        w.print();
    }

    function printThisMonth() {
        const now = new Date();
        const month = now.toISOString().slice(0, 7);
        window.location.href = 'payments.php?filter_date=' + month + '&print=true';
    }

    function printThisYear() {
        const now = new Date();
        const year = now.getFullYear();
        window.location.href = 'payments.php?filter_date=' + year + '&print=true';
    }

    <?php if ($shouldPrint): ?>
        window.onload = function() {
            setTimeout(() => {
                window.print();
                window.onafterprint = function() {
                    window.location.href = window.location.href.split('&print')[0];
                };
            }, 500);
        };
    <?php endif; ?>
</script>

</body>
</html>