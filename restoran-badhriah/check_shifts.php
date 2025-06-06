<?php
session_start();
require 'db.php'; // Your database connection file

// Only managers can access this
if (!isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Manager') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

$response = ['updatedShifts' => []];

// Get all active shifts that might have ended
$query = "SELECT ShiftID, StaffID, StartDateTime, EndDateTime 
          FROM workshift 
          WHERE StartDateTime BETWEEN ? AND ? AND EndDateTime IS NOT NULL";
          
$date_from = date('Y-m-d', strtotime('-7 days'));
$date_to = date('Y-m-d');

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();

while ($shift = $result->fetch_assoc()) {
    $response['updatedShifts'][] = [
        'shiftId' => $shift['ShiftID'],
        'startTime' => $shift['StartDateTime'],
        'endTime' => $shift['EndDateTime']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
?>