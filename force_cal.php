<?php
require 'session_check.php';
require 'db_connect.php';

$my_id = $_SESSION['user_id'];
$friend_name = "Cal"; // We will search for 'Cal'

echo "<h1>🛠️ Force Friendship Script</h1>";

// 1. Create the Friends Table (if it doesn't exist yet)
$sql_table = "CREATE TABLE IF NOT EXISTS friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    status VARCHAR(20) DEFAULT 'accepted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_table)) {
    echo "<p>✅ Friends database table is ready.</p>";
}

// 2. Find Cal's ID
$sql_find = "SELECT id, first_name, last_name FROM users WHERE first_name LIKE '%$friend_name%' LIMIT 1";
$result = $conn->query($sql_find);

if ($result->num_rows > 0) {
    $cal = $result->fetch_assoc();
    $cal_id = $cal['id'];
    echo "<p>Found user: <strong>" . $cal['first_name'] . " " . $cal['last_name'] . "</strong> (ID: $cal_id)</p>";

    // 3. Force the connection
    // We insert TWO rows so it works both ways (Dan->Cal and Cal->Dan) just to be safe for the demo
    $sql_insert = "INSERT IGNORE INTO friends (user_id_1, user_id_2, status) VALUES 
                   ($my_id, $cal_id, 'accepted'),
                   ($cal_id, $my_id, 'accepted')";
    
    if ($conn->query($sql_insert)) {
        echo "<h1>🎉 Success!</h1>";
        echo "<p>You strictly forced a friendship with Cal Willits.</p>";
        echo "<a href='friends.php'>➡️ Go see it in your Friends List</a>";
    } else {
        echo "Error inserting friend: " . $conn->error;
    }

} else {
    echo "<h2 style='color:red'>Could not find Cal!</h2>";
    echo "<p>Are you sure his First Name is 'Cal' in the users list?</p>";
}
?>