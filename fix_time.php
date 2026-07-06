<?php
require 'db_connect.php';
echo "Upgrading database to support times...\n";

// Change the column from DATE to DATETIME
$sql = "ALTER TABLE events MODIFY event_date DATETIME";

if ($conn->query($sql) === TRUE) {
    echo "✅ Success! Column updated.\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}
?>