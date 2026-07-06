<?php
// TURN ON ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

// 1. FIRST: Delete the Check-ins (Child rows)
// We must do this first, otherwise the database blocks us.
$sql_clear_checkins = "DELETE FROM checkins";

if ($conn->query($sql_clear_checkins) === TRUE) {
    echo "<p>✅ Cleared user check-in history.</p>";
} else {
    die("Error clearing checkins: " . $conn->error);
}

// 2. SECOND: Delete the Events (Parent rows)
$sql_delete_events = "DELETE FROM events";

if ($conn->query($sql_delete_events) === TRUE) {
    echo "<h1>🗑️ Events Cleared!</h1>";
    echo "<p>All old event data has been deleted.</p>";
    
    // 3. Reset the ID counters (Optional, keeps IDs starting at 1)
    $conn->query("ALTER TABLE events AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE checkins AUTO_INCREMENT = 1");
    
} else {
    echo "Error deleting events: " . $conn->error;
}

// 4. Link to the Import Script
echo "<br><hr>";
echo "<a href='import_games.php' style='font-size: 1.2em; font-weight: bold;'>➡️ Now Click Here to Import Real Games</a>";
?>