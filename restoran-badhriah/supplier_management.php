<?php
session_start();

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'restoran_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle delete
if (isset($_POST['delete_supplier'])) {
    $id = intval($_POST['supplier_id']);
    $result = $conn->query("SELECT * FROM supplier WHERE SupplierID = $id");
    if ($result && $row = $result->fetch_assoc()) {
        $_SESSION['deleted_supplier'] = $row;
        $conn->query("DELETE FROM supplier WHERE SupplierID = $id");
    }
}

// Handle undo
if (isset($_POST['undo_delete']) && isset($_SESSION['deleted_supplier'])) {
    $s = $_SESSION['deleted_supplier'];
    $stmt = $conn->prepare("INSERT INTO supplier (SupplierID, Name, ContactInfo, Address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $s['SupplierID'], $s['Name'], $s['ContactInfo'], $s['Address']);
    $stmt->execute();
    unset($_SESSION['deleted_supplier']);
}

// Fetch all suppliers
$result = $conn->query("SELECT * FROM supplier ORDER BY Name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Management</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            font-family: Arial, sans-serif;
            background: #121212;
            color: #fff;
        }

        /* Header styling (unchanged) */
        header {
            background-color: #000;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo-title {
            display: flex;
            align-items: center;
            transform: translateX(75%);
        }
        .logo-circle {
            background-color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        .logo-circle img {
            width: 30px;
            height: 30px;
        }
        .title {
            font-size: 1.4rem;
            font-weight: bold;
        }
        .logout {
            background-color: #FFFFFF;
            color: #000000;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 4px;
        }

        /* Footer styling (unchanged) */
        footer {
            background-color: #000;
            color: #fff;
            text-align: center;
            padding: 12px 0;
            margin-top: auto;
        }

        /* Back button */
        .back-btn {
            position: absolute;
            top: 70px;
            left: 20px;
            background-color: #FFFFFF;
            color: #121212;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.3s;
        }
        .back-btn:hover {
            background: #ccc;
            color: #000;
        }

        /* Page title */
        h2 {
            text-align: center;
            color: #fff;
            margin: 30px 0 10px;
            font-size: 1.8rem;
        }

        /* Supplier card grid */
        .supplier-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            padding: 30px 40px;
        }
        .supplier-card {
            background: #1e1e1e;
            border: 1px solid #444;
            border-radius: 16px;
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .supplier-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.4);
        }
        .supplier-card h3 {
            margin: 0;
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #fff;
        }
        .supplier-details {
            font-size: 0.95em;
            margin: 10px 0;
            padding: 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.04);
            color: #ccc;
        }
        .supplier-details p {
            margin: 6px 0;
        }
        .actions {
            text-align: right;
        }
        .btn {
            background: #ff4d4d;
            color: #fff;
            border: none;
            padding: 8px 16px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #e60000;
        }

        /* Undo Button */
        .undo-container {
            text-align: center;
            margin-top: -10px;
        }
        .undo-container form {
            display: inline-block;
        }
        .undo-btn {
            background: #4caf50;
            color: white;
            padding: 6px 12px;
            font-size: 0.95rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .undo-btn:hover {
            background-color: #3e8e41;
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

<a href="javascript:history.back()" class="back-btn">&larr; Back to Dashboard</a>
<h2>Supplier Management</h2>

<div class="supplier-container">
<?php while($row = $result->fetch_assoc()): ?>
    <div class="supplier-card">
        <h3><?= htmlspecialchars($row['Name']) ?></h3>
        <div class="supplier-details">
            <p><strong>📞 Contact:</strong> <?= htmlspecialchars($row['ContactInfo']) ?></p>
            <p><strong>📍 Address:</strong> <?= htmlspecialchars($row['Address']) ?></p>
        </div>
        <div class="actions">
            <form method="post">
                <input type="hidden" name="supplier_id" value="<?= $row['SupplierID'] ?>">
                <button type="submit" name="delete_supplier" class="btn">Delete</button>
            </form>
        </div>
    </div>
<?php endwhile; ?>
</div>

<?php if (isset($_SESSION['deleted_supplier'])): ?>
    <div class="undo-container">
        <form method="post">
            <button type="submit" name="undo_delete" class="undo-btn">Undo Last Delete</button>
        </form>
    </div>
<?php endif; ?>

<footer>
    &copy; 2025 Restoran Badriah. All rights reserved.
</footer>

</body>
</html>

<?php $conn->close(); ?>