<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Adding Dev Test Event...</h1>";

// We set the date to today so it shows up in your "Today & Tomorrow" feed
$today = date('Y-m-d H:i:s');

$sql = "INSERT INTO events (event_name, location_name, event_date, league) 
        VALUES ('Mock Testing: Hoosiers Home vs Away', 'Simon Skjodt Assembly Hall', '$today', 'NCAAB')";

if ($conn->query($sql)) {
    echo "<p>✅ Test Event added successfully!</p>";
    echo "<p>You should now see this at the top of your Check-In page and be able to check in from Bloomington.</p>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}
?>