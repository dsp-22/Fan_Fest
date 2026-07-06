<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Setting up Seat Architecture...</h1>";

// Add seat columns to RSVPs
$conn->query("ALTER TABLE rsvps ADD COLUMN IF NOT EXISTS seat_number VARCHAR(50) DEFAULT NULL");
$conn->query("ALTER TABLE rsvps ADD COLUMN IF NOT EXISTS share_seat TINYINT(1) DEFAULT 1");

// Add global privacy setting to Users
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS default_seat_privacy TINYINT(1) DEFAULT 1");

echo "<p>✅ Seat columns added to rsvps table!</p>";
echo "<p>✅ Global privacy setting added to users table!</p>";
echo "<p><strong>Next Step:</strong> You can safely load your event pages now.</p>";
?>