<?php
session_start();
if (!isset($_SESSION['Username']) || $_SESSION['Role'] !== 'Manager') {
    die("❌ Access denied.");
}

require_once 'db.php';

if (isset($_GET['MenuItemID'])) {
    $id = intval($_GET['MenuItemID']);
    $stmt = $conn->prepare("DELETE FROM menu_items WHERE MenuItemID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: menu.php");
exit();
?>
