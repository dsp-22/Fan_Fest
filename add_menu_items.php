<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Expanding the Menu...</h1>";

// Insert new menu items 
$sql_items = "INSERT IGNORE INTO menu_items (name, description, price) VALUES 
    ('Large Order of Fries', 'Crispy, golden, and lightly salted. A massive portion.', 6.50),
    ('Plain Crispy Chicken Sandwich', 'Juicy fried chicken breast on a toasted bun. No mayo, no slaw, no pickles.', 10.00),
    ('Brownie Batter Donuts', 'Warm, fresh donuts filled with rich, gooey brownie batter.', 8.00),
    ('Classic Cheese Pizza', 'A simple, classic, and delicious oversized slice of cheese pizza.', 7.50)";

if ($conn->query($sql_items)) {
    echo "<p>✅ New food items added to the database!</p>";
    
    // Assign them to ALL existing concession stands so they populate your UI immediately
    $conn->query("INSERT IGNORE INTO stand_inventory (stand_id, item_id) 
                  SELECT s.stand_id, m.item_id 
                  FROM concession_stands s 
                  CROSS JOIN menu_items m 
                  WHERE m.name IN ('Large Order of Fries', 'Plain Crispy Chicken Sandwich', 'Brownie Batter Donuts', 'Classic Cheese Pizza')");
                  
    echo "<p>✅ Items successfully linked to your concourses!</p>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}
?>