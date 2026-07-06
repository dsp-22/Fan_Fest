<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connect.php';

echo "Clearing old dummy RSVPs and Check-ins...\n";
// Assuming you have test users or want to use existing accounts
$conn->query("DELETE FROM rsvps WHERE user_id IN (2, 3, 4, 5, 6)");
$conn->query("DELETE FROM checkins WHERE user_id IN (2, 3, 4, 5, 6)");

echo "Fetching current upcoming events to seed with dummy data...\n";
// Grab up to 20 upcoming events
$events_res = $conn->query("SELECT event_id FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20");

$event_ids = [];
while ($row = $events_res->fetch_assoc()) {
    $event_ids[] = $row['event_id'];
}

if (empty($event_ids)) {
    echo "No events found to seed!\n";
    exit();
}

// Dummy user IDs to act as "friends" (e.g., your capstone teammates or test accounts)
$dummy_users = [2, 3, 4, 5]; 

$seat_sections = ['Sec 12, Row C', 'Sec 33, Row A', 'Loge 4, Seat 2', 'GA Lawn', 'Sec 104, Row K'];

echo "Seeding RSVPs with friends going...\n";

$rsvp_stmt = $conn->prepare("INSERT IGNORE INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES (?, ?, ?, 1)");

foreach ($event_ids as $event_id) {
    // Randomly assign between 1 and 4 "friends" to this event
    $friends_count = rand(1, 4);
    $shuffled_users = $dummy_users;
    shuffle($shuffled_users);
    
    for ($i = 0; $i < $friends_count; $i++) {
        $u_id = $shuffled_users[$i];
        $seat = $seat_sections[array_rand($seat_sections)] . ' ' . rand(1, 20);
        
        $rsvp_stmt->bind_param("iis", $u_id, $event_id, $seat);
        $rsvp_stmt->execute();
    }
    
    // Optionally seed 1 or 2 check-ins for past/immediate events to show attendance stacks
    // (We'll just insert a couple of random records for visual flair)
    if (rand(0,1) === 1) {
        $random_user = $dummy_users[array_rand($dummy_users)];
        $conn->query("INSERT IGNORE INTO checkins (user_id, event_id, latitude, longitude) VALUES ($random_user, $event_id, 39.1809, -86.5222)");
    }
}

echo "Dummy data seeding complete! Your event lists and friends stacks will now be populated.\n";
?>
