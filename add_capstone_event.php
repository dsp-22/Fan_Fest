<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Adding Capstone Fair Event...</h1>";

// Sets the event to today so it stays at the top of your feed
$today = date('Y-m-d H:i:s'); 

$sql = "INSERT INTO events (event_name, location_name, event_date, league) 
        VALUES ('Test Check In: Luddy Capstone Fair', 'Franklin Hall', '$today', 'All')";

if ($conn->query($sql)) {
    echo "<p>✅ Capstone Fair Test Event added successfully!</p>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}
?>