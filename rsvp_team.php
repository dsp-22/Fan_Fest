<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>RSVPing Team to Capstone Fair...</h1>";


$ev_res = $conn->query("SELECT event_id FROM events WHERE event_name = 'Test Check In: Luddy Capstone Fair' LIMIT 1");
if ($ev_res->num_rows === 0) {
    die("<p>Error: Could not find the Capstone Fair event. Are you sure you added it?</p>");
}
$event_id = $ev_res->fetch_assoc()['event_id'];


$team_ids = [1, 5, 6, 7];

$success_count = 0;
foreach ($team_ids as $uid) {

    $seat = "Capstone Booth";
    $share = 1; // 

    $stmt = $conn->prepare("INSERT INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE seat_number = VALUES(seat_number), share_seat = VALUES(share_seat)");
    $stmt->bind_param("iisi", $uid, $event_id, $seat, $share);
    
    if ($stmt->execute()) {
        $success_count++;
    }
}

echo "<p>✅ Successfully RSVP'd $success_count team members to the Capstone Fair event!</p>";
?>