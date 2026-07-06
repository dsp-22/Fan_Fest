<?php
require 'db_connect.php';

echo "<h1>🛠️ Fixing ONLY the Check-Ins Table</h1>";

// 1. Drop only the checkins table (leaves Menu/Events/Friends alone)
$sql_drop = "DROP TABLE IF EXISTS checkins";
if ($conn->query($sql_drop)) {
    echo "<p>🗑️ Removed old/broken checkins table.</p>";
}

// 2. Create the new table with GPS columns (latitude/longitude)
$sql_create = "CREATE TABLE checkins (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    latitude DECIMAL(10, 8),   -- Needed for the check-in to work
    longitude DECIMAL(11, 8),  -- Needed for the check-in to work
    checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (event_id) REFERENCES events(event_id)
)";

if ($conn->query($sql_create)) {
    echo "<p style='color:green; font-weight:bold;'>✅ Check-ins table updated successfully!</p>";
    echo "<p>Your Menu and Event data was not touched.</p>";
    echo "<a href='checkin.php'>&larr; Return to Check In</a>";
} else {
    echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
}
?>