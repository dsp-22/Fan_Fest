<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connect.php';

// --- 1. THEME ENGINE: FETCH AND MAP USER COLORS ---
// Default theme for guests who are not logged in
$theme = 'blue'; 

// Only fetch personalized theme if the user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Guest Logic Hook: convert 'guest' to 0 so the query doesn't crash
    $safe_user_id = ($user_id === 'guest') ? 0 : $user_id;
    
    $theme_data = $conn->query("SELECT theme_color FROM users WHERE id = $safe_user_id")->fetch_assoc();
    $theme = $theme_data['theme_color'] ?? 'blue';
}

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
            color: white !important; padding: 60px 20px 100px 20px; text-align: center; margin-bottom: -60px;
        }
        .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }

        .main-container { width: 95%; max-width: 1100px; margin: 0 auto 50px auto; position: relative; z-index: 5; }
        .card { 
            background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
            padding: 30px; margin-bottom: 25px; 
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        /* TEAM STYLES */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px;
            margin-top: 40px;
        }

        .team-member {
            background: white;
            border-radius: 12px;
            padding: 30px 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
            height: 100%;
            /* Linked to Database Theme */
            border-top: 5px solid <?php echo $accent; ?> !important; 
        }
        .team-member:hover { transform: translateY(-5px); }

        .profile-pic {
            width: 120px; height: 120px; object-fit: cover; border-radius: 50%;
            margin-bottom: 15px; border: 3px solid #f8f9fa; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }

        .team-member:hover .profile-pic {
            transform: scale(1.05);
            border-color: <?php echo $accent; ?>;
        }

        .role { 
            font-weight: 800; 
            color: <?php echo $accent; ?> !important; 
            background: <?php echo $accent; ?>1A; 
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 10px; text-transform: uppercase; font-size: 0.75em; letter-spacing: 1px; 
        }

        
        .bio { font-size: 0.9em; color: #636e72; margin-bottom: 15px; }
        .fun-fact { 
            font-style: italic; font-size: 0.85em; 
            background-color: #f8f9fa; padding: 12px; border-radius: 8px; 
            color: #636e72; margin-top: auto;
            border-left: 4px solid <?php echo $accent; ?>;
            text-align: left;
        }
        /* PROJECT SECTION STYLES */
        .section-divider {
            text-align: center;
            margin: 60px auto 40px auto;
            padding-top: 40px;
            border-top: 4px dotted #dcdde1;
            width: 80%;
        }
        .section-divider h2 { color: #2d3436; font-size: 2.2em; margin-bottom: 10px; }
        .section-divider p { color: #636e72; font-size: 1.1em; }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 50px;
        }

        .card-icon { 
            font-size: 2em; margin-bottom: 20px; 
            background: #f1f2f6; width: 65px; height: 65px; 
            display: inline-flex; align-items: center; justify-content: center; 
            border-radius: 50%; 
        }
        .feature-list { list-style: none; padding: 0; margin: 0; }
        .feature-list li {
            padding: 12px 10px;
            border-bottom: 1px solid #f1f2f6;
            color: #636e72;
            display: flex;
            align-items: start;
            transition: background 0.2s, padding-left 0.2s;
            border-radius: 8px;
        }
        .feature-list li:hover {
            background: #f8f9fa;
            padding-left: 15px;
        }
        .feature-list li:last-child { border-bottom: none; }
        
        /* Themed Checkmarks */
        .check-icon {
            color: <?php echo $accent; ?> !important;
            margin-right: 10px;
            font-weight: bold;
        }

        /* RESPONSIVENESS */
        @media (max-width: 1000px) { .team-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 768px) { .content-grid { grid-template-columns: 1fr; } }
        @media (max-width: 600px) { .team-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="hero-banner">
        <h1>About Us & Our Project</h1>
        <p>Innovating the stadium experience.</p>
    </div>

    <div class="main-container">
        
        <div class="card" style="text-align: center; margin-bottom: 40px;">
            <h2 style="color: #2d3436; margin-top: 0;">Our Motivation</h2>
            <p style="font-size: 1.1em; color: #636e72; margin-bottom: 30px;">
                "We believe finding a venue should be as easy as ordering a pizza."
            </p>

            <h3 style="color: #2d3436; margin-bottom: 10px;">Why Trust Us?</h3>
            <p style="color: #636e72; max-width: 800px; margin: 0 auto;">
                We are a team of senior Informatics students at Indiana University, dedicated to solving real-world problems through clean code and user-centric design.
            </p>
        </div>

        <div class="team-grid">
            <div class="team-member">
                <img src="images/dan_sproat.jpeg" alt="Dan Sproat" class="profile-pic">
                <h3>Dan Sproat</h3>
                <p class="role">Backend Developer</p>
                <p class="bio">Senior | Informatics</p>
                <div class="fun-fact"><strong>Fun Fact:</strong> I Trust The Process.</div>
            </div>

            <div class="team-member">
                <img src="images/cal_willits.jpeg" alt="Cal Willits" class="profile-pic" style="object-position: 50% 20%;">
                <h3>Cal Willits</h3>
                <p class="role">Frontend Developer</p>
                <p class="bio">Senior | Informatics</p>
                <div class="fun-fact"><strong>Fun Fact:</strong> I am a huge fan of IU basketball.</div>
            </div>

            <div class="team-member">
                <img src="images/dylan_hollen.jpg" alt="Dylan Hollen" class="profile-pic">
                <h3>Dylan Hollen</h3>
                <p class="role">UX/UI Designer</p>
                <p class="bio">Senior | Informatics</p>
                <div class="fun-fact"><strong>Fun Fact:</strong> I can juggle.</div>
            </div>

            <div class="team-member">
                <img src="images/nathan_li.jpg" alt="Nathan Li" class="profile-pic">
                <h3>Nathan Li</h3>
                <p class="role">UX/UI Designer</p> 
                <p class="bio">Senior | Informatics</p>
                <div class="fun-fact"><strong>Fun Fact:</strong> I have a pet bird.</div>
            </div>
        </div>

        <div class="section-divider">
            <h2>Connect. Engage. Experience.</h2>
            <p>The ultimate companion for the live stadium experience.</p>
        </div>

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

<?php include 'footer.php'; ?>
