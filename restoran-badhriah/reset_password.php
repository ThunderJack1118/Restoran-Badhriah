<?php
session_start();
if (!isset($_SESSION['Username']) || $_SESSION['Role'] !== 'Manager') {
    die("❌ Access denied.");
}

require_once 'db.php';

// Get staff ID from URL
$staffId = $_GET['id'] ?? 0;
if (!$staffId) {
    die("❌ Invalid staff ID.");
}

// Reset password to default
$defaultPassword = password_hash('default123', PASSWORD_DEFAULT);
$query = "UPDATE Staff SET Password = ? WHERE StaffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("si", $defaultPassword, $staffId);

if ($stmt->execute()) {
    header("Location: staff.php?success=Password+reset+to+default");
} else {
    header("Location: staff.php?error=Error+resetting+password");
}
exit();