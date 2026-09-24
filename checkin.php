<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Timezone setup
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

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// Only the worker records successful completion. Throttle failed launch/retry attempts.
$last_import_file = __DIR__ . '/last_game_import.txt';
$last_success = is_file($last_import_file) ? (int)file_get_contents($last_import_file) : 0;
if (time() - $last_success > 86400) {
    $attempt = fopen(sys_get_temp_dir() . '/fanfest-launch-' . sha1(__DIR__) . '.lock', 'c+');
    if ($attempt && flock($attempt, LOCK_EX | LOCK_NB)) {
        $last_attempt = (int)stream_get_contents($attempt);
        if (time() - $last_attempt > 300) {
            ftruncate($attempt, 0);
            rewind($attempt);
            fwrite($attempt, (string)time());
            fflush($attempt);
            if (function_exists('exec')) {
                $php = PHP_BINDIR . '/php';
                $command = escapeshellarg($php) . ' -d log_errors=1 -d display_errors=0 '
                    . escapeshellarg(__DIR__ . '/import_games.php') . ' > /dev/stderr 2>&1 &';
                exec($command, $output, $exit_code);
                if ($exit_code !== 0) error_log('[FanFest schedule] Unable to start import worker');
            } else {
                error_log('[FanFest schedule] exec is unavailable; configure a scheduled CLI import');
            }
        }
        flock($attempt, LOCK_UN);
    }
    if ($attempt) fclose($attempt);
}

$is_guest = ($_SESSION['user_id'] === 'guest');
$user_id = $is_guest ? 0 : $_SESSION['user_id']; 

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
        'franklin hall'              => [39.1673, -86.5233],
        'simon skjodt assembly hall' => [39.1809, -86.5222],
        'assembly hall'              => [39.1809, -86.5222],
        'memorial stadium'           => [39.1809, -86.5256],
        'united center'              => [41.8806, -87.6742],
        'target center'              => [44.9795, -93.2762],
        'soldier field'              => [41.8623, -87.6167],
        'lucas oil stadium'          => [39.7601, -86.1639],
    ];
    $name = strtolower(trim($venue_name));
    foreach ($venues as $key => $coords) {
        if (str_contains($name, $key) || str_contains($key, $name)) return $coords;
    }
    return null;
}

// Handle RSVP
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

// Handle Check In
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

// User styling
if ($is_guest) {
    $user_name = "Guest Fan";
    $theme = "blue";
} else {
    $theme_data = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $user_id")->fetch_assoc();
    $user_name  = $theme_data['first_name'] ?? "Fan";
    $theme      = $theme_data['theme_color'] ?? 'blue';
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

$date_limit = date('Y-m-d 23:59:59', strtotime('+14 days'));

// Main Games Query
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

$all_games = [];
$calendar_dates = []; // Stores unique dates for the calendar ribbon

if ($events_result && $events_result->num_rows > 0) {
    while($row = $events_result->fetch_assoc()) {
        if (strpos($row['event_name'], '/') !== false) continue;
        
        $ymd = date('Y-m-d', strtotime($row['event_date']));
        $row['ymd'] = $ymd;
        $all_games[] = $row;
        
        if (!isset($calendar_dates[$ymd])) {
            $calendar_dates[$ymd] = [
                'ymd'   => $ymd,
                'dow'   => date('D', strtotime($ymd)), // Mon, Tue
                'day'   => date('j', strtotime($ymd)), // 1, 2
                'month' => date('M', strtotime($ymd))  // Aug
            ];
        }
    }
}
ksort($calendar_dates); // Ensure chronologically sorted

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
    WHERE f.status = 'accepted' AND e.event_date >= '$today_start'
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

$current_level = null; $next_target = null; $next_title = null;
foreach ($milestones as $target => $ms) {
    if ($checkin_count >= $target) $current_level = ['target' => $target, 'title' => $ms['title'], 'icon' => $ms['icon']];
    else if ($next_target === null) { $next_target = $target; $next_title  = $ms['title']; }
}

$prev_target = $current_level ? $current_level['target'] : 0;
if ($next_target !== null) {
    $pct = min(100, round((($checkin_count - $prev_target) / ($next_target - $prev_target)) * 100));
} else { $pct = 100; }

$av_colors = [
    ['bg'=>'#dbeafe','fg'=>'#1e40af'], ['bg'=>'#fce7f3','fg'=>'#9d174d'],
    ['bg'=>'#d1fae5','fg'=>'#065f46'], ['bg'=>'#fef3c7','fg'=>'#92400e'],
    ['bg'=>'#ede9fe','fg'=>'#5b21b6'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        
        /* New Calendar Ribbon Styles */
        .calendar-ribbon { display: flex; overflow-x: auto; gap: 12px; padding-bottom: 15px; margin-bottom: 20px; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; }
        .calendar-ribbon::-webkit-scrollbar { height: 6px; }
        .calendar-ribbon::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .cal-day { display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; min-width: 65px; height: 75px; cursor: pointer; transition: 0.2s; flex-shrink: 0; }
        .cal-day:hover { border-color: <?php echo $accent; ?>; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .cal-day.active { background: <?php echo $accent; ?>; border-color: <?php echo $accent; ?>; color: white; box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .cal-dow { font-size: 0.75em; text-transform: uppercase; font-weight: 700; color: #64748b; margin-bottom: 2px; }
        .cal-day.active .cal-dow { color: rgba(255,255,255,0.8); }
        .cal-num { font-size: 1.4em; font-weight: 800; color: #1a1a2e; line-height: 1; }
        .cal-day.active .cal-num { color: white; }
        
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

        /* Check-in page: a quieter layout that keeps the schedule in focus. */
        body { background:#f4f6fa; color:#172033; }
        .hero-banner { padding:42px 24px 84px; text-align:left; margin-bottom:-46px; }
        .hero-inner { max-width:1160px; margin:0 auto; display:flex; align-items:flex-end; justify-content:space-between; gap:24px; }
        .hero-banner .hero-eyebrow { margin:0 0 10px; font-size:11px; font-weight:800; letter-spacing:.2em; text-transform:uppercase; color:rgba(255,255,255,.76); opacity:1; }
        .hero-banner h1 { font-size:clamp(29px,3.5vw,46px); line-height:1.12; letter-spacing:-.04em; }
        .hero-banner p.hero-copy { max-width:580px; margin:13px 0 0; color:rgba(255,255,255,.87); font-weight:400; font-size:15px; line-height:1.6; }
        .hero-stat { min-width:148px; padding:15px 20px; border:1px solid rgba(255,255,255,.28); border-radius:18px; background:rgba(255,255,255,.13); backdrop-filter:blur(12px); }
        .hero-stat strong { display:block; font-size:30px; line-height:1; letter-spacing:-.05em; }
        .hero-stat span { display:block; margin-top:7px; font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:rgba(255,255,255,.82); }
        .main-container { width:calc(100% - 40px); max-width:1160px; margin:0 auto 60px; }
        .card, .compact-card { border:1px solid #e8ecf3; border-radius:18px; box-shadow:0 8px 28px rgba(24,39,67,.05); }
        .progress-card { padding:20px 24px; margin-bottom:22px; }
        .progress-card-head { display:flex; align-items:center; justify-content:space-between; gap:18px; margin-bottom:12px; }
        .progress-card h2 { margin:0 0 3px; font-size:18px; letter-spacing:-.025em; }
        .progress-card .rank-copy { font-size:13px; color:#637086; }
        .progress-count { text-align:right; white-space:nowrap; }
        .progress-count strong { display:block; color:<?php echo $accent; ?>; font-size:27px; line-height:1; letter-spacing:-.04em; }
        .progress-count span { color:#64748b; font-size:11px; font-weight:700; }
        .progress-track { height:7px; background:#e8edf5; border-radius:999px; overflow:hidden; }
        .milestone-details { margin-top:12px; }
        .milestone-details summary { width:max-content; cursor:pointer; color:<?php echo $accent; ?>; font-size:12px; font-weight:800; }
        .milestone-list { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
        .milestone-list .milestone-card { min-width:105px; flex:1; }
        .browse-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin:8px 0 18px; }
        .browse-heading h2 { margin:0 0 3px; font-size:25px; letter-spacing:-.04em; }
        .browse-heading p { margin:0; color:#657187; font-size:13px; }
        .filters-container { padding:15px; margin-bottom:16px; background:#fff; border:1px solid #e8ecf3; border-radius:18px; box-shadow:0 8px 28px rgba(24,39,67,.04); gap:12px; }
        .filters { display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
        .league-btn { margin:0; padding:8px 13px; border-radius:10px; border:1px solid transparent; background:transparent; color:#617088; font-size:12px; font-weight:800; }
        .league-btn:hover { color:#172033; background:#f0f3f8; }
        .league-btn.active { border-color:transparent; box-shadow:0 4px 10px rgba(20,35,61,.13); }
        .search-form { flex:0 0 210px; margin:0; }
        .search-input { width:100% !important; box-sizing:border-box; margin:0 !important; padding:10px 13px !important; border-radius:10px !important; border:1px solid #dfe5ee !important; background:#f8fafc; color:#172033; font-size:12px; }
        .search-input:focus { background:#fff; border-color:<?php echo $accent; ?> !important; box-shadow:0 0 0 3px rgba(9,132,227,.11); }
        .schedule-section { margin:0 0 26px; }
        .calendar-ribbon { gap:8px; padding:2px 2px 12px; margin:0 0 13px; }
        .cal-day { width:auto; min-width:62px; height:68px; padding:0 12px; border:1px solid #e5eaf2; border-radius:12px; box-shadow:0 3px 10px rgba(24,39,67,.025); font-family:inherit; }
        .cal-day:first-child { min-width:94px; }
        .cal-day.active { box-shadow:0 7px 17px rgba(24,39,67,.15); }
        .cal-dow { font-size:10px; letter-spacing:.09em; }
        .cal-num { font-size:18px; }
        .cal-day:first-child .cal-num { font-size:13px; }
        .games-grid { grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }
        .game-card { min-height:196px; padding:20px; border:1px solid #e7ebf2; border-radius:16px; box-shadow:0 5px 18px rgba(24,39,67,.035); }
        .game-card:hover { transform:translateY(-3px); border-color:#cdd7e5; box-shadow:0 14px 30px rgba(24,39,67,.09); }
        .game-card.checked-in { opacity:1; background:#f8fbfa; }
        .game-card-top { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:14px; }
        .league-pill, .checked-pill { display:inline-flex; align-items:center; border-radius:7px; padding:5px 8px; font-size:10px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
        .league-pill { color:<?php echo $accent; ?>; background:#eff5fb; }
        .checked-pill { color:#187247; background:#e8f7ef; }
        .game-name-link { width:100%; padding:0; margin:0 0 13px; border:0; border-radius:0; background:none; color:#172033; text-align:left; font-family:inherit; font-size:17px; line-height:1.35; letter-spacing:-.025em; }
        .game-name-link:hover { background:none; color:<?php echo $accent; ?>; }
        .game-meta { display:flex; flex-direction:column; gap:5px; margin-bottom:15px; color:#68768a; font-size:12px; line-height:1.45; }
        .game-meta a { color:#68768a; font-weight:500; }
        .game-meta a:hover { color:<?php echo $accent; ?>; }
        .card-actions { gap:8px; border-top:1px solid #edf0f5; padding-top:14px; }
        .btn-rsvp, .btn-checkin, .btn-attended { min-height:38px; padding:8px 10px; border-radius:9px; font-size:12px; font-family:inherit; }
        .btn-rsvp.not-rsvped { border:1px solid #d8e1ee; background:#fff; color:#304258; }
        .btn-rsvp.not-rsvped:hover { border-color:<?php echo $accent; ?>; background:#eef5fc; color:<?php echo $accent; ?>; }
        .btn-rsvp.rsvped { border:1px solid #bfe5cd; }
        .btn-checkin:hover { filter:brightness(.94); }
        .empty-games { padding:36px 20px; text-align:center; background:#fff; border:1px solid #e8ecf3; border-radius:16px; color:#64748b; }
        .empty-games p { margin:0 0 8px; }
        .empty-games a { color:<?php echo $accent; ?>; font-weight:700; }
        #no-games-msg { padding:26px; background:#fff; border:1px solid #e8ecf3; border-radius:16px; }
        .rsvp-table-wrap { overflow-x:auto; }
        .rsvp-section h2 { margin:0 0 16px; font-size:20px; letter-spacing:-.03em; }
        .rsvp-section table { min-width:580px; }
        .rsvp-section td { border-bottom:1px solid #edf0f5; }
        :is(.league-btn,.cal-day,.game-name-link,.btn-rsvp,.btn-checkin,.milestone-details summary,.search-input):focus-visible { outline:3px solid <?php echo $accent; ?>; outline-offset:3px; }
        @media (max-width:720px) {
            .hero-banner { padding:32px 20px 74px; }
            .hero-inner { align-items:flex-start; }
            .hero-stat { min-width:88px; padding:10px 12px; border-radius:13px; }
            .hero-stat strong { font-size:23px; }
            .hero-stat span { font-size:9px; line-height:1.3; }
            .hero-banner h1 { font-size:30px; }
            .hero-banner p.hero-copy { font-size:13px; }
            .main-container { width:calc(100% - 28px); }
            .progress-card { padding:17px; }
            .filters-container { display:block; padding:12px; }
            .filters { flex-wrap:nowrap; overflow-x:auto; padding-bottom:4px; scrollbar-width:thin; }
            .league-btn { flex-shrink:0; }
            .search-form { display:block; margin-top:10px; }
            .games-grid { grid-template-columns:1fr; }
            .game-card { min-height:0; }
        }
        @media (max-width:420px) { .hero-stat { display:none; } }
        @media (prefers-reduced-motion:reduce) { .game-card,.cal-day,.league-btn { transition:none; } }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="hero-banner">
    <div class="hero-inner">
        <div>
            <p class="hero-eyebrow">FanFest / Game day</p>
            <h1>Find your next game, <?php echo htmlspecialchars($user_name); ?>.</h1>
            <p class="hero-copy">See what's coming up, make plans with friends, and check in when you get there.</p>
        </div>
        <div class="hero-stat" aria-label="<?php echo count($all_games); ?> games currently shown">
            <strong><?php echo count($all_games); ?></strong>
            <span>Games to explore</span>
        </div>
    </div>
</div>

<div class="main-container">

    <?php if($message): ?>
        <div class="card" style="background:#f0f9ff; border-left:5px solid <?php echo $accent; ?>; color:#333; text-align:center;">
            <strong><?php echo htmlspecialchars($message); ?></strong>
        </div>
    <?php endif; ?>

    <div class="card progress-card">
        <div class="progress-card-head">
            <div>
                <h2>Your fan progress</h2>
                <div class="rank-copy">
                    <?php if ($current_level): ?>
                        Current rank: <strong><?php echo $current_level['icon'] . ' ' . $current_level['title']; ?></strong>
                    <?php else: ?>
                        Start checking in to earn your first badge!
                    <?php endif; ?>
                </div>
            </div>
            <div class="progress-count">
                <strong><?php echo $checkin_count; ?></strong>
                <span>check-ins</span>
            </div>
        </div>

        <?php if ($next_target !== null): ?>
            <div style="margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; font-size:0.8em; color:#888; margin-bottom:5px;">
                    <span><?php echo $checkin_count; ?> / <?php echo $next_target; ?> check-ins to <strong><?php echo $next_title; ?></strong></span>
                    <span><?php echo $pct; ?>%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                </div>
            </div>
        <?php else: ?>
            <div style="margin-bottom:8px;">
                <div style="font-size:0.8em; color:#888; margin-bottom:5px;">You've reached the top rank! 🐐</div>
                <div class="progress-track">
                    <div class="progress-bar-fill" style="width:100%;"></div>
                </div>
            </div>
        <?php endif; ?>

        <details class="milestone-details">
            <summary>View badges</summary>
            <div class="milestone-list">
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
        </details>
    </div>

    <!-- Location-Based Venues -->
    <div class="compact-card" id="venue-card" style="display:none;">
        <h3 style="margin:0 0 10px; font-size:1.1em; color:#333;">Venues Near You</h3>
        <div id="venue-list" style="display:flex; gap:10px; flex-wrap:wrap;">
            <div style="color:#888; font-size:0.9em;">Locating nearest stadiums...</div>
        </div>
    </div>

    <div class="browse-heading">
        <div>
            <h2>Upcoming games</h2>
            <p><span id="results-count"><?php echo count($all_games); ?> game<?php echo count($all_games) === 1 ? '' : 's'; ?></span> in the upcoming schedule</p>
        </div>
    </div>

    <div class="filters-container">
        <div class="filters" style="margin-bottom:0;">
            <?php foreach($valid_leagues as $l): ?>
                <a href="checkin.php?league=<?php echo $l; ?>&search=<?php echo urlencode($search_query); ?>" class="league-btn <?php echo ($league_filter==$l)?'active':''; ?>"><?php echo $l; ?></a>
            <?php endforeach; ?>
        </div>
        <form action="checkin.php" method="GET" class="search-form">
            <input type="hidden" name="league" value="<?php echo htmlspecialchars($league_filter); ?>">
            <input type="search" name="search" class="search-input" aria-label="Search teams" placeholder="Search teams…" value="<?php echo htmlspecialchars($search_query); ?>">
        </form>
    </div>

    <!-- CALENDAR UI -->
    <section class="schedule-section" aria-label="Upcoming game schedule">
        
        <?php if (!empty($calendar_dates)): ?>
            <!-- Horizontal Date Scroller -->
            <div class="calendar-ribbon">
                <button type="button" class="cal-day active" data-filter="all" onclick="filterByDate('all')" aria-pressed="true">
                    <span class="cal-dow">ALL</span>
                    <span class="cal-num">Games</span>
                </button>
                <?php foreach($calendar_dates as $d): ?>
                <button type="button" class="cal-day" data-filter="<?php echo $d['ymd']; ?>" onclick="filterByDate('<?php echo $d['ymd']; ?>')" aria-pressed="false" aria-label="Show games on <?php echo $d['ymd']; ?>">
                    <span class="cal-dow"><?php echo $d['dow']; ?></span>
                    <span class="cal-num"><?php echo $d['day']; ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Games Grid -->
            <div class="games-grid" id="main-games-grid">
                <?php foreach($all_games as $row): 
                    $done       = !is_null($row['checkin_id']);
                    $is_rsvped  = !is_null($row['rsvp_id']);
                    $ev_friends = $friends_going[$row['event_id']] ?? [];
                ?>
                    <div class="game-card <?php echo $done ? 'checked-in' : ''; ?>" data-ymd="<?php echo $row['ymd']; ?>">
                        <div>
                            <div class="game-card-top">
                                <span class="league-pill">
                                    <?php echo htmlspecialchars($row['league'] ?? 'N/A'); ?>
                                </span>
                                <?php if($done): ?>
                                    <span class="checked-pill">✓ Checked in</span>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" class="game-name-link" onclick="openGameModal(<?php echo $row['event_id']; ?>)">
                                <?php echo htmlspecialchars($row['event_name']); ?>
                            </button>
                            
                            <div class="game-meta">
                                <span>◷ &nbsp;<?php echo date("D, M j · g:i A", strtotime($row['event_date'])); ?></span>
                                <a href="maps.php?venue=<?php echo urlencode($row['location_name'] ?? 'TBA'); ?>">
                                    📍 &nbsp;<?php echo htmlspecialchars($row['location_name'] ?? 'TBA'); ?>
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
            
            <div id="no-games-msg" style="display:none;">
                <p>No games scheduled for this selected date.</p>
            </div>

        <?php else: ?>
            <div class="empty-games">
                <p>No games match your filters right now.</p>
                <a href="checkin.php">View all games</a>
            </div>
        <?php endif; ?>
    </section>


    <div class="card" id="trending-card" style="display:none;">
        <h2>🔥 Trending Events Near You</h2>
        <div id="trending-list" style="display:flex; gap:15px; flex-wrap:wrap; margin-top:15px;">
            <div style="color:#888; font-size:0.9em;">Analyzing local attendance data...</div>
        </div>
    </div>

 
    <div class="card rsvp-section">
        <h2>My RSVPs</h2>
        <?php if ($rsvp_result && $rsvp_result->num_rows > 0): ?>
            <div class="rsvp-table-wrap">
            <table style="width:100%;">
                <?php while($rsvp_row = $rsvp_result->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px 15px;">
                        <strong><?php echo htmlspecialchars($rsvp_row['event_name']); ?></strong><br>
                        <small style="color:#666;">
                            <?php echo htmlspecialchars($rsvp_row['location_name'] ?? 'TBA'); ?>
                            | <?php echo date("M j, g:i A", strtotime($rsvp_row['event_date'])); ?>
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
            </div>
        <?php else: ?>
            <p style="color:#888;">You haven't RSVPed to any upcoming events. Hit <strong>RSVP</strong> on a game above to save your spot!</p>
        <?php endif; ?>
    </div>

</div>


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
        </div>

        <div style="display:flex;gap:10px;">
            <form id="modal-rsvp-form" action="checkin.php" method="POST" style="flex:1;">
                <input type="hidden" name="rsvp_event_id" id="modal-rsvp-event-id">
                <input type="hidden" name="rsvp_action"   id="modal-rsvp-action">
                <input type="hidden" name="league" value="<?php echo htmlspecialchars($league_filter); ?>">
                <button type="submit" id="modal-rsvp-btn" style="width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;"></button>
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

function filterByDate(dateStr) {
    document.querySelectorAll('.cal-day').forEach(el => {
        const selected = el.dataset.filter === dateStr;
        el.classList.toggle('active', selected);
        el.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    
    
    let visibleCount = 0;
    const cards = document.querySelectorAll('.game-card');
    
    cards.forEach(card => {
        if (dateStr === 'all' || card.getAttribute('data-ymd') === dateStr) {
            card.style.display = 'flex'; 
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
   
    document.getElementById('no-games-msg').style.display = (visibleCount === 0) ? 'block' : 'none';
    document.getElementById('results-count').textContent = visibleCount + (visibleCount === 1 ? ' game' : ' games');
}

const isGuestUser = <?php echo $is_guest ? 'true' : 'false'; ?>; 

function openGameModal(eventId) {
    const row = document.querySelector(`span[data-event-id="${eventId}"]`);
    if(!row) return;

    document.getElementById('modal-league').innerText = row.dataset.league;
    document.getElementById('modal-name').innerText   = row.dataset.name;
    document.getElementById('modal-date').innerText   = row.dataset.date;

    const venueLink = document.getElementById('modal-venue-link');
    venueLink.innerText = row.dataset.venue;
    venueLink.href = 'maps.php?venue=' + encodeURIComponent(row.dataset.venue);

    const rsvpBtn = document.getElementById('modal-rsvp-btn');
    document.getElementById('modal-rsvp-event-id').value = eventId;
    
    if (isGuestUser) {
        rsvpBtn.innerText = 'RSVP';
        rsvpBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:white;color:<?php echo $accent; ?>;border:2px solid <?php echo $accent; ?>;';
        rsvpBtn.onclick = function(e) { e.preventDefault(); closeGameModal(); requireLogin('RSVP to this game'); };
    } else {
        if (row.dataset.rsvped === 'true') {
            rsvpBtn.innerText = 'Cancel RSVP';
            rsvpBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:#fff0f0;color:#c0392b;border:2px solid #f5c6cb;';
            document.getElementById('modal-rsvp-action').value = 'remove';
        } else {
            rsvpBtn.innerText = 'RSVP';
            rsvpBtn.style.cssText = 'width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;background:white;color:<?php echo $accent; ?>;border:2px solid <?php echo $accent; ?>;';
            document.getElementById('modal-rsvp-action').value = 'add';
        }
    }

    const checkinBtn = document.getElementById('modal-checkin-btn');
    document.getElementById('modal-checkin-event-id').value = eventId;
    
    if (row.dataset.checkedin === 'true') {
        checkinBtn.innerText = '✓ Attended';
        checkinBtn.style.background = '#ccc';
        checkinBtn.disabled = true;
    } else {
        checkinBtn.innerText = 'Check In';
        checkinBtn.style.background = '<?php echo $accent; ?>';
        checkinBtn.disabled = false;
    }

    document.getElementById('gameModal').style.display = 'flex';
}

function closeGameModal() { document.getElementById('gameModal').style.display = 'none'; }

document.getElementById('gameModal').addEventListener('click', e => {
    if (e.target === document.getElementById('gameModal')) closeGameModal();
});

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
            () => document.getElementById('modal-checkin-form').submit()
        );
    } else document.getElementById('modal-checkin-form').submit();
}

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
            () => document.getElementById('form_' + eventId).submit()
        );
    } else document.getElementById('form_' + eventId).submit();
}


window.addEventListener('DOMContentLoaded', () => {
    if (navigator.geolocation) {
        document.getElementById('venue-card').style.display = 'block';
        document.getElementById('trending-card').style.display = 'block';
        
        navigator.geolocation.getCurrentPosition(pos => {
            fetch(`api_local_events.php?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}`)
                .then(res => res.json())
                .then(data => {
                    let vHtml = data.venues.map(v => 
                        `<div style="background:#f1f2f6;padding:8px 15px;border-radius:20px;border:1px solid #ddd;font-size:0.9em;display:flex;align-items:center;gap:8px;">
                            <strong>${v.name}</strong><span style="color:#666;">${parseFloat(v.distance).toFixed(1)} mi away</span>
                         </div>`
                    ).join('');
                    document.getElementById('venue-list').innerHTML = vHtml || '<div style="color:#888;">No venues found nearby.</div>';

                    let tHtml = data.trending.map((t, i) => 
                        `<div style="background:linear-gradient(145deg, #ffffff, #f8f9fa); border:1px solid #e2e8f0; border-radius:12px; padding:16px; flex:1; min-width:250px; position:relative; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
                            <div style="position:absolute; top:12px; right:12px; background:<?php echo $accent; ?>20; color:<?php echo $accent; ?>; font-weight:800; font-size:11px; padding:3px 8px; border-radius:12px;">#${i+1} Hot</div>
                            <strong style="font-size:0.95em; display:block; padding-right:45px; color:#1a1a2e;">${t.event_name}</strong>
                            <div style="margin-top:8px; font-size:0.8em; color:#64748b; display:flex; align-items:center; gap:5px;"><span>📍</span> ${t.location_name}</div>
                            <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid #edf2f7; padding-top:10px;">
                                <span style="font-size:0.75em; background:#f1f5f9; padding:2px 8px; border-radius:6px; font-weight:600; color:#475569;">${t.league}</span>
                                <span style="font-size:0.85em; color:green; font-weight:700;">⚡ ${t.total_attendance} checked in</span>
                            </div>
                        </div>`
                    ).join('');
                    document.getElementById('trending-list').innerHTML = tHtml || '<p style="color:#888; font-size:0.9em;">No trending events near you yet.</p>';
                });
        });
    }
});
</script>


<div style="display:none;">
<?php foreach($all_games as $r2): ?>
<span
    data-event-id="<?php echo $r2['event_id']; ?>"
    data-name="<?php echo htmlspecialchars($r2['event_name'] ?? 'TBA', ENT_QUOTES); ?>"
    data-venue="<?php echo htmlspecialchars($r2['location_name'] ?? 'TBA', ENT_QUOTES); ?>"
    data-date="<?php echo date('F j, Y · g:i A', strtotime($r2['event_date'])); ?>"
    data-league="<?php echo htmlspecialchars($r2['league'] ?? 'N/A', ENT_QUOTES); ?>"
    data-checkedin="<?php echo is_null($r2['checkin_id']) ? 'false' : 'true'; ?>"
    data-rsvped="<?php echo is_null($r2['rsvp_id']) ? 'false' : 'true'; ?>">
</span>
<?php endforeach; ?>
</div>

</body>
</html>
