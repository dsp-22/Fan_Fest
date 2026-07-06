<?php
require 'db_connect.php';

// Clear existing data (optional - comment out if you want to keep it)
// $conn->query("DELETE FROM events");
// $conn->query("DELETE FROM users");
// $conn->query("DELETE FROM checkins");

// Add dummy users
$users = [
    ['Cal', 'Wilson', 'willitsc@iu.edu', 'blue'],
    ['Dan', 'Sproat', 'dsproat@iu.edu', 'red'],
    ['Alex', 'Chen', 'alexchen@iu.edu', 'gold'],
    ['Jordan', 'Smith', 'jsmith@iu.edu', 'black'],
    ['Taylor', 'Johnson', 'tjohnson@iu.edu', 'blue'],
];

foreach ($users as $u) {
    $stmt = $conn->prepare("INSERT IGNORE INTO users (first_name, last_name, email, theme_color) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $u[0], $u[1], $u[2], $u[3]);
    $stmt->execute();
    echo "Added user: {$u[0]} {$u[1]}\n";
}

// Add dummy events for next 10 days
$events = [
    ['Indiana Hoosiers vs Michigan State', 'basketball', '2026-06-24 19:00:00', 'Simon Skjodt Assembly Hall', 'NCAAB'],
    ['Chicago Bulls vs Miami Heat', 'basketball', '2026-06-24 20:00:00', 'United Center', 'NBA'],
    ['New York Yankees vs Boston Red Sox', 'baseball', '2026-06-24 19:30:00', 'Yankee Stadium', 'MLB'],
    ['Indianapolis Colts vs Tennessee Titans', 'football', '2026-06-25 13:00:00', 'Lucas Oil Stadium', 'NFL'],
    ['Minnesota Timberwolves vs Denver Nuggets', 'basketball', '2026-06-25 20:30:00', 'Target Center', 'NBA'],
    ['Chicago Cubs vs St. Louis Cardinals', 'baseball', '2026-06-25 19:00:00', 'Wrigley Field', 'MLB'],
    ['Indiana Hoosiers vs Ohio State', 'football', '2026-06-26 15:30:00', 'Memorial Stadium', 'NCAAF'],
    ['Los Angeles Lakers vs Golden State Warriors', 'basketball', '2026-06-26 22:00:00', 'Crypto.com Arena', 'NBA'],
    ['New York Mets vs Philadelphia Phillies', 'baseball', '2026-06-27 19:00:00', 'Citi Field', 'MLB'],
];

foreach ($events as $e) {
    $stmt = $conn->prepare("INSERT IGNORE INTO events (event_name, event_date, location_name, league) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $e[0], $e[2], $e[3], $e[4]);
    $stmt->execute();
    echo "Added event: {$e[0]}\n";
}

// Add dummy check-ins
$checkin_data = [
    [1, 1, '2026-06-24 19:15:00', 39.1809, -86.5222],
    [1, 2, '2026-06-24 20:10:00', 41.8806, -87.6742],
    [2, 3, '2026-06-24 19:45:00', 40.7282, -73.7949],
    [3, 4, '2026-06-25 13:15:00', 39.7601, -86.1639],
    [4, 5, '2026-06-25 20:45:00', 44.9795, -93.2762],
];

foreach ($checkin_data as $c) {
    $stmt = $conn->prepare("INSERT IGNORE INTO checkins (user_id, event_id, checkin_time, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisdd", $c[0], $c[1], $c[2], $c[3], $c[4]);
    $stmt->execute();
    echo "Added check-in for user {$c[0]} to event {$c[1]}\n";
}

// Add dummy RSVPs
$rsvp_data = [
    [1, 5, 'Section 12, Row C, Seat 4', 1],
    [2, 6, 'Section 5, Row A, Seat 1', 1],
    [3, 7, 'Upper Level, Row 20', 0],
    [4, 8, 'Lower Bowl, Baseline', 1],
];

foreach ($rsvp_data as $r) {
    $stmt = $conn->prepare("INSERT IGNORE INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $r[0], $r[1], $r[2], $r[3]);
    $stmt->execute();
    echo "Added RSVP for user {$r[0]} to event {$r[1]}\n";
}

echo "\n✅ Dummy data populated successfully!\n";
?>
