<?php
session_start();

// Security: Check if user is logged in
if (!isset($_SESSION['StaffID']) || !isset($_SESSION['Role'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "restoran_db";

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error. Please try again later.");
}

// Get user role from session
$userRole = isset($_SESSION['Role']) ? $_SESSION['Role'] : '';

// Determine dashboard URL based on role
$dashboardUrl = 'home.php';
switch($userRole) {
    case 'Manager':
        $dashboardUrl = 'home.php';
        break;
    case 'Waiter':
        $dashboardUrl = 'waiter.php';
        break;
    case 'Cashier':
        $dashboardUrl = 'cashier.php';
        break;
    case 'Chef':
        $dashboardUrl = 'chef.php';
        break;
    default:
        $dashboardUrl = 'home.php';
}

// Enhanced query with more details and sorting
$query = "SELECT i.*, s.Name, s.ContactInfo,
          CASE 
              WHEN i.QuantityInStock <= i.ReorderLevel THEN 'Low Stock'
              WHEN i.QuantityInStock <= (i.ReorderLevel * 1.5) THEN 'Medium Stock'
              ELSE 'Good Stock'
          END as StockStatus,
          CASE 
              WHEN i.ExpiryDate IS NOT NULL AND i.ExpiryDate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Expiring Soon'
              WHEN i.ExpiryDate IS NOT NULL AND i.ExpiryDate <= CURDATE() THEN 'Expired'
              ELSE 'Fresh'
          END as ExpiryStatus
          FROM inventory i 
          LEFT JOIN supplier s ON i.SupplierID = s.SupplierID 
          ORDER BY 
              CASE WHEN i.QuantityInStock <= i.ReorderLevel THEN 1 ELSE 2 END,
              i.IngredientName ASC";

$result = $conn->query($query);

// Get filter parameters
$stockFilter = isset($_GET['stock_filter']) ? $_GET['stock_filter'] : 'all';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Apply filters if needed
if ($stockFilter !== 'all' || !empty($searchTerm)) {
    $whereConditions = [];
    
    if ($stockFilter === 'low') {
        $whereConditions[] = "i.QuantityInStock <= i.ReorderLevel";
    } elseif ($stockFilter === 'expiring') {
        $whereConditions[] = "i.ExpiryDate IS NOT NULL AND i.ExpiryDate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
    
    if (!empty($searchTerm)) {
        $searchTerm = $conn->real_escape_string($searchTerm);
        $whereConditions[] = "(i.IngredientName LIKE '%$searchTerm%' OR s.Name LIKE '%$searchTerm%')";
    }
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $result = $conn->query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory Management - Restoran Badhriah</title>
<style>
html, body {
    height: 100%;
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #121212;
    color: #fff;
}

body {
    display: flex;
    flex-direction: column;
}

header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #000, #1a1a1a);
    padding: 15px 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.5);
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
}

.logo-circle img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.title {
    font-size: 1.4rem;
    font-weight: bold;
    color: #fff;
}



main {
    flex: 1;
    padding: 30px;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
}

h1 {
    text-align: center;
    color: #FFFFFF;
    margin-bottom: 30px;
    font-size: 2.5rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
}

.controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.nav-buttons {
    display: flex;
    gap: 10px;
}

.filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-box {
    padding: 10px 15px;
    border: 2px solid #444;
    border-radius: 25px;
    background: #1a1a1a;
    color: #fff;
    outline: none;
    transition: border-color 0.3s;
}

.search-box:focus {
    border-color: #007bff;
}

.filter-select {
    padding: 10px 15px;
    border: 2px solid #444;
    border-radius: 8px;
    background: #1a1a1a;
    color: #fff;
    outline: none;
}

.button {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 123, 255, 0.4);
}

.stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    border: 1px solid #333;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    color: #ccc;
    font-size: 0.9rem;
}

.low-stock { color: #ff4757; }
.medium-stock { color: #ffa502; }
.good-stock { color: #2ed573; }
.expiring { color: #ff6348; }

.inventory-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.inventory-box {
    border: 2px solid #333;
    border-radius: 12px;
    padding: 20px;
    background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
    position: relative;
}

.inventory-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.4);
}

.inventory-box.low-stock {
    border-color: #ff4757;
    background: linear-gradient(135deg, #2a1a1a, #3a2a2a);
}

.inventory-box.expiring {
    border-color: #ff6348;
}

.item-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 15px;
}

.item-name {
    font-size: 1.3rem;
    font-weight: bold;
    color: #fff;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
}

.item {
    margin: 10px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.item strong {
    color: #ccc;
    min-width: 120px;
}

.quantity-bar {
    width: 100%;
    height: 8px;
    background: #333;
    border-radius: 4px;
    margin: 10px 0;
    overflow: hidden;
}

.quantity-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.checkbox-container {
    margin-top: 15px;
    text-align: center;
}

.checkbox-container label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.3s;
}

.checkbox-container label:hover {
    background: rgba(255,255,255,0.1);
}

.update-section {
    margin-top: 40px;
    text-align: center;
}

.update-button {
    background: linear-gradient(135deg, #2ed573, #17a2b8);
    color: #fff;
    border: none;
    border-radius: 25px;
    padding: 15px 40px;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.update-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(46, 213, 115, 0.4);
}

footer {
    background: linear-gradient(135deg, #000, #1a1a1a);
    color: #888;
    text-align: center;
    padding: 20px;
    font-size: 0.9rem;
    border-top: 1px solid #333;
}

@media (max-width: 768px) {
    .controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .inventory-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-bar {
        grid-template-columns: repeat(2, 1fr);
    }
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-weight: bold;
}

.alert-warning {
    background: rgba(255, 165, 2, 0.2);
    border: 1px solid #ffa502;
    color: #ffa502;
}

.loading {
    display: none;
    text-align: center;
    padding: 20px;
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
</header>

<main>
    <h1>📦 Inventory Management</h1>

    <div class="controls">
        <div class="nav-buttons">
            <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="button">← Back to Dashboard</a>
            <a href="checklist.php" class="button">📋 View Checklist</a>
            <a href="add_inventory.php" class="button">➕ Add Item</a>
        </div>
        
        <div class="filters">
            <input type="text" class="search-box" placeholder="🔍 Search items..." 
                   value="<?= htmlspecialchars($searchTerm) ?>" onkeyup="handleSearch(this.value)">
            <select class="filter-select" onchange="handleFilter(this.value)">
                <option value="all" <?= $stockFilter === 'all' ? 'selected' : '' ?>>All Items</option>
                <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Low Stock</option>
                <option value="expiring" <?= $stockFilter === 'expiring' ? 'selected' : '' ?>>Expiring Soon</option>
            </select>
        </div>
    </div>

    <?php
    // Calculate statistics
    $totalItems = 0;
    $lowStockItems = 0;
    $expiringItems = 0;
    $goodStockItems = 0;
    
    if ($result->num_rows > 0) {
        $result->data_seek(0); // Reset pointer
        while ($row = $result->fetch_assoc()) {
            $totalItems++;
            if ($row['StockStatus'] === 'Low Stock') $lowStockItems++;
            elseif ($row['StockStatus'] === 'Good Stock') $goodStockItems++;
            if ($row['ExpiryStatus'] === 'Expiring Soon') $expiringItems++;
        }
        $result->data_seek(0); // Reset pointer again
    }
    ?>

    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-number"><?= $totalItems ?></div>
            <div class="stat-label">Total Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-number low-stock"><?= $lowStockItems ?></div>
            <div class="stat-label">Low Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-number expiring"><?= $expiringItems ?></div>
            <div class="stat-label">Expiring Soon</div>
        </div>
        <div class="stat-card">
            <div class="stat-number good-stock"><?= $goodStockItems ?></div>
            <div class="stat-label">Good Stock</div>
        </div>
    </div>

    <?php if ($lowStockItems > 0): ?>
    <div class="alert alert-warning">
        ⚠️ Warning: <?= $lowStockItems ?> item(s) are running low on stock and need reordering!
    </div>
    <?php endif; ?>

    <form method="POST" action="checklist_update.php" id="inventoryForm">
        <div class="inventory-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $stockClass = '';
                    if ($row['StockStatus'] === 'Low Stock') $stockClass = 'low-stock';
                    elseif ($row['ExpiryStatus'] === 'Expiring Soon') $stockClass = 'expiring';
                    
                    // Calculate percentage for progress bar
                    $percentage = $row['ReorderLevel'] > 0 ? min(100, ($row['QuantityInStock'] / ($row['ReorderLevel'] * 2)) * 100) : 100;
                    $barColor = $percentage <= 50 ? '#ff4757' : ($percentage <= 75 ? '#ffa502' : '#2ed573');
                    ?>
                    <div class="inventory-box <?= $stockClass ?>">
                        <div class="item-header">
                            <div class="item-name"><?= htmlspecialchars($row['IngredientName']) ?></div>
                            <div class="status-badge <?= strtolower(str_replace(' ', '-', $row['StockStatus'])) ?>">
                                <?= $row['StockStatus'] ?>
                            </div>
                        </div>
                        
                        <div class="item">
                            <strong>Quantity:</strong> 
                            <span><?= htmlspecialchars($row['QuantityInStock']) ?> <?= htmlspecialchars($row['UnitOfMeasure']) ?></span>
                        </div>
                        
                        <div class="quantity-bar">
                            <div class="quantity-fill" style="width: <?= $percentage ?>%; background: <?= $barColor ?>;"></div>
                        </div>
                        
                        <div class="item">
                            <strong>Reorder Level:</strong> 
                            <span><?= htmlspecialchars($row['ReorderLevel']) ?></span>
                        </div>
                        
                        <div class="item">
                            <strong>Supplier:</strong> 
                            <span><?= htmlspecialchars($row['Name'] ?? 'N/A') ?></span>
                        </div>
                        
                        <div class="item">
                            <strong>Contact:</strong> 
                            <span><?= htmlspecialchars($row['ContactInfo'] ?? 'N/A') ?></span>
                        </div>
                        
                        <?php if (!empty($row['ExpiryDate'])): ?>
                        <div class="item">
                            <strong>Expiry Date:</strong> 
                            <span class="<?= strtolower(str_replace(' ', '-', $row['ExpiryStatus'])) ?>">
                                <?= date('M d, Y', strtotime($row['ExpiryDate'])) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="checkbox-container">
                            <label>
                                <input type="checkbox" name="checklist[]" value="<?= $row['IngredientID'] ?>">
                                ✅ Mark for today's checklist
                            </label>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">
                    <h3>No inventory items found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="update-section">
            <button type="submit" class="update-button" onclick="return confirmUpdate()">
                📝 Update Daily Checklist
            </button>
        </div>
    </form>
</main>

<footer>
    &copy; 2025 Restoran Badhriah. All rights reserved. 
    <span style="margin-left: 20px;">Last updated: <?= date('F j, Y g:i A') ?></span>
</footer>

<script>

function confirmUpdate() {
    const checkedItems = document.querySelectorAll('input[name="checklist[]"]:checked');
    if (checkedItems.length === 0) {
        alert('Please select at least one item for the checklist.');
        return false;
    }
    return confirm(`Update checklist with ${checkedItems.length} selected item(s)?`);
}

function handleSearch(searchTerm) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const currentUrl = new URL(window.location);
        if (searchTerm.trim()) {
            currentUrl.searchParams.set('search', searchTerm.trim());
        } else {
            currentUrl.searchParams.delete('search');
        }
        window.location = currentUrl;
    }, 500);
}

function handleFilter(filterValue) {
    const currentUrl = new URL(window.location);
    if (filterValue !== 'all') {
        currentUrl.searchParams.set('stock_filter', filterValue);
    } else {
        currentUrl.searchParams.delete('stock_filter');
    }
    window.location = currentUrl;
}

// Auto-refresh page every 5 minutes to keep data current
setTimeout(() => {
    if (confirm('Refresh data to get latest inventory status?')) {
        location.reload();
    }
}, 300000);
</script>

</body>
</html>