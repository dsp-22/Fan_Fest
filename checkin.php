<?php
// Force re-import for testing
if (file_exists('last_game_import.txt')) {
    unlink('last_game_import.txt');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// --- TIMEZONE FIX ---
date_default_timezone_set('America/New_York');
$today_start = date('Y-m-d 00:00:00');

require 'db_connect.php';

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 3958.8;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

$last_import_file = 'last_game_import.txt';
$should_import = false;
if (!file_exists($last_import_file)) {
    $should_import = true;
} else {
    $last_time = (int)file_get_contents($last_import_file);
    if (time() - $last_time > 86400) $should_import = true;
}
if ($should_import) {
    exec('php import_games.php > /dev/null 2>&1 &');
    file_put_contents($last_import_file, time());
}

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// --- GUEST LOGIC HOOK ---
$is_guest = ($_SESSION['user_id'] === 'guest');
$user_id = $is_guest ? 0 : $_SESSION['user_id']; 
// ------------------------

$message = "";

function getCountdown($event_date) {
    $today = new DateTime('today');
    $game  = new DateTime((new DateTime($event_date))->format('Y-m-d'));
    $diff  = (int)$today->diff($game)->days;
    if ($diff == 0) return "<span style='color:#e17055;font-weight:700;'>Today</span>";
    if ($diff == 1) return "<span style='color:#e17055;'>Tomorrow</span>";
    return "<span style='color:#888;'>in {$diff} days</span>";
}

function getVenueCoords(string $venue_name): ?array {
    $venues = [
        'franklin hall'                     => [39.1673, -86.5233],
        'simon skjodt assembly hall'        => [39.1809, -86.5222],
        'assembly hall'                     => [39.1809, -86.5222],
        'memorial stadium'                  => [39.1809, -86.5256],
        'united center'                     => [41.8806, -87.6742],
        'target center'                     => [44.9795, -93.2762],
        'soldier field'                     => [41.8623, -87.6167],
        'lucas oil stadium'                 => [39.7601, -86.1639],
    ];
    $name = strtolower(trim($venue_name));
    foreach ($venues as $key => $coords) {
        if (str_contains($name, $key) || str_contains($key, $name)) return $coords;
    }
    return null;
}

// handle RSVP (Block guests securely at the server level)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rsvp_event_id'])) {
    if ($is_guest) { header("Location: login.php"); exit(); }
    
    $rsvp_event_id = (int)$_POST['rsvp_event_id'];
    $action        = $_POST['rsvp_action'] ?? 'add';
    $seat_number   = trim($_POST['seat_number'] ?? '');
    $share_seat    = isset($_POST['share_seat']) ? 1 : 0;

    if ($action === 'remove') {
        $stmt = $conn->prepare("DELETE FROM rsvps WHERE user_id = ? AND event_id = ?");
        $stmt->bind_param("ii", $user_id, $rsvp_event_id);
        $stmt->execute();
        header("Location: checkin.php?rsvp=removed&league=" . urlencode($_POST['league'] ?? 'All') . "&search=" . urlencode($_POST['search'] ?? ''));
    } elseif ($action === 'update_seat') {
        $stmt = $conn->prepare("UPDATE rsvps SET seat_number = ?, share_seat = ? WHERE user_id = ? AND event_id = ?");
        $stmt->bind_param("siii", $seat_number, $share_seat, $user_id, $rsvp_event_id);
        $stmt->execute();
        header("Location: checkin.php?rsvp=seat_updated&league=" . urlencode($_POST['league'] ?? 'All') . "&search=" . urlencode($_POST['search'] ?? ''));
    } else {
        $stmt = $conn->prepare("INSERT INTO rsvps (user_id, event_id, seat_number, share_seat) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE seat_number = VALUES(seat_number), share_seat = VALUES(share_seat)");
        $stmt->bind_param("iisi", $user_id, $rsvp_event_id, $seat_number, $share_seat);
        $stmt->execute();
        header("Location: checkin.php?rsvp=added&league=" . urlencode($_POST['league'] ?? 'All') . "&search=" . urlencode($_POST['search'] ?? ''));
    }
    exit();
}

// handle check in (Block guests securely at the server level)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['event_id'])) {
    if ($is_guest) { header("Location: login.php"); exit(); }
    
    $event_id = $_POST['event_id'];
    $lat = floatval($_POST['lat'] ?? 0);
    $lon = floatval($_POST['lon'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM checkins WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        header("Location: checkin.php?error=duplicate"); exit();
    }

    if ($lat != 0 && $lon != 0) {
        $ev = $conn->prepare("SELECT location_name FROM events WHERE event_id = ?");
        $ev->bind_param("i", $event_id);
        $ev->execute();
        $ev_row = $ev->get_result()->fetch_assoc();
        $ev->close();
        if ($ev_row) {
            $coords = getVenueCoords($ev_row['location_name']);
            if ($coords) {
                $dLat = deg2rad($coords[0] - $lat);
                $dLon = deg2rad($coords[1] - $lon);
                $a    = sin($dLat/2)**2 + cos(deg2rad($lat)) * cos(deg2rad($coords[0])) * sin($dLon/2)**2;
                $dist = 3958.8 * 2 * asin(sqrt($a));
                if ($dist > 1.0) {
                    header("Location: checkin.php?error=toolong&dist=" . round($dist, 1)); exit();
                }
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO checkins (user_id, event_id, latitude, longitude) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iidd", $user_id, $event_id, $lat, $lon);
    if ($stmt->execute()) { header("Location: checkin.php?success=1"); exit(); }
    else { header("Location: checkin.php?error=failed"); exit(); }
}

if (isset($_GET['success']))              $message = "Check-in Successful! Achievement Unlocked.";
elseif (isset($_GET['rsvp']) && $_GET['rsvp']==='added')    $message = "RSVP confirmed! We'll see you there.";
elseif (isset($_GET['rsvp']) && $_GET['rsvp']==='removed')  $message = "RSVP removed.";
elseif (isset($_GET['rsvp']) && $_GET['rsvp']==='seat_updated') $message = "Seat updated successfully.";
elseif (isset($_GET['error']) && $_GET['error']==='toolong') {
    $dist = $_GET['dist'] ?? '?';
    $message = "You're too far from the venue ({$dist} miles away). You need to be within 1 mile to check in.";
} elseif (isset($_GET['error'])) {
    $message = ($_GET['error']=='duplicate') ? "Already checked in!" : "Error checking in.";
}

// Ensure theme doesn't break for guests
if ($is_guest) {
    $user_name = "Guest Fan";
    $theme = "blue";
} else {
    $theme_data      = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $user_id")->fetch_assoc();
    $user_name       = $theme_data['first_name'] ?? "Fan";
    $theme           = $theme_data['theme_color'] ?? 'blue';
}

$theme_map       = ['blue'=>'linear-gradient(135deg,#0984e3,#6c5ce7)','red'=>'linear-gradient(135deg,#990000,#660000)','gold'=>'linear-gradient(135deg,#ceb888,#000000)','black'=>'linear-gradient(135deg,#2d3436,#000000)'];
$active_gradient = $theme_map[$theme] ?? $theme_map['blue'];
$accent          = ['blue'=>'#0984e3','red'=>'#990000','gold'=>'#ceb888','black'=>'#2d3436'][$theme] ?? '#0984e3';

$league_filter = $_GET['league'] ?? 'All';
$search_query  = $_GET['search'] ?? '';
$valid_leagues = ['All','FIFA','MLB','NBA','NFL','NCAAF','NCAAB'];

$trimmed_search  = trim($search_query);
$ignore_words    = ['at','vs','versus','v'];
$search_words    = explode(' ', strtolower($trimmed_search));
$filtered_words  = array_filter($search_words, fn($w) => !empty(trim($w)) && !in_array(trim($w), $ignore_words));
$cleaned_search  = trim(implode(' ', $filtered_words));
$is_valid_search = !empty($cleaned_search);

if (!empty($search_query) && empty($cleaned_search)) {
    header("Location: checkin.php?league=" . urlencode($league_filter)); exit();
}

// --- NEW CALENDAR DATE LOGIC ---
// Look ahead 14 days by default, or 30 days if searching
$date_limit = $is_valid_search
    ? date('Y-m-d 23:59:59', strtotime('+30 days'))
    : date('Y-m-d 23:59:59', strtotime('+14 days'));

// --- STRICT SQL MODE COMPATIBLE QUERY ---
$sql = "SELECT e.*, c.checkin_id, r.rsvp_id,
               (SELECT COUNT(*) FROM rsvps WHERE event_id = e.event_id) AS rsvp_count
        FROM events e
        LEFT JOIN checkins c ON e.event_id = c.event_id AND c.user_id = $user_id
        LEFT JOIN rsvps r    ON e.event_id = r.event_id AND r.user_id = $user_id
        WHERE e.event_date >= '$today_start'
          AND e.event_date <= '$date_limit'
          AND e.event_name NOT LIKE '%TBD%'";
if ($league_filter != 'All') $sql .= " AND e.league = '" . $conn->real_escape_string($league_filter) . "'";
if ($is_valid_search)        $sql .= " AND e.event_name LIKE '%" . $conn->real_escape_string($cleaned_search) . "%'";
$sql .= " ORDER BY e.event_date ASC";
if (!$is_valid_search) $sql .= " LIMIT 150";

$events_result = $conn->query($sql);

// Group events by Date for the Calendar View
$calendar_days = [];
if ($events_result && $events_result->num_rows > 0) {
    while($row = $events_result->fetch_assoc()) {
        if (strpos($row['event_name'], '/') !== false) continue;
        
        $day_key = date('Y-m-d', strtotime($row['event_date']));
        if (!isset($calendar_days[$day_key])) {
            $calendar_days[$day_key] = [];
        }
        $calendar_days[$day_key][] = $row;
    }
}

$stmt = $conn->prepare("SELECT e.event_id, e.event_name, e.event_date, c.checkin_time FROM checkins c JOIN events e ON c.event_id = e.event_id WHERE c.user_id = ? ORDER BY c.checkin_time DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history_result = $stmt->get_result();
$checkin_count  = $history_result->num_rows;

$stmt = $conn->prepare("SELECT e.event_id, e.event_name, e.event_date, e.location_name, e.league, r.seat_number, r.share_seat FROM rsvps r JOIN events e ON r.event_id = e.event_id WHERE r.user_id = ? AND e.event_date >= '$today_start' ORDER BY e.event_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rsvp_result = $stmt->get_result();

$friends_going = [];
$fg = $conn->prepare("
    SELECT DISTINCT e.event_id, u.id as user_id, u.first_name, u.last_name, u.username, u.profile_pic, 
           r.seat_number, r.share_seat
    FROM rsvps r
    JOIN users u ON u.id = r.user_id
    JOIN friends f ON (
        (f.user_id_1 = ? AND f.user_id_2 = r.user_id)
        OR (f.user_id_2 = ? AND f.user_id_1 = r.user_id)
    )
    JOIN events e ON e.event_id = r.event_id
    WHERE f.status = 'accepted'
      AND e.event_date >= '$today_start'
");
$fg->bind_param("ii", $user_id, $user_id);
$fg->execute();
$fg_res = $fg->get_result();
while ($row = $fg_res->fetch_assoc()) {
    $friends_going[$row['event_id']][] = [
        'user_id'     => $row['user_id'],
        'name'        => $row['first_name'] . ' ' . $row['last_name'],
        'first'       => $row['first_name'],
        'last'        => $row['last_name'],
        'profile_pic' => $row['profile_pic'] ?? '',
        'seat'        => $row['seat_number'] ?? '',
        'share_seat'  => $row['share_seat']
    ];
}
$fg->close();
$friends_json = $friends_going;

// ── Milestone system ──────────────────────────────────────────────────────────
$milestones = [
    1   => ['title' => 'Rookie',        'icon' => '🥉'],
    5   => ['title' => 'Regular',       'icon' => '🥈'],
    10  => ['title' => 'Super Fan',     'icon' => '🥇'],
    25  => ['title' => 'Hall of Fame',  'icon' => '🏆'],
    50  => ['title' => 'Legend',        'icon' => '🌟'],
    100 => ['title' => 'GOAT',          'icon' => '🐐'],
];

$current_level  = null;
$next_target    = null;
$next_title     = null;
foreach ($milestones as $target => $ms) {
    if ($checkin_count >= $target) {
        $current_level = ['target' => $target, 'title' => $ms['title'], 'icon' => $ms['icon']];
    } else {
        if ($next_target === null) {
            $next_target = $target;
            $next_title  = $ms['title'];
        }
    }
}

$prev_target = 0;
if ($current_level) {
    $prev_target = $current_level['target'];
}
if ($next_target !== null) {
    $range    = $next_target - $prev_target;
    $progress = $checkin_count - $prev_target;
    $pct      = min(100, round(($progress / $range) * 100));
} else {
    $pct = 100;
}

$av_colors = [
    ['bg'=>'#dbeafe','fg'=>'#1e40af'],
    ['bg'=>'#fce7f3','fg'=>'#9d174d'],
    ['bg'=>'#d1fae5','fg'=>'#065f46'],
    ['bg'=>'#fef3c7','fg'=>'#92400e'],
    ['bg'=>'#ede9fe','fg'=>'#5b21b6'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check In - FanFest</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .hero-banner { background: <?php echo $active_gradient; ?> !important; color:white; padding:60px 20px; text-align:center; margin-bottom:-60px; }
        .hero-banner h1 { margin:0; font-weight:800; letter-spacing:-1px; }
        .main-container { width:95%; max-width:1100px; margin:40px auto 50px auto; position:relative; z-index:5; }
        .card { background:white; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,0.08); padding:30px; margin-bottom:25px; }
        .compact-card { background:white; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,0.05); padding:15px 25px; margin-bottom:20px; }
        
        .league-btn.active { background:<?php echo $accent; ?> !important; color:white !important; }
        .progress-bar-fill { background-color:<?php echo $accent; ?> !important; height:100%; border-radius:10px; transition: width 0.6s ease; }
        .league-btn { display:inline-block; padding:8px 15px; margin-right:5px; text-decoration:none; color:#333; background:#f1f2f6; border-radius:20px; border:1px solid #ccc; font-size:0.9em; transition:0.2s; }
        .milestone-card { background:#f8f9fa; border-radius:12px; padding:14px 10px; text-align:center; border:2px solid #eee; flex:1; }
        .milestone-card.unlocked { background:white; border-color:<?php echo $accent; ?>; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
        .milestone-card.locked { opacity:0.4; filter:grayscale(100%); }
        .filters-container { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .search-input { padding:8px 15px; border-radius:20px; border:1px solid #ccc; font-size:0.85em; width:180px; outline:none; transition:border-color 0.2s; }
        .search-input:focus { border-color:<?php echo $accent; ?>; }
        
        /* New Calendar Grid Styles */
        .calendar-day { margin-bottom: 30px; }
        .day-header { border-bottom: 2px solid #eee; padding-bottom: 8px; margin-bottom: 15px; color: #1a1a2e; display: flex; align-items: center; gap: 10px; font-size: 1.2em; font-weight: 700; }
        .day-header.today { border-bottom-color: <?php echo $accent; ?>; color: <?php echo $accent; ?>; }
        .badge-today { background: <?php echo $accent; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.6em; text-transform: uppercase; letter-spacing: 1px; }
        .games-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px; }
        .game-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .game-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        .game-card.checked-in { background: #f8fafc; border-color: #cbd5e1; opacity: 0.8; }
        
        .game-name-link { cursor:pointer; color:#1a1a2e; font-weight:800; font-size: 1.1em; transition:color 0.15s; text-decoration: none; display: block; margin-bottom: 5px; }
        .game-name-link:hover { color:<?php echo $accent; ?>; }
        .game-meta { font-size: 0.85em; color: #64748b; margin-bottom: 12px; }
        .game-meta a { color: <?php echo $accent; ?>; text-decoration: none; font-weight: 600; }
        
        .av-stack { display:flex; align-items:center; margin-top:10px; margin-bottom: 15px;}
        .av-stack .av-sm { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:700; margin-left:-6px; border:2px solid white; overflow:hidden; flex-shrink:0; }
        .av-stack .av-sm:first-child { margin-left:0; }
        .av-stack .av-sm img { width:100%; height:100%; object-fit:cover; }
        .av-stack-label { font-size:12px; color:#888; margin-left:8px; font-weight: 500;}
        
        .card-actions { display: flex; gap: 10px; margin-top: auto; border-top: 1px solid #f1f5f9; padding-top: 15px; }
        .card-actions form { flex: 1; }
        
        .btn-rsvp { width:100%; padding:10px 0; border-radius:8px; cursor:pointer; font-size:0.9em; font-weight:700; transition: 0.2s; }
        .btn-rsvp.rsvped     { background:#e8f5e9; color:#2e7d32; border:2px solid #a5d6a7; }
        .btn-rsvp.not-rsvped { background:white; color:<?php echo $accent; ?>; border:2px solid <?php echo $accent; ?>; }
        .btn-rsvp:hover.not-rsvped { background: <?php echo $accent; ?>; color: white; }
        
        .btn-checkin  { width:100%; padding:10px 0; border:none; border-radius:8px; cursor:pointer; font-size:0.9em; font-weight:700; background:<?php echo $accent; ?>; color:white; transition: 0.2s; }
        .btn-checkin:hover { filter: brightness(1.1); }
        .btn-attended { width:100%; padding:10px 0; border:none; border-radius:8px; font-size:0.9em; font-weight:700; background:#e2e8f0; color:#64748b; cursor:not-allowed; }
        
        .modal-close-btn { position:absolute; top:16px; right:16px; width:32px; height:32px; background:#f1f2f6; border:none; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; color:#636e72; transition:background 0.2s, color 0.2s; line-height:1; }
        .modal-close-btn:hover { background:#e0e0e0; color:#2d3436; }
        .friend-chip { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #f1f2f6; text-decoration:none; }
        .friend-chip:hover .friend-name { color:<?php echo $accent; ?>; }
        .friend-chip:hover { background:#f8f9fa; border-radius:8px; padding-left:6px; }
        .friend-chip:last-child { border-bottom:none; }
        .friend-av { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; overflow:hidden; }
        .friend-av img { width:100%; height:100%; object-fit:cover; }
        .friend-name { font-size:13px; font-weight:600; color:#2d3436; text-decoration:none; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="hero-banner">
    <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
    <p>Verify your location to earn your attendance badges.</p>
</div>

<div class="main-container">

    <?php if($message): ?>
        <div class="card" style="background:#f0f9ff; border-left:5px solid <?php echo $accent; ?>; color:#333; text-align:center;">
            <strong><?php echo htmlspecialchars($message); ?></strong>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
            <div>
                <h2 style="margin:0 0 2px;">Milestones</h2>
                <div style="font-size:0.9em; color:#636e72;">
                    <?php if ($current_level): ?>
                        Current rank: <strong><?php echo $current_level['icon'] . ' ' . $current_level['title']; ?></strong>
                    <?php else: ?>
                        Start checking in to earn your first badge!
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align:right;">
                <span style="font-size:2em; font-weight:800; color:<?php echo $accent; ?>;"><?php echo $checkin_count; ?></span>
                <span style="font-size:0.85em; color:#888; display:block; margin-top:-4px;">check-ins</span>
            </div>
        </div>

        <?php if ($next_target !== null): ?>
            <div style="margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; font-size:0.8em; color:#888; margin-bottom:5px;">
                    <span><?php echo $checkin_count; ?> / <?php echo $next_target; ?> check-ins to <strong><?php echo $next_title; ?></strong></span>
                    <span><?php echo $pct; ?>%</span>
                </div>
                <div style="background:#e0e0e0; height:10px; border-radius:10px; overflow:hidden;">
                    <div class="progress-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                </div>
            </div>
        <?php else: ?>
            <div style="margin-bottom:8px;">
                <div style="font-size:0.8em; color:#888; margin-bottom:5px;">You've reached the top rank! 🐐</div>
                <div style="background:#e0e0e0; height:10px; border-radius:10px; overflow:hidden;">
                    <div class="progress-bar-fill" style="width:100%;"></div>
                </div>
            </div>
        <?php endif; ?>

        <div style="display:flex; gap:8px; margin-top:18px; flex-wrap:wrap;">
            <?php foreach($milestones as $target => $ms):
                if ($checkin_count >= $target) $cls = 'unlocked';
                else $cls = 'locked';
            ?>
                <div class="milestone-card <?php echo $cls; ?>">
                    <span style="font-size:1.6em;"><?php echo $ms['icon']; ?></span><br>
                    <strong style="font-size:0.78em; display:block; margin-top:4px;"><?php echo $ms['title']; ?></strong>
                    <small style="font-size:0.7em; color:#aaa;"><?php echo $target; ?> games</small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Dynamic Location-Based Containers -->
    <div class="compact-card" id="venue-card" style="display:none;">
        <h3 style="margin:0 0 10px; font-size:1.1em; color:#333;">Venues Near You</h3>
        <div id="venue-list" style="display:flex; gap:10px; flex-wrap:wrap;">
            <div style="color:#888; font-size:0.9em;">Locating nearest stadiums...</div>
        </div>
    </div>

    <div class="filters-container">
        <div class="filters" style="margin-bottom:0;">
            <?php foreach($valid_leagues as $l): ?>
                <a href="checkin.php?league=<?php echo $l; ?>&search=<?php echo urlencode($search_query); ?>" class="league-btn <?php echo ($league_filter==$l)?'active':''; ?>"><?php echo $l; ?></a>
            <?php endforeach; ?>
        </div>
        <form action="checkin.php" method="GET" style="display:flex;">
            <input type="hidden" name="league" value="<?php echo htmlspecialchars($league_filter); ?>">
            <input type="text" name="search" class="search-input" placeholder="Search team" value="<?php echo htmlspecialchars($search_query); ?>">
        </form>
    </div>

    <!-- CALENDAR VIEW -->
    <div class="card" style="background: transparent; box-shadow: none; padding: 0;">
        <h2 style="margin-bottom: 25px; font-size: 1.8em; color: #1a1a2e;">
            <?php echo $is_valid_search ? 'Search Results' : 'Upcoming Schedule'; ?>
        </h2>

        <?php if (!empty($calendar_days)): ?>
            <?php foreach ($calendar_days as $day => $day_games): 
                $display_date = date("l, F jS", strtotime($day));
                $is_today = ($day == date('Y-m-d'));
            ?>
                <div class="calendar-day">
                    <h3 class="day-header <?php echo $is_today ? 'today' : ''; ?>">
                        <?php echo $display_date; ?>
                        <?php if($is_today): ?><span class="badge-today">Today</span><?php endif; ?>
                    </h3>
                    
                    <div class="games-grid">
                        <?php foreach($day_games as $row): 
                            $done       = !is_null($row['checkin_id']);
                            $is_rsvped  = !is_null($row['rsvp_id']);
                            $ev_friends = $friends_going[$row['event_id']] ?? [];
                        ?>
                            <div class="game-card <?php echo $done ? 'checked-in' : ''; ?>">
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                        <span style="background:#f1f5f9; padding:3px 8px; border-radius:6px; font-weight:700; font-size:0.7em; text-transform:uppercase; color:#475569;">
                                            <?php echo htmlspecialchars($row['league'] ?? 'N/A'); ?>
                                        </span>
                                        <?php if($done): ?>
                                            <span style="color:green;font-size:0.8em;font-weight:700;">✓ Checked In</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <span class="game-name-link" onclick="openGameModal(<?php echo $row['event_id']; ?>)">
                                        <?php echo htmlspecialchars($row['event_name']); ?>
                                    </span>
                                    
                                    <div class="game-meta">
                                        <?php echo date("g:i A", strtotime($row['event_date'])); ?> &middot; 
                                        <a href="maps.php?venue=<?php echo urlencode($row['location_name'] ?? 'TBA'); ?>">
                                            📍 <?php echo htmlspecialchars($row['location_name'] ?? 'TBA'); ?>
                                        </a>
                                    </div>

                                    <?php if (!empty($ev_friends)): ?>
                                        <div class="av-stack">
                                            <?php foreach(array_slice($ev_friends, 0, 4) as $fi => $f):
                                                $c = $av_colors[$fi % count($av_colors)];
                                                $initials = strtoupper(substr($f['first'],0,1) . substr($f['last'],0,1));
                                                $has_pic = !empty($f['profile_pic']) && $f['profile_pic'] !== 'default_avatar.png' && file_exists($f['profile_pic']);
                                            ?>
                                            <div class="av-sm" style="background:<?php echo $c['bg']; ?>; color:<?php echo $c['fg']; ?>;">
                                                <?php if($has_pic): ?>
                                                    <img src="<?php echo htmlspecialchars($f['profile_pic']); ?>" alt="">
                                                <?php else: ?>
                                                    <?php echo $initials; ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                            <span class="av-stack-label">
                                                <?php
                                                $names = array_map(fn($f) => $f['first'], array_slice($ev_friends, 0, 2));
                                                $extra = count($ev_friends) - count($names);
                                                echo implode(', ', $names) . ($extra > 0 ? " +$extra" : '') . ' going';
                                                ?>
                                            </span>
                                        </div>
                                    <?php elseif ($row['rsvp_count'] > 0): ?>
                                        <div style="color:#888;font-size:0.8em;margin-top:10px;margin-bottom:15px;font-weight:500;">
                                            👥 <?php echo $row['rsvp_count']; ?> fan<?php echo $row['rsvp_count']!=1?'s':''; ?> going
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="card-actions">
                                    <?php if ($is_guest): ?>
                                        <button type="button" class="btn-rsvp not-rsvped" onclick="requireLogin('RSVP and connect with friends')">RSVP</button>
                                        <button type="button" class="btn-checkin" onclick="requireLogin('check in and earn points')">Check In</button>
                                    <?php else: ?>
                                        <form action="checkin.php" method="POST">
                                            <input type="hidden" name="rsvp_event_id" value="<?php echo $row['event_id']; ?>">
                                            <input type="hidden" name="rsvp_action"   value="<?php echo $is_rsvped?'remove':'add'; ?>">
                                            <input type="hidden" name="league"        value="<?php echo htmlspecialchars($league_filter); ?>">
                                            <input type="hidden" name="search"        value="<?php echo htmlspecialchars($search_query); ?>">
                                            <button type="submit" class="btn-rsvp <?php echo $is_rsvped?'rsvped':'not-rsvped'; ?>">
                                                <?php echo $is_rsvped?'Going':'RSVP'; ?>
                                            </button>
                                        </form>
                                        <?php if(!$done): ?>
                                            <form id="form_<?php echo $row['event_id']; ?>" action="checkin.php" method="POST">
                                                <input type="hidden" name="event_id" value="<?php echo $row['event_id']; ?>">
                                                <input type="hidden" name="lat" id="lat_<?php echo $row['event_id']; ?>">
                                                <input type="hidden" name="lon" id="lon_<?php echo $row['event_id']; ?>">
                                                <button type="button" class="btn-checkin" onclick="initiateCheckIn(<?php echo $row['event_id']; ?>)">Check In</button>
                                            </form>
                                        <?php else: ?>
                                            <form><button disabled class="btn-attended">Attended</button></form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 40px;">
                <p style="color:#64748b; font-size: 1.1em;">No games found matching your criteria in the upcoming schedule.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" id="trending-card" style="display:none;">
        <h2>🔥 Trending Events Near You</h2>
        <div id="trending-list" style="display:flex; gap:15px; flex-wrap:wrap; margin-top:15px;">
            <div style="color:#888; font-size:0.9em;">Analyzing local attendance data...</div>
        </div>
    </div>

    <div class="card">
        <h2>My RSVPs</h2>
        <?php if ($rsvp_result && $rsvp_result->num_rows > 0): ?>
            <table style="width:100%;">
                <?php while($rsvp_row = $rsvp_result->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px 15px;">
                        <strong><?php echo htmlspecialchars($rsvp_row['event_name']); ?></strong><br>
                        <small style="color:#666;">
                            <?php echo htmlspecialchars($rsvp_row['location_name'] ?? 'TBA'); ?>
                            | <?php echo date("M j, g:i A", strtotime($rsvp_row['event_date'])); ?>
                            &middot; <?php echo getCountdown($rsvp_row['event_date']); ?>
                            <span style="margin-left:8px;background:#f1f2f6;border-radius:10px;padding:2px 8px;font-size:0.85em;"><?php echo htmlspecialchars($rsvp_row['league'] ?? 'N/A'); ?></span>
                        </small>
                    </td>
                    <td style="text-align:right;padding-right:15px;">
                        <form action="checkin.php" method="POST">
                            <input type="hidden" name="rsvp_event_id" value="<?php echo $rsvp_row['event_id']; ?>">
                            <input type="hidden" name="rsvp_action"   value="remove">
                            <input type="hidden" name="league"        value="<?php echo htmlspecialchars($league_filter ?? 'All'); ?>">
                            <input type="hidden" name="search"        value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                            <button type="submit" style="background:#fff0f0;color:#c0392b;border:1px solid #f5c6cb;padding:7px 16px;border-radius:5px;cursor:pointer;font-size:0.85em;font-weight:600;">Cancel RSVP</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p style="color:#888;">You haven't RSVPed to any upcoming events. Hit <strong>RSVP</strong> on a game above to save your spot!</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Event History</h2>
        <?php if ($history_result->num_rows > 0): ?>
            <div style="display:flex;gap:15px;flex-wrap:wrap;margin-top:15px;">
                <?php $history_result->data_seek(0); while($ach = $history_result->fetch_assoc()): ?>
                <a href="event_details.php?id=<?php echo $ach['event_id']; ?>" style="display:block;text-decoration:none;color:inherit;background:#f1f2f6;padding:15px;border-radius:12px;text-align:center;width:140px;border:1px solid #eee;transition:0.2s;">
                    <div style="font-size:2.2em;">🎟️</div>
                    <strong style="font-size:0.8em;display:block;margin-top:5px;"><?php echo htmlspecialchars($ach['event_name']); ?></strong>
                    <small style="font-size:0.7em;color:#888;"><?php echo date("M j", strtotime($ach['checkin_time'])); ?></small>
                </a>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>You haven't checked into any events yet. Start attending games to build your history!</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Logic Remains the Same -->
<div id="gameModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);backdrop-filter:blur(6px);z-index:1000;justify-content:center;align-items:center;">
    <div style="background:white;border-radius:20px;padding:32px;width:480px;max-width:95%;box-shadow:0 20px 60px rgba(0,0,0,0.2);position:relative;max-height:85vh;overflow-y:auto;">
        <button class="modal-close-btn" onclick="closeGameModal()">✕</button>
        <div id="modal-league" style="display:inline-block;background:<?php echo $accent; ?>18;color:<?php echo $accent; ?>;border-radius:20px;padding:3px 12px;font-size:11px;font-weight:800;margin-bottom:12px;"></div>
        <h2 id="modal-name" style="margin:0 0 16px;font-size:18px;font-weight:800;color:#1a1a2e;line-height:1.3;padding-right:40px;"></h2>
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:10px;font-size:14px;">
                <span style="width:28px;text-align:center;">📅</span>
                <span id="modal-date" style="color:#2d3436;font-weight:600;"></span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;font-size:14px;">
                <span style="width:28px;text-align:center;">📍</span>
                <a id="modal-venue-link" href="#" style="color:<?php echo $accent; ?>;font-weight:600;text-decoration:none;"></a>
            </div>
            <div style="display:flex;align-items:center;gap:10px;font-size:14px;">
                <span style="width:28px;text-align:center;">👥</span>
                <span id="modal-fans" style="color:#636e72;"></span>
            </div>
        </div>
        <div id="modal-friends-section" style="display:none;background:#f8f9fa;border-radius:12px;padding:14px;margin-bottom:20px;">
            <div style="font-size:11px;font-weight:800;color:#b2bec3;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:10px;">Friends Going</div>
            <div id="modal-friends"></div>
        </div>

        <div id="modal-seat-section" style="background:#f8f9fa;border-radius:12px;padding:14px;margin-bottom:16px;">
            <div style="font-size:11px;font-weight:800;color:#b2bec3;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:10px;">Your Seat</div>
            <input type="text" id="modal-seat-input" placeholder="e.g. Section 12, Row C, Seat 4"
                style="width:100%;padding:9px 12px;border:1.5px solid #dfe6e9;border-radius:8px;font-size:13px;box-sizing:border-box;margin-bottom:10px;font-family:inherit;">
            <div style="display:flex;align-items:center;justify-content:space-between;background:white;border-radius:8px;padding:10px 12px;">
                <div>
                    <div style="font-size:13px;font-weight:600;color:#2d3436;">Share seat with friends</div>
                    <div style="font-size:11px;color:#b2bec3;">Friends can see your seat on the event page</div>
                </div>
                <div style="position:relative;">
                    <input type="checkbox" id="modal-share-seat" style="display:none;" checked>
                    <label for="modal-share-seat" id="modal-share-track"
                        style="width:38px;height:22px;background:#0984e3;border-radius:11px;display:block;cursor:pointer;position:relative;transition:background 0.2s;">
                        <span id="modal-share-knob" style="position:absolute;width:18px;height:18px;background:white;border-radius:50%;top:2px;left:18px;transition:transform 0.2s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                    </label>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px;">
            <form id="modal-rsvp-form" action="checkin.php" method="POST" style="flex:1;">
                <input type="hidden" name="rsvp_event_id" id="modal-rsvp-event-id">
                <input type="hidden" name="rsvp_action"   id="modal-rsvp-action">
                <input type="hidden" name="seat_number"   id="modal-seat-hidden">
                <input type="hidden" name="share_seat"    id="modal-share-hidden" value="1">
                <input type="hidden" name="league" value="<?php echo htmlspecialchars($league_filter); ?>">
                <button type="submit" id="modal-rsvp-btn" onclick="syncSeatFields()" style="width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;"></button>
            </form>
            <form id="modal-checkin-form" action="checkin.php" method="POST" style="flex:1;">
                <input type="hidden" name="event_id" id="modal-checkin-event-id">
                <input type="hidden" name="lat" id="modal-lat">
                <input type="hidden" name="lon" id="modal-lon">
                <button type="button" id="modal-checkin-btn" onclick="initiateModalCheckIn()" style="width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:<?php echo $accent; ?>;color:white;border:none;"></button>
            </form>
        </div>
    </div>
</div>

<script>
const friendsData = <?php echo json_encode($friends_json); ?>;
const avColors = <?php echo json_encode($av_colors); ?>;
const isGuestUser = <?php echo $is_guest ? 'true' : 'false'; ?>; 

function openGameModal(eventId) {
    const row = document.querySelector(`[data-event-id="${eventId}"]`);
    const name         = row.dataset.name;
    const venue        = row.dataset.venue;
    const date         = row.dataset.date;
    const league       = row.dataset.league;
    const fanCount     = parseInt(row.dataset.fancount);
    const isCheckedIn = row.dataset.checkedin === 'true';
    const isRsvped    = row.dataset.rsvped === 'true';
    const seatNumber  = row.dataset.seat || '';
    const shareSeat   = row.dataset.shareseat !== 'false';

    document.getElementById('modal-league').innerText = league;
    document.getElementById('modal-name').innerText   = name;
    document.getElementById('modal-date').innerText   = date;

    const venueLink = document.getElementById('modal-venue-link');
    venueLink.innerText = venue;
    venueLink.href = 'maps.php?venue=' + encodeURIComponent(venue);
    document.getElementById('modal-fans').innerText = fanCount + ' fan' + (fanCount !== 1 ? 's' : '') + ' going';

    // populate seat fields
    document.getElementById('modal-seat-input').value = seatNumber;
    const shareCheckbox = document.getElementById('modal-share-seat');
    const shareTrack    = document.getElementById('modal-share-track');
    const shareKnob     = document.getElementById('modal-share-knob');
    shareCheckbox.checked = shareSeat;
    updateToggleUI(shareTrack, shareKnob, shareSeat);

    shareCheckbox.onchange = function() {
        updateToggleUI(shareTrack, shareKnob, this.checked);
        document.getElementById('modal-share-hidden').value = this.checked ? '1' : '0';
    };
    document.getElementById('modal-share-track').onclick = function() {
        shareCheckbox.checked = !shareCheckbox.checked;
        shareCheckbox.dispatchEvent(new Event('change'));
    };

    const friends = friendsData[eventId] || [];
    const friendsSection = document.getElementById('modal-friends-section');
    const friendsDiv     = document.getElementById('modal-friends');
    if (friends.length > 0) {
        friendsSection.style.display = 'block';
        friendsDiv.innerHTML = friends.map((f, i) => {
            const c = avColors[i % avColors.length];
            const initials = (f.first[0] + f.last[0]).toUpperCase();
            const avatarHtml = (f.profile_pic && f.profile_pic !== 'default_avatar.png')
                ? `<div class="friend-av"><img src="${f.profile_pic}" alt=""></div>`
                : `<div class="friend-av" style="background:${c.bg};color:${c.fg};">${initials}</div>`;
            
            let seatHtml = '';
            if (f.seat && f.share_seat == 1) {
                seatHtml = `<div style="font-size:11px; color:#0984e3; background:#f0f9ff; border:1px solid #bfdbfe; border-radius:6px; padding:2px 6px; margin-top:2px; display:inline-block; font-weight:700;">${f.seat}</div>`;
            } else if (f.seat && f.share_seat == 0) {
                seatHtml = `<div style="font-size:11px; color:#b2bec3; font-weight:600; margin-top:2px;">🔒 Seat private</div>`;
            }

            return `<a href="profile.php?id=${f.user_id}" class="friend-chip" style="align-items:flex-start;">
                ${avatarHtml}
                <div style="display:flex; flex-direction:column; padding-top:2px;">
                    <span class="friend-name">${f.name}</span>
                    ${seatHtml}
                </div>
            </a>`;
        }).join('');
    } else {
        friendsSection.style.display = 'none';
    }

    const rsvpBtn = document.getElementById('modal-rsvp-btn');
    document.getElementById('modal-rsvp-event-id').value = eventId;
    
    if (isGuestUser) {
        rsvpBtn.innerText = 'RSVP';
        rsvpBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:white;color:<?php echo $accent; ?>;border:2px solid <?php echo $accent; ?>;';
        rsvpBtn.onclick = function(e) { e.preventDefault(); closeGameModal(); requireLogin('RSVP to this game'); };
    } else {
        if (isRsvped) {
            rsvpBtn.innerText = 'Update Seat';
            rsvpBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:#e8f5e9;color:#2e7d32;border:2px solid #a5d6a7;';
            document.getElementById('modal-rsvp-action').value = 'update_seat';
        } else {
            rsvpBtn.innerText = 'RSVP';
            rsvpBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:white;color:<?php echo $accent; ?>;border:2px solid <?php echo $accent; ?>;';
            document.getElementById('modal-rsvp-action').value = 'add';
        }
        rsvpBtn.onclick = syncSeatFields;
    }

    const checkinBtn = document.getElementById('modal-checkin-btn');
    document.getElementById('modal-checkin-event-id').value = eventId;
    
    if (isGuestUser) {
        checkinBtn.innerText = 'Check In';
        checkinBtn.disabled = false;
        checkinBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:<?php echo $accent; ?>;color:white;border:none;';
        checkinBtn.onclick = function(e) { e.preventDefault(); closeGameModal(); requireLogin('check in to earn points'); };
    } else {
        if (isCheckedIn) {
            checkinBtn.innerText = '✓ Attended';
            checkinBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;background:#ccc;color:#666;border:none;cursor:not-allowed;';
            checkinBtn.disabled = true;
            checkinBtn.onclick = null;
        } else {
            checkinBtn.innerText = 'Check In';
            checkinBtn.disabled = false;
            checkinBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:<?php echo $accent; ?>;color:white;border:none;';
            checkinBtn.onclick = initiateModalCheckIn;
        }
    }

    document.getElementById('gameModal').style.display = 'flex';
}

function closeGameModal() {
    document.getElementById('gameModal').style.display = 'none';
}

function initiateModalCheckIn() {
    const btn = document.getElementById('modal-checkin-btn');
    btn.innerText = 'Locating...';
    btn.style.background = '#ff9f43';
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => {
                document.getElementById('modal-lat').value = pos.coords.latitude;
                document.getElementById('modal-lon').value = pos.coords.longitude;
                document.getElementById('modal-checkin-form').submit();
            },
            () => document.getElementById('modal-checkin-form').submit(),
            { enableHighAccuracy:true, timeout:10000, maximumAge:0 }
        );
    } else {
        document.getElementById('modal-checkin-form').submit();
    }
}

document.getElementById('gameModal').addEventListener('click', e => {
    if (e.target === document.getElementById('gameModal')) closeGameModal();
});

function initiateCheckIn(eventId) {
    let btn = document.querySelector('#form_' + eventId + ' button');
    btn.innerText = "Locating...";
    btn.style.background = "#ff9f43";
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => {
                document.getElementById('lat_' + eventId).value = pos.coords.latitude;
                document.getElementById('lon_' + eventId).value = pos.coords.longitude;
                document.getElementById('form_' + eventId).submit();
            },
            () => document.getElementById('form_' + eventId).submit(),
            { enableHighAccuracy:true, timeout:10000, maximumAge:0 }
        );
    } else {
        document.getElementById('form_' + eventId).submit();
    }
}

function syncSeatFields() {
    document.getElementById('modal-seat-hidden').value   = document.getElementById('modal-seat-input').value;
    document.getElementById('modal-share-hidden').value  = document.getElementById('modal-share-seat').checked ? '1' : '0';
}

function updateToggleUI(track, knob, isOn) {
    track.style.background = isOn ? '<?php echo $accent; ?>' : '#dfe6e9';
    knob.style.transform   = isOn ? 'translateX(16px)' : 'translateX(0)';
}

window.addEventListener('DOMContentLoaded', () => {
    if (navigator.geolocation) {
        document.getElementById('venue-card').style.display = 'block';
        document.getElementById('trending-card').style.display = 'block';
        
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude;
            const lon = pos.coords.longitude;
            
            fetch(`api_local_events.php?lat=${lat}&lon=${lon}`)
                .then(res => res.json())
                .then(data => {
                    // Update Nearest Venues
                    let vHtml = data.venues.map(v => 
                        `<div style="background:#f1f2f6;padding:8px 15px;border-radius:20px;border:1px solid #ddd;font-size:0.9em;display:flex;align-items:center;gap:8px;">
                            <strong>${v.name}</strong>
                            <span style="color:#666;">${parseFloat(v.distance).toFixed(1)} mi away</span>
                         </div>`
                    ).join('');
                    document.getElementById('venue-list').innerHTML = vHtml || '<div style="color:#888;">No venues registered in your database yet.</div>';

                    // Update Trending Local Events
                    let tHtml = data.trending.map((t, i) => 
                        `<div style="background:linear-gradient(145deg, #ffffff, #f8f9fa); border:1px solid #e2e8f0; border-radius:12px; padding:16px; flex:1; min-width:250px; position:relative; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
                            <div style="position:absolute; top:12px; right:12px; background:<?php echo $accent; ?>20; color:<?php echo $accent; ?>; font-weight:800; font-size:11px; padding:3px 8px; border-radius:12px;">
                                #${i+1} Hot
                            </div>
                            <strong style="font-size:0.95em; display:block; padding-right:45px; color:#1a1a2e;">
                                ${t.event_name}
                            </strong>
                            <div style="margin-top:8px; font-size:0.8em; color:#64748b; display:flex; align-items:center; gap:5px;">
                                <span>📍</span> ${t.location_name}
                            </div>
                            <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid #edf2f7; padding-top:10px;">
                                <span style="font-size:0.75em; background:#f1f5f9; padding:2px 8px; border-radius:6px; font-weight:600; text-transform:uppercase; color:#475569;">
                                    ${t.league}
                                </span>
                                <span style="font-size:0.85em; color:green; font-weight:700;">
                                    ⚡ ${t.total_attendance} checked in
                                </span>
                            </div>
                        </div>`
                    ).join('');
                    document.getElementById('trending-list').innerHTML = tHtml || '<p style="color:#888; font-size:0.9em;">No trending events near you yet.</p>';
                });
        }, () => { 
            document.getElementById('venue-list').innerHTML = '<div style="color:red;">Location access denied.</div>';
            document.getElementById('trending-card').style.display = 'none';
        });
    }
});
</script>

<div style="display:none;">
<?php
// Mirror the new calendar date logic for the hidden modal data
$sql2 = "SELECT e.event_id, e.event_name, e.location_name, e.event_date, e.league,
                (SELECT COUNT(*) FROM rsvps WHERE event_id = e.event_id) AS rsvp_count,
                c.checkin_id, r.rsvp_id, r.seat_number, r.share_seat
         FROM events e
         LEFT JOIN checkins c ON e.event_id = c.event_id AND c.user_id = $user_id
         LEFT JOIN rsvps r    ON e.event_id = r.event_id AND r.user_id = $user_id
         WHERE e.event_date >= '$today_start'
           AND e.event_date <= '$date_limit'
           AND e.event_name NOT LIKE '%TBD%'
           AND e.event_name NOT LIKE '%/%'";
if ($league_filter != 'All') $sql2 .= " AND e.league = '" . $conn->real_escape_string($league_filter) . "'";
if ($is_valid_search)        $sql2 .= " AND e.event_name LIKE '%" . $conn->real_escape_string($cleaned_search) . "%'";
$sql2 .= " ORDER BY e.event_date ASC";
if (!$is_valid_search) $sql2 .= " LIMIT 150";

$ev2 = $conn->query($sql2);
while ($r2 = $ev2->fetch_assoc()):
    if (strpos($r2['event_name'], '/') !== false) continue;
?>
<span
    data-event-id="<?php echo $r2['event_id']; ?>"
    data-name="<?php echo htmlspecialchars($r2['event_name'] ?? 'TBA', ENT_QUOTES); ?>"
    data-venue="<?php echo htmlspecialchars($r2['location_name'] ?? 'TBA', ENT_QUOTES); ?>"
    data-date="<?php echo date('F j, Y · g:i A', strtotime($r2['event_date'])); ?>"
    data-league="<?php echo htmlspecialchars($r2['league'] ?? 'N/A', ENT_QUOTES); ?>"
    data-fancount="<?php echo $r2['rsvp_count']; ?>"
    data-checkedin="<?php echo is_null($r2['checkin_id']) ? 'false' : 'true'; ?>"
    data-rsvped="<?php echo is_null($r2['rsvp_id']) ? 'false' : 'true'; ?>"
    data-seat="<?php echo htmlspecialchars($r2['seat_number'] ?? '', ENT_QUOTES); ?>"
    data-shareseat="<?php echo ($r2['share_seat'] ?? 1) ? 'true' : 'false'; ?>">
</span>
<?php endwhile; ?>
</div>

</body>
</html>
