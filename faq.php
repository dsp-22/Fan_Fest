<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connect.php';

$theme = 'blue';
if (isset($_SESSION['user_id'])) {
    $u_id = $_SESSION['user_id'];
    $theme_data = $conn->query("SELECT theme_color FROM users WHERE id = $u_id")->fetch_assoc();
    $theme = $theme_data['theme_color'] ?? 'blue';
}

$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)'
];

$active_gradient = $theme_map[$theme] ?? $theme_map['blue'];
$accent_colors = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$accent = $accent_colors[$theme] ?? '#0984e3';

include 'header.php';
?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; margin: 0; }

    .hero-banner {
        background: <?php echo $active_gradient; ?> !important;
        color: white;
        padding: 60px 20px 100px 20px;
        text-align: center;
        margin-bottom: -60px;
        transition: background 0.5s ease;
    }

    .main-container {
        width: 90%;
        max-width: 800px;
        margin: 0 auto 120px auto;
        position: relative;
        z-index: 5;
    }

    .faq-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        padding: 10px;
    }

    details {
        padding: 20px;
        border-bottom: 1px solid #f1f2f6;
        cursor: pointer;
    }

    details:last-child { border-bottom: none; }

    summary {
        font-weight: 700;
        color: #2d3436;
        list-style: none;
        display: flex;
        justify-content: space-between;
        outline: none;
    }

    summary::after {
        content: '+';
        color: <?php echo $accent; ?>;
        font-weight: 800;
    }

    details[open] summary::after { content: '-'; }

    .faq-content {
        padding-top: 15px;
        color: #636e72;
        line-height: 1.6;
        font-size: 0.95em;
    }
</style>

<div class="hero-banner">
    <h1>Common Questions</h1>
    <p>Everything you need to know about using FanFest.</p>
</div>

<div class="main-container">
    <div class="faq-card">
        <details>
            <summary>How do I check in to the game?</summary>
            <div class="faq-content">
                Go to the "Check In" page. The app uses your GPS to verify you are at the stadium so you can earn badges and points!
            </div>
        </details>

        <details>
            <summary>How does mobile ordering work?</summary>
            <div class="faq-content">
                Pick your items from the Concessions menu and checkout. Your order goes straight to the stand and we'll let you know when it's ready.
            </div>
        </details>

        <details>
            <summary>Is my location private?</summary>
            <div class="faq-content">
                Yes. Only your accepted friends can see your seat location. You can toggle your privacy settings in your profile anytime.
            </div>
        </details>

        <details>
            <summary>What is the Stadium Light Show?</summary>
            <div class="faq-content">
                During the game, open the Light Show page to sync your phone's screen and flash with thousands of other fans!
            </div>
        </details>

        <details>
            <summary>How do I earn rewards points?</summary>
            <div class="faq-content">
                You earn 1 point for every $1 you spend on concessions. Points unlock tier upgrades (Rookie → Pro → All-Star → Hall of Fame) and can be redeemed for free food, merch, and VIP perks.
            </div>
        </details>

        <details>
            <summary>What if my geolocation check-in fails?</summary>
            <div class="faq-content">
                Make sure location services are turned on in your browser and that you're actually at the venue. Our GPS radius is generous, but you need to be within a reasonable distance of the stadium. If it still fails, try refreshing the page.
            </div>
        </details>

        <details>
            <summary>Can I cancel an order after I've placed it?</summary>
            <div class="faq-content">
                Orders are sent straight to the stand once you check out, so they can't be cancelled from the app. If there's an issue, find a FanFest rep at the venue.
            </div>
        </details>

        <details>
            <summary>How do I add friends?</summary>
            <div class="faq-content">
                Head to the Friends page and search by name or username. Send a request and once they accept, you'll see when they RSVP to games and when they check in.
            </div>
        </details>

        <details>
            <summary>Can I change my team theme color?</summary>
            <div class="faq-content">
                Yep — go to your Profile and pick from blue, red, gold, or black. The whole site will recolor to match.
            </div>
        </details>

        <details>
            <summary>How do I RSVP to a game?</summary>
            <div class="faq-content">
                On the Check-In page, find the game you're going to and hit the RSVP button. Your friends will see you're going, and on game day the check-in button will unlock when you arrive.
            </div>
        </details>
    </div>
</div>

<?php include 'footer.php'; ?>