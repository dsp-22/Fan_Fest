<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id      = $_SESSION['user_id'];
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $my_id;
$is_me      = ($profile_id == $my_id);
$error_msg  = "";

// check if we're friends
$is_friend = false;
if (!$is_me) {
    $cf = $conn->prepare("SELECT id FROM friends WHERE ((user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)) AND status = 'accepted'");
    $cf->bind_param("iiii", $my_id, $profile_id, $profile_id, $my_id);
    $cf->execute();
    $is_friend = $cf->get_result()->num_rows > 0;
} else {
    $is_friend = true;
}

// theme
$my_settings     = $conn->query("SELECT theme_color FROM users WHERE id = $my_id")->fetch_assoc();
$my_theme        = $my_settings['theme_color'] ?? 'red';
$theme_map       = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)',
];
$accent_map      = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$light_map       = ['blue' => '#e8f4fd', 'red' => '#fdeaea', 'gold' => '#fdf8ee', 'black' => '#f1f2f4'];
$active_gradient = $theme_map[$my_theme]  ?? $theme_map['red'];
$accent          = $accent_map[$my_theme] ?? '#990000';
$light           = $light_map[$my_theme]  ?? '#fdeaea';

// handle settings save
if ($is_me && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $u  = strtolower(trim($_POST['username']));
    $b  = trim($_POST['bio']);
    $s  = trim($_POST['seat_location']);
    $t  = trim($_POST['favorite_team']);
    $th = $_POST['theme_color'];
    $lp = isset($_POST['location_public']) ? 1 : 0;

    $pic_path = $_POST['current_pic'];
    if (!empty($_FILES['profile_photo']['name'])) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $ext      = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
        $pic_path = $target_dir . "user_" . $my_id . "_" . time() . "." . $ext;
        move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $pic_path);
    }

    // make sure username isn't already taken
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->bind_param("si", $u, $my_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error_msg = "That username is already taken.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, bio=?, seat_location=?, favorite_team=?, theme_color=?, location_public=?, profile_pic=? WHERE id=?");
        $stmt->bind_param("sssssisi", $u, $b, $s, $t, $th, $lp, $pic_path, $my_id);
        if ($stmt->execute()) { header("Location: profile.php"); exit(); }
    }
}

// user data
$user         = $conn->query("SELECT * FROM users WHERE id = $profile_id")->fetch_assoc();
$points       = $conn->query("SELECT COUNT(*) as total FROM checkins WHERE user_id = $profile_id")->fetch_assoc()['total'] ?? 0;
$friend_count = $conn->query("SELECT COUNT(*) as total FROM friends WHERE (user_id_1 = $profile_id OR user_id_2 = $profile_id) AND status = 'accepted'")->fetch_assoc()['total'] ?? 0;
$order_count  = $conn->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $profile_id")->fetch_assoc()['total'] ?? 0;

// rank = people with more points than me + 1
$rank_result = $conn->query("SELECT COUNT(*) as better FROM (SELECT user_id, COUNT(*) as cnt FROM checkins GROUP BY user_id HAVING cnt > $points) as sub")->fetch_assoc();
$rank        = ($rank_result['better'] ?? 0) + 1;

$leaderboard = $conn->query("SELECT u.first_name, u.id, COUNT(c.checkin_id) as pts FROM users u LEFT JOIN checkins c ON u.id = c.user_id GROUP BY u.id ORDER BY pts DESC LIMIT 5");

// milestone
$milestones        = [1 => 'Rookie', 3 => 'Regular', 5 => 'Super Fan', 10 => 'Hall of Fame'];
$current_milestone = 'Rookie';
foreach ($milestones as $req => $label) {
    if ($points >= $req) $current_milestone = $label;
}
$progress = min(($points / 10) * 100, 100);

// next rsvp for sidebar
$next_rsvp = null;
$nr = $conn->prepare("SELECT e.event_name, e.event_date, e.location_name FROM rsvps r JOIN events e ON e.event_id = r.event_id WHERE r.user_id = ? AND e.event_date >= NOW() ORDER BY e.event_date ASC LIMIT 1");
$nr->bind_param("i", $profile_id);
$nr->execute();
$nr_row = $nr->get_result()->fetch_assoc();
if ($nr_row) {
    $diff = (int)(new DateTime('today'))->diff(new DateTime((new DateTime($nr_row['event_date']))->format('Y-m-d')))->days;
    $nr_row['countdown'] = $diff === 0 ? 'Today' : ($diff === 1 ? 'Tomorrow' : "in $diff days");
    $next_rsvp = $nr_row;
}
$nr->close();

// pull all activity types then sort together
$activities = [];

$ci = $conn->prepare("SELECT 'checkin' as type, e.event_name as title, e.location_name as subtitle, c.checkin_time as ts FROM checkins c JOIN events e ON e.event_id = c.event_id WHERE c.user_id = ? ORDER BY c.checkin_time DESC LIMIT 6");
$ci->bind_param("i", $profile_id);
$ci->execute();
$ci_res = $ci->get_result();
while ($row = $ci_res->fetch_assoc()) $activities[] = $row;
$ci->close();

$ord = $conn->prepare("SELECT 'order' as type, CONCAT('Order #', order_id) as title, CONCAT('\$', total_price) as subtitle, order_date as ts FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 4");
$ord->bind_param("i", $profile_id);
$ord->execute();
$ord_res = $ord->get_result();
while ($row = $ord_res->fetch_assoc()) $activities[] = $row;
$ord->close();

$rv = $conn->prepare("SELECT 'rsvp' as type, e.event_name as title, e.location_name as subtitle, r.created_at as ts FROM rsvps r JOIN events e ON e.event_id = r.event_id WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 4");
$rv->bind_param("i", $profile_id);
$rv->execute();
$rv_res = $rv->get_result();
while ($row = $rv_res->fetch_assoc()) $activities[] = $row;
$rv->close();

$fr = $conn->prepare("SELECT 'friend' as type, CONCAT('Connected with ', u.first_name, ' ', u.last_name) as title, CONCAT('@', u.username) as subtitle, f.created_at as ts FROM friends f JOIN users u ON u.id = IF(f.user_id_1 = ?, f.user_id_2, f.user_id_1) WHERE (f.user_id_1 = ? OR f.user_id_2 = ?) AND f.status = 'accepted' ORDER BY f.created_at DESC LIMIT 3");
$fr->bind_param("iii", $profile_id, $profile_id, $profile_id);
$fr->execute();
$fr_res = $fr->get_result();
while ($row = $fr_res->fetch_assoc()) $activities[] = $row;
$fr->close();

usort($activities, fn($a, $b) => strtotime($b['ts']) - strtotime($a['ts']));

$type_meta = [
    'checkin' => ['icon' => '📍', 'bg' => $light,   'pts' => true],
    'order'   => ['icon' => '🌭', 'bg' => '#fff7ed', 'pts' => false],
    'rsvp'    => ['icon' => '🎟️', 'bg' => '#fdf4ff', 'pts' => false],
    'friend'  => ['icon' => '👥', 'bg' => '#f0fdf4', 'pts' => false],
];
?>
<?php include 'header.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #f4f6f9; color: #2d3436; margin: 0; }

    /* same hero as all other pages */
    .hero-banner {
        background: <?php echo $active_gradient; ?>;
        color: white;
        padding: 60px 20px 100px 20px;
        text-align: center;
        margin-bottom: -60px;
    }
    .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }
    .hero-banner p  { margin: 10px 0 0; color: rgba(255,255,255,0.7); font-size: 1em; }

    .main-container { width: 95%; max-width: 1100px; margin: 0 auto 80px auto; position: relative; z-index: 5; }

    /* top hud card */
    .hud {
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        padding: 24px 28px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 22px;
        flex-wrap: wrap;
    }
    .hud-avatar {
        width: 82px; height: 82px;
        border-radius: 50%;
        overflow: hidden;
        border: 4px solid white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.12);
        flex-shrink: 0;
        background: <?php echo $light; ?>;
        display: flex; align-items: center; justify-content: center;
        font-size: 28px; font-weight: 800; color: <?php echo $accent; ?>;
    }
    .hud-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .hud-info { flex: 1; min-width: 0; }
    .hud-name { font-size: 20px; font-weight: 800; color: #1a1a2e; margin-bottom: 6px; }
    .hud-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .h-handle { color: <?php echo $accent; ?>; font-weight: 700; font-size: 13px; }
    .h-pill { background: #f1f2f6; border-radius: 20px; padding: 3px 11px; font-size: 11px; font-weight: 700; color: #636e72; text-decoration: none; transition: background 0.15s; }
    .h-pill:hover { background: <?php echo $light; ?>; color: <?php echo $accent; ?>; }
    .h-ms { background: <?php echo $light; ?>; color: <?php echo $accent; ?>; border-radius: 20px; padding: 3px 11px; font-size: 11px; font-weight: 700; }
    .xp-track { background: #f1f2f6; height: 10px; border-radius: 6px; overflow: hidden; margin-bottom: 5px; }
    .xp-fill { height: 10px; background: <?php echo $accent; ?>; width: <?php echo $progress; ?>%; border-radius: 6px; transition: width 1s ease-out; }
    .xp-labels { display: flex; justify-content: space-between; font-size: 9px; font-weight: 700; color: #b2bec3; }
    .hud-btns { display: flex; gap: 10px; flex-shrink: 0; }
    .btn { padding: 10px 22px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .btn-settings { background: <?php echo $accent; ?>; color: white; }
    .btn-logout   { background: white; color: #d63031; border: 2px solid #ff7675; }
    .btn-logout:hover { background: #fff5f5; }

    /* clickable stat cards */
    .stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 20px; }
    .stat-card {
        background: white;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.05);
        text-decoration: none;
        color: inherit;
        display: block;
        border: 2px solid transparent;
        transition: all 0.2s;
    }
    .stat-card:hover {
        border-color: <?php echo $accent; ?>;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.09);
    }
    .stat-val { font-size: 24px; font-weight: 800; color: #2d3436; line-height: 1; margin-bottom: 4px; }
    .stat-val small { font-size: 12px; font-weight: 600; color: <?php echo $accent; ?>; margin-left: 2px; }
    .stat-lbl { font-size: 11px; color: #636e72; }
    .stat-hint { font-size: 10px; color: #b2bec3; margin-top: 3px; }

    /* sidebar + tab panel same height */
    .profile-grid {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 20px;
        align-items: start;
    }

    /* about me — always fits its content, no scroll needed */
    .about-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .about-top { padding: 20px 22px 16px; border-bottom: 1px solid #f1f2f6; }
    .about-top h3 { font-size: 14px; font-weight: 800; color: #1a1a2e; margin-bottom: 10px; }
    .bio-text { font-size: 13px; color: #636e72; font-style: italic; line-height: 1.6; }
    .info-item { display: flex; flex-direction: column; gap: 4px; padding: 14px 22px; border-bottom: 1px solid #f8f9fa; }
    .info-item:last-child { border-bottom: none; }
    .info-lbl { font-size: 10px; font-weight: 800; color: #b2bec3; letter-spacing: 0.07em; text-transform: uppercase; }
    .info-val { font-size: 14px; font-weight: 600; color: #2d3436; }
    .info-val.empty { color: #b2bec3; font-weight: 400; font-style: italic; font-size: 13px; }
    .info-val.green { color: #22c55e; }
    .info-val a { color: <?php echo $accent; ?>; font-size: 12px; font-weight: 600; text-decoration: none; margin-left: 6px; }

    /* tab card — matches sidebar height via JS, activity scrolls inside */
    .tab-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .tab-bar { display: flex; border-bottom: 1px solid #f1f2f6; flex-shrink: 0; }
    .tab-btn {
        flex: 1; padding: 15px; text-align: center;
        font-size: 12px; font-weight: 700; color: #b2bec3;
        cursor: pointer; border-bottom: 2px solid transparent;
        transition: all 0.15s; background: none;
        border-top: none; border-left: none; border-right: none;
    }
    .tab-btn.active { color: <?php echo $accent; ?>; border-bottom-color: <?php echo $accent; ?>; }

    /* scrollable feed — height set by JS to match sidebar */
    .tab-panel { display: none; overflow-y: auto; }
    .tab-panel.active { display: block; }
    .tab-panel-inner { padding: 8px 20px 16px; }

    /* activity items */
    .activity { display: flex; gap: 14px; padding: 13px 0; border-bottom: 1px solid #f8f9fa; align-items: flex-start; }
    .activity:last-child { border-bottom: none; }
    .act-icon { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
    .act-title { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 3px; }
    .act-sub   { font-size: 11px; color: #b2bec3; line-height: 1.4; }
    .act-pts   { color: #22c55e; font-weight: 700; }
    .act-empty { text-align: center; padding: 40px 0; color: #b2bec3; font-size: 13px; }

    /* leaderboard */
    .lb-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f8f9fa; font-size: 13px; }
    .lb-row:last-child { border-bottom: none; }
    .lb-row.me { background: <?php echo $light; ?>; margin: 0 -8px; padding: 12px 8px; border-radius: 8px; border-bottom: none; }
    .lb-pts { font-weight: 800; color: <?php echo $accent; ?>; }

    /* settings modal */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); z-index: 1000; justify-content: center; align-items: center; }
    .modal.open { display: flex; }
    .modal-box { background: white; border-radius: 20px; padding: 28px; width: 460px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
    .modal-box h2 { margin: 0 0 20px; font-size: 18px; font-weight: 800; color: #1a1a2e; }
    .f-label { display: block; font-size: 10px; font-weight: 800; color: #b2bec3; letter-spacing: 0.07em; text-transform: uppercase; margin-bottom: 5px; }
    .f-input { width: 100%; padding: 10px 14px; border: 1.5px solid #dfe6e9; border-radius: 8px; margin-bottom: 14px; font-size: 13px; font-family: inherit; box-sizing: border-box; transition: border-color 0.2s; }
    .f-input:focus { outline: none; border-color: <?php echo $accent; ?>; }
    .f-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .photo-upload { border: 2px dashed #dfe6e9; border-radius: 10px; padding: 16px; text-align: center; margin-bottom: 14px; cursor: pointer; transition: border-color 0.2s; }
    .photo-upload:hover { border-color: <?php echo $accent; ?>; }
    .photo-upload span { font-size: 12px; color: #b2bec3; }
    .toggle-row { display: flex; align-items: center; justify-content: space-between; background: #f8f9fa; border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; }
    .toggle-info strong { display: block; font-size: 13px; font-weight: 600; color: #2d3436; margin-bottom: 2px; }
    .toggle-info span { font-size: 11px; color: #b2bec3; }
    .toggle-cb { display: none; }
    .toggle-track { width: 38px; height: 22px; background: #dfe6e9; border-radius: 11px; display: block; cursor: pointer; transition: background 0.2s; position: relative; }
    .toggle-cb:checked + .toggle-track { background: <?php echo $accent; ?>; }
    .toggle-track::after { content: ''; position: absolute; width: 18px; height: 18px; background: white; border-radius: 50%; top: 2px; left: 2px; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
    .toggle-cb:checked + .toggle-track::after { transform: translateX(16px); }
    .btn-save   { width: 100%; background: <?php echo $accent; ?>; color: white; border: none; border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 700; cursor: pointer; margin-bottom: 8px; }
    .btn-cancel { width: 100%; background: #f1f2f6; color: #636e72; border: none; border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .activity:hover { background: #f8f9fa; border-radius: 8px; }

    @media (max-width: 900px) {
        .profile-grid { grid-template-columns: 1fr; }
        .stats-row    { grid-template-columns: repeat(2,1fr); }
        .tab-panel    { max-height: 400px !important; }
    }
    @media (max-width: 560px) {
        .stats-row { grid-template-columns: repeat(2,1fr); gap: 10px; }
        .hud-btns  { width: 100%; }
        .btn       { flex: 1; }
    }
</style>

<div class="hero-banner">
    <h1><?php echo $is_me ? "Welcome, " . htmlspecialchars($user['first_name']) . "!" : htmlspecialchars($user['first_name']) . "'s Profile"; ?></h1>
    <p><?php echo $is_me ? "Your Stadium Command Center" : "Fan Identity"; ?></p>
</div>

<div class="main-container">

    <div class="hud">
        <div class="hud-avatar">
            <?php if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default_avatar.png'): ?>
                <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile">
            <?php else: ?>
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="hud-info">
            <div class="hud-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
            <div class="hud-meta">
                <span class="h-handle">@<?php echo htmlspecialchars($user['username'] ?? 'fan'); ?></span>
                <a href="friends.php" class="h-pill"><?php echo $friend_count; ?> Friends</a>
                <span class="h-ms">⭐ <?php echo $current_milestone; ?></span>
            </div>
            <div class="xp-track"><div class="xp-fill"></div></div>
            <div class="xp-labels">
                <span>Rookie</span>
                <span>Super Fan (5)</span>
                <span>Hall of Fame (10)<?php echo $points < 10 ? ' · ' . (10 - $points) . ' pts away' : ' ✓'; ?></span>
            </div>
        </div>
        <?php if ($is_me): ?>
        <div class="hud-btns">
            <button class="btn btn-settings" onclick="document.getElementById('settingsModal').classList.add('open')">Settings</button>
            <a href="logout.php" class="btn btn-logout">Logout</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- stat cards — each links somewhere useful -->
    <div class="stats-row">
        <a href="#leaderboard-tab" class="stat-card" onclick="openTab('leaderboard'); return false;">
            <div class="stat-val"><?php echo $points; ?> <small>pts</small></div>
            <div class="stat-lbl">Rank #<?php echo $rank; ?> overall</div>
            <div class="stat-hint">View leaderboard →</div>
        </a>
        <a href="friends.php" class="stat-card">
            <div class="stat-val"><?php echo $friend_count; ?></div>
            <div class="stat-lbl">Friends connected</div>
            <div class="stat-hint">Go to Friends →</div>
        </a>
        <a href="checkin.php" class="stat-card">
            <div class="stat-val"><?php echo $points; ?></div>
            <div class="stat-lbl">Games attended</div>
            <div class="stat-hint">Check-in history →</div>
        </a>
        <a href="past_orders.php" class="stat-card">
            <div class="stat-val"><?php echo $order_count; ?></div>
            <div class="stat-lbl">Orders placed</div>
            <div class="stat-hint">Order history →</div>
        </a>
    </div>

    <div class="profile-grid">

        <!-- about me sidebar -->
        <div class="about-card" id="aboutCard">
            <div class="about-top">
                <h3>About Me</h3>
                <div class="bio-text">"<?php echo htmlspecialchars($user['bio'] ?? 'FanFest member'); ?>"</div>
            </div>
            <div class="info-item">
                <div class="info-lbl">Favorite Team</div>
                <div class="info-val"><?php echo htmlspecialchars($user['favorite_team'] ?? 'Not set'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-lbl">Current Seat</div>
                <?php if (!empty($user['seat_location'])): ?>
                    <div class="info-val"><?php echo htmlspecialchars($user['seat_location']); ?></div>
                <?php else: ?>
                    <div class="info-val empty">
                        Not set
                        <?php if ($is_me): ?>
                            <a href="#" onclick="document.getElementById('settingsModal').classList.add('open'); return false;">Add in settings →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="info-item">
                <div class="info-lbl">Location Sharing</div>
                <?php if ($user['location_public']): ?>
                    <div class="info-val green">🟢 Visible to friends</div>
                <?php else: ?>
                    <div class="info-val" style="color:#b2bec3;">🔒 Private</div>
                <?php endif; ?>
            </div>
            <?php if ($next_rsvp): ?>
            <div class="info-item">
                <div class="info-lbl">Next RSVP</div>
                <div class="info-val">
                    <?php echo htmlspecialchars($next_rsvp['event_name']); ?>
                    <span style="color:#b2bec3;font-size:12px;font-weight:400;"> · <?php echo $next_rsvp['countdown']; ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- tabbed panel — scrollable feed matches sidebar height -->
        <div class="tab-card" id="tabCard">
            <div class="tab-bar">
                <button class="tab-btn active" onclick="switchTab(this,'activity')">Recent Activity</button>
                <button class="tab-btn" id="leaderboard-tab" onclick="switchTab(this,'leaderboard')">🏆 Leaderboard</button>
            </div>

            <div class="tab-panel active" id="activity">
                <div class="tab-panel-inner">
                    <?php if (empty($activities)): ?>
                        <div class="act-empty">No activity yet — start checking in to games!</div>
                    <?php else: ?>
                        <?php foreach ($activities as $a):
                            $meta = $type_meta[$a['type']] ?? ['icon' => '•', 'bg' => '#f1f2f6', 'pts' => false];
                        ?>
                        <?php
                            $act_links = [
                                'checkin' => 'checkin.php',
                                'order'   => 'past_orders.php',
                                'rsvp'    => 'checkin.php',
                                'friend'  => 'friends.php',
                            ];
                            $act_href = $act_links[$a['type']] ?? '#';
                            ?>
                            <a href="<?php echo $act_href; ?>" class="activity" style="text-decoration:none; color:inherit;">
                                <div class="act-icon" style="background:<?php echo $meta['bg']; ?>;"><?php echo $meta['icon']; ?></div>
                                <div>
                                    <div class="act-title"><?php echo htmlspecialchars($a['title']); ?></div>
                                    <div class="act-sub">
                                        <?php echo htmlspecialchars($a['subtitle'] ?? ''); ?>
                                        · <?php echo date("M j, Y", strtotime($a['ts'])); ?>
                                        <?php if ($meta['pts']): ?><span class="act-pts"> · +1 pt</span><?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-panel" id="leaderboard">
                <div class="tab-panel-inner">
                    <?php
                    $lb_rank = 1;
                    $medals  = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                    while ($row = $leaderboard->fetch_assoc()):
                        $is_you = ($row['id'] == $profile_id);
                    ?>
                    <div class="lb-row <?php echo $is_you ? 'me' : ''; ?>">
                        <span>
                            <?php echo $medals[$lb_rank] ?? '🔥'; ?>
                            <?php echo htmlspecialchars($row['first_name']); ?>
                            <?php if ($is_you) echo '<strong>(you)</strong>'; ?>
                        </span>
                        <span class="lb-pts"><?php echo $row['pts']; ?> pts</span>
                    </div>
                    <?php $lb_rank++; endwhile; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php if ($is_me): ?>
<div class="modal" id="settingsModal">
    <div class="modal-box">
        <h2>Fan Settings</h2>
        <?php if ($error_msg): ?>
            <div style="background:#fdeaea;color:#c0392b;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="current_pic" value="<?php echo htmlspecialchars($user['profile_pic'] ?? ''); ?>">

            <label class="f-label">Profile Photo</label>
            <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
                <div style="font-size:26px;margin-bottom:5px;">📷</div>
                <span>Click to upload a new photo</span>
                <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;">
            </div>

            <label class="f-label">Username</label>
            <input type="text" name="username" class="f-input" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>

            <label class="f-label">Bio</label>
            <textarea name="bio" class="f-input" style="height:64px;resize:none;"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>

            <label class="f-label">Current Seat</label>
            <input type="text" name="seat_location" class="f-input" placeholder="e.g. Section 12, Row C" value="<?php echo htmlspecialchars($user['seat_location'] ?? ''); ?>">

            <div class="f-row">
                <div>
                    <label class="f-label">League</label>
                    <select id="leagueSelect" onchange="fetchTeams()" class="f-input">
                        <option value="">Select...</option>
                        <option value="nba">NBA</option>
                        <option value="nfl">NFL</option>
                        <option value="college-football">NCAAF</option>
                        <option value="mens-college-basketball">NCAAB</option>
                    </select>
                </div>
                <div>
                    <label class="f-label">Fave Team</label>
                    <select name="favorite_team" id="teamSelect" class="f-input">
                        <option value="<?php echo htmlspecialchars($user['favorite_team'] ?? ''); ?>">
                            <?php echo htmlspecialchars($user['favorite_team'] ?? 'Pick a league first'); ?>
                        </option>
                    </select>
                </div>
            </div>

            <label class="f-label">Theme</label>
            <select name="theme_color" class="f-input">
                <option value="blue"  <?php echo $my_theme === 'blue'  ? 'selected' : ''; ?>>Modern Blue</option>
                <option value="red"   <?php echo $my_theme === 'red'   ? 'selected' : ''; ?>>IU Crimson</option>
                <option value="gold"  <?php echo $my_theme === 'gold'  ? 'selected' : ''; ?>>Purdue Gold</option>
                <option value="black" <?php echo $my_theme === 'black' ? 'selected' : ''; ?>>Blackout</option>
            </select>

            <div class="toggle-row">
                <div class="toggle-info">
                    <strong>Share location with friends</strong>
                    <span>Friends can see your seat when you're checked in</span>
                </div>
                <div>
                    <input type="checkbox" name="location_public" id="locToggle" class="toggle-cb" <?php echo ($user['location_public'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="locToggle" class="toggle-track"></label>
                </div>
            </div>

            <button type="submit" class="btn-save">Save Changes</button>
            <button type="button" class="btn-cancel" onclick="document.getElementById('settingsModal').classList.remove('open')">Cancel</button>
        </form>
    </div>
</div>

<script>
// match tab card height to the about me sidebar so they line up
function matchHeight() {
    const about  = document.getElementById('aboutCard');
    const panels = document.querySelectorAll('.tab-panel');
    const tabBar = document.querySelector('.tab-bar');
    if (!about || !tabBar) return;

    const targetH = about.offsetHeight - tabBar.offsetHeight;
    panels.forEach(p => {
        p.style.maxHeight = Math.max(targetH, 300) + 'px';
    });
}

window.addEventListener('load', matchHeight);
window.addEventListener('resize', matchHeight);

function switchTab(el, target) {
    document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById(target).classList.add('active');
}

// lets the stat card link open the leaderboard tab
function openTab(target) {
    const btn = document.querySelector(`[onclick="switchTab(this,'${target}')"]`);
    if (btn) btn.click();
    document.getElementById('tabCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function fetchTeams() {
    const league = document.getElementById('leagueSelect').value;
    const ts = document.getElementById('teamSelect');
    if (!league) return;
    ts.innerHTML = '<option>Loading...</option>';
    try {
        const sport = (league === 'nba' || league === 'mens-college-basketball') ? 'basketball' : 'football';
        const res   = await fetch(`https://site.api.espn.com/apis/site/v2/sports/${sport}/${league}/teams?limit=200`);
        const data  = await res.json();
        const teams = data.sports[0].leagues[0].teams;
        ts.innerHTML = '<option value="">Select team...</option>';
        teams.sort((a, b) => a.team.displayName.localeCompare(b.team.displayName));
        teams.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.team.displayName;
            opt.text  = t.team.displayName;
            ts.add(opt);
        });
    } catch (e) {
        ts.innerHTML = '<option>Error loading teams</option>';
    }
}

// close modal on outside click
document.getElementById('settingsModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>