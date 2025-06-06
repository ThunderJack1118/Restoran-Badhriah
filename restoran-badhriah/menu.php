<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Use your existing MySQLi database connection file
require_once 'db.php';

// Create menu_items table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS menu_items (
    MenuItemID INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Description TEXT,
    Price DECIMAL(10,2) NOT NULL,
    Category VARCHAR(50) NOT NULL,
    status ENUM('available','unavailable') DEFAULT 'available',
    image_url VARCHAR(500)
)");

// Insert sample menu items with image URLs
$result = $conn->query("SELECT COUNT(*) AS count FROM menu_items");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    // Insert each item separately to handle errors better
    $sample_items = [
        ['Margherita Pizza', 'Classic pizza with tomato and cheese', 12.99, 'Food', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80'],
        ['Coffee', 'Freshly brewed coffee', 3.50, 'Beverages', 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80'],
        ['Dal Palak Recipe', 'Lentils and spinach cooked with Indian spices', 12.90, 'Food/Noodle', 'https://images.unsplash.com/photo-1601050690597-df0568f70950?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80'],
        ['Vegetable Jalfrezi', 'Stir-fried vegetables in a spicy tomato sauce', 14.50, 'Food/Noodle', 'https://images.unsplash.com/photo-1634034379073-f689b460a3fc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80'],
        ['Palak Paneer Bhurji', 'Cottage cheese scrambled with spinach and spices', 18.90, 'Main Course', 'https://images.unsplash.com/photo-1633945274309-2c16c9682a8c?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80'],
        ['Kadai Paneer Gravy', 'Cottage cheese in a rich tomato and bell pepper gravy', 16.50, 'Main Course', 'https://images.unsplash.com/photo-1634034379073-f689b460a3fc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80'],
        ['Fresh Mango Lassi', 'Refreshing yogurt drink with sweet mango', 8.90, 'Beverages', 'https://images.unsplash.com/photo-1600271886742-f049cd5bba13?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80']
    ];
    
    $stmt = $conn->prepare("INSERT INTO menu_items (Name, Description, Price, Category, image_url) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($sample_items as $item) {
        $stmt->bind_param("ssdss", $item[0], $item[1], $item[2], $item[3], $item[4]);
        if (!$stmt->execute()) {
            echo "Error inserting " . $item[0] . ": " . $stmt->error . "<br>";
        }
    }
    $stmt->close();
}

// Get menu items from database
$menu_items = [];
$sql = "SELECT * FROM menu_items";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

// Get statistics
$total_items = count($menu_items);
$categories = [];
foreach ($menu_items as $item) {
    $categories[$item['Category']] = true;
}
$total_categories = count($categories);

// Map emojis to categories
$category_emojis = [
    'Food' => '🍕',
    'Beverages' => '☕',
    'Food/Noodle' => '🍜',
    'Main Course' => '🍛',
    'Desserts' => '🍰'
];

// Helper function to generate random gradients
function getRandomGradient() {
    $gradients = [
        '#667eea, #764ba2',
        '#f093fb, #f5576c',
        '#4facfe, #00f2fe',
        '#fa709a, #fee140',
        '#a18cd1, #fbc2eb',
        '#ffecd2, #fcb69f',
        '#43e97b, #38f9d7',
        '#ff9a9e, #fecfef',
        '#5ee7df, #b490ca',
        '#d299c2, #fef9d7'
    ];
    return $gradients[array_rand($gradients)];
}

// Prepare JavaScript data
$js_gradients = json_encode([
    '#667eea, #764ba2',
    '#f093fb, #f5576c',
    '#4facfe, #00f2fe',
    '#fa709a, #fee140',
    '#a18cd1, #fbc2eb',
    '#ffecd2, #fcb69f',
    '#43e97b, #38f9d7',
    '#ff9a9e, #fecfef',
    '#5ee7df, #b490ca',
    '#d299c2, #fef9d7'
]);

$js_category_emojis = json_encode($category_emojis);

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Menu Management | Restoran Badhriah</title>
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

    .header-actions {
      display: flex;
      gap: 1rem;
      align-items: center;
    }

    .search-box {
      position: relative;
    }

    .search-box input {
      background: #222;
      border: 1px solid #333;
      border-radius: 8px;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      color: #fff;
      font-size: 1rem;
      width: 300px;
    }

    .search-box::before {
      content: '🔍';
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #666;
    }

    .btn {
      padding: 0.75rem 1.5rem;
      background: #fff;
      color: #000;
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

    .btn:hover {
      background: #ddd;
      transform: translateY(-1px);
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    }

    /* Stats Cards */
    .stats-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
      border: 1px solid #333;
      border-radius: 12px;
      padding: 1.5rem;
      text-align: center;
    }

    .stat-number {
      font-size: 2rem;
      font-weight: 600;
      color: #fff;
      margin-bottom: 0.5rem;
    }

    .stat-label {
      color: #999;
      font-size: 0.9rem;
    }

    /* Menu Grid */
    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
      margin-bottom: 3rem;
    }

    .menu-card {
      background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
      border: 1px solid #333;
      border-radius: 12px;
      overflow: hidden;
      transition: all 0.3s ease;
      position: relative;
    }

    .menu-card:hover {
      transform: translateY(-5px);
      border-color: #555;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }

    .menu-image {
      width: 100%;
      height: 200px;
      background-size: cover;
      background-position: center;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .menu-card:hover .menu-image {
      transform: scale(1.05);
      transition: transform 0.3s ease;
    }

    @keyframes shimmer {
      0% { background-position: -1000px 0; }
      100% { background-position: 1000px 0; }
    }

    .menu-image.loading {
      background: linear-gradient(to right, #2d2d2d 8%, #333 18%, #2d2d2d 33%);
      background-size: 1000px 100%;
      animation: shimmer 2s infinite linear;
    }

    .menu-info {
      padding: 1.5rem;
    }

    .menu-title {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: #fff;
    }

    .menu-category {
      color: #999;
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }

    .menu-price {
      font-size: 1.4rem;
      font-weight: 600;
      color: #4ade80;
      margin-bottom: 1rem;
    }

    .menu-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .action-btn {
      padding: 0.5rem 1rem;
      border: none;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 0.3rem;
      text-decoration: none;
    }

    .btn-view {
      background: rgba(34, 197, 94, 0.1);
      color: #22c55e;
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .btn-edit {
      background: rgba(59, 130, 246, 0.1);
      color: #3b82f6;
      border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .btn-delete {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .btn-duplicate {
      background: rgba(168, 85, 247, 0.1);
      color: #a855f7;
      border: 1px solid rgba(168, 85, 247, 0.3);
    }

    .action-btn:hover {
      transform: translateY(-1px);
      opacity: 0.8;
    }

    /* Categories Filter */
    .categories-filter {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;
    }

    .category-btn {
      padding: 0.5rem 1rem;
      background: #222;
      color: #ccc;
      border: 1px solid #333;
      border-radius: 20px;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.9rem;
    }

    .category-btn.active,
    .category-btn:hover {
      background: #fff;
      color: #000;
      border-color: #fff;
    }

    .footer {
      text-align: center;
      margin-top: 3rem;
      color: #777;
      font-size: 0.9rem;
      border-top: 1px solid #333;
      padding-top: 2rem;
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
      
      .header-actions {
        flex-direction: column;
      }
      
      .search-box input {
        width: 100%;
      }
      
      .menu-grid {
        grid-template-columns: 1fr;
      }
      
      .stats-container {
        grid-template-columns: repeat(2, 1fr);
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
    <h1>Menu Management</h1>
    <div class="header-actions">
      <div class="search-box">
        <input type="text" placeholder="Search menu items..." id="searchInput">
      </div>
      <a href="add_menu.php" class="btn btn-primary">➕ Add New Menu</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-container">
    <div class="stat-card">
      <div class="stat-number"><?= $total_items ?></div>
      <div class="stat-label">Total Menu Items</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?= $total_categories ?></div>
      <div class="stat-label">Categories</div>
    </div>
    <div class="stat-card">
      <div class="stat-number">RM 1,847</div>
      <div class="stat-label">Average Revenue</div>
    </div>
    <div class="stat-card">
      <div class="stat-number">18</div>
      <div class="stat-label">Popular Items</div>
    </div>
  </div>

  <!-- Categories Filter -->
  <div class="categories-filter">
    <button class="category-btn active" onclick="filterCategory('all')">All Items</button>
    <?php foreach ($categories as $category => $value): ?>
      <button class="category-btn" onclick="filterCategory('<?= htmlspecialchars($category) ?>')"><?= htmlspecialchars($category) ?></button>
    <?php endforeach; ?>
  </div>

  <!-- Menu Grid -->
  <div class="menu-grid" id="menuGrid">
    <?php foreach ($menu_items as $item): 
      $category_emoji = $category_emojis[$item['Category']] ?? '🍽️';
      $gradient = getRandomGradient();
    ?>
    <div class="menu-card" data-category="<?= htmlspecialchars($item['Category']) ?>">
      <div class="menu-image" 
           data-image-url="<?= htmlspecialchars($item['image_url'] ?? '') ?>"
           data-gradient="<?= htmlspecialchars($gradient) ?>"
           data-emoji="<?= htmlspecialchars($category_emoji) ?>"
           style="background-image: <?= !empty($item['image_url']) ? 'url(' . htmlspecialchars($item['image_url']) . ')' : 'linear-gradient(45deg, ' . $gradient . ')' ?>;">
      </div>
      <div class="menu-info">
        <h3 class="menu-title"><?= htmlspecialchars($item['Name']) ?></h3>
        <p class="menu-category"><?= htmlspecialchars($item['Category']) ?></p>
        <div class="menu-price">RM <?= number_format($item['Price'], 2) ?></div>
        <div class="menu-actions">
          <a href="edit_menu.php?id=<?= $item['MenuItemID'] ?>" class="action-btn btn-edit">✏️ Edit</a>
          <a href="delete_menu.php?id=<?= $item['MenuItemID'] ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this item?')">🗑️ Delete</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="footer">
    &copy; 2025 Restoran Badhriah. All rights reserved.
  </div>
</div>

<!-- JavaScript -->
<script>
  // Pass PHP data to JavaScript
  const gradients = <?= $js_gradients ?>;
  const categoryEmojis = <?= $js_category_emojis ?>;

  // Helper function to get random gradient
  function getRandomGradient() {
    return gradients[Math.floor(Math.random() * gradients.length)];
  }

  // Toggle sidebar
  const toggleBtn = document.getElementById('toggleBtn');
  const sidebar = document.getElementById('sidebar');

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('hidden');
  });

  // Search functionality
  const searchInput = document.getElementById('searchInput');
  const menuCards = document.querySelectorAll('.menu-card');

  searchInput.addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    
    menuCards.forEach(card => {
      const title = card.querySelector('.menu-title').textContent.toLowerCase();
      const category = card.querySelector('.menu-category').textContent.toLowerCase();
      
      if (title.includes(searchTerm) || category.includes(searchTerm)) {
        card.style.display = 'block';
      } else {
        card.style.display = 'none';
      }
    });
  });

  // Category filter
  function filterCategory(category) {
    // Update active button
    document.querySelectorAll('.category-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    event.target.classList.add('active');

    // Filter cards
    menuCards.forEach(card => {
      if (category === 'all' || card.dataset.category === category) {
        card.style.display = 'block';
      } else {
        card.style.display = 'none';
      }
    });
  }

  // Delete item with confirmation
  function deleteItem(button, id) {
    if (confirm('Are you sure you want to delete this menu item?')) {
      const card = button.closest('.menu-card');
      card.style.transform = 'scale(0)';
      card.style.opacity = '0';
      
      setTimeout(() => {
        card.remove();
        updateStats();
        
        // Send AJAX request to delete from database
        fetch('delete_menu.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            alert('Error deleting item: ' + data.message);
          }
        })
        .catch(error => console.error('Error:', error));
      }, 300);
    }
  }

  // Update stats (simplified)
  function updateStats() {
    const totalItems = document.querySelectorAll('.menu-card').length;
    document.querySelector('.stat-number').textContent = totalItems;
  }

  // Image loading functionality
  document.addEventListener('DOMContentLoaded', () => {
    // Animate cards on load
    const cards = document.querySelectorAll('.menu-card');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      
      setTimeout(() => {
        card.style.transition = 'all 0.5s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, index * 100);
    });

    // Handle image loading
    document.querySelectorAll('.menu-image').forEach(img => {
      const imageUrl = img.dataset.imageUrl;
      const gradient = img.dataset.gradient;
      const emoji = img.dataset.emoji;
      
      if (imageUrl) {
        img.classList.add('loading');
        const image = new Image();
        image.src = imageUrl;
        
        image.onload = function() {
          img.classList.remove('loading');
          img.style.backgroundImage = `url('${imageUrl}')`;
        };
        
        image.onerror = function() {
          img.classList.remove('loading');
          // Fallback to gradient with emoji if image fails to load
          img.style.backgroundImage = `linear-gradient(45deg, ${gradient}), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="80">${emoji}</text></svg>')`;
          img.style.backgroundBlendMode = 'overlay';
        };
      }
    });
  });
</script>

</body>
</html>