<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// theme
$user_data = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $user_id")->fetch_assoc();
$user_name = $user_data['first_name'] ?? "Fan";
$theme = $user_data['theme_color'] ?? 'blue';

$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)'
];

$active_gradient = $theme_map[$theme] ?? $theme_map['blue'];
$accent_colors = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$accent = $accent_colors[$theme] ?? '#0984e3';

// only unlock once they've checked in today
$stmt = $conn->prepare("SELECT checkin_id FROM checkins WHERE user_id = ? AND DATE(checkin_time) = CURDATE() LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$checked_in_today = $stmt->get_result()->num_rows > 0;
$stmt->close();
?>

<?php include 'header.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #2d3436; margin: 0; }

    .hero-banner {
        background: <?php echo $active_gradient; ?> !important;
        color: white; padding: 60px 20px 100px 20px; text-align: center; margin-bottom: -60px;
        transition: background 0.5s ease;
    }
    .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }

    .main-container { width: 90%; max-width: 1100px; margin: 0 auto 50px auto; position: relative; z-index: 5; }
    .card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 25px; }

    #light-screen {
        width: 100%; height: 350px;
        background: radial-gradient(circle at center, #1a1a2e 0%, #000 100%);
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; margin-top: 20px;
        transition: background 0.5s;
        box-shadow: 0 0 25px rgba(0,0,0,0.4); position: relative;
        overflow: hidden;
    }
    #light-screen::before {
        content: '';
        position: absolute;
        width: 200px; height: 200px;
        border-radius: 50%;
        background: radial-gradient(circle, <?php echo $accent; ?>44 0%, transparent 70%);
        animation: pulse 2.5s ease-in-out infinite;
    }
    @keyframes pulse {
        0%, 100% { transform: scale(0.8); opacity: 0.5; }
        50%      { transform: scale(1.3); opacity: 1; }
    }
    #status-text {
        color: white; font-size: 1.8em; font-weight: 800;
        text-transform: uppercase; letter-spacing: 3px; text-align: center;
        position: relative; z-index: 2;
        text-shadow: 0 0 20px rgba(255,255,255,0.3);
    }

    .controls { margin-top: 30px; text-align: center; }
    .btn-sync {
        background-color: <?php echo $accent; ?>;
        color: white; padding: 18px 40px; font-size: 1.1em;
        border: none; border-radius: 50px; cursor: pointer;
        font-weight: 700; transition: 0.3s;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .btn-sync:hover { transform: scale(1.05); filter: brightness(1.1); }

    .fullscreen-btn {
        position: absolute; top: 10px; right: 10px;
        background: rgba(255,255,255,0.15); color: white;
        border: 1px solid rgba(255,255,255,0.4); padding: 6px 12px;
        font-size: 0.7em; border-radius: 20px; cursor: pointer;
        backdrop-filter: blur(5px); transition: 0.3s;
        z-index: 3;
    }
    .fullscreen-btn:hover { background: rgba(255,255,255,0.3); }
</style>

<div class="hero-banner">
    <h1>Sync Up, <?php echo htmlspecialchars($user_name); ?>!</h1>
    <p>Join the crowd. Sync your phone to the stadium beat.</p>
</div>

<div class="main-container">

    <?php if (!$checked_in_today): ?>
        <div class="card" style="text-align:center; padding:60px 30px;">
            <div style="font-size:3em; margin-bottom:15px;">🔒</div>
            <h2 style="margin:0 0 10px;">Light show locked</h2>
            <p style="color:#636e72; margin:0 0 25px;">Check in to today's game to sync your phone with the stadium.</p>
            <a href="checkin.php" style="background:<?php echo $accent; ?>; color:white; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700;">Check In Now</a>
        </div>
    <?php else: ?>

        <div class="card">
            <div id="light-screen">
                <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶</button>
                <span id="status-text">Waiting for Signal...</span>
            </div>

            <div class="controls">
                <button class="btn-sync" onclick="startShow()">JOIN LIVE SHOW</button>
                <p style="color: #636e72; margin-top: 20px; font-size: 0.9em; font-weight: 600;">
                    Keep your screen brightness up for the best effect.
                </p>
            </div>
        </div>

        <div class="card" style="text-align: center; border-left: 5px solid <?php echo $accent; ?>;">
            <h3>How it works</h3>
            <p style="color: #636e72; font-size: 0.9em;">
                When the game hits a big moment, the stadium command center sends a signal to all connected devices.
                Your screen will flash in sync with the stadium lights and music!
            </p>
        </div>

    <?php endif; ?>

</div>

<script>
    let intervalId = null;
    const themeAccent = '<?php echo $accent; ?>';
    const colors = [themeAccent, '#d63031', '#f1c40f', '#6c5ce7', '#00b894', '#ffffff'];
    const screen = document.getElementById('light-screen');
    const text = document.getElementById('status-text');

    function startShow() {
        if (intervalId || !screen) return;
        text.innerText = "SYNCED!";
        if (navigator.vibrate) navigator.vibrate(200);

        let counter = 0;
        intervalId = setInterval(() => {
            const randomColor = colors[Math.floor(Math.random() * colors.length)];
            screen.style.background = randomColor;
            text.style.opacity = (counter % 2 === 0) ? '1' : '0.5';
            counter++;
        }, 500);

        setTimeout(() => {
            clearInterval(intervalId);
            intervalId = null;
            screen.style.background = 'radial-gradient(circle at center, #1a1a2e 0%, #000 100%)';
            text.style.opacity = '1';
            text.innerText = "SHOW ENDED";
        }, 10000);
    }

    function toggleFullscreen() {
        const element = document.getElementById("light-screen");
        if (!document.fullscreenElement) {
            if (element.requestFullscreen) element.requestFullscreen();
        } else {
            if (document.exitFullscreen) document.exitFullscreen();
        }
    }
</script>

<?php include 'footer.php'; ?>