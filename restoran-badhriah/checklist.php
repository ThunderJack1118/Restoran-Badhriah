<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "restoran_db";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle delete
if (isset($_POST['delete_checklist'])) {
    $checklistID = intval($_POST['checklist_id']);
    $stmt = $conn->prepare("DELETE FROM daily_checklist WHERE ChecklistID = ?");
    $stmt->bind_param("i", $checklistID);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle mark as checked
if (isset($_POST['mark_checked'])) {
    $checklistID = intval($_POST['checklist_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First get the ingredient details
        $get_query = "SELECT dc.IngredientID, i.QuantityInStock, i.ReorderLevel 
                     FROM daily_checklist dc
                     JOIN inventory i ON dc.IngredientID = i.IngredientID
                     WHERE dc.ChecklistID = ?";
        $get_stmt = $conn->prepare($get_query);
        $get_stmt->bind_param("i", $checklistID);
        $get_stmt->execute();
        $ingredient = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        // Calculate new quantity (for example, restock to reorder level + 10%)
        $newQuantity = $ingredient['Quantity'] * 1.1; // 10% above reorder level
        // Or you could add a fixed amount:
        // $newQuantity = $ingredient['QuantityInStock'] + 10;
        
        // Update inventory
        $update_stmt = $conn->prepare("UPDATE inventory SET QuantityInStock = ? WHERE IngredientID = ?");
        $update_stmt->bind_param("di", $newQuantity, $ingredient['IngredientID']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Mark as checked
        $check_stmt = $conn->prepare("UPDATE daily_checklist SET IsChecked = 1, CheckedAt = NOW() WHERE ChecklistID = ?");
        $check_stmt->bind_param("i", $checklistID);
        $check_stmt->execute();
        $check_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        die("Error updating inventory: " . $e->getMessage());
    }
}

// Get filter parameters
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
$show_checked = isset($_GET['show_checked']) ? true : false;

// Build query with filters
$query = "SELECT dc.ChecklistID, dc.Date, dc.IsChecked, dc.CheckedAt, dc.Notes,
                 i.IngredientName, i.UnitOfMeasure, i.QuantityInStock, i.ReorderLevel
          FROM daily_checklist dc
          JOIN inventory i ON dc.IngredientID = i.IngredientID
          WHERE DATE(dc.Date) = ?";

if (!$show_checked) {
    $query .= " AND (dc.IsChecked IS NULL OR dc.IsChecked = 0)";
}

$query .= " ORDER BY dc.Date DESC, i.IngredientName ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter_date);
$stmt->execute();
$result = $stmt->get_result();

// Get summary stats
$stats_query = "SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN IsChecked = 1 THEN 1 ELSE 0 END) as checked_items,
    SUM(CASE WHEN i.QuantityInStock <= i.ReorderLevel THEN 1 ELSE 0 END) as low_stock_items
    FROM daily_checklist dc
    JOIN inventory i ON dc.IngredientID = i.IngredientID
    WHERE DATE(dc.Date) = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("s", $filter_date);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Checklist - Restoran Badhriah</title>
<style>
html, body {
    height: 100%;
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
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
    background: linear-gradient(90deg, #000 0%, #1a1a1a 100%);
    padding: 15px 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.logo-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo-circle {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #fff;
    flex-shrink: 0;
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
    color: #fff;
    margin-bottom: 30px;
    font-size: 2.5rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
}

.controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    gap: 20px;
    flex-wrap: wrap;
}

.back-button {
    background: linear-gradient(45deg, #4CAF50, #45a049);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    box-shadow: 0 2px 10px rgba(76,175,80,0.3);
}

.back-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(76,175,80,0.4);
}

.filter-section {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-section input, .filter-section select {
    background: #2d2d2d;
    border: 2px solid #444;
    color: #fff;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 14px;
}

.filter-section input:focus, .filter-section select:focus {
    border-color: #4CAF50;
    outline: none;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #ccc;
}

.stats-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #2d2d2d, #3d3d3d);
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    border: 1px solid #444;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
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

.total { color: #4CAF50; }
.checked { color: #2196F3; }
.low-stock { color: #ff6b6b; }

.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.checklist-box {
    border: 2px solid #444;
    border-radius: 15px;
    padding: 20px;
    background: linear-gradient(135deg, #2d2d2d, #3d3d3d);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    position: relative;
    transition: all 0.3s ease;
}

.checklist-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.checklist-box.checked {
    border-color: #4CAF50;
    background: linear-gradient(135deg, #2d3d2d, #3d4d3d);
}

.checklist-box.low-stock {
    border-color: #ff6b6b;
    background: linear-gradient(135deg, #3d2d2d, #4d3d3d);
}

.item {
    margin: 10px 0;
    padding: 8px 0;
    border-bottom: 1px solid #444;
}

.item:last-child {
    border-bottom: none;
}

.item strong {
    color: #4CAF50;
    display: inline-block;
    width: 80px;
}

.status-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
}

.status-badge.checked {
    background: #4CAF50;
    color: white;
}

.status-badge.pending {
    background: #ff9800;
    color: white;
}

.status-badge.low-stock {
    background: #ff6b6b;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    justify-content: center;
}

.check-btn {
    background: linear-gradient(45deg, #4CAF50, #45a049);
    border: none;
    color: white;
    font-size: 0.9rem;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: bold;
}

.check-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(76,175,80,0.4);
}

.delete-btn {
    background: linear-gradient(45deg, #ff6b6b, #ee5a52);
    border: none;
    color: white;
    font-size: 0.9rem;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: bold;
}

.delete-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255,107,107,0.4);
}

.no-items {
    text-align: center;
    color: #ccc;
    font-size: 1.2rem;
    margin: 40px 0;
    grid-column: 1 / -1;
}

.checked-info {
    margin-top: 10px;
    color: #4CAF50;
    font-size: 0.9rem;
}

footer {
    background: linear-gradient(90deg, #000 0%, #1a1a1a 100%);
    color: #888;
    text-align: center;
    padding: 20px;
    font-size: 0.9rem;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
}

@media (max-width: 768px) {
    .controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-section {
        justify-content: center;
    }
    
    .stats-section {
        grid-template-columns: 1fr;
    }
    
    .grid-container {
        grid-template-columns: 1fr;
    }
    
    main {
        padding: 20px;
    }
    
    h1 {
        font-size: 2rem;
    }
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
    <h1>📋 Daily Inventory Checklist</h1>

    <div class="controls">
        <a href="inventory_management.php" class="back-button">← Back to Inventory</a>
        
        <div class="filter-section">
            <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>" 
                       onchange="this.form.submit()">
                
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="show_checked" name="show_checked" 
                           <?= $show_checked ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label for="show_checked">Show Completed</label>
                </div>
            </form>
        </div>
    </div>

    <div class="stats-section">
        <div class="stat-card">
            <div class="stat-number total"><?= $stats['total_items'] ?></div>
            <div class="stat-label">Total Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-number checked"><?= $stats['checked_items'] ?></div>
            <div class="stat-label">Checked Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-number low-stock"><?= $stats['low_stock_items'] ?></div>
            <div class="stat-label">Low Stock Items</div>
        </div>
    </div>

    <div class="grid-container">
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <?php 
            $isChecked = $row['IsChecked'];
            $isLowStock = $row['QuantityInStock'] <= $row['ReorderLevel'];
            $boxClass = '';
            if ($isChecked) $boxClass .= ' checked';
            if ($isLowStock) $boxClass .= ' low-stock';
            ?>
            <div class="checklist-box<?= $boxClass ?>">
                <?php if ($isChecked): ?>
                    <div class="status-badge checked">✓ Checked</div>
                <?php elseif ($isLowStock): ?>
                    <div class="status-badge low-stock">⚠ Low Stock</div>
                <?php else: ?>
                    <div class="status-badge pending">⏳ Pending</div>
                <?php endif; ?>
                
                <div class="item"><strong>Date:</strong> <?= htmlspecialchars(date('M j, Y', strtotime($row['Date']))) ?></div>
                <div class="item"><strong>Item:</strong> <?= htmlspecialchars($row['IngredientName']) ?></div>
                <div class="item"><strong>Stock:</strong> <?= htmlspecialchars($row['QuantityInStock']) ?> <?= htmlspecialchars($row['UnitOfMeasure']) ?></div>
                <div class="item"><strong>Min Level:</strong> <?= htmlspecialchars($row['ReorderLevel']) ?> <?= htmlspecialchars($row['UnitOfMeasure']) ?></div>
                
                <?php if ($isChecked && $row['CheckedAt']): ?>
                    <div class="checked-info">
                        ✓ Checked on <?= date('M j, Y g:i A', strtotime($row['CheckedAt'])) ?>
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <?php if (!$isChecked): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="checklist_id" value="<?= $row['ChecklistID'] ?>">
                            <button type="submit" name="mark_checked" class="check-btn" 
                                    onclick="return confirm('Mark this item as checked?')">
                                ✓ Mark Checked
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <form method="post" style="display: inline;" 
                          onsubmit="return confirm('Are you sure you want to delete this checklist item?')">
                        <input type="hidden" name="checklist_id" value="<?= $row['ChecklistID'] ?>">
                        <button type="submit" name="delete_checklist" class="delete-btn">
                            🗑 Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-items">
            <p>📭 No checklist items found for <?= date('M j, Y', strtotime($filter_date)) ?></p>
            <p style="color: #888; font-size: 1rem;">Items will appear here when inventory levels trigger restock alerts.</p>
        </div>
    <?php endif; ?>
    </div>
</main>

<footer>
    &copy; 2025 Restoran Badhriah. All rights reserved.
</footer>

<script>
// Auto-refresh page every 5 minutes to keep data current
setTimeout(function() {
    location.reload();
}, 300000);

// Add smooth scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});
</script>

</body>
</html>

<?php
$conn->close();
?>