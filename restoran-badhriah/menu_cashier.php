<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

// Get menu items from database
$menu_items = [];
$sql = "SELECT * FROM menu_items WHERE status = 'available'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

// Get categories
$categories = [];
foreach ($menu_items as $item) {
    if (!in_array($item['Category'], $categories)) {
        $categories[] = $item['Category'];
    }
}

// Map emojis to categories
$category_emojis = [
    'Food' => '🍕',
    'Beverage' => '☕',
    'Food/Noodle' => '🍜',
    'Main Course' => '🍛',
    'Beverages' => '🧃',
    'Desserts' => '🍰'
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Menu & Prices | Restoran Badhriah</title>
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
      background-color: #2a2a2a;
    }

    .menu-image .emoji {
      font-size: 4rem;
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

    .menu-description {
      color: #ccc;
      font-size: 0.9rem;
      line-height: 1.5;
      margin-bottom: 1rem;
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
    <a href="payments.php">💳 Payment History</a>
    <a href="menu.php" class="active">📋 Menu Items</a>
    <a href="refunds.php">🛒 Process Refunds</a>
    <a href="reports.php" >📊 Overall Reports</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="main" id="main">
  <button id="toggleBtn" class="toggle-btn">☰</button>

  <!-- Header -->
  <div class="header">
    <h1>Menu & Prices</h1>
    <div class="header-actions">
      <div class="search-box">
        <input type="text" placeholder="Search menu items..." id="searchInput">
      </div>
    </div>
  </div>

  <!-- Categories Filter -->
  <div class="categories-filter">
    <button class="category-btn active" onclick="filterCategory('all')">All Items</button>
    <?php foreach ($categories as $category): ?>
      <button class="category-btn" onclick="filterCategory('<?= htmlspecialchars($category) ?>')">
        <?= $category_emojis[$category] ?? '🍽️' ?> <?= htmlspecialchars($category) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Menu Grid -->
  <div class="menu-grid" id="menuGrid">
    <?php foreach ($menu_items as $item): 
      $category_emoji = $category_emojis[$item['Category']] ?? '🍽️';
    ?>
    <div class="menu-card" data-category="<?= htmlspecialchars($item['Category']) ?>">
      <div class="menu-image">
        <div class="emoji"><?= $category_emoji ?></div>
      </div>
      <div class="menu-info">
        <h3 class="menu-title"><?= htmlspecialchars($item['Name']) ?></h3>
        <p class="menu-category"><?= htmlspecialchars($item['Category']) ?></p>
        <div class="menu-price">RM <?= number_format($item['Price'], 2) ?></div>
        <?php if (!empty($item['Description'])): ?>
          <p class="menu-description"><?= htmlspecialchars($item['Description']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
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
      const desc = card.querySelector('.menu-description')?.textContent.toLowerCase() || '';
      
      if (title.includes(searchTerm) || category.includes(searchTerm) || desc.includes(searchTerm)) {
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

  // Animate cards on load
  document.addEventListener('DOMContentLoaded', () => {
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
  });
</script>
</body>
</html>