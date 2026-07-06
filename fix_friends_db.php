<?php
require 'db_connect.php';

// 1. Delete the old broken table
$sql_drop = "DROP TABLE IF EXISTS friends";
if ($conn->query($sql_drop) === TRUE) {
    echo "Old table deleted.<br>";
}

// 2. Create the new correct table
$sql_create = "CREATE TABLE friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_create) === TRUE) {
    echo "Success: 'friends' table created correctly.";
} else {
    echo "Error: " . $conn->error;
}
?>