<?php

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ Invalid or missing expense ID.");
}

$expenseId = (int) $_GET['id'];

$servername = "localhost";
$username = "root";
$password = "Momoyo1130";
$dbname = "test_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

$sql = "DELETE FROM expense WHERE ExpenseID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $expenseId);

if ($stmt->execute()) {
    header("Location: expenses.php?noPrompt=1&deleted=1");
    exit();
} else {
    echo "❌ Error deleting expense record: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
