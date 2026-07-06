<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Adding Dan Sproat to the Capstone Roster...</h1>";


$password = password_hash('password123', PASSWORD_DEFAULT);
$conn->query("INSERT IGNORE INTO users (first_name, last_name, username, email, password, theme_color) VALUES ('Dan', 'Sproat', 'dsproat', 'dan@demo.com', '$password', 'blue')");

$dan_res = $conn->query("SELECT id FROM users WHERE username = 'dsproat'");
if ($dan_res && $dan_res->num_rows > 0) {
    $dan_id = $dan_res->fetch_assoc()['id'];


    $team_ids = [1, 5, 6, 7]; 
    foreach ($team_ids as $f_id) {
        if ($dan_id == $f_id) continue;
        $low = min($dan_id, $f_id);
        $high = max($dan_id, $f_id);
        $conn->query("INSERT IGNORE INTO friends (user_id_1, user_id_2, status) VALUES ($low, $high, 'accepted')");
    }
    echo "<p>✅ Linked Dan Sproat as friends with User Test, Cal, Dylan, and Nathan.</p>";

    
    $ev_res = $conn->query("SELECT event_id FROM events WHERE event_name = 'Test Check In: Luddy Capstone Fair' LIMIT 1");
    if ($ev_res && $ev_res->num_rows > 0) {
        $event_id = $ev_res->fetch_assoc()['event_id'];
        $conn->query("INSERT INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES ($dan_id, $event_id, 'Capstone Booth', 1) ON DUPLICATE KEY UPDATE seat_number = 'Capstone Booth', share_seat = 1");
        echo "<p>✅ RSVP'd Dan Sproat to the Capstone Fair at the Capstone Booth.</p>";
    }
} else {
    echo "<p>Error: Could not set up Dan's account.</p>";
}

echo "<h3>All set!</h3>";
?>