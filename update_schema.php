<?php
// Connect to database
require 'db_connect.php';

echo "Updating events table...\n";

// Add 'league' column, defaulting to 'ncaaf'
$sql = "ALTER TABLE events ADD COLUMN league VARCHAR(10) DEFAULT 'ncaaf'";

if ($conn->query($sql) === TRUE) {
    echo "SUCCESS: Added 'league' column.\n";
} else {
    // If it fails, print the error
    echo "NOTE: " . $conn->error . "\n";
}
?>