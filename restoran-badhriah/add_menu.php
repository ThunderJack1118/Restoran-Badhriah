<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Use existing MySQLi database connection file
require_once 'db.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $description = trim($_POST['Description']);
    $price = floatval($_POST['Price']);
    $category = trim($_POST['Category']);
    $status = $_POST['status'];
    $image_url = trim($_POST['image_url'] ?? '');
    
    // Validation
    if (empty($name) || empty($price) || empty($category)) {
        $error_message = "Name, Price, and Category are required fields.";
    } elseif ($price <= 0) {
        $error_message = "Price must be greater than 0.";
    } elseif (!empty($image_url) && !filter_var($image_url, FILTER_VALIDATE_URL)) {
        $error_message = "Please enter a valid image URL";
    } else {
        // Check if menu item already exists
        $check_sql = "SELECT MenuItemID FROM menu_items WHERE Name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "A menu item with this name already exists.";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert new menu item
                $insert_sql = "INSERT INTO menu_items (Name, Description, Price, Category, status, image_url) VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssdsss", $name, $description, $price, $category, $status, $image_url);
                
                if ($insert_stmt->execute()) {
                    $conn->commit();
                    $success_message = "Menu item added successfully!";
                    // Clear form data
                    $name = $description = $category = $image_url = '';
                    $price = 0;
                    $status = 'available';
                } else {
                    throw new Exception("Error adding menu item: " . $conn->error);
                }
                $insert_stmt->close();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = $e->getMessage();
            }
        }
        $check_stmt->close();
    }
}

// Get existing categories for the dropdown
$categories_sql = "SELECT DISTINCT category FROM menu_items ORDER BY category";
$categories_result = $conn->query($categories_sql);
$existing_categories = [];
if ($categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $existing_categories[] = $row['category'];
    }
}

$conn->close();

// Predefined categories with emojis
$predefined_categories = [
    'Food' => '🍕',
    'Beverages' => '☕',
    'Food/Noodle' => '🍜',
    'Main Course' => '🍛',
    'Desserts' => '🍰',
    'Appetizers' => '🥗',
    'Snacks' => '🍿'
];

// Merge existing and predefined categories
$all_categories = array_unique(array_merge($existing_categories, array_keys($predefined_categories)));
sort($all_categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Menu Item | Restoran Badhriah</title>
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
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 10px;
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
            max-width: 1000px;
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

        /* Form Container */
        .form-container {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid #333;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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
            background: #222;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .category-input-group {
            display: flex;
            gap: 0.5rem;
        }

        .category-input-group select {
            flex: 1;
        }

        .add-category-btn {
            padding: 0.75rem 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .add-category-btn:hover {
            background: #5a67d8;
        }

        .custom-category-input {
            display: none;
            margin-top: 0.5rem;
        }

        .custom-category-input.show {
            display: block;
        }

        /* Price input styling */
        .price-input-group {
            position: relative;
        }

        .price-input-group::before {
            content: 'RM';
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-weight: 600;
        }

        .price-input-group input {
            padding-left: 2.5rem;
        }

        /* Status toggle */
        .status-toggle {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .status-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .status-option input[type="radio"] {
            width: auto;
            margin: 0;
        }

        /* Submit button */
        .submit-section {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #333;
        }

        .submit-btn {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
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

        /* Preview section */
        .preview-section {
            background: #222;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .preview-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #fff;
        }

        .menu-preview {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid #333;
            border-radius: 8px;
            padding: 1rem;
            max-width: 300px;
        }

        .preview-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .preview-category {
            color: #999;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .preview-description {
            color: #ccc;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .preview-price {
            font-size: 1.2rem;
            font-weight: 600;
            color: #4ade80;
        }

        .preview-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .status-available {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .status-unavailable {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        /* Image preview styles */
        #previewImageContainer {
            text-align: center;
            background: #1a1a1a;
            padding: 1rem;
            border-radius: 8px;
            border: 1px dashed #333;
            margin-bottom: 1rem;
        }

        #previewImageContainer img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            object-fit: contain;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 1rem;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .category-input-group {
                flex-direction: column;
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
  <a href="home.php">🏠 Dashboard</a>
  <a href="workshift.php">⏱️ Clock In/Out</a>
  <a href="reports.php">📊 Overall Reports</a>
  <a href="orders.php">🍽️ Orders</a>
  <a href="menu.php" class="active">📋 Menu Items</a>
  <a href="inventory_management.php">📦 Inventory</a>
  <a href="payments.php">💳 Payments</a>
  <a href="staff.php">💼 Staff Management</a>
  <a href="salaries.php">💰 Salaries and Expenses</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="main" id="main">
    <button id="toggleBtn" class="toggle-btn">☰</button>

    <!-- Header -->
    <div class="header">
        <h1>Add New Menu Item</h1>
        <a href="menu.php" class="back-btn">← Back to Menu</a>
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

    <!-- Form Container -->
    <div class="form-container">
        <form method="POST" id="menuForm">
            <div class="form-grid">
                <!-- Menu Name -->
                <div class="form-group">
                    <label for="name">Menu Item Name *</label>
                    <input type="text" id="Name" name="Name" required 
                           value="<?= isset($name) ? htmlspecialchars($name) : '' ?>"
                           placeholder="e.g., Margherita Pizza">
                </div>

                <!-- Price -->
                <div class="form-group">
                    <label for="Price">Price *</label>
                    <div class="price-input-group">
                        <input type="number" id="Price" name="Price" step="0.01" min="0.01" required
                               value="<?= isset($price) ? $price : '' ?>"
                               placeholder="0.00">
                    </div>
                </div>

                <!-- Image URL -->
                <div class="form-group">
                    <label for="image_url">Image URL</label>
                    <input type="url" id="image_url" name="image_url" 
                           value="<?= isset($image_url) ? htmlspecialchars($image_url) : '' ?>"
                           placeholder="https://example.com/image.jpg">
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label for="Category">Category *</label>
                    <div class="category-input-group">
                        <select id="Category" name="Category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"
                                        <?= (isset($category) && $category === $cat) ? 'selected' : '' ?>>
                                    <?= isset($predefined_categories[$cat]) ? $predefined_categories[$cat] . ' ' : '' ?>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="custom">+ Add New Category</option>
                        </select>
                    </div>
                    <div class="custom-category-input" id="customCategoryInput">
                        <input type="text" id="newCategory" placeholder="Enter new category name">
                        <button type="button" class="add-category-btn" onclick="addCustomCategory()">Add</button>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label>Status</label>
                    <div class="status-toggle">
                        <div class="status-option">
                            <input type="radio" id="available" name="status" value="available" 
                                   <?= (!isset($status) || $status === 'available') ? 'checked' : '' ?>>
                            <label for="available">🟢 Available</label>
                        </div>
                        <div class="status-option">
                            <input type="radio" id="unavailable" name="status" value="unavailable"
                                   <?= (isset($status) && $status === 'unavailable') ? 'checked' : '' ?>>
                            <label for="unavailable">🔴 Unavailable</label>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group full-width">
                    <label for="description">Description</label>
                    <textarea id="Description" name="Description" 
                              placeholder="Describe the menu item, ingredients, etc."><?= isset($description) ? htmlspecialchars($description) : '' ?></textarea>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="submit-section">
                <button type="submit" class="submit-btn">
                    ➕ Add Menu Item
                </button>
            </div>
        </form>
    </div>

    <!-- Preview Section -->
    <div class="preview-section">
        <div class="preview-title">🔍 Live Preview</div>
        <div class="menu-preview" id="menuPreview">
            <div id="previewImageContainer">
                <img id="previewImage" src="" alt="Menu Item Preview" style="max-width: 100%; max-height: 200px; border-radius: 8px; display: none;">
            </div>
            <div class="preview-name" id="previewName">Menu Item Name</div>
            <div class="preview-category" id="previewCategory">Category</div>
            <div class="preview-description" id="previewDescription">Description will appear here...</div>
            <div class="preview-price" id="previewPrice">RM 0.00</div>
            <span class="preview-status status-available" id="previewStatus">Available</span>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
    // Toggle sidebar
    const toggleBtn = document.getElementById('toggleBtn');
    const sidebar = document.getElementById('sidebar');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
    });

    // Category selection handling
    const categorySelect = document.getElementById('Category');
    const customCategoryInput = document.getElementById('customCategoryInput');

    categorySelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customCategoryInput.classList.add('show');
            this.removeAttribute('name');
        } else {
            customCategoryInput.classList.remove('show');
            this.setAttribute('name', 'Category');
        }
        updatePreview();
    });

    function addCustomCategory() {
        const newCategoryInput = document.getElementById('newCategory');
        const newCategory = newCategoryInput.value.trim();
        
        if (newCategory) {
            // Add new option to select
            const option = document.createElement('option');
            option.value = newCategory;
            option.textContent = newCategory;
            option.selected = true;
            
            // Insert before the "Add New Category" option
            const customOption = categorySelect.querySelector('option[value="custom"]');
            categorySelect.insertBefore(option, customOption);
            
            // Hide custom input and restore name attribute
            customCategoryInput.classList.remove('show');
            categorySelect.setAttribute('name', 'Category');
            
            // Clear input
            newCategoryInput.value = '';
            
            updatePreview();
        }
    }

    // Live preview functionality
    function updatePreview() {
        const name = document.getElementById('Name').value || 'Menu Item Name';
        const category = categorySelect.value || 'Category';
        const description = document.getElementById('Description').value || 'Description will appear here...';
        const price = document.getElementById('Price').value || '0.00';
        const status = document.querySelector('input[name="status"]:checked').value;
        const imageUrl = document.getElementById('image_url').value;
        const previewImage = document.getElementById('previewImage');
        const imageContainer = document.getElementById('previewImageContainer');
        
        document.getElementById('previewName').textContent = name;
        document.getElementById('previewCategory').textContent = category;
        document.getElementById('previewDescription').textContent = description;
        document.getElementById('previewPrice').textContent = `RM ${parseFloat(price).toFixed(2)}`;
        
        const statusElement = document.getElementById('previewStatus');
        statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        statusElement.className = `preview-status status-${status}`;
        
        if (imageUrl) {
            previewImage.src = imageUrl;
            previewImage.style.display = 'block';
            // Add error handling for broken images
            previewImage.onerror = function() {
                imageContainer.innerHTML = '<div style="color: #ef4444; padding: 0.5rem; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">⚠️ Image failed to load</div>';
            };
        } else {
            previewImage.style.display = 'none';
            // Restore the container if it was replaced by error message
            if (!imageContainer.querySelector('img')) {
                imageContainer.innerHTML = '<img id="previewImage" src="" alt="Menu Item Preview" style="max-width: 100%; max-height: 200px; border-radius: 8px; display: none;">';
            }
        }
    }

    // Add event listeners for live preview
    document.getElementById('Name').addEventListener('input', updatePreview);
    document.getElementById('Description').addEventListener('input', updatePreview);
    document.getElementById('Price').addEventListener('input', updatePreview);
    document.getElementById('image_url').addEventListener('input', updatePreview);
    document.querySelectorAll('input[name="status"]').forEach(radio => {
        radio.addEventListener('change', updatePreview);
    });

    // Form validation
    document.getElementById('menuForm').addEventListener('submit', function(e) {
        const name = document.getElementById('Name').value.trim();
        const price = parseFloat(document.getElementById('Price').value);
        const category = categorySelect.value || document.getElementById('newCategory').value.trim();
        const imageUrl = document.getElementById('image_url').value.trim();
        
        if (!name || !price || !category) {
            e.preventDefault();
            alert('Please fill in all required fields (Name, Price, Category)');
            return;
        }
        
        if (price <= 0) {
            e.preventDefault();
            alert('Price must be greater than 0');
            return;
        }
        
        if (imageUrl && !imageUrl.startsWith('http')) {
            e.preventDefault();
            alert('Please enter a valid image URL starting with http:// or https://');
            return;
        }
    });

    // Initialize preview on page load
    document.addEventListener('DOMContentLoaded', updatePreview);
</script>

</body>
</html>