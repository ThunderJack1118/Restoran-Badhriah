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

// Toggle staff active status
$query = "UPDATE Staff SET IsActive = NOT IsActive WHERE StaffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staffId);

if ($stmt->execute()) {
    header("Location: staff.php?success=Staff+status+updated");
} else {
    header("Location: staff.php?error=Error+updating+staff+status");
}
exit();