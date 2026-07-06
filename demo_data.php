<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

$demo_user_id = 5;

echo "=== FanFest Demo Data Setup ===\n\n";

$fake_users = [
    ['Emma',    'Johnson',  'emmaj',      'emma@demo.com',    'blue'],
    ['Liam',    'Williams', 'liamw',      'liam@demo.com',    'red'],
    ['Olivia',  'Brown',    'oliviab',    'olivia@demo.com',  'gold'],
    ['Noah',    'Jones',    'noahj',      'noah@demo.com',    'black'],
    ['Ava',     'Garcia',   'avag',       'ava@demo.com',     'blue'],
    ['Ethan',   'Martinez', 'ethanm',     'ethan@demo.com',   'red'],
    ['Sophia',  'Davis',    'sophiad',    'sophia@demo.com',  'gold'],
    ['Mason',   'Lopez',    'masonl',     'mason@demo.com',   'blue'],
    ['Isabella','Wilson',   'isabellw',   'isabella@demo.com','black'],
    ['Logan',   'Anderson', 'logana',     'logan@demo.com',   'red'],
    ['Mia',     'Thomas',   'miat',       'mia@demo.com',     'blue'],
    ['Lucas',   'Taylor',   'lucast',     'lucas@demo.com',   'gold'],
    ['Charlotte','Moore',   'charlottem', 'charlotte@demo.com','red'],
    ['James',   'Jackson',  'jamesj',     'james@demo.com',   'black'],
    ['Amelia',  'White',    'ameliaw',    'amelia@demo.com',  'blue'],
    ['Aiden',   'Harris',   'aidenh',     'aiden@demo.com',   'red'],
    ['Harper',  'Clark',    'harperc',    'harper@demo.com',  'gold'],
    ['Elijah',  'Lewis',    'elijahl',    'elijah@demo.com',  'black'],
    ['Evelyn',  'Robinson', 'evelynr',    'evelyn@demo.com',  'blue'],
    ['Oliver',  'Walker',   'oliverw',    'oliver@demo.com',  'red'],
    ['Abigail', 'Hall',     'abigailh',   'abigail@demo.com', 'gold'],
    ['Jacob',   'Allen',    'jacoba',     'jacob@demo.com',   'black'],
    ['Emily',   'Young',    'emilyy',     'emily@demo.com',   'blue'],
    ['Carter',  'King',     'carterk',    'carter@demo.com',  'red'],
    ['Scarlett','Wright',   'scarlettw',  'scarlett@demo.com','gold'],
];

$sections = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
$password_hash = password_hash('password123', PASSWORD_DEFAULT);
$inserted_user_ids = [];

echo "Creating fake users...\n";
foreach ($fake_users as $u) {
    $stmt = $conn->prepare("INSERT IGNORE INTO users (first_name, last_name, username, email, password, theme_color) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $u[0], $u[1], $u[2], $u[3], $password_hash, $u[4]);
    $stmt->execute();

    $sel = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $sel->bind_param("s", $u[2]);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if ($row) {
        $inserted_user_ids[] = $row['id'];
        echo "  + {$u[0]} {$u[1]} (ID: {$row['id']})\n";
    }
}
echo "Done. " . count($inserted_user_ids) . " users ready.\n\n";

echo "Creating friend connections...\n";
foreach ($inserted_user_ids as $uid) {
    $low  = min($demo_user_id, $uid);
    $high = max($demo_user_id, $uid);
    $stmt = $conn->prepare("INSERT IGNORE INTO friends (user_id_1, user_id_2, status) VALUES (?, ?, 'accepted')");
    $stmt->bind_param("ii", $low, $high);
    $stmt->execute();
}
$count = count($inserted_user_ids);
for ($i = 0; $i < $count; $i++) {
    for ($j = $i + 1; $j < $count; $j++) {
        if (rand(0, 1) === 1) {
            $low  = min($inserted_user_ids[$i], $inserted_user_ids[$j]);
            $high = max($inserted_user_ids[$i], $inserted_user_ids[$j]);
            $stmt = $conn->prepare("INSERT IGNORE INTO friends (user_id_1, user_id_2, status) VALUES (?, ?, 'accepted')");
            $stmt->bind_param("ii", $low, $high);
            $stmt->execute();
        }
    }
}
echo "Done.\n\n";

echo "Fetching upcoming events...\n";
$events_result = $conn->query("
    SELECT event_id, event_name FROM events
    WHERE event_date >= CURDATE()
      AND event_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
      AND event_name NOT LIKE '%TBD%'
      AND event_name NOT LIKE '%/%'
    ORDER BY event_date ASC
    LIMIT 15
");

$event_ids = [];
while ($row = $events_result->fetch_assoc()) {
    $event_ids[] = $row['event_id'];
    echo "  [{$row['event_id']}] {$row['event_name']}\n";
}

if (empty($event_ids)) {
    echo "WARNING: No upcoming events found. Run import_games.php first.\n\n";
} else {
    echo "\nAdding RSVPs with seats to upcoming events...\n";
    $rsvp_count = 0;
    foreach ($event_ids as $eid) {
        $shuffled = $inserted_user_ids;
        shuffle($shuffled);
        $num_rsvps = rand(8, 15);
        $attendees = array_slice($shuffled, 0, $num_rsvps);
        foreach ($attendees as $uid) {
            $section    = $sections[array_rand($sections)];
            $row_num    = rand(1, 20);
            $seat_num   = rand(1, 30);
            $seat       = "Section $section, Row $row_num, Seat $seat_num";
            $share_seat = rand(0, 3) > 0 ? 1 : 0; // 75% share, 25% private

            $stmt = $conn->prepare("INSERT INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE seat_number = VALUES(seat_number), share_seat = VALUES(share_seat)");
            $stmt->bind_param("iisi", $uid, $eid, $seat, $share_seat);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $rsvp_count++;
        }
        echo "  + Event $eid: {$num_rsvps} RSVPs with seats\n";
    }
    echo "Done. $rsvp_count total RSVPs added.\n\n";

    echo "RSVPing Dan to upcoming games with seat...\n";
    $dan_events = array_slice($event_ids, 0, min(6, count($event_ids)));
    foreach ($dan_events as $eid) {
        $section  = $sections[array_rand($sections)];
        $row_num  = rand(1, 20);
        $seat_num = rand(1, 30);
        $seat     = "Section $section, Row $row_num, Seat $seat_num";
        $share    = 1;
        $stmt = $conn->prepare("INSERT INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE seat_number = VALUES(seat_number), share_seat = VALUES(share_seat)");
        $stmt->bind_param("iisi", $demo_user_id, $eid, $seat, $share);
        $stmt->execute();
        echo "  + Event $eid — $seat\n";
    }
    echo "Done.\n\n";
}

echo "Fetching past events...\n";
$past_events = $conn->query("
    SELECT event_id, event_name FROM events
    WHERE event_date < CURDATE()
      AND event_name NOT LIKE '%TBD%'
      AND event_name NOT LIKE '%/%'
    ORDER BY event_date DESC
    LIMIT 20
");

$past_ids = [];
while ($row = $past_events->fetch_assoc()) {
    $past_ids[] = $row['event_id'];
    echo "  [{$row['event_id']}] {$row['event_name']}\n";
}

if (empty($past_ids)) {
    echo "No past events found — skipping check-in history.\n\n";
} else {
    echo "\nAdding Dan check-ins to past events...\n";
    $dan_checkins = array_slice($past_ids, 0, min(10, count($past_ids)));
    foreach ($dan_checkins as $eid) {
        $stmt = $conn->prepare("INSERT IGNORE INTO checkins (user_id, event_id, latitude, longitude) VALUES (?, ?, 0, 0)");
        $stmt->bind_param("ii", $demo_user_id, $eid);
        $stmt->execute();
        if ($stmt->affected_rows > 0) echo "  + Event $eid\n";
    }
    echo "Done.\n\n";

    echo "Adding fake users to same events Dan checked into...\n";
    $fake_ci_count = 0;
    foreach ($dan_checkins as $eid) {
        $shuffled = $inserted_user_ids;
        shuffle($shuffled);
        $num      = rand(5, 15);
        $attendees = array_slice($shuffled, 0, $num);
        foreach ($attendees as $uid) {
            $stmt = $conn->prepare("INSERT IGNORE INTO checkins (user_id, event_id, latitude, longitude) VALUES (?, ?, 0, 0)");
            $stmt->bind_param("ii", $uid, $eid);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $fake_ci_count++;
        }
        echo "  + Event $eid: {$num} fake users checked in\n";
    }
    echo "Done. $fake_ci_count fake check-ins added.\n\n";
}

echo "=== ALL DONE ===\n";
echo "  " . count($inserted_user_ids) . " fake users created\n";
echo "  All friended with Dan (ID: $demo_user_id)\n";
echo "  8-15 friends RSVPed per upcoming game with seats (75% sharing, 25% private)\n";
echo "  Dan RSVPed to upcoming games with seats\n";
echo "  Dan has 10 past check-ins\n";
echo "  5-15 fake users checked into each of Dan's past events\n";