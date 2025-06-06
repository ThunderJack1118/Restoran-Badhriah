<?php
session_start();

// Security: Check if user is logged in
if (!isset($_SESSION['StaffID']) || !isset($_SESSION['Role'])) {
    header("Location: login.php");
    exit();
}

// Check if user has permission to add inventory (only Manager and Chef)
$userRole = isset($_SESSION['Role']) ? $_SESSION['Role'] : '';
if (!in_array($userRole, ['Manager', 'Chef'])) {
    $_SESSION['error'] = "Access denied. Only Managers and Chefs can add inventory items.";
    header("Location: inventory_management.php");
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

// Get suppliers for dropdown
$suppliersQuery = "SELECT SupplierID, Name FROM supplier ORDER BY Name";
$suppliersResult = $conn->query($suppliersQuery);

// Determine dashboard URL based on role
$dashboardUrl = 'dashboard.php';
switch($userRole) {
    case 'Manager':
        $dashboardUrl = 'home.php';
        break;
    case 'Chef':
        $dashboardUrl = 'chef.php';
        break;
    default:
        $dashboardUrl = 'home.php';
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize variables with default values or from POST data
    $IngredientName = isset($_POST['IngredientName']) ? trim($_POST['IngredientName']) : '';
    $QuantityInStock = isset($_POST['QuantityInStock']) ? floatval($_POST['QuantityInStock']) : 0;
    $UnitOfMeasure = isset($_POST['UnitOfMeasure']) ? trim($_POST['UnitOfMeasure']) : '';
    $ReorderLevel = isset($_POST['ReorderLevel']) ? floatval($_POST['ReorderLevel']) : 0;
    $SupplierID = isset($_POST['SupplierID']) && !empty($_POST['SupplierID']) ? intval($_POST['SupplierID']) : null;
    $ExpiryDate = isset($_POST['ExpiryDate']) && !empty($_POST['ExpiryDate']) ? $_POST['ExpiryDate'] : null;
    $Category = isset($_POST['Category']) ? trim($_POST['Category']) : '';
    $CostPerUnit = isset($_POST['CostPerUnit']) && !empty($_POST['CostPerUnit']) ? floatval($_POST['CostPerUnit']) : null;
    $notes = isset($_POST['Notes']) ? trim($_POST['Notes']) : '';
    
    // Validation
    $errors = [];
    
    if (empty($IngredientName)) {
        $errors[] = "Ingredient name is required.";
    }
    
    if ($QuantityInStock < 0) {
        $errors[] = "Quantity in stock cannot be negative.";
    }
    
    if (empty($UnitOfMeasure)) {
        $errors[] = "Unit of measure is required.";
    }
    
    if ($ReorderLevel < 0) {
        $errors[] = "Reorder level cannot be negative.";
    }
    
    // Check if ingredient already exists
    $checkQuery = "SELECT IngredientID FROM inventory WHERE IngredientName = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $IngredientName);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $errors[] = "An ingredient with this name already exists.";
    }
    
    if (empty($errors)) {
        // Insert new inventory item
        $insertQuery = "INSERT INTO inventory (IngredientName, QuantityInStock, UnitOfMeasure, ReorderLevel, SupplierID, ExpiryDate, Category, CostPerUnit, Notes, DateAdded) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("sdssissds", $IngredientName, $QuantityInStock, $UnitOfMeasure, $ReorderLevel, $SupplierID, $ExpiryDate, $Category, $CostPerUnit, $notes);
        
        if ($insertStmt->execute()) {
            $success_message = "Inventory item '$IngredientName' has been added successfully!";
            // Clear form data after success
            $_POST = array();
        } else {
            $error_message = "Error adding inventory item: " . $conn->error;
        }
        
        $insertStmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
    
    $checkStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Inventory Item - Restoran Badhriah</title>
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
    max-width: 800px;
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

.nav-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
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

.form-container {
    background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    border: 1px solid #333;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: bold;
    margin-bottom: 8px;
    color: #ccc;
    display: flex;
    align-items: center;
    gap: 5px;
}

.required {
    color: #ff4757;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 12px 15px;
    border: 2px solid #444;
    border-radius: 8px;
    background: #0a0a0a;
    color: #fff;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.full-width {
    grid-column: 1 / -1;
}

/* Style for select dropdown options */
select option {
    background-color: #1a1a1a;
    color: #fff;
    padding: 10px;
}

/* Style for the dropdown itself */
select {
    background-color: #0a0a0a;
    color: #fff;
}

/* Style for the dropdown when opened */
select:focus {
    background-color: #0a0a0a;
}

.submit-section {
    text-align: center;
    margin-top: 30px;
}

.submit-btn {
    background: linear-gradient(135deg, #2ed573, #17a2b8);
    color: #fff;
    border: none;
    padding: 15px 40px;
    border-radius: 25px;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 200px;
}

.submit-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(46, 213, 115, 0.4);
}

.submit-btn:disabled {
    background: #555;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    font-weight: bold;
    border: 1px solid;
}

.alert-success {
    background: rgba(46, 213, 115, 0.2);
    border-color: #2ed573;
    color: #2ed573;
}

.alert-error {
    background: rgba(255, 71, 87, 0.2);
    border-color: #ff4757;
    color: #ff4757;
}

.form-help {
    font-size: 0.9rem;
    color: #888;
    margin-top: 5px;
}

.input-group {
    display: flex;
    align-items: center;
    gap: 3px;
}

.input-group input {
    flex: 1;
}

.unit-display {
    background: #333;
    padding: 12px 15px;
    border-radius: 8px;
    color: #ccc;
    min-width: 50px;
    text-align: center;
    border: 2px solid #444;
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
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .nav-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    main {
        padding: 20px;
    }
    
    .form-container {
        padding: 25px;
    }
}

.Category-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.Category-tag {
    background: #333;
    color: #ccc;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.Category-tag:hover {
    background: #007bff;
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
</header>

<main>
    <h1>➕ Add New Inventory Item</h1>

    <div class="nav-buttons">
        <a href="inventory_management.php" class="button">← Back to Inventory</a>
        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="button">🏠 Dashboard</a>
        <a href="checklist.php" class="button">📋 View Checklist</a>
    </div>

    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        ✅ <?= $success_message ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-error">
        ❌ <?= $error_message ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="form-container" id="addInventoryForm">
        <div class="form-grid">
            <div class="form-group">
                <label for="IngredientName">
                    🥘 Ingredient Name <span class="required">*</span>
                </label>
                <input type="text" id="IngredientName" name="IngredientName" 
                       value="<?= htmlspecialchars($_POST['IngredientName'] ?? '') ?>" 
                       required maxlength="100" placeholder="e.g., Fresh Tomatoes">
                <div class="form-help">Enter the name of the ingredient or item</div>
            </div>

            <div class="form-group">
                <label for="Category">
                    📂 Category
                </label>
                <input type="text" id="Category" name="Category" 
                       value="<?= htmlspecialchars($_POST['Category'] ?? '') ?>" 
                       maxlength="50" placeholder="e.g., Vegetables">
                <div class="Category-suggestions">
                    <span class="Category-tag" onclick="setCategory('Vegetables')">Vegetables</span>
                    <span class="Category-tag" onclick="setCategory('Meat')">Meat</span>
                    <span class="Category-tag" onclick="setCategory('Dairy')">Dairy</span>
                    <span class="Category-tag" onclick="setCategory('Grains')">Grains</span>
                    <span class="Category-tag" onclick="setCategory('Spices')">Spices</span>
                    <span class="Category-tag" onclick="setCategory('Fruits')">Fruits</span>
                    <span class="Category-tag" onclick="setCategory('Beverages')">Beverages</span>
                </div>
            </div>

            <div class="form-group">
                <label for="QuantityInStock">
                    📦 Current Quantity <span class="required">*</span>
                </label>
                <input type="number" id="QuantityInStock" name="QuantityInStock" 
                       value="<?= htmlspecialchars($_POST['QuantityInStock'] ?? '') ?>" 
                       required min="0" step="0.01" placeholder="0.00">
                <div class="form-help">Current stock quantity available</div>
            </div>

            <div class="form-group">
                <label for="UnitOfMeasure">
                    📏 Unit of Measure <span class="required">*</span>
                </label>
                <select id="UnitOfMeasure" name="UnitOfMeasure" required>
                    <option value="">Select Unit</option>
                    <option value="kg" <?= (isset($_POST['UnitOfMeasure']) && $_POST['UnitOfMeasure'] === 'kg') ? 'selected' : '' ?>>Kilograms (kg)</option>
                    <option value="g" <?= (isset($_POST['UnitOfMeasure']) && $_POST['UnitOfMeasure'] === 'g') ? 'selected' : '' ?>>Grams (g)</option>
                    <option value="L" <?= (isset($_POST['UnitOfMeasure']) && $_POST['UnitOfMeasure'] === 'L') ? 'selected' : '' ?>>Liters (L)</option>
                    <option value="ml" <?= (isset($_POST['UnitOfMeasure']) && $_POST['UnitOfMeasure'] === 'ml') ? 'selected' : '' ?>>Milliliters (ml)</option>
                    <option value="pieces" <?= (isset($_POST['UnitOfMeasure']) && $_POST['UnitOfMeasure'] === 'pieces') ? 'selected' : '' ?>>Pieces</option>
                    <option value="boxes" <?= (isset($_POST['UnitOfMeasure']) && $_POST['UnitOfMeasure'] === 'boxes') ? 'selected' : '' ?>>Boxes</option>
                    <option value="cans" <?= (isset($_POST['UnitOfMeasure']) && $_POST['UnitOfMeasure'] === 'cans') ? 'selected' : '' ?>>Cans</option>
                    <option value="bottles" <?= (isset($_POST['UnitOfMeasure']) && $_POST['UnitOfMeasure'] === 'bottles') ? 'selected' : '' ?>>Bottles</option>
                </select>
            </div>

            <div class="form-group">
                <label for="ReorderLevel">
                    ⚠️ Reorder Level <span class="required">*</span>
                </label>
                <input type="number" id="ReorderLevel" name="ReorderLevel" 
                       value="<?= htmlspecialchars($_POST['ReorderLevel'] ?? '') ?>" 
                       required min="0" step="0.01" placeholder="0.00">
                <div class="form-help">Minimum quantity before reordering is needed</div>
            </div>

            <div class="form-group">
                <label for="CostPerUnit">
                    💰 Cost per Unit
                </label>
                <div class="input-group">
                    <span class="unit-display">RM</span>
                    <input type="number" id="CostPerUnit" name="CostPerUnit" 
                           value="<?= htmlspecialchars($_POST['CostPerUnit'] ?? '') ?>" 
                           min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-help">Purchase cost per unit (optional)</div>
            </div>

            <div class="form-group">
                <label for="SupplierID">
                    🏪 Supplier
                </label>
                <select id="SupplierID" name="SupplierID">
                    <option value="">Select Supplier (Optional)</option>
                    <?php if ($suppliersResult && $suppliersResult->num_rows > 0): ?>
                        <?php while ($supplier = $suppliersResult->fetch_assoc()): ?>
                            <option value="<?= $supplier['SupplierID'] ?>" 
                                    <?= (isset($_POST['SupplierID']) && $_POST['SupplierID'] == $supplier['SupplierID'] ? 'selected' : '') ?>>
                                <?= htmlspecialchars($supplier['Name']) ?>
                            </option>
                        <?php endwhile; ?>
                        <?php $suppliersResult->data_seek(0); // Reset pointer for potential future use ?>
                    <?php endif; ?>
                </select>
                <div class="form-help">Choose the supplier for this ingredient</div>
            </div>

            <div class="form-group">
                <label for="ExpiryDate">
                    📅 Expiry Date
                </label>
                <input type="date" id="ExpiryDate" name="ExpiryDate" 
                       value="<?= htmlspecialchars($_POST['ExpiryDate'] ?? '') ?>" 
                       min="<?= date('Y-m-d') ?>">
                <div class="form-help">Leave empty if item doesn't expire</div>
            </div>

            <div class="form-group full-width">
                <label for="notes">
                    📝 Notes
                </label>
                <textarea id="Notes" name="Notes" placeholder="Additional notes about this inventory item..."><?= htmlspecialchars($_POST['Notes'] ?? '') ?></textarea>
                <div class="form-help">Any additional information or special handling instructions</div>
            </div>
        </div>

        <div class="submit-section">
            <button type="submit" class="submit-btn" id="submitBtn">
                ✅ Add Inventory Item
            </button>
        </div>
    </form>
</main>

<footer>
    &copy; 2025 Restoran Badhriah. All rights reserved. 
    <span style="margin-left: 20px;">Inventory Management System</span>
</footer>

<script>
function setCategory(category) {
    document.getElementById('Category').value = category;
}

// Form validation and enhancement
document.getElementById('addInventoryForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Adding Item...';
    
    // Re-enable button after 3 seconds in case of error
    setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = '✅ Add Inventory Item';
    }, 3000);
});

// Auto-calculate suggestions
document.getElementById('QuantityInStock').addEventListener('input', function() {
    const quantity = parseFloat(this.value) || 0;
    const reorderInput = document.getElementById('ReorderLevel');
    
    if (reorderInput.value === '' && quantity > 0) {
        // Suggest reorder level as 20% of current stock
        const suggestedReorder = Math.max(1, Math.round(quantity * 0.2 * 100) / 100);
        reorderInput.placeholder = `Suggested: ${suggestedReorder}`;
    }
});

// Expiry date warning
document.getElementById('ExpiryDate').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const today = new Date();
    const daysDiff = Math.ceil((selectedDate - today) / (1000 * 60 * 60 * 24));
    
    if (daysDiff <= 7 && daysDiff > 0) {
        alert('⚠️ Warning: This item expires within a week!');
    } else if (daysDiff <= 0) {
        alert('❌ Error: Expiry date cannot be in the past!');
        this.value = '';
    }
});

// Auto-save form data to prevent loss
setInterval(() => {
    const formData = new FormData(document.getElementById('addInventoryForm'));
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    localStorage.setItem('inventory_form_backup', JSON.stringify(data));
}, 30000); // Save every 30 seconds

// Restore form data on page load
window.addEventListener('load', () => {
    const backup = localStorage.getItem('inventory_form_backup');
    if (backup && !<?= !empty($_POST) ? 'true' : 'false' ?>) {
        const data = JSON.parse(backup);
        if (confirm('Restore previously entered data?')) {
            Object.keys(data).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element && element.value === '') {
                    element.value = data[key];
                }
            });
        }
    }
});

// Clear backup on successful submission
<?php if (!empty($success_message)): ?>
localStorage.removeItem('inventory_form_backup');
<?php endif; ?>
</script>

</body>
</html>