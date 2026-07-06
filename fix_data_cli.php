<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';


$my_id = 5;

echo "🛠️ Fixing Dummy Data for User ID: $my_id...\n\n";


$demo_users = $conn->query("SELECT id, first_name FROM users WHERE email LIKE '%@demo.com%'");
$friend_count = 0;

if ($demo_users->num_rows > 0) {
    while ($du = $demo_users->fetch_assoc()) {
        $f_id = $du['id'];
        if ($f_id == $my_id) continue;
        
        $low = min($my_id, $f_id);
        $high = max($my_id, $f_id);
        
        $conn->query("INSERT IGNORE INTO friends (user_id_1, user_id_2, status) VALUES ($low, $high, 'accepted')");
        if ($conn->affected_rows > 0) {
            $friend_count++;
        }
    }
    echo "✅ Linked $friend_count demo users to your friends list.\n";
} else {
    echo "⚠️ No demo users found.\n";
}

$sections = ['101', '102', '115', '220', 'VIP Box'];
$rsvps = $conn->query("SELECT rsvp_id FROM rsvps WHERE seat_number IS NULL OR seat_number = ''");
$seat_count = 0;

while ($row = $rsvps->fetch_assoc()) {
    $sec = $sections[array_rand($sections)];
    $row_num = rand(1, 20);
    $seat_num = rand(1, 15);
    $fake_seat = "Sec $sec, Row $row_num, Seat $seat_num";
    
    // 75% chance to share the seat, 25% chance to keep it private
    $share = (rand(1, 4) > 1) ? 1 : 0; 

    $conn->query("UPDATE rsvps SET seat_number = '$fake_seat', share_seat = $share WHERE rsvp_id = " . $row['rsvp_id']);
    $seat_count++;
}
echo "✅ Assigned new random seats to $seat_count blank RSVPs.\n\n";

echo "All done! 🎉 Go refresh your check-in page in the browser!\n";
?>