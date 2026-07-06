<?php
require 'db_connect.php';

echo "Cleaning up duplicate events...\n";

// 1. Delete Check-ins first 
// We only delete check-ins for games happening Today or in the Future
$sql_checkins = "DELETE FROM checkins WHERE event_id IN (SELECT event_id FROM events WHERE event_date >= CURDATE())";

if ($conn->query($sql_checkins) === TRUE) {
    echo "✅ Cleared test check-ins for upcoming games.\n";
} else {
    echo "❌ Error clearing check-ins: " . $conn->error . "\n";
    exit(); // Stop if this fails
}

// 2. Now we can safely delete the Events
$sql_events = "DELETE FROM events WHERE event_date >= CURDATE()";

if ($conn->query($sql_events) === TRUE) {
    echo "✅ Future schedule cleared. Ready to re-import.\n";
} else {
    echo "❌ Error clearing events: " . $conn->error . "\n";
}
?>