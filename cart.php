<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$u_id = $_SESSION['user_id'];

// --- AUTO-CONSOLIDATE CART (MERGE DUPLICATES) ---
if (!empty($_SESSION['cart'])) {
    $merged_cart = [];
    foreach ($_SESSION['cart'] as $item) {
        $name = $item['name'];
        $notes = trim($item['notes'] ?? '');
        $qty = $item['quantity'] ?? 1;
        
        // Group by name AND specific notes so modified items stay separate
        $key = $name . '|||' . $notes;
        
        if (isset($merged_cart[$key])) {
            $merged_cart[$key]['quantity'] += $qty;
        } else {
            $merged_cart[$key] = [
                'name' => $name,
                'price' => $item['price'],
                'quantity' => $qty,
                'notes' => $notes
            ];
        }
    }
    $_SESSION['cart'] = array_values($merged_cart);
}

// --- THEME SYNC ---
$theme_data = $conn->query("SELECT theme_color FROM users WHERE id = $u_id")->fetch_assoc();
$user_theme = $theme_data['theme_color'] ?? 'blue';
$accent_map = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$accent = $accent_map[$user_theme] ?? '#0984e3'; 

$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)'
];
$active_gradient = $theme_map[$user_theme] ?? $theme_map['blue'];

// --- ACTIONS ---
$promo_error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_qty') {
        $idx = $_POST['item_index'];
        $new_qty = intval($_POST['quantity']);
        if ($new_qty > 0) {
            $_SESSION['cart'][$idx]['quantity'] = $new_qty;
        } else {
            array_splice($_SESSION['cart'], $idx, 1);
        }
    }
    elseif ($_POST['action'] == 'clear') {
        unset($_SESSION['cart']);
        unset($_SESSION['promo']);
    } 
    elseif ($_POST['action'] == 'reorder') {
        $names  = $_POST['reorder_name'] ?? [];
        $prices = $_POST['reorder_price'] ?? [];
        $qtys   = $_POST['reorder_quantity'] ?? [];
        $notes  = $_POST['reorder_notes'] ?? [];
        
        for ($i=0; $i < count($names); $i++) {
            $_SESSION['cart'][] = [
                'name'     => $names[$i],
                'price'    => $prices[$i],
                'quantity' => $qtys[$i],
                'notes'    => $notes[$i] ?? ''
            ];
        }
        header("Location: cart.php"); 
        exit();
    }
    elseif ($_POST['action'] == 'apply_promo') {
        $promo_code = strtoupper(trim($_POST['promo_code']));
        $stmt = $conn->prepare("SELECT discount_percent FROM coupons WHERE code = ? AND is_active = 1");
        $stmt->bind_param("s", $promo_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $coupon = $result->fetch_assoc();
            $_SESSION['promo'] = [
                'code' => $promo_code,
                'discount_percent' => $coupon['discount_percent']
            ];
        } else {
            $promo_error = "Invalid or expired promo code.";
            unset($_SESSION['promo']);
        }
    }
    elseif ($_POST['action'] == 'remove_promo') {
        unset($_SESSION['promo']);
    }
    elseif ($_POST['action'] == 'checkout' && !empty($_SESSION['cart'])) {
        $subtotal = 0;
        foreach($_SESSION['cart'] as $item) { 
            $qty = $item['quantity'] ?? 1;
            $subtotal += ($item['price'] * $qty); 
        }

        $discount_amount = 0;
        $applied_promo_code = null;
        if (isset($_SESSION['promo'])) {
            $discount_amount = $subtotal * ($_SESSION['promo']['discount_percent'] / 100);
            $applied_promo_code = $_SESSION['promo']['code'];
        }
        $final_total = $subtotal - $discount_amount;
        $points_earned = floor($final_total);

        // Save order with JSON that includes the item notes
        $stmt = $conn->prepare("INSERT INTO orders (user_id, items_json, total_price, promo_code, discount_amount, points_earned) VALUES (?, ?, ?, ?, ?, ?)");
        $cart_json = json_encode($_SESSION['cart']);
        $stmt->bind_param("isdsdi", $u_id, $cart_json, $final_total, $applied_promo_code, $discount_amount, $points_earned);
        
        if ($stmt->execute()) {
            $order_num = $conn->insert_id;
            $update_pts = $conn->prepare("UPDATE users SET rewards_points = rewards_points + ? WHERE id = ?");
            $update_pts->bind_param("ii", $points_earned, $u_id);
            $update_pts->execute();

            unset($_SESSION['cart']);
            unset($_SESSION['promo']);
            $success_message = "Order #$order_num confirmed! You earned $points_earned points.";
        }
    }
}

$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;
?>

<?php include 'header.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
    .hero-banner { background: <?php echo $active_gradient; ?>; color: white; padding: 60px 20px 120px 20px; text-align: center; margin-bottom: -80px; }
    .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1.5px; }
    .main-container { width: 95%; max-width: 700px; margin: 0 auto 120px auto; position: relative; z-index: 5; }
    .card { background: white; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); padding: 50px; }
    .btn { padding: 14px 28px; border-radius: 14px; font-weight: 800; cursor: pointer; border: none; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; width: 100%; box-sizing: border-box; }
    .btn-main { background: <?php echo $accent; ?>; color: white; box-shadow: 0 10px 20px -5px <?php echo $accent; ?>4D; }
    .btn-main:hover { transform: translateY(-3px); box-shadow: 0 15px 30px -5px <?php echo $accent; ?>66; }
    .btn-outline { background: #f1f2f6; color: #636e72; font-size: 0.9em; margin-top: 15px; }
    .cart-item { display: flex; align-items: center; justify-content: space-between; padding: 25px 0; border-bottom: 1px solid #f1f2f6; }
    .qty-input { width: 55px; padding: 10px; border-radius: 10px; border: 2px solid #f1f2f6; text-align: center; font-weight: 800; }
    .total-row { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 15px; border-top: 2px solid #f1f2f6; }
    .item-notes { font-size: 13px; color: #e17055; font-weight: 700; margin-top: 4px; }
</style>

<div class="hero-banner">
    <h1>Your Cart</h1>
    <p>Review your selection before heading to the checkout.</p>
</div>

<div class="main-container">
    <a href="concessions.php" style="color: white; text-decoration: none; display: block; margin-bottom: 25px; font-weight: 700; opacity: 0.9;">&larr; Back to Menu</a>

    <div class="card">
        <?php if(isset($success_message)): ?>
            <div style="text-align: center;">
                <div style="font-size: 5em; margin-bottom: 20px;">🎉</div>
                <h2 style="color: #00b894; font-size: 2em; margin-bottom: 10px;"><?php echo $success_message; ?></h2>
                <p style="color: #b2bec3; font-weight: 500; line-height: 1.6;">Your food is being prepared! Enjoy those brownie batter donuts.</p>
                <div style="margin-top: 40px;">
                    <a href="index.php" class="btn btn-main">Return to Dashboard</a>
                    <a href="past_orders.php" class="btn btn-outline">View Order History</a>
                </div>
            </div>
        <?php elseif (empty($cart)): ?>
            <div style="text-align: center; padding: 40px 0;">
                <h3 style="color: #b2bec3;">Your cart is empty.</h3>
                <br>
                <a href="concessions.php" class="btn btn-main">Browse Menu</a>
            </div>
        <?php else: ?>
            <?php foreach($cart as $index => $item): 
                $qty = $item['quantity'] ?? 1;
                $sub = $item['price'] * $qty;
                $subtotal += $sub;
            ?>
                <div class="cart-item">
                    <div style="display: flex; align-items: center;">
                        <div>
                            <strong style="display: block; font-size: 1.2em;"><?php echo htmlspecialchars($item['name']); ?></strong>
                            <?php if(!empty($item['notes'])): ?>
                                <div class="item-notes">📝 <?php echo htmlspecialchars($item['notes']); ?></div>
                            <?php endif; ?>
                            <small style="color: #b2bec3; font-weight: 700;">$<?php echo number_format($item['price'], 2); ?> each</small>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                            <input type="hidden" name="action" value="update_qty">
                            <input type="hidden" name="item_index" value="<?php echo $index; ?>">
                            <input type="number" name="quantity" class="qty-input" value="<?php echo $qty; ?>" min="0">
                            <button type="submit" style="background:none; border:none; color:<?php echo $accent; ?>; font-weight:800; cursor:pointer;">Set</button>
                        </form>
                        <div style="min-width: 90px; text-align: right;">
                            <strong style="font-size: 1.1em;">$<?php echo number_format($sub, 2); ?></strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="margin: 20px 0; padding: 20px; background: #fafbfc; border-radius: 14px; border: 1px solid #f1f2f6;">
                <?php if(isset($_SESSION['promo'])): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #00b894; font-weight: 800;">✅ <?php echo $_SESSION['promo']['discount_percent']; ?>% Off Applied! (<?php echo htmlspecialchars($_SESSION['promo']['code']); ?>)</span>
                        <form method="POST" style="margin: 0;">
                            <button type="submit" name="action" value="remove_promo" style="background: none; border: none; color: #d63031; cursor: pointer; font-weight: bold; text-decoration: underline;">Remove</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="POST" style="display: flex; gap: 10px; margin: 0;">
                        <input type="hidden" name="action" value="apply_promo">
                        <input type="text" name="promo_code" placeholder="Have a promo code?" style="flex: 1; padding: 12px; border-radius: 10px; border: 2px solid #e2e8f0; font-size: 1em;">
                        <button type="submit" class="btn btn-main" style="width: auto; padding: 10px 20px;">Apply</button>
                    </form>
                    <?php if($promo_error): ?>
                        <div style="color: #d63031; font-size: 0.9em; margin-top: 10px; font-weight: 600;"><?php echo htmlspecialchars($promo_error); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php 
                $discount = isset($_SESSION['promo']) ? $subtotal * ($_SESSION['promo']['discount_percent'] / 100) : 0;
                $final_total = $subtotal - $discount;
            ?>

            <div style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; color: #636e72; font-weight: 600; font-size: 1.1em; margin-bottom: 10px;">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <?php if($discount > 0): ?>
                    <div style="display: flex; justify-content: space-between; color: #00b894; font-weight: 700; font-size: 1.1em; margin-bottom: 10px;">
                        <span>Discount</span>
                        <span>-$<?php echo number_format($discount, 2); ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row">
                    <span style="font-weight: 800; color: #2d3436; font-size: 1.2em;">TOTAL DUE</span>
                    <span style="font-size: 2.2em; font-weight: 900; color: <?php echo $accent; ?>;">$<?php echo number_format($final_total, 2); ?></span>
                </div>
            </div>

            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="action" value="checkout" class="btn btn-main">Place Order</button>
                <button type="submit" name="action" value="clear" class="btn btn-outline">Empty Cart</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>