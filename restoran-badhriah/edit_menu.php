<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Use your existing MySQLi database connection file
require_once 'db.php';

$message = '';
$error = '';
$menu_item = null;

// Get menu item ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: menu.php");
    exit();
}

$item_id = (int)$_GET['id'];

// Fetch existing menu item
$stmt = $conn->prepare("SELECT * FROM menu_items WHERE MenuItemID = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: menu.php");
    exit();
}

$menu_item = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $description = trim($_POST['Description']);
    $price = floatval($_POST['Price']);
    $category = trim($_POST['Category']);
    $image_url = trim($_POST['image_url']);
    $status = $_POST['status'];
    
    // Validation
    if (empty($name)) {
        $error = "Menu item name is required.";
    } elseif ($price <= 0) {
        $error = "Price must be greater than 0.";
    } elseif (empty($category)) {
        $error = "Category is required.";
    } else {
        // Update menu item
        $stmt = $conn->prepare("UPDATE menu_items SET Name = ?, Description = ?, Price = ?, Category = ?, image_url = ?, status = ? WHERE MenuItemID = ?");
        $stmt->bind_param("ssdsssi", $name, $description, $price, $category, $image_url, $status, $item_id);
        
        if ($stmt->execute()) {
            $message = "Menu item updated successfully!";
            // Refresh the menu item data
            $stmt = $conn->prepare("SELECT * FROM menu_items WHERE MenuItemID = ?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $menu_item = $result->fetch_assoc();
        } else {
            $error = "Error updating menu item: " . $conn->error;
        }
    }
}

// Get all categories for dropdown
$categories_result = $conn->query("SELECT DISTINCT category FROM menu_items ORDER BY category");
$existing_categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $existing_categories[] = $row['category'];
}

// Add common categories if not present
$common_categories = ['Food', 'Beverage', 'Food/Noodle', 'Main Course', 'Beverages', 'Desserts', 'Appetizers', 'Snacks'];
$all_categories = array_unique(array_merge($existing_categories, $common_categories));
sort($all_categories);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Menu Item | Restoran Badhriah</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
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
      background: #555;
      transform: translateY(-1px);
    }

    /* Form Container */
    .form-container {
      max-width: 800px;
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

    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: #fff;
    }

    .form-input,
    .form-select,
    .form-textarea {
      width: 100%;
      padding: 0.75rem;
      background: #222;
      border: 1px solid #333;
      border-radius: 8px;
      color: #fff;
      font-size: 1rem;
      transition: border-color 0.3s;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
      outline: none;
      border-color: #667eea;
    }

    .form-textarea {
      resize: vertical;
      min-height: 100px;
    }

    .form-select option {
      background: #222;
      color: #fff;
    }

    /* Preview Section */
    .preview-section {
      background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
      border: 1px solid #333;
      border-radius: 12px;
      padding: 2rem;
      margin-bottom: 2rem;
    }

    .preview-title {
      font-size: 1.5rem;
      margin-bottom: 1rem;
      color: #fff;
    }

    .preview-card {
      max-width: 320px;
      background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
      border: 1px solid #333;
      border-radius: 12px;
      overflow: hidden;
    }

    .preview-image {
      width: 100%;
      height: 200px;
      background-size: cover;
      background-position: center;
      background-color: #333;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #666;
      font-size: 3rem;
    }

    .preview-info {
      padding: 1.5rem;
    }

    .preview-name {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: #fff;
    }

    .preview-category {
      color: #999;
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }

    .preview-price {
      font-size: 1.4rem;
      font-weight: 600;
      color: #4ade80;
      margin-bottom: 0.5rem;
    }

    .preview-status {
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
      display: inline-block;
    }

    .status-available {
      background: rgba(34, 197, 94, 0.1);
      color: #22c55e;
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .status-unavailable {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }

    /* Buttons */
    .btn {
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 1rem;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: #333;
      color: #fff;
    }

    .btn-secondary:hover {
      background: #555;
      transform: translateY(-1px);
    }

    .form-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }

    /* Messages */
    .message {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
    }

    .message.success {
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.3);
      color: #22c55e;
    }

    .message.error {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #ef4444;
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
      }
      
      .form-actions {
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
    <h1>Edit Menu Item</h1>
    <a href="menu.php" class="back-btn">← Back to Menu</a>
  </div>

  <!-- Messages -->
  <?php if ($message): ?>
    <div class="message success">✅ <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  
  <?php if ($error): ?>
    <div class="message error">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Form Container -->
  <div class="form-container">
    <form method="POST" id="editMenuForm">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="Name">Menu Item Name *</label>
          <input type="text" id="Name" name="Name" class="form-input" 
                 value="<?= htmlspecialchars($menu_item['Name']) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="Price">Price (RM) *</label>
          <input type="number" id="Price" name="Price" class="form-input" 
                 step="0.01" min="0.01" value="<?= $menu_item['Price'] ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="Category">Category *</label>
          <select id="Category" name="Category" class="form-select" required>
            <option value="">Select Category</option>
            <?php foreach ($all_categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>" 
                      <?= ($cat === $menu_item['Category']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
              </option>
            <?php endforeach; ?>
            <option value="custom">+ Add New Category</option>
          </select>
          <input type="text" id="customCategory" name="customCategory" class="form-input" 
                 placeholder="Enter new category name" style="display: none; margin-top: 0.5rem;">
        </div>

        <div class="form-group">
          <label class="form-label" for="status">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="available" <?= ($menu_item['status'] === 'available') ? 'selected' : '' ?>>Available</option>
            <option value="unavailable" <?= ($menu_item['status'] === 'unavailable') ? 'selected' : '' ?>>Unavailable</option>
          </select>
        </div>

        <div class="form-group full-width">
          <label class="form-label" for="Description">Description</label>
          <textarea id="Description" name="Description" class="form-textarea" 
                    placeholder="Enter menu item description"><?= htmlspecialchars($menu_item['Description']) ?></textarea>
        </div>

        <div class="form-group full-width">
          <label class="form-label" for="image_url">Image URL</label>
          <input type="url" id="image_url" name="image_url" class="form-input" 
                 value="<?= htmlspecialchars($menu_item['image_url']) ?>"
                 placeholder="https://example.com/image.jpg">
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Update Menu Item</button>
        <a href="menu.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>

  <!-- Preview Section -->
  <div class="preview-section">
    <h3 class="preview-title">Live Preview</h3>
    <div class="preview-card" id="previewCard">
      <div class="preview-image" id="previewImage">🍽️</div>
      <div class="preview-info">
        <h3 class="preview-name" id="previewName"><?= htmlspecialchars($menu_item['Name']) ?></h3>
        <p class="preview-category" id="previewCategory"><?= htmlspecialchars($menu_item['Category']) ?></p>
        <div class="preview-price" id="previewPrice">RM <?= number_format($menu_item['Price'], 2) ?></div>
        <span class="preview-status status-<?= $menu_item['status'] ?>" id="previewStatus">
          <?= ucfirst($menu_item['status']) ?>
        </span>
      </div>
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

  // Handle custom category
  const categorySelect = document.getElementById('category');
  const customCategoryInput = document.getElementById('customCategory');

  categorySelect.addEventListener('change', function() {
    if (this.value === 'custom') {
      customCategoryInput.style.display = 'block';
      customCategoryInput.required = true;
    } else {
      customCategoryInput.style.display = 'none';
      customCategoryInput.required = false;
      customCategoryInput.value = '';
    }
  });

  // Live preview updates
  const nameInput = document.getElementById('name');
  const priceInput = document.getElementById('price');
  const statusSelect = document.getElementById('status');
  const imageInput = document.getElementById('image_url');
  
  const previewName = document.getElementById('previewName');
  const previewPrice = document.getElementById('previewPrice');
  const previewCategory = document.getElementById('previewCategory');
  const previewStatus = document.getElementById('previewStatus');
  const previewImage = document.getElementById('previewImage');

  function updatePreview() {
    // Update name
    previewName.textContent = nameInput.value || 'Menu Item Name';
    
    // Update price
    const price = parseFloat(priceInput.value) || 0;
    previewPrice.textContent = 'RM ' + price.toFixed(2);
    
    // Update category
    const categoryVal = categorySelect.value === 'custom' ? customCategoryInput.value : categorySelect.value;
    previewCategory.textContent = categoryVal || 'Category';
    
    // Update status
    const statusVal = statusSelect.value;
    previewStatus.textContent = statusVal.charAt(0).toUpperCase() + statusVal.slice(1);
    previewStatus.className = 'preview-status status-' + statusVal;
    
    // Update image
    const imageUrl = imageInput.value;
    if (imageUrl && isValidUrl(imageUrl)) {
      previewImage.style.backgroundImage = `url(${imageUrl})`;
      previewImage.textContent = '';
    } else {
      previewImage.style.backgroundImage = '';
      previewImage.textContent = '🍽️';
    }
  }

  function isValidUrl(string) {
    try {
      new URL(string);
      return true;
    } catch (_) {
      return false;
    }
  }

  // Add event listeners
  nameInput.addEventListener('input', updatePreview);
  priceInput.addEventListener('input', updatePreview);
  categorySelect.addEventListener('change', updatePreview);
  customCategoryInput.addEventListener('input', updatePreview);
  statusSelect.addEventListener('change', updatePreview);
  imageInput.addEventListener('input', updatePreview);

  // Form submission handling
  document.getElementById('editMenuForm').addEventListener('submit', function(e) {
    // Handle custom category
    if (categorySelect.value === 'custom' && customCategoryInput.value.trim()) {
      categorySelect.innerHTML += `<option value="${customCategoryInput.value.trim()}" selected>${customCategoryInput.value.trim()}</option>`;
      categorySelect.value = customCategoryInput.value.trim();
    }
  });

  // Initialize preview with current values
  updatePreview();

  // Add loading animation for form submission
  document.getElementById('editMenuForm').addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '⏳ Updating...';
    submitBtn.disabled = true;
  });
</script>

</body>
</html>