<?php
require 'db_connect.php';

// Ask the database to describe the table structure
$result = $conn->query("DESCRIBE menu_items");

echo "<h3>Columns in 'menu_items':</h3>";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . "<br>";
}
?>