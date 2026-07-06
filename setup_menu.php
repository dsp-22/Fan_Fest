<?php
// 1. Connect to Database
require 'db_connect.php'; 

// 2. Ensure the Table Exists (Standard check)
$sql_table = "CREATE TABLE IF NOT EXISTS menu_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    price DECIMAL(4, 2) NOT NULL,
    category VARCHAR(50), 
    image_url VARCHAR(255) DEFAULT 'default_food.png'
)";

if ($conn->query($sql_table) === TRUE) {
    echo "Table check passed.<br>";
} else {
    die("Error checking table: " . $conn->error);
}

// 3. Add 'stand_name' column if it is missing
// This allows us to update the table structure without deleting it entirely
$check_col = "SHOW COLUMNS FROM menu_items LIKE 'stand_name'";
$result = $conn->query($check_col);

if ($result->num_rows == 0) {
    // The column doesn't exist, so let's add it
    $alter_sql = "ALTER TABLE menu_items ADD COLUMN stand_name VARCHAR(50) DEFAULT 'Main Stand'";
    if ($conn->query($alter_sql) === TRUE) {
        echo "Column 'stand_name' added successfully.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
}

// 4. RESET DATA
// We truncate (empty) the table so we can insert the new list fresh.
// This prevents having "Hot Dog" twice (one with a stand name, one without).
$conn->query("TRUNCATE TABLE menu_items");
echo "Old menu data cleared.<br>";

// 5. Insert New Data with Multiple Stands
$insert_sql = "INSERT INTO menu_items (name, description, price, category, stand_name) VALUES 
    -- Stand 4: The Classics
    ('Stadium Hot Dog', 'Classic beef frank on a bun', 6.50, 'Food', 'Stand 4: The Classics'),
    ('Soft Pretzel', 'Warm pretzel with cheese dip', 5.00, 'Food', 'Stand 4: The Classics'),
    ('Large Soda', '32oz fountain drink', 4.50, 'Drink', 'Stand 4: The Classics'),

    -- Stand 7: Tacos & Nachos
    ('Nachos Supreme', 'Chips, cheese, jalapeños, and beef', 9.00, 'Food', 'Stand 7: Tacos & Nachos'),
    ('Street Tacos', '3 beef tacos with cilantro lime', 8.50, 'Food', 'Stand 7: Tacos & Nachos'),
    ('Churro', 'Cinnamon sugar pastry', 4.00, 'Food', 'Stand 7: Tacos & Nachos'),

    -- Stand 10: Pizza & Brews
    ('Pepperoni Slice', 'NY Style pepperoni pizza', 5.50, 'Food', 'Stand 10: Pizza & Brews'),
    ('Cheese Slice', 'Classic cheese pizza', 5.00, 'Food', 'Stand 10: Pizza & Brews'),
    ('Craft Beer', 'Local IPA on tap', 11.00, 'Drink', 'Stand 10: Pizza & Brews'),
    ('Domestic Draft', 'Light american lager', 9.00, 'Drink', 'Stand 10: Pizza & Brews')";

if ($conn->query($insert_sql) === TRUE) {
    echo "New menu items with multiple stands added successfully!<br>";
} else {
    echo "Error adding items: " . $conn->error;
}
?>