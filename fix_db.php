<?php
require 'db_connect.php';


$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$conn->query("TRUNCATE TABLE checkins"); 
if ($conn->query("TRUNCATE TABLE events") === TRUE) {
    echo "✅ Old broken events and test check-ins cleared.\n";
} else {
    echo "❌ Error clearing events: " . $conn->error . "\n";
}


$sql = "ALTER TABLE events ADD UNIQUE KEY unique_game (event_name, event_date)";
if ($conn->query($sql) === TRUE) {
    echo "✅ Unique rule added! Duplicates are now impossible.\n";
} else {
    echo "⚠️ Rule might already exist or error: " . $conn->error . "\n";
}


$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\nDone! Now run: php import_games.php\n";
?>