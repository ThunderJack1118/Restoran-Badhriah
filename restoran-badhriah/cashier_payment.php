<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

$success_message = '';
$error_message = '';

// Fetch orders ready for payment - GROUPED BY TABLE
$orders = [];
$order_query = "SELECT 
                    GROUP_CONCAT(o.OrderID) as OrderIDs,
                    o.number as table_number,
                    SUM(o.TotalAmount) as CombinedTotal,
                    COUNT(o.OrderID) as OrderCount,
                    GROUP_CONCAT(CONCAT('#', LPAD(o.OrderID, 4, '0')) SEPARATOR ', ') as OrderNumbers
                FROM `order` o
                WHERE o.O_Status = 'Completed' 
                AND NOT EXISTS (
                    SELECT 1 FROM payment p 
                    WHERE p.OrderID = o.OrderID 
                    AND p.Status = 'Completed'
                )
                GROUP BY o.number
                ORDER BY MAX(o.OrderDateTime) DESC";

$order_result = $conn->query($order_query);
if ($order_result->num_rows > 0) {
    while($row = $order_result->fetch_assoc()) {
        $order_ids = explode(',', $row['OrderIDs']);
        
        // Get all items for all orders from this table
        $items_query = "SELECT mi.Name, oi.Quantity, oi.UnitPrice, o.OrderID
                        FROM orderitem oi
                        JOIN menu_items mi ON oi.MenuItemID = mi.MenuItemID
                        JOIN `order` o ON oi.OrderID = o.OrderID
                        WHERE o.OrderID IN (" . $row['OrderIDs'] . ")
                        ORDER BY o.OrderID, mi.Name";
        
        $items_result = $conn->query($items_query);
        $row['items'] = [];
        $items_by_order = [];
        
        while($item = $items_result->fetch_assoc()) {
            $row['items'][] = $item;
            if (!isset($items_by_order[$item['OrderID']])) {
                $items_by_order[$item['OrderID']] = [];
            }
            $items_by_order[$item['OrderID']][] = $item;
        }
        
        $row['items_by_order'] = $items_by_order;
        $orders[] = $row;
    }
}

// Handle payment processing for combined orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $order_ids = $_POST['order_ids']; // This will be comma-separated string
    $payment_method = $_POST['payment_method'];
    $amount_paid = floatval($_POST['amount_paid']);
    $table_number = $_POST['table_number'];
    
    // Get combined total for validation
    $validation_query = "SELECT SUM(TotalAmount) as CombinedTotal 
                        FROM `order` 
                        WHERE OrderID IN ($order_ids)";
    $validation_result = $conn->query($validation_query);
    
    if ($validation_result->num_rows > 0) {
        $validation = $validation_result->fetch_assoc();
        $total_amount = floatval($validation['CombinedTotal']);
        
        if ($amount_paid >= $total_amount) {
            $conn->begin_transaction();
            
            try {
                // Create payment records for each order
                $order_ids_array = explode(',', $order_ids);
                foreach ($order_ids_array as $order_id) {
                    $order_id = intval(trim($order_id));
                    
                    // Get individual order amount for proportional payment recording
                    $individual_query = "SELECT TotalAmount FROM `order` WHERE OrderID = $order_id";
                    $individual_result = $conn->query($individual_query);
                    $individual_order = $individual_result->fetch_assoc();
                    $individual_amount = floatval($individual_order['TotalAmount']);
                    
                    // Create payment record for this order
                    $insert_payment = "INSERT INTO payment (OrderID, Amount, PaymentMethod, Status)
                                       VALUES ($order_id, $individual_amount, '$payment_method', 'Completed')";
                    $conn->query($insert_payment);
                }
                
                // Update table status to available
                $update_table = "UPDATE `tables` SET status = 'available', waiter = NULL WHERE number = '$table_number'";
                $conn->query($update_table);
                
                $conn->commit();
                
                $change = $amount_paid - $total_amount;
                $_SESSION['payment_success'] = "Payment processed successfully for " . count($order_ids_array) . " orders! Change: RM " . number_format($change, 2);
                
                // Store order IDs in session for feedback page
                $_SESSION['feedback_order'] = $order_ids;
                
                // Redirect to feedback page
                header("Location: feedback.php");
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error processing payment: " . $e->getMessage();
            }
        } else {
            $error_message = "Insufficient payment! Total amount is RM " . number_format($total_amount, 2);
        }
    } else {
        $error_message = "Orders not found!";
    }
}



$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing | Restoran Badhriah</title>
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

        .back-btn {
            padding: 0.75rem 1.5rem;
            background: #333;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            background: #444;
            transform: translateY(-1px);
        }

        /* Dashboard layout */
        .dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .orders-section, .payment-section {
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

        /* Orders list */
        .orders-container {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .order-card {
            background: #222;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid #333;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .order-card.selected {
            border: 2px solid #4CAF50;
            background: #2a2a2a;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .order-id {
            font-weight: 700;
            font-size: 1.1rem;
            color: #4CAF50;
        }
        
        .table-info {
            background: #333;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .order-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: #4CAF50;
            text-align: right;
        }
        
        .order-items {
            margin: 1rem 0;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #444;
            font-size: 0.9rem;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-separator {
            border-top: 2px dashed #666;
            margin: 0.5rem 0;
            padding-top: 0.5rem;
        }
        
        .order-label {
            font-size: 0.8rem;
            color: #aaa;
            font-style: italic;
            margin-bottom: 0.5rem;
        }
        
        .combined-badge {
            background: #4CAF50;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            color: #aaa;
            padding: 3rem;
            font-style: italic;
        }

        /* Payment form */
        .payment-form {
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
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            background: #333;
            border: 1px solid #444;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
        }
        
        .payment-summary {
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
            color: #4CAF50;
        }
        
        .change-row {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #444;
        }
        
        .change-amount {
            font-weight: 700;
            color: #4CAF50;
        }
        
        .insufficient {
            color: #f44336;
        }
        
        .process-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #4CAF50, #45a049);
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
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.3);
        }
        
        .process-btn:disabled {
            background: #555;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
        <a href="cashier_payment.php" class="active">📇 Process Payment</a>
        <a href="workshift.php">⏱️ Clock In/Out</a>
        <a href="orders.php">🍽️ View Orders</a>
        <a href="payments.php" >💳 Payment History</a>
        <a href="menu_cashier.php">📋 Menu Items</a>
        <a href="refunds.php">🛒 Process Refunds</a>
        <a href="reports.php">📊 Overall Reports</a>
        <a href="logout.php">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="main" id="main">
    <button id="toggleBtn" class="toggle-btn">☰</button>

    <!-- Header -->
    <div class="header">
        <h1>Payment Processing</h1>
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
        <!-- Orders Section -->
        <div class="orders-section">
            <div class="section-title">
                <span>📋 Orders Ready for Payment (Grouped by Table)</span>
            </div>
            
            <div class="orders-container">
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card" 
                             data-order-ids="<?= $order['OrderIDs'] ?>" 
                             data-table="<?= $order['table_number'] ?>"
                             data-total="<?= $order['CombinedTotal'] ?>"
                             onclick="selectOrder(this, '<?= $order['OrderIDs'] ?>', <?= $order['CombinedTotal'] ?>, '<?= $order['table_number'] ?>')">
                            <div class="order-header">
                                <div class="order-id">
                                    <?= $order['OrderNumbers'] ?>
                                    <?php if ($order['OrderCount'] > 1): ?>
                                        <span class="combined-badge"><?= $order['OrderCount'] ?> Orders Combined</span>
                                    <?php endif; ?>
                                </div>
                                <div class="table-info">Table <?= $order['table_number'] ?></div>
                            </div>
                            
                            <div class="order-items">
                                <?php 
                                $current_order_id = null;
                                foreach ($order['items'] as $item): 
                                    if ($current_order_id !== $item['OrderID'] && $order['OrderCount'] > 1):
                                        if ($current_order_id !== null): ?>
                                            <div class="order-separator"></div>
                                        <?php endif; ?>
                                        <div class="order-label">Order #<?= str_pad($item['OrderID'], 4, '0', STR_PAD_LEFT) ?>:</div>
                                    <?php 
                                        $current_order_id = $item['OrderID'];
                                    endif; 
                                ?>
                                    <div class="order-item">
                                        <span><?= $item['Quantity'] ?> × <?= htmlspecialchars($item['Name']) ?></span>
                                        <span>RM <?= number_format($item['UnitPrice'] * $item['Quantity'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="order-total">
                                Combined Total: RM <?= number_format($order['CombinedTotal'], 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        No orders ready for payment
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment Section -->
        <div class="payment-section">
            <div class="section-title">
                <span>💳 Process Payment</span>
            </div>
            
            <div class="payment-form">
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="order_ids" id="orderIds">
                    <input type="hidden" name="table_number" id="tableNumber">
                    <input type="hidden" name="process_payment" value="1">
                    
                    <div class="form-group">
                        <label for="orderDisplay">Selected Orders</label>
                        <input type="text" id="orderDisplay" readonly placeholder="Select orders to process">
                    </div>
                    
                    <div class="payment-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">RM 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (6%):</span>
                            <span id="tax">RM 0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total Amount:</span>
                            <span id="totalAmount">RM 0.00</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="">Select method</option>
                            <option value="cash">Cash</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="digital">Digital Wallet</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount_paid">Amount Paid (RM)</label>
                        <input type="number" name="amount_paid" id="amount_paid" 
                               step="0.01" min="0" placeholder="0.00" 
                               oninput="calculateChange()" required>
                    </div>
                    
                    <div class="change-row">
                        <span>Change:</span>
                        <span id="changeAmount" class="change-amount">RM 0.00</span>
                    </div>
                    <br>
                    
                    <button type="submit" class="process-btn" id="processBtn" disabled>
                        Process Combined Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle sidebar
    const toggleBtn = document.getElementById('toggleBtn');
    const sidebar = document.getElementById('sidebar');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
    });

    // Order selection and payment processing
    let selectedOrderIds = null;
    let selectedTotal = 0;
    let selectedTable = null;
    const taxRate = 0.06; // 6% tax
    
    function selectOrder(element, orderIds, totalAmount, tableNumber) {
        // Remove selection from all orders
        document.querySelectorAll('.order-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Select clicked order group
        element.classList.add('selected');
        selectedOrderIds = orderIds;
        selectedTotal = totalAmount;
        selectedTable = tableNumber;
        
        // Update form
        document.getElementById('orderIds').value = orderIds;
        document.getElementById('tableNumber').value = tableNumber;
        
        // Create display text
        const orderIdsArray = orderIds.split(',');
        const displayText = orderIdsArray.length > 1 ? 
            `${orderIdsArray.length} Orders - Table ${tableNumber}` : 
            `Order #${String(orderIdsArray[0]).padStart(4, '0')} - Table ${tableNumber}`;
        
        document.getElementById('orderDisplay').value = displayText;
        
        // Calculate amounts
        const subtotal = totalAmount / (1 + taxRate);
        const tax = subtotal * taxRate;
        
        document.getElementById('subtotal').textContent = 'RM ' + subtotal.toFixed(2);
        document.getElementById('tax').textContent = 'RM ' + tax.toFixed(2);
        document.getElementById('totalAmount').textContent = 'RM ' + totalAmount.toFixed(2);
        
        // Reset payment inputs
        document.getElementById('payment_method').value = '';
        document.getElementById('amount_paid').value = '';
        document.getElementById('changeAmount').textContent = 'RM 0.00';
        document.getElementById('changeAmount').classList.remove('insufficient');
        
        // Enable/disable process button
        updateProcessButton();
    }
    
    function calculateChange() {
        if (!selectedOrderIds) return;
        
        const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
        const change = amountPaid - selectedTotal;
        
        document.getElementById('changeAmount').textContent = 'RM ' + Math.max(0, change).toFixed(2);
        
        if (change < 0) {
            document.getElementById('changeAmount').classList.add('insufficient');
        } else {
            document.getElementById('changeAmount').classList.remove('insufficient');
        }
        
        updateProcessButton();
    }
    
    function updateProcessButton() {
        const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
        const paymentMethod = document.getElementById('payment_method').value;
        const canProcess = selectedOrderIds && paymentMethod && amountPaid >= selectedTotal;
        
        document.getElementById('processBtn').disabled = !canProcess;
    }
    
    // Add event listeners
    document.getElementById('payment_method').addEventListener('change', updateProcessButton);
    document.getElementById('amount_paid').addEventListener('input', updateProcessButton);
    
    // Form validation
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        if (!document.getElementById('orderIds').value) {
            e.preventDefault();
            alert('Please select orders to process payment');
            return;
        }
        
        const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
        if (amountPaid < selectedTotal) {
            e.preventDefault();
            alert('Amount paid is less than total amount');
            return;
        }
        
        const paymentMethod = document.getElementById('payment_method').value;
        if (!paymentMethod) {
            e.preventDefault();
            alert('Please select a payment method');
            return;
        }
        
        // Confirm before processing
        const orderCount = selectedOrderIds.split(',').length;
        const confirmMessage = orderCount > 1 ? 
            `Are you sure you want to process payment for ${orderCount} combined orders from Table ${selectedTable}?` :
            'Are you sure you want to process this payment?';
            
        if (!confirm(confirmMessage)) {
            e.preventDefault();
        }
    });
</script>

</body>
</html>