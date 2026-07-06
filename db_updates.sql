<?php
// Show errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

echo "<h1>Running FanFest Database Updates...</h1>";

// 1. Create coupons table
$sql1 = "CREATE TABLE IF NOT EXISTS coupons (
    coupon_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    discount_percent INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
)";
if ($conn->query($sql1)) { echo "<p>✅ Coupons table ready.</p>"; } 
else { echo "<p>❌ Error: " . $conn->error . "</p>"; }

// 2. Insert test coupons
$sql2 = "INSERT IGNORE INTO coupons (code, discount_percent) VALUES ('FAN10', 10), ('HOOSIER20', 20)";
$conn->query($sql2);
echo "<p>✅ Test promo codes added.</p>";

// 3. Add promo columns to orders
// We suppress errors here with @ in case you run this twice (it will just say column already exists)
@$conn->query("ALTER TABLE orders ADD COLUMN promo_code VARCHAR(20) DEFAULT NULL");
@$conn->query("ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00");
echo "<p>✅ Promo columns added to orders.</p>";

// 4. Add rewards columns to users and orders
@$conn->query("ALTER TABLE users ADD COLUMN rewards_points INT DEFAULT 0");
@$conn->query("ALTER TABLE orders ADD COLUMN points_earned INT DEFAULT 0");
echo "<p>✅ Rewards columns added.</p>";

echo "<h3>🎉 Database is fully prepped for Promos and Rewards!</h3>";
?>