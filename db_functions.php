<?php
// Show errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

echo "<h1>Running FanFest Database Update...</h1>";


$sql = "ALTER TABLE orders ADD COLUMN order_notes TEXT DEFAULT NULL";

if ($conn->query($sql)) { 
    echo "<p>✅ Column 'order_notes' added successfully to the orders table!</p>"; 
} else { 
   
    echo "<p>ℹ️ Message: " . $conn->error . "</p>"; 
}
?>