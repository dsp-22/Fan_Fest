<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connect.php';

// --- GUEST LOGIC HOOK ---
$is_guest = (isset($_SESSION['user_id']) && $_SESSION['user_id'] === 'guest');
$u_id = $is_guest ? 0 : ($_SESSION['user_id'] ?? 0);
// ------------------------

if ($is_guest) {
    $user_theme = 'blue';
    $f_name = "Guest Fan";
} else if ($u_id > 0) {
    $theme_data = $conn->query("SELECT first_name, theme_color FROM users WHERE id = $u_id")->fetch_assoc();
    $f_name = $theme_data['first_name'] ?? "Fan";
    $user_theme = $theme_data['theme_color'] ?? 'blue';
} else {
    $user_theme = 'blue';
    $f_name = "Fan";
}

// check if user is checked in today and get their current venue
$current_venue = null;
if ($u_id > 0) {
    $cv = $conn->prepare("
        SELECT e.location_name, e.event_name
        FROM checkins c
        JOIN events e ON e.event_id = c.event_id
        WHERE c.user_id = ?
          AND DATE(c.checkin_time) = CURDATE()
        ORDER BY c.checkin_time DESC
        LIMIT 1
    ");
    $cv->bind_param("i", $u_id);
    $cv->execute();
    $cv_row = $cv->get_result()->fetch_assoc();
    if ($cv_row) $current_venue = $cv_row;
    $cv->close();
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


if (isset($_POST['add_item_id'])) {
    if ($is_guest) { header("Location: login.php"); exit(); } // Block backend processing for guests
    
    $item_id = $_POST['add_item_id'];
    $item_name = $_POST['item_name'];
    $item_price = $_POST['item_price'];
    $item_notes = trim($_POST['item_notes'] ?? '');

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart'][] = [
        'id' => $item_id,
        'name' => $item_name,
        'price' => $item_price,
        'notes' => $item_notes,
        'quantity' => 1
    ];
    $message = "Added $item_name to your order!";
}
?>

<?php include 'header.php'; ?>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #2d3436; margin: 0; }

        .hero-banner {
            background: <?php echo $active_gradient; ?>;
            color: white;
            padding: 60px 20px 100px 20px;
            text-align: center;
            margin-bottom: -60px;
            transition: background 0.5s ease;
        }
        .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }

        .main-container { width: 95%; max-width: 1100px; margin: 0 auto 50px auto; position: relative; z-index: 5; }

        .stand-section {
            background: white; border-radius: 16px; padding: 25px; margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border-left: 6px solid <?php echo $accent; ?>;
        }

        .stand-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f2f6; padding-bottom: 15px; margin-bottom: 20px; }
        .stand-title h2 { margin: 0; font-size: 1.4em; font-weight: 800; color: #2d3436; }
        .stand-location { color: #b2bec3; font-weight: 800; font-size: 0.8em; text-transform: uppercase; letter-spacing: 1px; }

        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }

        .menu-item {
            background: #fafafa;
            border: 1px solid #f1f2f6;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: 0.3s;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            height: 100%;
        }
        .menu-item:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }

        .item-name { font-weight: 800; font-size: 1.1em; color: #2d3436; margin-bottom: 5px; }

        .item-desc {
            font-size: 0.85em;
            color: #b2bec3;
            line-height: 1.4;
            margin-bottom: 12px;
            flex-grow: 1;
        }

        .item-price { color: <?php echo $accent; ?>; font-weight: 800; font-size: 1.2em; margin: 10px 0; }

        .toggle-mod {
            font-size: 12px;
            color: #b2bec3;
            cursor: pointer;
            font-weight: 700;
            margin-bottom: 12px;
            display: inline-block;
            transition: color 0.2s;
        }
        .toggle-mod:hover { color: <?php echo $accent; ?>; }
        .notes-wrapper { display: none; margin-bottom: 12px; }

        .notes-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            transition: border-color 0.2s;
        }
        .notes-input:focus { outline: none; border-color: <?php echo $accent; ?>; }

        .btn-add {
            background-color: #2d3436; color: white; border: none; padding: 10px 15px;
            border-radius: 8px; cursor: pointer; width: 100%; font-weight: 700; transition: transform 0.2s;
        }
        .btn-add:hover { background-image: <?php echo $active_gradient; ?>; transform: scale(1.02); }

        .cart-float {
            position: fixed; bottom: 30px; right: 30px;
            background: <?php echo $active_gradient; ?>;
            color: white; padding: 18px 30px; border-radius: 50px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            text-decoration: none; font-weight: 800; z-index: 1000; transition: 0.3s;
            display: flex; align-items: center; gap: 10px;
        }
        .cart-float:hover { transform: translateY(-5px) scale(1.05); }
        .cart-float .cart-divider { opacity: 0.5; }
        .cart-float .cart-total { font-weight: 800; }
    </style>

    <div class="hero-banner">
        <h1>Mobile Ordering</h1>
        <?php if ($current_venue): ?>
            <p>Ordering for <strong><?php echo htmlspecialchars($current_venue['location_name']); ?></strong> · <?php echo htmlspecialchars($current_venue['event_name']); ?></p>
        <?php else: ?>
            <p>Skip the line, <?php echo htmlspecialchars($f_name); ?>. Order food directly to your seat.</p>
        <?php endif; ?>
        <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; margin-top: 15px; display: inline-block; font-weight: bold;">
            🏆 FanFest Rewards: Earn 1 point for every $1 you spend!
        </div>
    </div>

    <div class="main-container">

        <?php if(isset($message)): ?>
            <div class="card" style="background:#e3fcef; border-left: 5px solid #00b894; color: #00a152; font-weight: 800; text-align:center; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!$current_venue && !$is_guest): ?>
            <div style="background:white; border-radius:14px; padding:20px 24px; margin-bottom:24px; box-shadow:0 4px 16px rgba(0,0,0,0.05); display:flex; align-items:center; gap:14px;">
                <span style="font-size:24px;">📍</span>
                <div>
                    <strong style="display:block; color:#2d3436; margin-bottom:3px;">Check in to unlock venue ordering</strong>
                    <span style="font-size:13px; color:#b2bec3;">Check in at a game today to see what's available at your venue.</span>
                </div>
                <a href="checkin.php" style="margin-left:auto; background:<?php echo $accent; ?>; color:white; padding:9px 20px; border-radius:10px; font-weight:700; font-size:13px; text-decoration:none; white-space:nowrap;">Check In</a>
            </div>
        <?php endif; ?>

        <?php
        $stands_result = $conn->query("SELECT * FROM concession_stands");
        if ($stands_result->num_rows > 0):
            while($stand = $stands_result->fetch_assoc()):
                $stand_id = $stand['stand_id'];
        ?>
            <div class="stand-section">
                <div class="stand-header">
                    <div class="stand-title">
                        <h2><?php echo htmlspecialchars($stand['stand_name']); ?></h2>
                    </div>
                    <div class="stand-location">📍 <?php echo htmlspecialchars($stand['location']); ?></div>
                </div>

                <div class="menu-grid">
                    <?php
                    $menu_sql = "SELECT m.* FROM menu_items m
                                 JOIN stand_inventory si ON m.item_id = si.item_id
                                 WHERE si.stand_id = $stand_id";
                    $menu_result = $conn->query($menu_sql);

                    if ($menu_result && $menu_result->num_rows > 0):
                        while($item = $menu_result->fetch_assoc()):
                    ?>
                        <div class="menu-item">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>

                            <div class="item-desc">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </div>

                            <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>

                            <form method="POST" style="margin-top: auto;">
                                <input type="hidden" name="add_item_id" value="<?php echo $item['item_id']; ?>">
                                <input type="hidden" name="item_name" value="<?php echo $item['name']; ?>">
                                <input type="hidden" name="item_price" value="<?php echo $item['price']; ?>">

                                <span class="toggle-mod" onclick="this.nextElementSibling.style.display='block'; this.style.display='none';">+ Add modifications</span>
                                <div class="notes-wrapper">
                                    <input type="text" name="item_notes" class="notes-input" placeholder="e.g. No mayo, no tomatoes...">
                                </div>
                                
                                <?php if ($is_guest): ?>
                                    <button type="button" class="btn-add" onclick="requireLogin('add items to your order')">Add to Order</button>
                                <?php else: ?>
                                    <button type="submit" class="btn-add">Add to Order</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endwhile; else: echo "<p>No items found.</p>"; endif; ?>
                </div>
            </div>
        <?php endwhile; else: echo "<p>No stands found.</p>"; endif; ?>
    </div>

    <?php
    $cart_count = 0;
    $cart_subtotal = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $c) {
            $qty = $c['quantity'] ?? 1;
            $cart_count += $qty;
            $cart_subtotal += $c['price'] * $qty;
        }
    }
    if ($cart_count > 0 && !$is_guest):
    ?>
        <a href="cart.php" class="cart-float">
            <span>View Cart (<?php echo $cart_count; ?>)</span>
            <span class="cart-divider">·</span>
            <span class="cart-total">$<?php echo number_format($cart_subtotal, 2); ?></span>
        </a>
    <?php endif; ?>

<?php include 'footer.php'; ?>
