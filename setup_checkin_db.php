<?php
require 'db_connect.php';

// 1. Create Events Table
$sql_events = "CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    event_date DATE NOT NULL,
    location_name VARCHAR(100) NOT NULL
)";
$conn->query($sql_events);

// 2. Create Check-ins (Achievements) Table
$sql_checkins = "CREATE TABLE IF NOT EXISTS checkins (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (event_id) REFERENCES events(event_id)
)";

if ($conn->query($sql_checkins) === TRUE) {
    echo "Tables created successfully.<br>";
} else {
    echo "Error creating tables: " . $conn->error;
}

// 3. Add Dummy Events (Only if table is empty)
$check = $conn->query("SELECT * FROM events");
if ($check->num_rows == 0) {
    $sql_insert = "INSERT INTO events (event_name, event_date, location_name) VALUES 
    ('Home Opener: IU vs Purdue', '2025-09-12', 'Memorial Stadium'),
    ('Friday Night Lights', '2025-09-19', 'Memorial Stadium'),
    ('Championship Tailgate', '2025-10-04', 'Tailgate Fields')";
    
    if ($conn->query($sql_insert)) {
        echo "Dummy events added!";
    }
} else {
    echo "Events already exist.";
}
?>