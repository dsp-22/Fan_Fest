<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Updating Capstone Event Time...</h1>";


$target_time = date('Y-m-d') . ' 16:00:00';

$sql = "UPDATE events 
        SET event_date = '$target_time' 
        WHERE event_name = 'Test Check In: Luddy Capstone Fair'";

if ($conn->query($sql)) {
    echo "<p>Capstone Fair event successfully updated to 4:00 PM ($target_time)!</p>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}
?>