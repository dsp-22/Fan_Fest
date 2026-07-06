<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connect.php';

// Gatekeeper
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- 1. THEME ENGINE: FETCH AND MAP USER COLORS ---
$theme_data = $conn->query("SELECT theme_color FROM users WHERE id = $user_id")->fetch_assoc();
$theme = $theme_data['theme_color'] ?? 'blue';

// Master Mapping for global consistency
$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)'
];
$active_gradient = $theme_map[$theme] ?? $theme_map['blue'];
$colors = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$accent = $colors[$theme] ?? '#0984e3';
?>

<?php include 'header.php'; ?>

    <style>
        /* Standardized Hero Banner */
        .hero-banner {
            background: <?php echo $active_gradient; ?> !important;
            color: white !important; 
            padding: 60px 20px 100px 20px; 
            text-align: center; 
            margin-bottom: -60px; /* Pulls cards into the banner for the overlap effect */
        }
        .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }

        .main-container { width: 95%; max-width: 1100px; margin: 0 auto 50px auto; position: relative; z-index: 5; }
        .card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 25px; }

        /* PAGE-SPECIFIC STYLES UNTOUCHED */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 50px;
        }

        .card-icon { font-size: 2.5em; margin-bottom: 20px; display: block; }
        .feature-list { list-style: none; padding: 0; margin: 0; }
        .feature-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f1f2f6;
            color: #636e72;
            display: flex;
            align-items: start;
        }
        .feature-list li:last-child { border-bottom: none; }
        
        /* Themed Checkmarks */
        .check-icon {
            color: <?php echo $accent; ?> !important;
            margin-right: 10px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .content-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="hero-banner">
        <h1>Connect. Engage. Experience.</h1>
        <p>The ultimate companion for the live stadium experience.</p>
    </div>

    <div class="main-container">
        
        <div class="content-grid">
            <div class="card">
                <span class="card-icon">🏟️</span> 
                <h2>The Challenge</h2>
                <p style="color: #636e72;">Live events are amazing, but the logistics can be frustrating. Fans struggle to find their friends in massive crowds, wait in long lines for food, and often feel like passive observers rather than active participants.</p>
            </div>
            
            <div class="card">
                <span class="card-icon">🚀</span> 
                <h2>Our Mission</h2>
                <p style="color: #636e72;">To transform the stadium experience through hyper-connectivity. We combine privacy-focused geolocation, seamless mobile ordering, and interactive crowd engagement to create an event environment where everything just flows.</p>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <span class="card-icon">📍</span>
                <h2>Core Features</h2>
                <ul class="feature-list">
                    <li><span class="check-icon">✓</span> <strong>Friend Mapping:</strong> "Where are you?" solved. See friends' seats with privacy controls.</li>
                    <li><span class="check-icon">✓</span> <strong>Mobile Concessions:</strong> Order food from your seat to skip the halftime rush.</li>
                    <li><span class="check-icon">✓</span> <strong>Event Check-in:</strong> Geofenced rewards and stat tracking for super-fans.</li>
                </ul>
            </div>
            
            <div class="card">
                <span class="card-icon">✨</span> 
                <h2>Fan Engagement</h2>
                <ul class="feature-list">
                    <li><span class="check-icon">✓</span> <strong>Live Light Shows:</strong> Your phone becomes part of the stadium light show.</li>
                    <li><span class="check-icon">✓</span> <strong>Smart Privacy:</strong> You decide who sees you—"Friends Only" or "Ghost Mode."</li>
                    <li><span class="check-icon">✓</span> <strong>Low Latency:</strong> Built to handle thousands of simultaneous triggers.</li>
                </ul>
            </div>
        </div>
    </div>

</body>
</html>