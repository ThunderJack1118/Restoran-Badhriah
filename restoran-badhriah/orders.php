<?php
session_start();

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'restoran_db';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (isset($_SESSION['StaffID'])) {
    $userId = $_SESSION['StaffID'];
    
    // Fetch user's role from database
    $query = "SELECT Role FROM staff WHERE StaffID = ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Error preparing query: " . $conn->error);
    }
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $role = $user['Role'];
        
        // Set back URL based on role
        switch($role) {
            case 'Manager':
                $backUrl = 'home.php';
                break;
            case 'Waiter':
                $backUrl = 'waiter.php';
                break;
            case 'Chef':
                $backUrl = 'chef.php';
                break;
            case 'Cashier':
                $backUrl = 'cashier.php';
                break;
            default:
                $backUrl = 'login.php';
        }
    } else {
        $backUrl = 'login.php'; // Redirect to login if user not found
    }
} else {
    $backUrl = 'login.php'; // Redirect to login if not logged in
}

// Redirect back to dashboard without deleting any orders
if (isset($_GET['back']) && $_GET['back'] == 'true') {
    header("Location: $backUrl");
    exit();
}

// Handle toggling done status
if (isset($_POST['toggle_done'])) {
    error_log("Toggle done received. OrderID: " . ($_POST['OrderID'] ?? 'null') . ", Status: " . ($_POST['O_Status'] ?? 'null'));
    
    if (isset($_POST['OrderID'], $_POST['O_Status'])) {
        $orderId = intval($_POST['OrderID']);
        $currentStatus = $_POST['O_Status'];

        error_log("Processing order $orderId with status $currentStatus");

        // Determine new status based on current status
        $newStatus = 'Pending'; // Default
        switch($currentStatus) {
            case 'Pending':
                $newStatus = 'In Progress';
                break;
            case 'In Progress':
                $newStatus = 'Completed';
                break;
            case 'Completed':
                $newStatus = 'Pending';
                break;
        }
        
        error_log("Changing status from $currentStatus to $newStatus");

        if ($orderId > 0) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                $stmt = $conn->prepare("UPDATE `order` SET `O_Status` = ? WHERE `OrderID` = ?");
                if ($stmt === false) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("si", $newStatus, $orderId);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                
                error_log("Rows affected: " . $stmt->affected_rows);
                
                if ($stmt->affected_rows === 0) {
                    throw new Exception("No rows updated - order may not exist");
                }
                
                // If marking as Completed, deduct ingredients from inventory
                if ($newStatus == 'Completed') {
                    // Get all items in this order
                    $itemsQuery = "SELECT MenuItemID, Quantity FROM orderitem WHERE OrderID = ?";
                    $itemsStmt = $conn->prepare($itemsQuery);
                    $itemsStmt->bind_param("i", $orderId);
                    $itemsStmt->execute();
                    $itemsResult = $itemsStmt->get_result();
                    
                    while ($item = $itemsResult->fetch_assoc()) {
                        $menuItemId = $item['MenuItemID'];
                        $quantity = $item['Quantity'];
                        
                        // Get all ingredients for this menu item from recipe_ingredients table
                        $ingredientsQuery = "SELECT IngredientID, QuantityRequired FROM recipe_ingredients WHERE MenuItemID = ?";
                        $ingredientsStmt = $conn->prepare($ingredientsQuery);
                        $ingredientsStmt->bind_param("i", $menuItemId);
                        $ingredientsStmt->execute();
                        $ingredientsResult = $ingredientsStmt->get_result();
                        
                        while ($ingredient = $ingredientsResult->fetch_assoc()) {
                            $ingredientId = $ingredient['IngredientID'];
                            $amountUsed = $ingredient['QuantityRequired'] * $quantity;
                            
                            // Check current stock before deducting
                            $checkStockQuery = "SELECT QuantityInStock, IngredientName FROM inventory WHERE IngredientID = ?";
                            $checkStmt = $conn->prepare($checkStockQuery);
                            $checkStmt->bind_param("i", $ingredientId);
                            $checkStmt->execute();
                            $stockResult = $checkStmt->get_result();
                            
                            if ($stockData = $stockResult->fetch_assoc()) {
                                $currentStock = $stockData['QuantityInStock'];
                                $ingredientName = $stockData['IngredientName'];
                                
                                if ($currentStock >= $amountUsed) {
                                    // Deduct from inventory
                                    $updateQuery = "UPDATE inventory SET QuantityInStock = QuantityInStock - ?, LastUpdated = CURRENT_TIMESTAMP WHERE IngredientID = ?";
                                    $updateStmt = $conn->prepare($updateQuery);
                                    $updateStmt->bind_param("di", $amountUsed, $ingredientId);
                                    $updateStmt->execute();
                                } else {
                                    // Not enough stock - log warning but continue
                                    error_log("Warning: Insufficient stock for ingredient '$ingredientName'. Required: $amountUsed, Available: $currentStock for Order #$orderId");
                                    $updateQuery = "UPDATE inventory SET QuantityInStock = 0, LastUpdated = CURRENT_TIMESTAMP WHERE IngredientID = ?";
                                    $updateStmt = $conn->prepare($updateQuery);
                                    $updateStmt->bind_param("i", $ingredientId);
                                    $updateStmt->execute();
                                }
                            }
                        }
                    }
                }
                
                $conn->commit();
                
                // Force refresh the page to show changes
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error updating order status: " . $e->getMessage());
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle order cancellation
if (isset($_POST['cancel_order']) && isset($_POST['OrderID'])) {
    $orderId = intval($_POST['OrderID']);
    
    if ($orderId > 0) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // 1. Get table number before cancelling
            $tableResult = $conn->query("SELECT number FROM `order` WHERE OrderID = $orderId");
            $tableData = $tableResult->fetch_assoc();
            $tableNumber = $tableData['number'] ?? null;
            
            // 2. Cancel the order
            $stmt = $conn->prepare("UPDATE `order` SET `O_Status` = 'Cancelled' WHERE `OrderID` = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            
            // 3. Update table status if there's a table number
            if ($tableNumber) {
                $conn->query("UPDATE `tables` SET `status` = 'available' WHERE id = $tableNumber");
            }
            
            // Commit transaction
            $conn->commit();
            
            // If this is an AJAX request, return success
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true]);
                exit;
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Cancellation failed: " . $e->getMessage());
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }
    }
}

// Handle undo cancellation
if (isset($_POST['undo_cancel']) && isset($_POST['OrderID'])) {
    $orderId = intval($_POST['OrderID']);
    
    if ($orderId > 0) {
        $stmt = $conn->prepare("UPDATE `order` SET `O_Status` = 'Pending' WHERE `OrderID` = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
    }
}

// Fetch orders with table information
$orders = [];
$sql = "SELECT o.*, t.status AS TableStatus 
        FROM `order` o
        LEFT JOIN `tables` t ON o.number = t.number 
        WHERE o.O_Status != 'Cancelled' AND o.O_Status != 'Completed'
        ORDER BY o.`OrderDateTime` DESC";
$result = $conn->query($sql);

if ($result === false) {
    error_log("Error fetching orders: " . $conn->error);
} else {
    while ($row = $result->fetch_assoc()) {
        // Fetch order items for each order
        $orderId = $row['OrderID'];
        $itemsQuery = "SELECT oi.*, mi.Name AS ItemName 
                      FROM orderitem oi
                      JOIN menu_items mi ON oi.MenuItemID = mi.MenuItemID
                      WHERE oi.OrderID = $orderId";
        $itemsResult = $conn->query($itemsQuery);
        $orderItems = [];
        
        if ($itemsResult) {
            while ($item = $itemsResult->fetch_assoc()) {
                $orderItems[] = $item;
            }
            $itemsResult->free();
        }
        
        $row['items'] = $orderItems;
        $orders[] = $row;
    }
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Management</title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        height: 100%;
        font-family: 'Arial', sans-serif;
        background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
        color: #fff;
    }

    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    /* Header Styles */
    header {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(90deg, #000000 0%, #1a1a1a 100%);
        padding: 15px 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        position: relative;
        z-index: 100;
    }

    .logo-title {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .logo-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid #fff;
        flex-shrink: 0;
        box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
    }

    .logo-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .title {
        font-size: 1.4rem;
        font-weight: bold;
        color: #fff;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
    }

    .logout {
        background: linear-gradient(135deg, #fff, #f0f0f0);
        color: #121212;
        border: none;
        padding: 10px 20px;
        border-radius: 25px;
        cursor: pointer;
        font-weight: bold;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        position: absolute;
        right: 30px;
    }

    .logout:hover {
        background: linear-gradient(135deg, #f0f0f0, #ddd);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }

    /* Navigation */
    .back-btn {
        position: fixed;
        top: 90px;
        left: 30px;
        background: linear-gradient(135deg, #fff, #f0f0f0);
        color: #121212;
        border: none;
        padding: 10px 16px;
        border-radius: 25px;
        cursor: pointer;
        font-weight: bold;
        font-size: 0.9rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 99;
    }

    .back-btn:hover {
        background: linear-gradient(135deg, #f0f0f0, #ddd);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }

    /* Main Content */
    h2 {
        text-align: center;
        color: #fff;
        margin: 40px 20px 30px;
        font-size: 2rem;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        font-weight: 300;
        letter-spacing: 1px;
    }

    .order-container {
        flex: 1;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        padding: 20px 30px;
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }

    /* Order Cards */
    .order-card {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        border-radius: 15px;
        padding: 25px;
        transition: all 0.3s ease;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .order-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s;
    }

    .order-card:hover::before {
        left: 100%;
    }

    .order-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
    }

    .order-card.pending {
        border-color: #ffd700;
        box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
    }

    .order-card.in-progress {
        border-color: #17a2b8;
        box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
    }

    .order-card.completed {
        border-color: #28a745;
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
    }

    .order-card.cancelled {
        border-color: #dc3545;
        opacity: 0.8;
        background: linear-gradient(135deg, #2d1a1a, #3d2222);
        box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
    }

    .order-card h3 {
        margin: 0 0 15px;
        font-size: 1.3em;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }

    .order-details {
        font-size: 0.95em;
        margin: 15px 0;
        line-height: 1.6;
        background: rgba(255, 255, 255, 0.08);
        padding: 15px;
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }

    .order-details p {
        margin: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .order-details strong {
        color: #ffd700;
        font-weight: 600;
    }

    /* Timer */
    .timer {
        display: inline-block;
        margin-top: 12px;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: bold;
        color: #000;
        font-size: 0.9em;
        text-align: center;
        min-width: 80px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

    /* Buttons */
    .toggle-items {
        background: linear-gradient(135deg, #444, #666);
        color: white;
        border: none;
        padding: 8px 16px;
        margin: 10px 0;
        cursor: pointer;
        border-radius: 20px;
        font-size: 0.85em;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .toggle-items:hover {
        background: linear-gradient(135deg, #555, #777);
        transform: translateY(-1px);
    }

    .order-items {
        margin-top: 15px;
        padding: 15px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 10px;
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .order-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.9em;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    .actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn {
        background: linear-gradient(135deg, #fff, #f8f9fa);
        color: #121212;
        border: 2px solid transparent;
        padding: 10px 16px;
        border-radius: 25px;
        cursor: pointer;
        font-size: 0.85em;
        font-weight: 600;
        transition: all 0.3s ease;
        flex: 1;
        min-width: 100px;
        text-align: center;
    }

    .btn:hover {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Footer */
    footer {
        background: linear-gradient(90deg, #000000 0%, #1a1a1a 100%);
        color: #888;
        text-align: center;
        padding: 20px;
        font-size: 0.9rem;
        border-top: 1px solid #333;
        margin-top: auto;
    }

    /* Responsive Design */
    @media (max-width: 768px) {        
        .title {
            font-size: 1.1rem;
        }
        
        header {
            padding: 20px;
        }
        
        .back-btn {
            top: 100px;
            left: 20px;
        }
        
        .order-container {
            grid-template-columns: 1fr;
            padding: 20px;
        }
        
        h2 {
            margin-top: 60px;
            font-size: 1.6rem;
        }
        
        .actions {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .order-card {
            padding: 20px;
        }
        
        .order-details {
            padding: 12px;
        }
        
        .order-details p {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
    }

    /* Loading Animation */
    .order-card.loading {
        opacity: 0.6;
        pointer-events: none;
    }

    /* Status Indicators */
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-pending {
        background: #ffd700;
        color: #000;
    }

    .status-in-progress {
        background: #17a2b8;
        color: #fff;
    }

    .status-completed {
        background: #28a745;
        color: #fff;
    }

    .status-cancelled {
        background: #dc3545;
        color: #fff;
    }
</style>
</head>
<body>

<header>
    <div class="logo-title">
        <div class="logo-circle">
            <img src="images/logo.png" alt="Logo">
        </div>
        <div class="title">Restoran Badhriah Dashboard</div>
    </div>
    <button class="logout" onclick="window.location.href='logout.php'">Logout</button>
</header>

<a href="?back=true" class="back-btn">
    <span>&larr;</span>
    <span>Back to <?php echo isset($role) ? ucfirst($role) : ''; ?> Dashboard</span>
</a>

<h2>Order Management</h2>

<?php if (isset($_SESSION['error_message'])): ?>
    <div style="color: red; text-align: center; margin: 10px 0;">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

<div class="order-container">
<?php foreach ($orders as $row): ?>
    <?php
    $status = strtolower(str_replace(' ', '-', $row['O_Status']));
    $orderTime = strtotime($row['OrderDateTime']);
    $elapsed = time() - $orderTime;

    if ($elapsed < 300) {
        $timerColor = '#28a745';
    } elseif ($elapsed < 600) {
        $timerColor = '#ffd700';
    } else {
        $timerColor = '#dc3545';
    }
    ?>
    <div class="order-card <?= $status ?>" data-order-id="<?= $row['OrderID'] ?>">
        <h3>Order #<?= $row['OrderID'] ?></h3>
        <div class="order-details">
            <p>
                <strong>Table:</strong> 
                <span><?= $row['number'] ?: 'N/A' ?></span>
            </p>
            <?php if ($row['number']): ?>
            <p>
                <strong>Table Status:</strong> 
                <span><?= ucfirst($row['TableStatus'] ?? 'N/A') ?></span>
            </p>
            <?php endif; ?>
            <p>
                <strong>Total:</strong> 
                <span>RM <?= number_format($row['TotalAmount'], 2) ?></span>
            </p>
            <p>
                <strong>Status:</strong> 
                <span class="status-badge status-<?= $status ?>">
                    <?= $row['O_Status'] ?>
                </span> 
            </p>
            <p>
                <strong>Time:</strong> 
                <span><?= date('H:i:s', strtotime($row['OrderDateTime'])) ?></span>
            </p>
            
            <button class="toggle-items" onclick="toggleItems(<?= $row['OrderID'] ?>)">
                Show Items
            </button>
            
            <div class="order-items" id="items-<?= $row['OrderID'] ?>" style="display:none;">
                <?php foreach ($row['items'] as $item): ?>
                    <div class="order-item">
                        <span><?= htmlspecialchars($item['ItemName']) ?> (x<?= $item['Quantity'] ?>)</span>
                        <span>RM <?= number_format($item['Subtotal'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="timer" 
                 data-order-time="<?= $row['OrderDateTime'] ?>" 
                 data-status="<?= $status ?>" 
                 style="background: <?= $timerColor ?>;">
                <?= gmdate("H:i:s", $elapsed) ?>
            </div>
        </div>
        
        <div class="actions">
            <form method="post" style="flex: 1;">
                <input type="hidden" name="OrderID" value="<?= $row['OrderID'] ?>">
                <input type="hidden" name="O_Status" value="<?= $row['O_Status'] ?>">
                <button type="submit" name="toggle_done" class="btn">
                    <?php 
                    if ($row['O_Status'] === 'Pending') {
                        echo 'Mark In Progress';
                    } elseif ($row['O_Status'] === 'In Progress') {
                        echo 'Mark Completed';
                    } else {
                        echo 'Mark Pending';
                    }
                    ?>
                </button>
            </form>
            <form method="post" style="flex: 1;" class="cancel-form">
                <input type="hidden" name="OrderID" value="<?= $row['OrderID'] ?>">
                <button type="submit" name="cancel_order" class="btn">Cancel</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<footer>
    &copy; 2025 Restoran Badhriah. All rights reserved.
</footer>

<script>
function handleCancelResponse(orderId) {
    const orderCard = document.querySelector(`.order-card[data-order-id="${orderId}"]`);
    if (orderCard) {
        orderCard.classList.add('cancelled');
        const statusBadge = orderCard.querySelector('.status-badge');
        if (statusBadge) {
            statusBadge.textContent = 'Cancelled';
            statusBadge.className = 'status-badge status-cancelled';
        }
        setTimeout(() => { 
            orderCard.style.opacity = '0';
            orderCard.style.transform = 'scale(0.8)';
            setTimeout(() => {
                orderCard.style.display = 'none';
            }, 300);
        }, 1500);
    }
}

function toggleItems(orderId) {
    const itemsDiv = document.getElementById(`items-${orderId}`);
    const button = itemsDiv.previousElementSibling;
    
    if (itemsDiv.style.display === 'none') {
        itemsDiv.style.display = 'block';
        button.textContent = 'Hide Items';
    } else {
        itemsDiv.style.display = 'none';
        button.textContent = 'Show Items';
    }
}

// Enhanced form submission with loading states
document.querySelectorAll('.cancel-form').forEach(form => {
    form.onsubmit = function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to cancel this order?')) {
            const formData = new FormData(this);
            const orderCard = this.closest('.order-card');
            const button = this.querySelector('button');
            
            // Add loading state
            orderCard.classList.add('loading');
            button.disabled = true;
            button.textContent = 'Cancelling...';
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                const orderId = formData.get('OrderID');
                handleCancelResponse(orderId);
            })
            .catch(error => {
                console.error('Error:', error);
                orderCard.classList.remove('loading');
                button.disabled = false;
                button.textContent = 'Cancel';
            });
        }
        return false;
    };
});

// Enhanced timer with better formatting
const timers = document.querySelectorAll('.timer');

function updateTimers() {
    timers.forEach(timer => {
        const status = timer.getAttribute('data-status');
        if (status === 'completed' || status === 'cancelled') return;

        const orderTimeStr = timer.getAttribute('data-order-time');
        const orderTime = new Date(orderTimeStr).getTime();
        const now = Date.now();
        const elapsed = Math.floor((now - orderTime) / 1000);

        const hours = String(Math.floor(elapsed / 3600)).padStart(2, '0');
        const mins = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
        const secs = String(elapsed % 60).padStart(2, '0');
        timer.textContent = `${hours}:${mins}:${secs}`;

        // Update color based on elapsed time
        if (elapsed < 300) {
            timer.style.background = '#28a745';
        } else if (elapsed < 600) {
            timer.style.background = '#ffd700';
        } else {
            timer.style.background = '#dc3545';
        }
    });
}

// Update timers every second
setInterval(updateTimers, 1000);

// Initialize timers on page load
updateTimers();
</script>

</body>
</html>

<?php $conn->close(); ?>