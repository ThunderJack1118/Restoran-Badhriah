<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "restoran_db"; // replace with your database name

$conn = new mysqli($host, $user, $password, $dbname);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["checklist"])) {
    $ingredient_ids = $_POST["checklist"];
    $date = date("Y-m-d");

    $added_items = [];

    foreach ($ingredient_ids as $ingredient_id) {
        // Prevent duplicate entries for the same day
        $check = $conn->query("SELECT * FROM daily_checklist WHERE IngredientID='$ingredient_id' AND Date='$date'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO daily_checklist (IngredientID, Date) VALUES ('$ingredient_id', '$date')");
            $added_items[] = $ingredient_id;
        }
    }

    // Create a JavaScript alert to show the added items
    $message = "Items have been added to the checklist!";

    echo "<script>
        alert('$message');
        window.location.href='checklist.php';
    </script>";

    exit();
} else {
    header("Location: inventory_management.php");
    exit();
}
?>
