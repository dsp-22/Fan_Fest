<?php
require 'db_connect.php';

echo "--- USER LIST ---\n";
$result = $conn->query("SELECT id, first_name, last_name, username FROM users");

while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['first_name'] . " " . $row['last_name'] . " | Username: " . $row['username'] . "\n";
}
echo "-----------------\n";
?>