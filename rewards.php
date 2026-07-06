<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// --- GUEST LOGIC HOOK ---
$is_guest = ($_SESSION['user_id'] === 'guest');
$u_id = $is_guest ? 0 : $_SESSION['user_id'];
// ------------------------

// --- HANDLE REDEMPTION LOGIC ---
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'redeem') {
    if ($is_guest) { header("Location: login.php"); exit(); } // Extra security layer
    
    $item_cost = intval($_POST['cost']);
    $item_name = $_POST['item_name'];

    // Get current points
    $stmt = $conn->prepare("SELECT rewards_points FROM users WHERE id = ?");
    $stmt->bind_param("i", $u_id);
    $stmt->execute();
    $current_points = $stmt->get_result()->fetch_assoc()['rewards_points'] ?? 0;
    $stmt->close();

    if ($current_points >= $item_cost) {
        $new_total = $current_points - $item_cost;
        $upd = $conn->prepare("UPDATE users SET rewards_points = ? WHERE id = ?");
        $upd->bind_param("ii", $new_total, $u_id);
        if ($upd->execute()) {
            $message = "🎉 Redeemed! You just got: $item_name. Check your digital wallet!";
        }
        $upd->close();
    } else {
        $message = "❌ Error: You do not have enough points.";
    }
}

// theme
if ($is_guest) {
    $f_name = "Guest Fan";
    $user_theme = "blue";
} else {
    $theme_data = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $u_id")->fetch_assoc();
    $f_name = $theme_data['first_name'] ?? "Fan";
    $user_theme = $theme_data['theme_color'] ?? 'blue';
}

$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)',
];
$accent_map = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$light_map  = ['blue' => '#e8f4fd', 'red' => '#fdeaea', 'gold' => '#fdf8ee', 'black' => '#f1f2f4'];
$active_gradient = $theme_map[$user_theme]  ?? $theme_map['blue'];
$accent          = $accent_map[$user_theme] ?? '#0984e3';
$light           = $light_map[$user_theme]  ?? '#e8f4fd';

// points
$stmt = $conn->prepare("SELECT rewards_points FROM users WHERE id = ?");
$stmt->bind_param("i", $u_id);
$stmt->execute();
$points = $stmt->get_result()->fetch_assoc()['rewards_points'] ?? 0;
$stmt->close();

// lifetime spend + order count
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_price), 0) AS spent, COUNT(*) AS orders FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $u_id);
$stmt->execute();
$spending = $stmt->get_result()->fetch_assoc();
$stmt->close();

$tiers = [
    ['name' => 'Rookie',       'min' => 0,    'icon' => '🥉', 'perk' => 'Welcome reward — 1pt per $1'],
    ['name' => 'Pro',          'min' => 100,  'icon' => '🥈', 'perk' => 'Free soda at any stand'],
    ['name' => 'All-Star',     'min' => 500,  'icon' => '🥇', 'perk' => '10% off every order'],
    ['name' => 'Hall of Fame', 'min' => 1000, 'icon' => '🏆', 'perk' => 'Exclusive merch + VIP line'],
];

$current_tier = $tiers[0];
$next_tier = null;
foreach ($tiers as $tier) {
    if ($points >= $tier['min']) $current_tier = $tier;
    if ($points < $tier['min'] && $next_tier === null) $next_tier = $tier;
}
$to_next = $next_tier ? ($next_tier['min'] - $points) : 0;
$progress_pct = $next_tier
    ? min(100, (($points - $current_tier['min']) / ($next_tier['min'] - $current_tier['min'])) * 100)
    : 100;

// Expanded Catalog
$catalog = [
    ['name' => 'Large Soda',           'cost' => 50,  'icon' => '🥤'],
    ['name' => 'Large Order of Fries', 'cost' => 65,  'icon' => '🍟'],
    ['name' => 'Stadium Hot Dog',      'cost' => 75,  'icon' => '🌭'],
    ['name' => 'Soft Pretzel',         'cost' => 60,  'icon' => '🥨'],
    ['name' => 'Brownie Batter Donuts','cost' => 85,  'icon' => '🍩'],
    ['name' => 'Nachos Supreme',       'cost' => 100, 'icon' => '🧀'],
    ['name' => 'FanFest T-Shirt',      'cost' => 400, 'icon' => '👕'],
    ['name' => 'Seat Upgrade',         'cost' => 650, 'icon' => '🎟️'],
    ['name' => 'VIP Lounge Pass',      'cost' => 750, 'icon' => '⭐'],
];

// Sort the catalog by cost (lowest to highest)
usort($catalog, function($a, $b) {
    return $a['cost'] - $b['cost'];
});

?>
<?php include 'header.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background: #f4f6f9; margin: 0; }
    .hero-banner { background: <?php echo $active_gradient; ?>; color: white; padding: 60px 20px 120px; text-align: center; margin-bottom: -80px; }
    .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }
    .hero-banner p { margin: 10px 0 0; color: rgba(255,255,255,0.8); }

    .main-container { width: 95%; max-width: 1000px; margin: 0 auto 100px; position: relative; z-index: 5; padding: 0 10px; }
    .card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 30px; margin-bottom: 25px; }

    .points-card { text-align: center; padding: 40px 30px; }
    .points-val { font-size: 4em; font-weight: 800; color: <?php echo $accent; ?>; line-height: 1; }
    .points-lbl { color: #b2bec3; text-transform: uppercase; font-size: 12px; letter-spacing: 0.1em; font-weight: 700; margin-top: 8px; }
    .tier-badge { display: inline-block; background: <?php echo $light; ?>; color: <?php echo $accent; ?>; padding: 8px 18px; border-radius: 20px; font-weight: 700; font-size: 14px; margin-top: 15px; }

    .progress-wrap { margin-top: 25px; text-align: left; }
    .progress-bar { background: #e0e0e0; height: 12px; border-radius: 10px; overflow: hidden; }
    .progress-fill { background: <?php echo $accent; ?>; height: 100%; transition: width 0.5s; }
    .progress-lbl { display: flex; justify-content: space-between; font-size: 13px; color: #636e72; margin-top: 8px; font-weight: 600; }

    .tiers-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
    .tier-card { text-align: center; padding: 20px 10px; border-radius: 12px; border: 2px solid #f1f2f6; }
    .tier-card.current { border-color: <?php echo $accent; ?>; background: <?php echo $light; ?>; }
    .tier-card.locked { opacity: 0.5; }
    .tier-icon { font-size: 2em; }
    .tier-name { font-weight: 800; margin-top: 6px; font-size: 14px; }
    .tier-min { color: #b2bec3; font-size: 11px; margin-top: 4px; }
    .tier-perk { color: #636e72; font-size: 11px; margin-top: 8px; line-height: 1.3; min-height: 28px; }

    .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
    .redeem-card { padding: 20px; border: 2px solid #f1f2f6; border-radius: 12px; text-align: center; transition: 0.2s; display: flex; flex-direction: column; justify-content: space-between; }
    .redeem-card:hover { transform: translateY(-3px); border-color: <?php echo $accent; ?>; }
    .redeem-icon { font-size: 2.2em; }
    .redeem-name { font-weight: 700; margin: 8px 0 4px; font-size: 15px; }
    .redeem-cost { color: <?php echo $accent; ?>; font-weight: 800; font-size: 14px; }
    .redeem-btn { margin-top: 12px; padding: 8px 14px; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; background: <?php echo $accent; ?>; color: white; width: 100%; }
    .redeem-btn:disabled { background: #e0e0e0; color: #999; cursor: not-allowed; }

    .section-label { font-size: 11px; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase; color: #b2bec3; margin: 30px 0 12px; }

    @media (max-width: 700px) {
        .tiers-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<div class="hero-banner">
    <h1>FanFest Rewards</h1>
    <p>Earn points every time you order. Unlock perks along the way.</p>
</div>

<div class="main-container">

    <?php if($message): ?>
        <div class="card" style="background:#e3fcef; border-left: 5px solid #00b894; color: #00a152; font-weight: 800; text-align:center;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="card points-card">
        <div class="points-val"><?php echo number_format($points); ?></div>
        <div class="points-lbl">Total Points</div>
        <div class="tier-badge"><?php echo $current_tier['icon']; ?> <?php echo $current_tier['name']; ?></div>

        <?php if ($next_tier): ?>
            <div class="progress-wrap">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress_pct; ?>%;"></div>
                </div>
                <div class="progress-lbl">
                    <span><?php echo $to_next; ?> pts to <?php echo $next_tier['name']; ?></span>
                    <span><?php echo $next_tier['min']; ?> pts</span>
                </div>
            </div>
        <?php else: ?>
            <p style="margin-top:20px; color:#636e72;">You've hit the top tier. You're the real MVP. 🏆</p>
        <?php endif; ?>

        <div style="display:flex; justify-content:center; gap:40px; margin-top:30px; padding-top:25px; border-top:1px solid #f1f2f6;">
            <div>
                <div style="font-size:1.5em; font-weight:800; color:#2d3436;"><?php echo (int)$spending['orders']; ?></div>
                <div style="font-size:11px; color:#b2bec3; text-transform:uppercase; letter-spacing:0.1em; font-weight:700;">Orders</div>
            </div>
            <div>
                <div style="font-size:1.5em; font-weight:800; color:#2d3436;">$<?php echo number_format($spending['spent'], 2); ?></div>
                <div style="font-size:11px; color:#b2bec3; text-transform:uppercase; letter-spacing:0.1em; font-weight:700;">Lifetime Spend</div>
            </div>
        </div>
    </div>

    <p class="section-label">Tiers</p>
    <div class="card">
        <div class="tiers-grid">
            <?php foreach ($tiers as $tier):
                $is_current = ($tier['name'] === $current_tier['name']);
                $is_locked = ($points < $tier['min']);
            ?>
                <div class="tier-card <?php echo $is_current ? 'current' : ($is_locked ? 'locked' : ''); ?>">
                    <div class="tier-icon"><?php echo $tier['icon']; ?></div>
                    <div class="tier-name"><?php echo $tier['name']; ?></div>
                    <div class="tier-min"><?php echo $tier['min']; ?> pts</div>
                    <div class="tier-perk"><?php echo htmlspecialchars($tier['perk']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="section-label">Redeem your points</p>
    <div class="card">
        <div class="catalog-grid">
            <?php foreach ($catalog as $item):
                $affordable = $points >= $item['cost'];
            ?>
                <div class="redeem-card">
                    <div>
                        <div class="redeem-icon"><?php echo $item['icon']; ?></div>
                        <div class="redeem-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="redeem-cost"><?php echo $item['cost']; ?> pts</div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="redeem">
                        <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($item['name']); ?>">
                        <input type="hidden" name="cost" value="<?php echo $item['cost']; ?>">
                        
                        <?php if ($is_guest): ?>
                            <button type="button" class="redeem-btn" onclick="requireLogin('redeem this reward')">Redeem</button>
                        <?php else: ?>
                            <button type="submit" class="redeem-btn" <?php echo $affordable ? '' : 'disabled'; ?>>
                                <?php echo $affordable ? 'Redeem' : 'Not enough'; ?>
                            </button>
                        <?php endif; ?>
                        
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>
