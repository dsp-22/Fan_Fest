<?php
set_time_limit(0);
require 'db_connect.php';

$events = $conn->query("SELECT event_id, event_date, latitude, longitude FROM events");
if (!$events) { die("No events found."); }

$batch_size = 500; // Insert 500 fans at a time
$total_inserted = 0;

$conn->query("START TRANSACTION");

while ($row = $events->fetch_assoc()) {
    $e_id = $row['event_id'];
    $is_past = (strtotime($row['event_date']) < time());
    $num_fans = rand(150, 450);
    
    $values = [];
    $query_parts = [];

    for ($i = 0; $i < $num_fans; $i++) {
        $fake_user_id = rand(100, 9999);
        
        if ($is_past) {
            $lat = $row['latitude'] ?? 39.1673;
            $lon = $row['longitude'] ?? -86.5233;
            // Build the values string for one row
            $query_parts[] = "($fake_user_id, $e_id, $lat, $lon)";
        } else {
            $seat = "Sec " . rand(100, 500);
            $share = rand(0, 1);
            $query_parts[] = "($fake_user_id, $e_id, '$seat', $share)";
        }

        // When we reach batch size, execute and reset
        if (count($query_parts) >= $batch_size) {
            $table = $is_past ? "checkins" : "rsvps";
            $cols = $is_past ? "(user_id, event_id, latitude, longitude)" : "(user_id, event_id, seat_number, share_seat)";
            $sql = "INSERT IGNORE INTO $table $cols VALUES " . implode(',', $query_parts);
            $conn->query($sql);
            $total_inserted += count($query_parts);
            $query_parts = [];
        }
    }

    // Final insert for any remaining fans
    if (!empty($query_parts)) {
        $table = $is_past ? "checkins" : "rsvps";
        $cols = $is_past ? "(user_id, event_id, latitude, longitude)" : "(user_id, event_id, seat_number, share_seat)";
        $sql = "INSERT IGNORE INTO $table $cols VALUES " . implode(',', $query_parts);
        $conn->query($sql);
        $total_inserted += count($query_parts);
    }
}

$conn->query("COMMIT");

echo "<h1>Population Complete!</h1>";
echo "<p>Successfully created $total_inserted fan records in record time.</p>";
echo "<a href='checkin.php'>Return to Dashboard</a>";
?>
