<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Use your existing MySQLi database connection file
require_once 'db.php';

// Fetch tables from database
$tables = [];
$sql = "SELECT * FROM `tables`";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $tables[] = $row;
    }
}

// Determine back URL based on user role
$backUrl = 'javascript:history.back()'; // Default fallback

if (isset($_SESSION['StaffID'])) {
    $userId = $_SESSION['StaffID'];
    
    // Fetch user's role from database
    $query = "SELECT Role FROM staff WHERE StaffID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $role = $user['Role'];
        
        // Set back URL based on role
        switch ($role) {
            case 'Waiter':
                $backUrl = 'waiter.php';
                break;
            case 'Chef':
                $backUrl = 'chef.php';
                break;
            case 'Manager':
                $backUrl = 'home.php';
                break;
            case 'Cashier':
                $backUrl = 'cashier.php';
                break;
        }
    }
}

// Check if menu_items table exists, create if not
$table_check = $conn->query("SHOW TABLES LIKE 'menu_items'");
if ($table_check->num_rows == 0) {
    // Create menu_items table
    $create_table = "CREATE TABLE `menu_items` (
        `MenuItemsID` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `Name` VARCHAR(255) NOT NULL,
        `Description` TEXT,
        `Price` DECIMAL(10,2) NOT NULL,
        `Category` VARCHAR(50) NOT NULL,
        `status` ENUM('available','unavailable') DEFAULT 'available'
    )";
    
    if ($conn->query($create_table)) {
        // Insert sample data
        $insert_data = "INSERT INTO `menu_items` (Name, Description, Price, Category) VALUES
            ('Margherita Pizza', 'Classic pizza with tomato and cheese', 12.99, 'Food'),
            ('Coffee', 'Freshly brewed coffee', 3.50, 'Beverage')";
        $conn->query($insert_data);
    }
}

// Fetch menu items
$menu_items = [];
$menu_query = "SELECT * FROM menu_items WHERE status = 'available' ORDER BY Category, Name";
$menu_result = $conn->query($menu_query);
if ($menu_result->num_rows > 0) {
    while($row = $menu_result->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

// Handle order submission
if ($_POST && isset($_POST['submit_order'])) {
    try {
        $conn->begin_transaction();
        
        $table_id = $_POST['table_id'];
        $order_items = json_decode($_POST['order_items'], true);
        $total_amount = $_POST['total_amount'];
        
        // Validate JSON decode
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid order items format");
        }
        
        // First, verify the table exists and get its number
        $table_check = $conn->prepare("SELECT number, status FROM tables WHERE id = ?");
        $table_check->bind_param("i", $table_id);
        $table_check->execute();
        $table_result = $table_check->get_result();
        
        if ($table_result->num_rows === 0) {
            throw new Exception("Selected table does not exist");
        }
        
        $table_data = $table_result->fetch_assoc();
        $table_number = $table_data['number'];
        
        // Insert order (modified to match your schema)
        $order_query = "INSERT INTO `order` (StaffID, TotalAmount, O_Status, number, OrderType, OrderDateTime) 
                        VALUES (?, ?, 'Pending', ?, 'DineIn', NOW())";
        $stmt = $conn->prepare($order_query);
        $stmt->bind_param("ids", $_SESSION['StaffID'], $total_amount, $table_number);
        $stmt->execute();
        $order_id = $conn->insert_id;
        
        // Insert order items (modified to match your schema)
        foreach ($order_items as $item) {
            $subtotal = $item['quantity'] * $item['price'];
            $item_query = "INSERT INTO orderitem (OrderID, MenuItemID, Quantity, UnitPrice, Subtotal) 
                           VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($item_query);
            $stmt->bind_param("iiidd", $order_id, $item['id'], $item['quantity'], $item['price'], $subtotal);
            $stmt->execute();
        }
        
        // Update table status to 'occupied'
        $update_table = $conn->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?");
        $update_table->bind_param("i", $table_id);
        $update_table->execute();
        
        $conn->commit();
        
        echo "<script>alert('Order submitted successfully!'); window.location.href = 'take_order.php';</script>";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error submitting order: " . addslashes($e->getMessage()) . "');</script>";
        // For debugging:
        echo "Error: " . $e->getMessage();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Customer Order | Restoran Badhriah</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #111;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background-color: #000;
            padding: 1rem;
            border-bottom: 1px solid #333;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: auto;
        }

        .logo-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #fff;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .back-btn {
            background-color: #fff;
            color: #000;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background-color: #ddd;
        }

        main {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: auto;
            width: 100%;
        }

        .order-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            height: calc(100vh - 200px);
        }

        .menu-section {
            background-color: #1a1a1a;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #333;
            overflow-y: auto;
        }

        .order-summary {
            background-color: #2a2a2a;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #333;
            height: fit-content;
            position: sticky;
            top: 0;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-select {
            margin-bottom: 1.5rem;
        }

        .table-select label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .table-select select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #444;
            background-color: #333;
            color: #fff;
            font-size: 1rem;
        }

        .menu-category {
            margin-bottom: 2rem;
        }

        .category-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2196F3;
            border-bottom: 1px solid #333;
            padding-bottom: 0.5rem;
        }

        .menu-item {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 1rem;
            padding: 1rem;
            background-color: #333;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }
        
        .menu-item h3 {
            width: 100%;
            margin: 0 0 0.5rem 0;
            color: #fff;
        }

        .menu-item span {
            color: #bbb;
        }

        .menu-item .price {
            margin-left: auto;
            color: #ffd700;
            font-weight: bold;
        }

        .menu-item button {
            margin-left: auto;
        }

        .menu-item:hover {
            background-color: #404040;
        }

        .item-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-category {
            color: #aaa;
            font-size: 0.9rem;
        }

        .item-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4CAF50;
            margin-right: 1rem;
        }

        .add-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .add-btn:hover {
            background-color: #45a049;
        }

        .order-items {
            max-height: 250px;
            overflow-y: auto;
            margin-bottom: 1rem;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 0.5rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background-color: #333;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .order-item:last-child {
            margin-bottom: 0;
        }

        .item-details {
            flex-grow: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-total {
            color: #4CAF50;
            font-size: 0.9rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .qty-btn {
            background-color: #555;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover {
            background-color: #666;
        }

        .quantity {
            min-width: 30px;
            text-align: center;
            font-weight: 600;
        }

        .total-section {
            border-top: 1px solid #444;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .total-amount {
            font-size: 1.4rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1rem;
            color: #4CAF50;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(33, 150, 243, 0.3);
        }

        .submit-btn:disabled {
            background: #555;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .empty-order {
            text-align: center;
            color: #aaa;
            padding: 2rem;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .order-container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            main {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<header>
  <div class="header-content">
    <div class="logo-title">
      <img src="images/logo.png" alt="Logo" class="logo">
      <span>Restoran Badhriah Dashboard</span>
    </div>
    <a href="<?php echo $backUrl; ?>" class="back-btn">← Back to Dashboard</a>
    </div>
  </div>
</header>

    <main>
        <div class="order-container">
            <!-- Menu Section -->
            <div class="menu-section">
                <div class="section-title">
                    📋 Menu Items
                </div>
                
                <?php
                if (count($menu_items) > 0) {
                    $categories = [];
                    foreach ($menu_items as $item) {
                        $categories[$item['Category']][] = $item;
                    }
                    
                    foreach ($categories as $category => $items): ?>
                        <div class="menu-category">
                            <div class="category-title"><?= htmlspecialchars(ucfirst($category)) ?></div>
                            <?php foreach ($items as $item): ?>
                                <div class="menu-item">
                                    <div class="item-info">
                                        <h4><?= htmlspecialchars($item['Name']) ?></h4>
                                        <div class="item-category"><?= htmlspecialchars(ucfirst($item['Category'])) ?></div>
                                    </div>
                                    <div class="item-price">RM <?= number_format($item['Price'], 2) ?></div>
                                    <button class="add-btn" onclick="addToOrder(<?= $item['MenuItemID'] ?>, '<?= htmlspecialchars($item['Name']) ?>', <?= $item['Price'] ?>)">
                                        Add
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach;
                } else {
                    echo '<div class="empty-order">No menu items available</div>';
                }
                ?>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <div class="section-title">
                    🛒 Order Summary
                </div>

                <div class="table-select">
                    <label for="table_select">Select Table:</label>
                    <select id="table_select" required>
                        <option value="">Choose Table</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?= $table['id'] ?>">
                                Table <?= $table['number'] ?> 
                                <?php if ($table['status'] === 'occupied'): ?>
                                    (Occupied)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="order-items" id="orderItems">
                    <div class="empty-order">
                        No items added yet
                    </div>
                </div>

                <div class="total-section">
                    <div class="total-amount" id="totalAmount">
                        Total: RM 0.00
                    </div>
                    <button class="submit-btn" id="submitBtn" disabled onclick="submitOrder()">
                        Submit Order
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        let orderItems = [];
        let totalAmount = 0;

        function addToOrder(id, name, price) {
            const existingItem = orderItems.find(item => item.id === id);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                orderItems.push({
                    id: id,
                    name: name,
                    price: price,
                    quantity: 1
                });
            }
            
            updateOrderDisplay();
        }

        function updateQuantity(id, change) {
            const item = orderItems.find(item => item.id === id);
            if (item) {
                item.quantity += change;
                if (item.quantity <= 0) {
                    orderItems = orderItems.filter(item => item.id !== id);
                }
            }
            updateOrderDisplay();
        }

        function updateOrderDisplay() {
            const orderItemsContainer = document.getElementById('orderItems');
            const totalAmountElement = document.getElementById('totalAmount');
            const submitBtn = document.getElementById('submitBtn');
            
            if (orderItems.length === 0) {
                orderItemsContainer.innerHTML = '<div class="empty-order">No items added yet</div>';
                totalAmount = 0;
            } else {
                let html = '';
                totalAmount = 0;
                
                orderItems.forEach(item => {
                    const itemTotal = item.price * item.quantity;
                    totalAmount += itemTotal;
                    
                    html += `
                        <div class="order-item">
                            <div class="item-details">
                                <div class="item-name">${item.name}</div>
                                <div class="item-total">RM ${itemTotal.toFixed(2)}</div>
                            </div>
                            <div class="quantity-controls">
                                <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)">-</button>
                                <span class="quantity">${item.quantity}</span>
                                <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
                            </div>
                        </div>
                    `;
                });
                
                orderItemsContainer.innerHTML = html;
            }
            
            totalAmountElement.textContent = `Total: RM ${totalAmount.toFixed(2)}`;
            
            // Enable/disable submit button
            const tableSelected = document.getElementById('table_select').value;
            submitBtn.disabled = orderItems.length === 0 || !tableSelected;
        }

        function submitOrder() {
            const tableId = document.getElementById('table_select').value;
            
            if (!tableId) {
                alert('Please select a table');
                return;
            }
            
            if (orderItems.length === 0) {
                alert('Please add items to the order');
                return;
            }
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="submit_order" value="1">
                <input type="hidden" name="table_id" value="${tableId}">
                <input type="hidden" name="order_items" value='${JSON.stringify(orderItems)}'>
                <input type="hidden" name="total_amount" value="${totalAmount}">
            `;
            
            document.body.appendChild(form);
            form.submit();
        }

        // Update submit button when table is selected
        document.getElementById('table_select').addEventListener('change', function() {
            updateOrderDisplay();
        });
    </script>
</body>
</html>