<?php
set_time_limit(0); // Allow this to run for a long time
require 'db_connect.php';

// 1. Fetch all events
$events = $conn->query("SELECT event_id, event_date, latitude, longitude FROM events");

if (!$events) { die("No events found."); }

$total_inserted = 0;

while ($row = $events->fetch_assoc()) {
    $e_id = $row['event_id'];
    $date = new DateTime($row['event_date']);
    $now  = new DateTime();
    
    // Determine if event is past or future
    $is_past = ($date < $now);
    
    // Randomized crowd size: between 150 and 450 fans per game
    $num_fans = rand(150, 450);
    
    //use a large range of fake User IDs to avoid collisions
    //assume your real user is ID 1.
    $start_user_id = 100;
    $end_user_id   = 5000;
    
    for ($i = 0; $i < $num_fans; $i++) {
        $fake_user_id = rand($start_user_id, $end_user_id);
        
        if ($is_past) {
            $lat = $row['latitude'] ?? 39.1673;
            $lon = $row['longitude'] ?? -86.5233;
            // Use INSERT IGNORE so we don't break if we run this twice
            $conn->query("INSERT IGNORE INTO checkins (user_id, event_id, latitude, longitude) VALUES ($fake_user_id, $e_id, $lat, $lon)");
        } else {
            $seat = "Sec " . rand(100, 500) . ", Row " . chr(rand(65, 75));
            $share = rand(0, 1);
            $conn->query("INSERT IGNORE INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES ($fake_user_id, $e_id, '$seat', $share)");
        }
        $total_inserted++;
    }
}

echo "<h1>Population Complete!</h1>";
echo "<p>Processed " . $total_inserted . " simulated fan interactions across all events.</p>";
echo "<a href='checkin.php'>Return to Dashboard</a>";
?>
