<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// --- GUEST LOGIC HOOK ---
$is_guest = ($_SESSION['user_id'] === 'guest');
$u_id = $is_guest ? 0 : $_SESSION['user_id'];

if ($is_guest) {
    $theme_data = [
        'first_name' => 'Guest Fan', 
        'theme_color' => 'blue'
    ];
} else {
    $theme_data = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $u_id")->fetch_assoc();
}
// ------------------------

$f_name     = $theme_data['first_name'] ?? "Fan";
$user_theme = $theme_data['theme_color'] ?? 'blue';

$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)',
];
$accent_map = [
    'blue'  => '#0984e3',
    'red'   => '#990000',
    'gold'  => '#ceb888',
    'black' => '#2d3436',
];
$light_map = [
    'blue'  => '#e8f4fd',
    'red'   => '#fdeaea',
    'gold'  => '#fdf8ee',
    'black' => '#f1f2f4',
];

$active_gradient = $theme_map[$user_theme]  ?? $theme_map['blue'];
$accent          = $accent_map[$user_theme] ?? '#0984e3';
$light           = $light_map[$user_theme]  ?? '#e8f4fd';

// journey step
$stmt = $conn->prepare("
    SELECT r.rsvp_id FROM rsvps r
    JOIN events e ON e.event_id = r.event_id
    WHERE r.user_id = ? AND DATE(e.event_date) >= CURDATE()
    LIMIT 1
");
$stmt->bind_param("i", $u_id);
$stmt->execute();
$has_rsvp = $stmt->get_result()->num_rows > 0;
$stmt->close();

$current_step = 1;

if ($has_rsvp) {
    $current_step = 2;

    $stmt = $conn->prepare("SELECT checkin_id FROM checkins WHERE user_id = ? AND DATE(checkin_time) = CURDATE() LIMIT 1");
    $stmt->bind_param("i", $u_id);
    $stmt->execute();
    $has_checkin_today = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($has_checkin_today) {
        $current_step = 3;

       $stmt = $conn->prepare("SELECT order_id FROM orders WHERE user_id = ? AND DATE(created_at) = CURDATE() LIMIT 1");
        $stmt->bind_param("i", $u_id);
        $stmt->execute();
        $has_order_today = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($has_order_today) {
            $current_step = 4;
        }
    }
}

$next_steps = [
    1 => ['href' => 'checkin.php',     'icon' => '🎟️', 'title' => 'RSVP to an upcoming game',   'sub' => "Let your friends know you're going and lock in your spot.",      'badge' => 'Start here'],
    2 => ['href' => 'checkin.php',     'icon' => '📍',  'title' => 'Check in once you arrive',    'sub' => 'Verify your location to earn badges and climb the leaderboard.', 'badge' => "You're going!"],
    3 => ['href' => 'concessions.php', 'icon' => '🌭',  'title' => 'Order food to your seat',     'sub' => 'Skip the concession line — food comes straight to you.',         'badge' => "You're in!"],
    4 => ['href' => 'lightshow.php',   'icon' => '✨',  'title' => 'Sync up for the light show',  'sub' => 'Join the crowd and light up the stadium together.',              'badge' => 'Game time!'],
];
$next = $next_steps[$current_step];

// total check-ins = points
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM checkins WHERE user_id = ?");
$stmt->bind_param("i", $u_id);
$stmt->execute();
$checkin_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// friend count
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM friends WHERE (user_id_1 = ? OR user_id_2 = ?) AND status = 'accepted'");
$stmt->bind_param("ii", $u_id, $u_id);
$stmt->execute();
$friend_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// leaderboard rank — count users with more check-ins than me
$stmt = $conn->prepare("SELECT COUNT(*) AS better FROM (SELECT user_id, COUNT(*) AS cnt FROM checkins GROUP BY user_id HAVING cnt > ?) AS sub");
$stmt->bind_param("i", $checkin_count);
$stmt->execute();
$rank = ($stmt->get_result()->fetch_assoc()['better'] ?? 0) + 1;
$stmt->close();


// next upcoming RSVP
$next_rsvp = null;
$stmt = $conn->prepare("
    SELECT e.event_name, e.event_date, e.location_name
    FROM rsvps r
    JOIN events e ON e.event_id = r.event_id
    WHERE r.user_id = ? AND e.event_date >= NOW()
    ORDER BY e.event_date ASC
    LIMIT 1
");
$stmt->bind_param("i", $u_id);
$stmt->execute();
$rsvp_row = $stmt->get_result()->fetch_assoc();
if ($rsvp_row) {
    $next_rsvp = $rsvp_row;
    $diff = (int)(new DateTime('today'))->diff(new DateTime((new DateTime($rsvp_row['event_date']))->format('Y-m-d')))->days;
    if ($diff === 0)      $next_rsvp['countdown'] = 'Today';
    elseif ($diff === 1)  $next_rsvp['countdown'] = 'Tomorrow';
    else                  $next_rsvp['countdown']  = "in $diff days";
}
$stmt->close();


// friends RSVPed to my next upcoming game
$friends_today = [];
if ($next_rsvp) {
    $stmt = $conn->prepare("
        SELECT DISTINCT u.first_name, u.last_name, u.profile_pic
        FROM friends f
        JOIN users u ON u.id = IF(f.user_id_1 = ?, f.user_id_2, f.user_id_1)
        JOIN rsvps r ON r.user_id = u.id
        JOIN events e ON e.event_id = r.event_id
        WHERE (f.user_id_1 = ? OR f.user_id_2 = ?)
          AND f.status = 'accepted'
          AND e.event_name = ?
        LIMIT 4
    ");
    $stmt->bind_param("iiis", $u_id, $u_id, $u_id, $next_rsvp['event_name']);
    $stmt->execute();
    $ft_result = $stmt->get_result();
    while ($row = $ft_result->fetch_assoc()) {
        $friends_today[] = $row;
    }
    $stmt->close();
}


// milestone label
$milestones = [1 => 'Rookie', 3 => 'Regular', 5 => 'Super Fan', 10 => 'Hall of Fame'];
$current_milestone = 'Rookie';
foreach ($milestones as $req => $label) {
    if ($checkin_count >= $req) $current_milestone = $label;
}

function step_class(int $step, int $current): string {
    if ($step < $current)   return 'done';
    if ($step === $current) return 'active';
    return 'pending';
}

// initials helper
function initials(string $first, string $last): string {
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}

$avatar_colors = [
    ['bg' => '#dbeafe', 'fg' => '#1e40af'],
    ['bg' => '#fce7f3', 'fg' => '#9d174d'],
    ['bg' => '#d1fae5', 'fg' => '#065f46'],
    ['bg' => '#fef3c7', 'fg' => '#92400e'],
];
?>
<?php include 'header.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #f4f6f9; color: #2d3436; margin: 0; }

    /* hero — same as every other page */
    .hero-banner {
        background: <?php echo $active_gradient; ?>;
        color: white;
        padding: 60px 20px 115px 20px;
        text-align: center;
        margin-bottom: -60px;
    }
    .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }
    .hero-banner p  { margin: 10px 0 0; color: rgba(255,255,255,0.7); font-size: 1em; }

    .main-container {
        width: 95%;
        max-width: 1100px;
        margin: 0 auto 120px auto;
    }

    .section-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: #b2bec3;
        margin: 0 0 12px 2px;
    }

    /* stepper card */
    .stepper-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.07);
        padding: 24px 32px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
    }
    .ff-step { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 1; }
    .ff-step-circle {
        width: 36px; height: 36px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 700;
        background: #f0f0f0; color: #bbb;
        border: 2px solid #e0e0e0;
    }
    .ff-step.done    .ff-step-circle { background: #4ade80; border-color: #4ade80; color: #14532d; font-size: 16px; }
    .ff-step.active  .ff-step-circle { background: <?php echo $accent; ?>; border-color: <?php echo $accent; ?>; color: #fff; box-shadow: 0 0 0 4px <?php echo $light; ?>; }
    .ff-step-label { font-size: 10px; font-weight: 600; color: #b2bec3; white-space: nowrap; }
    .ff-step.done   .ff-step-label { color: #4ade80; }
    .ff-step.active .ff-step-label { color: <?php echo $accent; ?>; }
    .ff-step-line { flex: 1; height: 2px; background: #e0e0e0; margin-bottom: 22px; }
    .ff-step-line.done { background: #4ade80; }

    /* next step card */
    .ff-next-card {
        background: white;
        border-radius: 16px;
        padding: 20px 24px;
        display: flex; align-items: center; gap: 18px;
        text-decoration: none; color: inherit;
        box-shadow: 0 8px 24px rgba(0,0,0,0.07);
        border: 2px solid <?php echo $accent; ?>;
        margin-bottom: 20px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .ff-next-card:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(0,0,0,0.12); color: inherit; text-decoration: none; }
    .ff-next-icon {
        width: 52px; height: 52px;
        border-radius: 13px;
        background: <?php echo $light; ?>;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; flex-shrink: 0;
    }
    .ff-next-text { flex: 1; min-width: 0; }
    .ff-next-text strong { display: block; font-size: 1.05em; font-weight: 800; color: #2d3436; margin-bottom: 3px; }
    .ff-next-text span   { font-size: 0.88em; color: #636e72; }
    .ff-next-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .ff-next-badge { background: <?php echo $accent; ?>; color: #fff; font-size: 11px; font-weight: 700; padding: 5px 13px; border-radius: 20px; white-space: nowrap; }
    .ff-next-arrow { font-size: 22px; color: <?php echo $accent; ?>; line-height: 1; }

    /* stats row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 16px;
    }
    .stat-card {
        background: white;
        border-radius: 14px;
        padding: 18px 20px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.05);
    }
    .stat-val {
        font-size: 26px;
        font-weight: 800;
        color: #2d3436;
        line-height: 1;
        margin-bottom: 4px;
    }
    .stat-val small {
        font-size: 13px;
        font-weight: 600;
        color: <?php echo $accent; ?>;
        margin-left: 4px;
    }
    .stat-lbl { font-size: 12px; color: #636e72; }

    /* friends at game strip */
    .friends-strip {
        background: white;
        border-radius: 16px;
        padding: 18px 24px;
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 4px 16px rgba(0,0,0,0.05);
        margin-bottom: 16px;
        gap: 16px;
    }
    .friends-strip-left { display: flex; align-items: center; gap: 12px; }
    .avatar-stack { display: flex; }
    .av {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
        margin-left: -8px;
        border: 2px solid white;
    }
    .av:first-child { margin-left: 0; }
    .friends-strip-text strong { font-size: 14px; color: #2d3436; display: block; margin-bottom: 2px; }
    .friends-strip-text span   { font-size: 12px; color: #636e72; }
    .btn-find {
        background: <?php echo $accent; ?>;
        color: white;
        border: none;
        border-radius: 20px;
        padding: 8px 18px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        transition: opacity 0.2s;
    }
    .btn-find:hover { opacity: 0.85; color: white; text-decoration: none; }

    /* rsvp banner */
    .rsvp-banner {
        background: <?php echo $active_gradient; ?>;
        border-radius: 16px;
        padding: 18px 24px;
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 20px;
        gap: 16px;
        text-decoration: none;
        transition: opacity 0.2s;
    }
    .rsvp-banner:hover { opacity: 0.92; }
    .rsvp-banner strong { color: white; font-size: 15px; font-weight: 800; display: block; margin-bottom: 3px; }
    .rsvp-banner span   { color: rgba(255,255,255,0.72); font-size: 12px; }
    .rsvp-countdown {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
        border-radius: 20px;
        padding: 6px 16px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* explore grid — reordered to match journey */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
        margin-bottom: 30px;
    }
    .card-link { text-decoration: none; color: inherit; display: block; height: 100%; }
    .action-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        padding: 30px 20px;
        text-align: center;
        height: 100%;
        box-sizing: border-box;
        display: flex; flex-direction: column; align-items: center;
        border: 2px solid transparent;
        transition: all 0.2s ease-in-out;
    }
    .card-link:hover .action-card {
        transform: translateY(-5px);
        box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        border-color: <?php echo $accent; ?>;
    }
    .icon-wrapper {
        font-size: 2.2em;
        background-color: <?php echo $light; ?>;
        width: 70px; height: 70px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 15px;
    }
    .action-card h3 { margin: 0 0 10px; font-size: 1.2em; color: #2d3436; }
    .action-card p  { margin: 0; color: #636e72; font-size: 0.9em; line-height: 1.4; }

    /* status bar */
    .status-card {
        background: white;
        border-radius: 12px;
        padding: 20px 25px;
        display: flex; align-items: center; gap: 14px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        border-left: 6px solid #4ade80;
    }
    .status-dot {
        width: 9px; height: 9px;
        background: #4ade80; border-radius: 50%; flex-shrink: 0;
        animation: blink 2s ease-in-out infinite;
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }
    .status-card h3 { margin: 0 0 3px; font-size: 1.1em; color: #2d3436; }
    .status-card p  { margin: 0; color: #636e72; font-size: 0.95em; }

    @media (max-width: 900px) {
        .dashboard-grid { grid-template-columns: repeat(2,1fr); }
        .stats-row { grid-template-columns: repeat(3,1fr); }
    }
    @media (max-width: 600px) {
        .dashboard-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
        .stats-row { grid-template-columns: repeat(3,1fr); gap: 10px; }
        .ff-next-badge { display: none; }
        .ff-step-label { font-size: 9px; }
        .stepper-card { padding: 18px 14px; }
    }
</style>

<div class="hero-banner">
    <h1>Hello, <?php echo htmlspecialchars($f_name); ?>!</h1>
    <p>Welcome to FanFest, your Stadium Command Center</p>
</div>

<div class="main-container">

    <div class="stepper-card">
        <div class="ff-step <?php echo step_class(1, $current_step); ?>">
            <div class="ff-step-circle"><?php echo $current_step > 1 ? '✓' : '1'; ?></div>
            <div class="ff-step-label">RSVP</div>
        </div>
        <div class="ff-step-line <?php echo $current_step > 1 ? 'done' : ''; ?>"></div>

        <div class="ff-step <?php echo step_class(2, $current_step); ?>">
            <div class="ff-step-circle"><?php echo $current_step > 2 ? '✓' : '2'; ?></div>
            <div class="ff-step-label">Check In</div>
        </div>
        <div class="ff-step-line <?php echo $current_step > 2 ? 'done' : ''; ?>"></div>

        <div class="ff-step <?php echo step_class(3, $current_step); ?>">
            <div class="ff-step-circle"><?php echo $current_step > 3 ? '✓' : '3'; ?></div>
            <div class="ff-step-label">Order Food</div>
        </div>
        <div class="ff-step-line <?php echo $current_step > 3 ? 'done' : ''; ?>"></div>

        <div class="ff-step <?php echo step_class(4, $current_step); ?>">
            <div class="ff-step-circle">4</div>
            <div class="ff-step-label">Light Show</div>
        </div>
    </div>

    <p class="section-label">Your next step</p>
    <a href="<?php echo $next['href']; ?>" class="ff-next-card">
        <div class="ff-next-icon"><?php echo $next['icon']; ?></div>
        <div class="ff-next-text">
            <strong><?php echo htmlspecialchars($next['title']); ?></strong>
            <span><?php echo htmlspecialchars($next['sub']); ?></span>
        </div>
        <div class="ff-next-right">
            <div class="ff-next-badge"><?php echo htmlspecialchars($next['badge']); ?></div>
            <div class="ff-next-arrow">›</div>
        </div>
    </a>

    <p class="section-label">Your stats</p>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-val"><?php echo $checkin_count; ?> <small>pts</small></div>
            <div class="stat-lbl"><?php echo htmlspecialchars($current_milestone); ?> · rank #<?php echo $rank; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?php echo $friend_count; ?></div>
            <div class="stat-lbl">Friends connected</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?php echo $checkin_count; ?></div>
            <div class="stat-lbl">Games attended</div>
        </div>
    </div>

    <?php if (!empty($friends_today)): ?>
        <?php
        $names = array_map(fn($f) => htmlspecialchars($f['first_name']), $friends_today);
        $count = count($names);
        if ($count === 1)      $name_str = $names[0];
        elseif ($count === 2)  $name_str = $names[0] . ' and ' . $names[1];
        else                   $name_str = $names[0] . ', ' . $names[1] . ' and ' . ($count - 2) . ' more';
        ?>
        <div class="friends-strip">
            <div class="friends-strip-left">
                <div class="avatar-stack">
                    <?php foreach (array_slice($friends_today, 0, 3) as $i => $f):
                        $c = $avatar_colors[$i % count($avatar_colors)];
                    ?>
                        <?php
                            $has_pic = !empty($f['profile_pic']) && $f['profile_pic'] !== 'default_avatar.png' && file_exists($f['profile_pic']);
                            ?>
                            <div class="av" style="background:<?php echo $c['bg']; ?>; color:<?php echo $c['fg']; ?>; overflow:hidden; padding:0;">
                                <?php if ($has_pic): ?>
                                    <img src="<?php echo htmlspecialchars($f['profile_pic']); ?>" style="width:100%; height:100%; object-fit:cover;" alt="">
                                <?php else: ?>
                                    <?php echo initials($f['first_name'], $f['last_name']); ?>
                                <?php endif; ?>
                            </div>
                    <?php endforeach; ?>
                </div>
                <div class="friends-strip-text">
                    <strong><?php echo $count; ?> friend<?php echo $count !== 1 ? 's' : ''; ?> going to the game</strong>
                    <span><?php echo $name_str; ?> <?php echo $count === 1 ? 'is' : 'are'; ?> checked in</span>
                </div>
            </div>
            <a href="friends.php" class="btn-find">Find them</a>
        </div>
    <?php endif; ?>

    <?php if ($next_rsvp): ?>
        <a href="checkin.php" class="rsvp-banner">
            <div>
                <strong><?php echo htmlspecialchars($next_rsvp['event_name']); ?></strong>
                <span><?php echo htmlspecialchars($next_rsvp['location_name']); ?> &middot; <?php echo date("M j, g:i A", strtotime($next_rsvp['event_date'])); ?> &middot; You're going!</span>
            </div>
            <div class="rsvp-countdown"><?php echo $next_rsvp['countdown']; ?></div>
        </a>
    <?php endif; ?>

    <p class="section-label">Explore</p>
    <div class="dashboard-grid">
        <a href="checkin.php" class="card-link">
            <div class="action-card">
                <div class="icon-wrapper">🏆</div>
                <h3>Check In</h3>
                <p>Verify your location and earn attendance badges.</p>
            </div>
        </a>
        <a href="friends.php" class="card-link">
            <div class="action-card">
                <div class="icon-wrapper">📍</div>
                <h3>Find Friends</h3>
                <p>See who is at the stadium and connect.</p>
            </div>
        </a>
        <a href="concessions.php" class="card-link">
            <div class="action-card">
                <div class="icon-wrapper">🌭</div>
                <h3>Mobile Order</h3>
                <p>Skip the line. Order food directly to your seat.</p>
            </div>
        </a>
        <a href="lightshow.php" class="card-link">
            <div class="action-card">
                <div class="icon-wrapper">✨</div>
                <h3>Light Show</h3>
                <p>Sync your phone for the live stadium light show.</p>
            </div>
        </a>
    </div>

    <div class="status-card">
        <div class="status-dot"></div>
        <div>
            <h3>All systems active</h3>
            <p>Database, Session, and Geolocation are running normally.</p>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>
