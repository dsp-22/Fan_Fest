<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$u_id = $_SESSION['user_id'];

// theme
$theme_data = $conn->query("SELECT theme_color FROM users WHERE id = $u_id")->fetch_assoc();
$user_theme = $theme_data['theme_color'] ?? 'blue';
$accent_map = ['blue' => '#0984e3', 'red' => '#990000', 'gold' => '#ceb888', 'black' => '#2d3436'];
$accent = $accent_map[$user_theme] ?? '#0984e3';
$theme_map = [
    'blue'  => 'linear-gradient(135deg, #0984e3, #6c5ce7)',
    'red'   => 'linear-gradient(135deg, #990000, #660000)',
    'gold'  => 'linear-gradient(135deg, #ceb888, #000000)',
    'black' => 'linear-gradient(135deg, #2d3436, #000000)',
];
$active_gradient = $theme_map[$user_theme] ?? $theme_map['blue'];

// pull all orders newest first
$stmt = $conn->prepare("SELECT order_id, items_json, total_price, order_date, order_notes FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->bind_param("i", $u_id);
$stmt->execute();
$orders_result = $stmt->get_result();

$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $row['items'] = json_decode($row['items_json'], true) ?? [];
    $orders[] = $row;
}
$stmt->close();

$total_spent = array_sum(array_column($orders, 'total_price'));
$order_count = count($orders);

// this month spend
$current_month = date('Y-m');
$month_spent = 0;
$month_count = 0;
foreach ($orders as $o) {
    if (date('Y-m', strtotime($o['order_date'])) === $current_month) {
        $month_spent += $o['total_price'];
        $month_count++;
    }
}
?>
<?php include 'header.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #f4f6f9; color: #2d3436; margin: 0; }

    .hero-banner {
        background: <?php echo $active_gradient; ?>;
        color: white;
        padding: 60px 20px 100px 20px;
        text-align: center;
        margin-bottom: -60px;
    }
    .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }
    .hero-banner p  { margin: 10px 0 0; color: rgba(255,255,255,0.7); font-size: 1em; }

    .main-container { width: 95%; max-width: 900px; margin: 0 auto 100px auto; position: relative; z-index: 5; }

    /* stats */
    .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px; }
    .stat-card { background: white; border-radius: 14px; padding: 20px 22px; box-shadow: 0 8px 20px rgba(0,0,0,0.07); border-left: 4px solid <?php echo $accent; ?>; }
    .stat-val { font-size: 26px; font-weight: 800; color: #2d3436; margin-bottom: 4px; line-height: 1; }
    .stat-val small { font-size: 14px; font-weight: 600; color: <?php echo $accent; ?>; margin-right: 2px; }
    .stat-lbl { font-size: 11px; color: #636e72; margin-top: 4px; }
    .stat-sub { font-size: 10px; color: #b2bec3; margin-top: 3px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }

    /* order cards */
    .order-card { background: white; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); margin-bottom: 14px; overflow: hidden; transition: box-shadow 0.2s; }
    .order-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.1); }

    .order-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; cursor: pointer; user-select: none; transition: background 0.15s; }
    .order-header:hover { background: #fafbfc; }

    .order-left { display: flex; align-items: center; gap: 16px; }
    .order-icon { width: 42px; height: 42px; border-radius: 12px; background: <?php echo $accent; ?>18; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .order-num  { font-size: 15px; font-weight: 800; color: #2d3436; margin-bottom: 3px; }
    .order-date { font-size: 12px; color: #b2bec3; }

    .order-right { display: flex; align-items: center; gap: 14px; }
    .order-total { font-size: 18px; font-weight: 800; color: <?php echo $accent; ?>; }
    .order-chevron { width: 28px; height: 28px; border-radius: 50%; background: #f1f2f6; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #b2bec3; transition: transform 0.2s, background 0.2s; flex-shrink: 0; }
    .order-chevron.open { transform: rotate(180deg); background: <?php echo $accent; ?>18; color: <?php echo $accent; ?>; }

    /* items dropdown */
    .order-items { display: none; border-top: 1px solid #f1f2f6; padding: 16px 24px 20px; }
    .order-items.open { display: block; }

    .order-item-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f8f9fa; font-size: 14px; }
    .order-item-row:last-of-type { border-bottom: none; }
    .item-name  { font-weight: 600; color: #2d3436; margin-bottom: 2px; }
    .item-meta  { font-size: 12px; color: #b2bec3; }
    .item-notes-disp { font-size: 12px; color: #e17055; font-weight: 600; margin-top: 3px; }
    .item-price { font-weight: 700; color: #2d3436; }

    .btn-reorder { margin-top: 14px; background: <?php echo $accent; ?>; color: white; border: none; border-radius: 10px; padding: 10px 22px; font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity 0.2s; }
    .btn-reorder:hover { opacity: 0.85; }

    /* empty state */
    .empty-state { background: white; border-radius: 16px; padding: 70px 20px; text-align: center; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
    .empty-icon { font-size: 52px; margin-bottom: 16px; }
    .empty-state h3 { color: #b2bec3; margin: 0 0 8px; font-size: 1.3em; }
    .empty-state p  { color: #b2bec3; font-size: 14px; margin: 0 0 24px; }
    .btn-browse { display: inline-block; background: <?php echo $accent; ?>; color: white; border-radius: 10px; padding: 12px 28px; font-weight: 700; font-size: 14px; text-decoration: none; transition: opacity 0.2s; }
    .btn-browse:hover { opacity: 0.85; color: white; }

    @media (max-width: 700px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 400px) { .stats-row { grid-template-columns: 1fr; } }
</style>

<div class="hero-banner">
    <h1>Order History</h1>
    <p>Everything you've ordered through FanFest.</p>
</div>

<div class="main-container">

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-val"><?php echo $order_count; ?></div>
            <div class="stat-lbl">Total orders placed</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><small>$</small><?php echo number_format($total_spent, 2); ?></div>
            <div class="stat-lbl">Total spent</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">
                <small>$</small><?php echo $order_count > 0 ? number_format($total_spent / $order_count, 2) : '0.00'; ?>
            </div>
            <div class="stat-lbl">Average order</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><small>$</small><?php echo number_format($month_spent, 2); ?></div>
            <div class="stat-lbl">This month</div>
            <div class="stat-sub"><?php echo $month_count; ?> order<?php echo $month_count !== 1 ? 's' : ''; ?> · <?php echo date('M Y'); ?></div>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-icon">🌭</div>
            <h3>No orders yet</h3>
            <p>You haven't placed any orders through FanFest.</p>
            <a href="concessions.php" class="btn-browse">Browse the Menu</a>
        </div>

    <?php else: ?>
        <?php foreach ($orders as $i => $order): ?>
        <div class="order-card">
            <div class="order-header" onclick="toggleOrder(<?php echo $i; ?>)">
                <div class="order-left">
                    <div class="order-icon">🧾</div>
                    <div>
                        <div class="order-num">Order #<?php echo $order['order_id']; ?></div>
                        <div class="order-date"><?php echo date("M j, Y · g:i A", strtotime($order['order_date'])); ?></div>
                    </div>
                </div>
                <div class="order-right">
                    <div class="order-total">$<?php echo number_format($order['total_price'], 2); ?></div>
                    <div class="order-chevron" id="chevron-<?php echo $i; ?>">▾</div>
                </div>
            </div>

            <div class="order-items" id="items-<?php echo $i; ?>">
                <?php foreach ($order['items'] as $item):
                    $qty   = $item['quantity'] ?? 1;
                    $price = $item['price'] * $qty;
                ?>
                <div class="order-item-row">
                    <div>
                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>

                        <?php if (!empty($item['notes'])): ?>
                            <div class="item-notes-disp">📝 <?php echo htmlspecialchars($item['notes']); ?></div>
                        <?php endif; ?>

                        <div class="item-meta">x<?php echo $qty; ?> &middot; $<?php echo number_format($item['price'], 2); ?> each</div>
                    </div>
                    <div class="item-price">$<?php echo number_format($price, 2); ?></div>
                </div>
                <?php endforeach; ?>

                <?php if (!empty($order['order_notes'])): ?>
                    <div style="background: #fff8e1; border-left: 4px solid #f1c40f; padding: 12px; border-radius: 4px; margin-top: 15px; font-size: 13px; color: #856404;">
                        <strong>📝 General Notes:</strong> <?php echo nl2br(htmlspecialchars($order['order_notes'])); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="cart.php">
                    <?php foreach ($order['items'] as $item):
                        $qty = $item['quantity'] ?? 1;
                    ?>
                        <input type="hidden" name="reorder_name[]"     value="<?php echo htmlspecialchars($item['name']); ?>">
                        <input type="hidden" name="reorder_price[]"    value="<?php echo $item['price']; ?>">
                        <input type="hidden" name="reorder_quantity[]" value="<?php echo $qty; ?>">
                        <input type="hidden" name="reorder_notes[]"    value="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="action" value="reorder">
                    <button type="submit" class="btn-reorder">🔁 Reorder this</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
function toggleOrder(i) {
    const items   = document.getElementById('items-' + i);
    const chevron = document.getElementById('chevron-' + i);
    items.classList.toggle('open');
    chevron.classList.toggle('open');
}
</script>

<?php include 'footer.php'; ?>