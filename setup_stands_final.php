<?php
// 1. Turn on Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

echo "<h3>Starting Database Setup...</h3>";

// 2. Clean up old tables
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("DROP TABLE IF EXISTS concession_stands");
$conn->query("DROP TABLE IF EXISTS stand_inventory");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
echo "Old tables cleaned up.<br>";

// 3. Create 'concession_stands'
$sql_stands = "CREATE TABLE concession_stands (
    stand_id INT PRIMARY KEY,
    stand_name VARCHAR(100),
    location VARCHAR(100),
    image_url VARCHAR(255) DEFAULT 'images/default_stand.jpg'
)";
if (!$conn->query($sql_stands)) { die("Error creating concession_stands: " . $conn->error); }
echo "Table 'concession_stands' created.<br>";

// 4. Create 'stand_inventory'
$sql_inv = "CREATE TABLE stand_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stand_id INT,
    item_id INT
)";
if (!$conn->query($sql_inv)) { die("Error creating stand_inventory: " . $conn->error); }
echo "Table 'stand_inventory' created.<br>";

// 5. Create the 4 Concourses
$stands = [
    [1, "North Concourse", "Section 101"], 
    [2, "South Concourse", "Section 115"], 
    [3, "East Concourse",  "Section 205"], 
    [4, "West Concourse",  "Section 220"] 
];

foreach ($stands as $s) {
    $stmt = $conn->prepare("INSERT INTO concession_stands (stand_id, stand_name, location) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $s[0], $s[1], $s[2]);
    if (!$stmt->execute()) { echo "Failed to add stand: " . $stmt->error . "<br>"; }
}
echo "4 Stands Inserted.<br>";

// 6. Assign Food (FIXED: Using 'item_id')
// We changed 'id' to 'item_id' in this line:
$result = $conn->query("SELECT item_id, name, category FROM menu_items");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $iid = $row['item_id']; // This is the fix
        $name = $row['name'];
        $cat = $row['category'];
        
        // Distribution Logic
        if (stripos($cat, 'Drink') !== false || stripos($name, 'Water') !== false) {
            linkItem($conn, 1, $iid); linkItem($conn, 2, $iid); linkItem($conn, 3, $iid); linkItem($conn, 4, $iid);
        } elseif (stripos($name, 'Burger') !== false || stripos($name, 'Dog') !== false) {
            linkItem($conn, 1, $iid);
        } elseif (stripos($name, 'Pizza') !== false || stripos($name, 'Slice') !== false) {
            linkItem($conn, 2, $iid);
        } elseif (stripos($name, 'Candy') !== false || stripos($name, 'Popcorn') !== false) {
            linkItem($conn, 3, $iid);
        } elseif (stripos($name, 'Nacho') !== false) {
            linkItem($conn, 4, $iid); linkItem($conn, 1, $iid);
        } else {
            linkItem($conn, 4, $iid);
        }
    }
    echo "Menu distributed.<br>";
} else {
    echo "No menu items found in database!<br>";
}

function linkItem($conn, $stand_id, $item_id) {
    $conn->query("INSERT INTO stand_inventory (stand_id, item_id) VALUES ($stand_id, $item_id)");
}

echo "<h3>Setup Complete!</h3>";
echo "<a href='concessions.php'>Go to Mobile Order Page</a>";
?>