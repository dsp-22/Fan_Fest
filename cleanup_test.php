<?php
require 'db_connect.php';

echo "<h1>Targeted Cleanup by Location...</h1>";

// 1. Delete check-ins for any event at Assembly Hall that matches our test criteria
$sql_checkins = "DELETE FROM checkins WHERE event_id IN (
                    SELECT event_id FROM events 
                    WHERE location_name = 'Simon Skjodt Assembly Hall' 
                    AND (event_name LIKE '%Dev%' OR event_name LIKE '%Hoosiers%')
                 )";
$conn->query($sql_checkins);
echo "<p>✅ Cleaned check-ins at Assembly Hall.</p>";

// 2. Delete the events themselves
$sql_events = "DELETE FROM events 
               WHERE location_name = 'Simon Skjodt Assembly Hall' 
               AND (event_name LIKE '%Dev%' OR event_name LIKE '%Hoosiers%')";

if ($conn->query($sql_events)) {
    $deleted_count = $conn->affected_rows;
    echo "<p>✅ Success! Removed $deleted_count event(s) from Assembly Hall.</p>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}

echo "<p><a href='checkin.php'>Verify on Check-in Page</a></p>";
?>