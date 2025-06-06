<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Initialize message variables
$success_message = '';
$error_message = '';

// Use your existing MySQLi database connection file
require_once 'db.php';

// Fetch paid payments that can be refunded
$payments = [];
$payment_query = "SELECT p.PaymentID, p.OrderID, p.Amount, p.PaymentMethod, p.PaymentDateTime, p.Status
                  FROM payment p
                  WHERE p.Status = 'completed' OR p.Status = 'paid'
                  ORDER BY p.PaymentDateTime DESC";
$payment_result = $conn->query($payment_query);
if ($payment_result->num_rows > 0) {
    while($row = $payment_result->fetch_assoc()) {
        // Check if already refunded
        $refund_check = "SELECT COUNT(*) as refund_count FROM refunds WHERE PaymentID = " . $row['PaymentID'];
        $refund_result = $conn->query($refund_check);
        $refund_data = $refund_result->fetch_assoc();
        
        if ($refund_data['refund_count'] == 0) {
            $payments[] = $row;
        }
    }
}

// Fetch existing refunds for display
$refunds = [];
$refund_query = "SELECT r.RefundID, r.PaymentID, r.RefundAmount, r.Reason, r.RefundDateTime, r.ProcessedBy,
                        p.OrderID, p.Amount as OriginalAmount, p.PaymentMethod
                 FROM refunds r
                 JOIN payment p ON r.PaymentID = p.PaymentID
                 ORDER BY r.RefundDateTime DESC";
$refund_result = $conn->query($refund_query);
if ($refund_result->num_rows > 0) {
    while($row = $refund_result->fetch_assoc()) {
        $refunds[] = $row;
    }
}

// Handle refund processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $payment_id = intval($_POST['payment_id']);
    $refund_amount = floatval($_POST['refund_amount']);
    $refund_reason = $_POST['refund_reason'];
    $processed_by = $_SESSION['Username'];
    
    // Get payment details
    $payment_query = "SELECT PaymentID, Amount, OrderID FROM payment WHERE PaymentID = $payment_id";
    $payment_result = $conn->query($payment_query);
    
    if ($payment_result->num_rows > 0) {
        $payment = $payment_result->fetch_assoc();
        $original_amount = floatval($payment['Amount']);
        
        if ($refund_amount > 0 && $refund_amount <= $original_amount) {
            $conn->begin_transaction();
            
            try {
                // Insert refund record
                $insert_refund = "INSERT INTO refunds (PaymentID, RefundAmount, Reason, RefundDateTime, ProcessedBy) 
                                 VALUES ($payment_id, $refund_amount, '" . $conn->real_escape_string($refund_reason) . "', NOW(), '" . $conn->real_escape_string($processed_by) . "')";
                $conn->query($insert_refund);
                
                // Update payment status if full refund
                if ($refund_amount == $original_amount) {
                    $update_payment = "UPDATE payment SET Status = 'refunded' WHERE PaymentID = $payment_id";
                    $conn->query($update_payment);
                } else {
                    $update_payment = "UPDATE payment SET Status = 'partially_refunded' WHERE PaymentID = $payment_id";
                    $conn->query($update_payment);
                }

                // COMMIT THE TRANSACTION
                $conn->commit();
                
               // Store success message in session and redirect
                $_SESSION['success_message'] = "Refund processed successfully! Amount: RM " . number_format($refund_amount, 2);
                header("Location: refunds.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error processing refund: " . $e->getMessage();
            }
        } else {
            $error_message = "Invalid refund amount! Must be between RM 0.01 and RM " . number_format($original_amount, 2);
        }
    } else {
        $error_message = "Payment not found!";
    }
}

        // Check for success message in session
        if (isset($_SESSION['success_message'])) {
            $success_message = $_SESSION['success_message'];
            unset($_SESSION['success_message']);
        }

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Processing | Restoran Badhriah</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #111;
            color: #fff;
            min-height: 100vh;
        }

        /* Sidebar styles */
        .sidebar {
            width: 240px;
            background: #000;
            padding: 2rem 1rem;
            border-right: 1px solid #333;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .sidebar h2 {
            color: #fff;
            font-size: 1.2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .sidebar a {
            display: block;
            color: #ccc;
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-weight: 500;
            transition: background 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #222;
            color: #fff;
        }

        /* Main content */
        .main {
            margin-left: 240px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
            max-width: 1400px;
        }

        .sidebar.hidden + .main {
            margin-left: 0;
        }

        .toggle-btn {
            position: fixed;
            top: 1rem;
            left: 1rem;
            padding: 0.5rem 1rem;
            background: #fff;
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            z-index: 1100;
            transition: left 0.3s ease;
        }

        .sidebar:not(.hidden) ~ .main .toggle-btn {
            left: 260px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            margin-top: 3rem;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 600;
        }

        /* Dashboard layout */
        .dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .payments-section, .refund-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid #333;
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Payments list */
        .payments-container {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .payment-card {
            background: #222;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid #333;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .payment-card.selected {
            border: 2px solid #f39c12;
            background: #2a2a2a;
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .payment-id {
            font-weight: 700;
            font-size: 1.1rem;
            color: #f39c12;
        }
        
        .order-info {
            background: #333;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .payment-amount {
            font-size: 1.2rem;
            font-weight: 700;
            color: #f39c12;
            text-align: right;
        }
        
        .payment-details {
            margin: 1rem 0;
            font-size: 0.9rem;
            color: #ccc;
        }
        
        .empty-state {
            text-align: center;
            color: #aaa;
            padding: 3rem;
            font-style: italic;
        }

        /* Refund form */
        .refund-form {
            background: #222;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #fff;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background: #333;
            border: 1px solid #444;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .refund-summary {
            background: #333;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }
        
        .summary-row.total {
            font-weight: 700;
            font-size: 1.3rem;
            color: #f39c12;
        }
        
        .process-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .process-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(243, 156, 18, 0.3);
        }
        
        .process-btn:disabled {
            background: #555;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Refunds history */
        .refunds-history {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid #333;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .refunds-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .refunds-table th,
        .refunds-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        .refunds-table th {
            background: #000;
            font-weight: 600;
        }

        .refunds-table tr:hover {
            background: #222;
        }

        /* Alert messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .main {
                padding: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
        }

        @keyframes highlight {
            0% { background-color: rgba(243, 156, 18, 0.3); }
            100% { background-color: transparent; }
        }
        
        .highlight {
            animation: highlight 3s ease;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div style="text-align: center;">
        <img src="images/logo.png" alt="Restoran Badhriah Logo"
             style="width: 100px; height: 100px; border-radius: 50%; border: 2px solid #fff; object-fit: contain; margin-bottom: 1rem;">
    </div>
    <h2>Restoran Badhriah</h2>
        <a href="cashier.php">🏠 Dashboard</a>
        <a href="cashier_payment.php" >📇 Process Payment</a>
        <a href="workshift.php">⏱️ Clock In/Out</a>
        <a href="orders.php">🍽️ View Orders</a>
        <a href="payments.php" >💳 Payment History</a>
        <a href="menu_cashier.php">📋 Menu Items</a>
        <a href="refunds.php" class="active">🛒 Process Refunds</a>
        <a href="reports.php">📊 Overall Reports</a>
        <a href="logout.php">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="main" id="main">
    <button id="toggleBtn" class="toggle-btn">☰</button>

    <!-- Header -->
    <div class="header">
        <h1>Refund Processing</h1>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            ❌ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="dashboard">
        <!-- Payments Section -->
        <div class="payments-section">
            <div class="section-title">
                <span>💳 Paid Orders Available for Refund</span>
            </div>
            
            <div class="payments-container">
                <?php if (count($payments) > 0): ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-card" 
                             data-id="<?= $payment['PaymentID'] ?>" 
                             data-amount="<?= $payment['Amount'] ?>"
                             onclick="selectPayment(this, <?= $payment['PaymentID'] ?>, <?= $payment['Amount'] ?>, '<?= htmlspecialchars($payment['OrderID']) ?>', '<?= htmlspecialchars($payment['PaymentMethod']) ?>')">
                            <div class="payment-header">
                                <div class="payment-id">Payment #<?= str_pad($payment['PaymentID'], 4, '0', STR_PAD_LEFT) ?></div>
                                <div class="order-info">Order #<?= $payment['OrderID'] ?></div>
                            </div>
                            
                            <div class="payment-details">
                                <div>Method: <?= htmlspecialchars($payment['PaymentMethod']) ?></div>
                                <div>Date: <?= date('d/m/Y H:i', strtotime($payment['PaymentDateTime'])) ?></div>
                                <div>Status: <?= htmlspecialchars($payment['Status']) ?></div>
                            </div>
                            
                            <div class="payment-amount">
                                RM <?= number_format($payment['Amount'], 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        No payments available for refund
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Refund Section -->
        <div class="refund-section">
            <div class="section-title">
                <span>🔄 Process Refund</span>
            </div>
            
            <div class="refund-form">
                <form method="POST" id="refundForm">
                    <input type="hidden" name="payment_id" id="paymentId">
                    <input type="hidden" name="process_refund" value="1">
                    
                    <div class="form-group">
                        <label for="paymentDisplay">Payment ID</label>
                        <input type="text" id="paymentDisplay" readonly placeholder="Select a payment">
                    </div>
                    
                    <div class="form-group">
                        <label for="orderDisplay">Order ID</label>
                        <input type="text" id="orderDisplay" readonly placeholder="Order information">
                    </div>
                    
                    <div class="refund-summary">
                        <div class="summary-row">
                            <span>Original Amount:</span>
                            <span id="originalAmount">RM 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Payment Method:</span>
                            <span id="paymentMethod">-</span>
                        </div>
                        <div class="summary-row total">
                            <span>Max Refund:</span>
                            <span id="maxRefund">RM 0.00</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="refund_amount">Refund Amount (RM)</label>
                        <input type="number" name="refund_amount" id="refund_amount" 
                               step="0.01" min="0" placeholder="0.00" 
                               oninput="validateRefund()" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="refund_reason">Refund Reason</label>
                        <textarea name="refund_reason" id="refund_reason" 
                                  placeholder="Enter reason for refund..." required></textarea>
                    </div>
                    
                    <button type="submit" class="process-btn" id="processBtn" disabled>
                        Process Refund
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Refunds History -->
    <div class="refunds-history">
        <div class="section-title">
            <span>📜 Refund History</span>
        </div>
        
        <?php if (count($refunds) > 0): ?>
            <table class="refunds-table">
                <thead>
                    <tr>
                        <th>Refund ID</th>
                        <th>Payment ID</th>
                        <th>Order ID</th>
                        <th>Original Amount</th>
                        <th>Refund Amount</th>
                        <th>Reason</th>
                        <th>Processed By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($refunds as $refund): ?>
                        <tr>
                            <td>#<?= str_pad($refund['RefundID'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td>#<?= str_pad($refund['PaymentID'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td>#<?= $refund['OrderID'] ?></td>
                            <td>RM <?= number_format($refund['OriginalAmount'], 2) ?></td>
                            <td>RM <?= number_format($refund['RefundAmount'], 2) ?></td>
                            <td><?= htmlspecialchars($refund['Reason']) ?></td>
                            <td><?= htmlspecialchars($refund['ProcessedBy']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($refund['RefundDateTime'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                No refunds processed yet
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Toggle sidebar
    const toggleBtn = document.getElementById('toggleBtn');
    const sidebar = document.getElementById('sidebar');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
    });

    // Payment selection and refund processing
    let selectedPayment = null;
    let selectedAmount = 0;
    
    function selectPayment(element, paymentId, amount, orderId, paymentMethod) {
        // Remove selection from all payments
        document.querySelectorAll('.payment-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Select clicked payment
        element.classList.add('selected');
        selectedPayment = paymentId;
        selectedAmount = amount;
        
        // Update form
        document.getElementById('paymentId').value = paymentId;
        document.getElementById('paymentDisplay').value = 'Payment #' + String(paymentId).padStart(4, '0');
        document.getElementById('orderDisplay').value = 'Order #' + orderId;
        document.getElementById('originalAmount').textContent = 'RM ' + amount.toFixed(2);
        document.getElementById('paymentMethod').textContent = paymentMethod;
        document.getElementById('maxRefund').textContent = 'RM ' + amount.toFixed(2);
        
        // Reset refund inputs
        document.getElementById('refund_amount').value = '';
        document.getElementById('refund_amount').max = amount;
        document.getElementById('refund_reason').value = '';
        
        // Enable/disable process button
        updateProcessButton();
    }
    
    function validateRefund() {
        if (!selectedPayment) return;
        
        const refundAmount = parseFloat(document.getElementById('refund_amount').value) || 0;
        const reason = document.getElementById('refund_reason').value.trim();
        
        updateProcessButton();
    }
    
    function updateProcessButton() {
        const refundAmount = parseFloat(document.getElementById('refund_amount').value) || 0;
        const reason = document.getElementById('refund_reason').value.trim();
        const canProcess = selectedPayment && refundAmount > 0 && refundAmount <= selectedAmount && reason.length > 0;
        
        document.getElementById('processBtn').disabled = !canProcess;
    }
    
    // Add event listeners
    document.getElementById('refund_amount').addEventListener('input', validateRefund);
    document.getElementById('refund_reason').addEventListener('input', updateProcessButton);
    
    // Form validation
    document.getElementById('refundForm').addEventListener('submit', function(e) {
        if (!selectedPayment) {
            e.preventDefault();
            alert('Please select a payment to process refund');
            return;
        }
        
        const refundAmount = parseFloat(document.getElementById('refund_amount').value) || 0;
        if (refundAmount <= 0 || refundAmount > selectedAmount) {
            e.preventDefault();
            alert('Invalid refund amount. Must be between RM 0.01 and RM ' + selectedAmount.toFixed(2));
            return;
        }
        
        const reason = document.getElementById('refund_reason').value.trim();
        if (!reason) {
            e.preventDefault();
            alert('Please provide a reason for the refund');
            return;
        }
        
        // Confirm before processing
        if (!confirm('Are you sure you want to process this refund of RM ' + refundAmount.toFixed(2) + '?')) {
            e.preventDefault();
        }
    });

     // Highlight new refunds in the table
    function highlightNewRefunds() {
        const table = document.getElementById('refundsTable');
        if (table) {
            const rows = table.getElementsByTagName('tr');
            if (rows.length > 1) {
                rows[1].classList.add('highlight');
            }
        }
    }

    // Highlight if there's a success message
    window.onload = function() {
        if (document.querySelector('.alert-success')) {
            highlightNewRefunds();
        }
    };
</script>

</body>
</html>