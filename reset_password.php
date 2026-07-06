<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

require 'db_connect.php';

$token   = trim($_GET['token'] ?? '');
$message = "";
$message_type = "";
$valid_token  = false;
$user_id      = null;

if (empty($token)) {
    header("Location: forgot_password.php"); exit();
}

// check token is valid and not expired
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if ($user) {
    $valid_token = true;
    $user_id     = $user['id'];
} else {
    $message      = "This reset link is invalid or has expired. Please request a new one.";
    $message_type = "error";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_pass     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 6) {
        $message      = "Password must be at least 6 characters.";
        $message_type = "error";
    } elseif ($new_pass !== $confirm_pass) {
        $message      = "Passwords do not match.";
        $message_type = "error";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        $stmt->execute();
        $stmt->close();

        $message      = "Password updated successfully! You can now sign in.";
        $message_type = "success";
        $valid_token  = false;
    }
}

$accent          = '#0984e3';
$active_gradient = 'linear-gradient(135deg, #0984e3, #6c5ce7)';
?>
<?php include 'header.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #f4f6f9; margin: 0; }
    .hero-banner { background: <?php echo $active_gradient; ?>; color: white; padding: 60px 20px 100px 20px; text-align: center; margin-bottom: -60px; }
    .hero-banner h1 { margin: 0; font-weight: 800; letter-spacing: -1px; }
    .hero-banner p  { margin: 10px 0 0; color: rgba(255,255,255,0.7); }
    .main-container { width: 95%; max-width: 480px; margin: 0 auto 80px auto; position: relative; z-index: 5; }
    .card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 36px; }
    label { display: block; font-size: 11px; font-weight: 800; color: #b2bec3; letter-spacing: 0.07em; text-transform: uppercase; margin-bottom: 6px; margin-top: 16px; }
    input { width: 100%; padding: 11px 14px; border: 1.5px solid #dfe6e9; border-radius: 8px; font-size: 14px; font-family: inherit; box-sizing: border-box; transition: border-color 0.2s; outline: none; }
    input:focus { border-color: <?php echo $accent; ?>; }
    .btn-submit { width: 100%; background: <?php echo $accent; ?>; color: white; border: none; border-radius: 10px; padding: 13px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 22px; transition: opacity 0.2s; }
    .btn-submit:hover { opacity: 0.88; }
    .msg-success { background: #e8f5e9; color: #2e7d32; border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; font-weight: 600; }
    .msg-error   { background: #fdeaea; color: #c0392b; border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; font-weight: 600; }
    .back-link { text-align: center; margin-top: 20px; font-size: 13px; }
    .back-link a { color: <?php echo $accent; ?>; font-weight: 600; text-decoration: none; }
</style>

<div class="hero-banner">
    <h1>Reset Password</h1>
    <p>Enter your new password below</p>
</div>

<div class="main-container">
    <div class="card">

        <?php if ($message): ?>
            <div class="msg-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($valid_token): ?>
            <form method="POST">
                <label>New Password</label>
                <input type="password" name="password" required placeholder="At least 6 characters" minlength="6">

                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="Repeat your new password">

                <button type="submit" class="btn-submit">Set New Password</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <?php if ($message_type === 'success'): ?>
                <a href="login.php">Sign In Now →</a>
            <?php else: ?>
                <a href="forgot_password.php">← Request a new link</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
