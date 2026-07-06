<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- GUEST LOGIC HOOK ---
$is_guest = ($_SESSION['user_id'] === 'guest');
$user_id = $is_guest ? 0 : $_SESSION['user_id'];
// ------------------------

// Your Google Maps Embed API Key
$google_maps_key = "AIzaSyBaEyRN5Om5_04Eu3TMwiSJcnY38Zl2jXQ";

$selected_venue = $_GET['venue'] ?? '';

// Determine venue display string
$display_venue = !empty($selected_venue) ? htmlspecialchars($selected_venue) : 'Memorial Stadium, Bloomington IN';

// theme
if ($is_guest) {
    $f_name = "Guest Fan";
    $user_theme = "blue";
} else {
    $theme_data = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $user_id")->fetch_assoc();
    $f_name = $theme_data['first_name'] ?? "Fan";
    $user_theme = $theme_data['theme_color'] ?? 'blue';
}

$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)'
];
$active_gradient = $theme_map[$user_theme] ?? $theme_map['blue'];
$colors = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$accent = $colors[$user_theme] ?? '#0984e3';
?>

<?php include 'header.php'; ?>

    <style>
        .hero-banner {
            background: <?php echo $active_gradient; ?> !important;
            color: white !important;
            padding: 60px 20px 100px 20px;
            text-align: center;
            margin-bottom: -60px;
        }
        .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }

        .main-container { width: 95%; max-width: 800px; margin: 0 auto 50px auto; position: relative; z-index: 5; }
        .card { 
            background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
            padding: 25px; margin-bottom: 25px; text-align: center;
        }

        .venue-title { font-size: 1.5em; font-weight: 800; color: #2d3436; margin-bottom: 20px; }
    </style>

    <div class="hero-banner">
        <h1>Stadium Directions</h1>
        <p>Get to the game on time, <?php echo htmlspecialchars($f_name); ?>!</p>
    </div>

    <div class="main-container">
        <div class="card">
            <div class="venue-title">
                📍 Venue Location: <?php echo $display_venue; ?>
            </div>
            
            <?php 
            // URL-encode the search query for safe transport via iframe HTTP embedding
            $search_term = urlencode(!empty($selected_venue) ? $selected_venue : 'Memorial Stadium, Bloomington IN');
            ?>

            <iframe 
                width="100%" 
                height="450" 
                style="border:0; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);" 
                loading="lazy" 
                allowfullscreen
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/search?key=<?php echo $google_maps_key; ?>&q=<?php echo $search_term; ?>">
            </iframe>
            
            <p style="margin-top: 25px; color: #636e72; font-size: 0.95em;">
                <?php echo !empty($selected_venue) ? "Directions and map dynamically centered on <i>" . htmlspecialchars($selected_venue) . "</i>." : "Interactive map centered on the default venue."; ?>
            </p>
        </div>
    </div>

<?php include 'footer.php'; ?>
