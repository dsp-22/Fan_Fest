<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connect.php';

echo "Seeding bulk capstone dummy data...\n";

// Fetch up to 20 upcoming event IDs
$events_res = $conn->query("SELECT event_id FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20");
$event_ids = [];
while ($row = $events_res->fetch_assoc()) {
    $event_ids[] = $row['event_id'];
}

if (empty($event_ids)) {
    echo "No upcoming events found! Please run your game import first.\n";
    exit();
}

// Mock user IDs (make sure these IDs like 2, 3, 4 exist in your users table)
$dummy_users = [2, 3, 4, 5]; 
$sections = ['Sec 12', 'Sec 33', 'Loge 4', 'Balcony', 'Floor'];

$rsvp_stmt = $conn->prepare("INSERT IGNORE INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES (?, ?, ?, ?)");
$checkin_stmt = $conn->prepare("INSERT IGNORE INTO checkins (user_id, event_id, latitude, longitude) VALUES (?, ?, 39.1809, -86.5222)");

$rsvp_seeded = 0;
$checkin_seeded = 0;

foreach ($event_ids as $ev_id) {
    // Assign 2 to 4 random mock users to this event
    $goers = rand(2, 4);
    shuffle($dummy_users);
    
    for ($i = 0; $i < $goers; $i++) {
        $u_id = $dummy_users[$i];
        $seat = $sections[array_rand($sections)] . ' ' . rand(1, 20);
        $share = rand(0, 1);
        
        $rsvp_stmt->bind_param("iisi", $u_id, $ev_id, $seat, $share);
        $rsvp_stmt->execute();
        $rsvp_seeded++;
        
        // Randomly check them in to show attendance
        if (rand(0, 1) === 1) {
            $checkin_stmt->bind_param("ii", $u_id, $ev_id);
            $checkin_stmt->execute();
            $checkin_seeded++;
        }
    }
}

echo "Successfully seeded $rsvp_seeded RSVPs and $checkin_seeded Check-ins!\n";
?>
