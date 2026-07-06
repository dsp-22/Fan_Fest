<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

require 'db_connect.php';

$message = "";
$message_type = "";
$reset_link = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND username = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // generate a secure token
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $stmt->bind_param("ssi", $token, $expires, $user['id']);
        $stmt->execute();
        $stmt->close();

        $reset_link = "reset_password.php?token=" . $token;
        $message      = "Identity verified! Use the link below to reset your password. This link expires in 1 hour.";
        $message_type = "success";
    } else {
        $message      = "No account found with that email and username combination.";
        $message_type = "error";
    }
}

// theme — no user logged in so use default blue
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
    .reset-link-box { background: #f0f7ff; border: 2px solid <?php echo $accent; ?>; border-radius: 10px; padding: 16px; margin-top: 20px; text-align: center; }
    .reset-link-box p { margin: 0 0 10px; font-size: 13px; color: #636e72; }
    .reset-link-box a { display: inline-block; background: <?php echo $accent; ?>; color: white; padding: 11px 28px; border-radius: 8px; font-weight: 700; font-size: 14px; text-decoration: none; transition: opacity 0.2s; }
    .reset-link-box a:hover { opacity: 0.88; }
    .back-link { text-align: center; margin-top: 20px; font-size: 13px; }
    .back-link a { color: <?php echo $accent; ?>; font-weight: 600; text-decoration: none; }
</style>

<div class="hero-banner">
    <h1>Forgot Password</h1>
    <p>Verify your identity to reset your password</p>
</div>

<div class="main-container">
    <div class="card">

        <?php if ($message): ?>
            <div class="msg-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($reset_link): ?>
            <div class="reset-link-box">
                <p>Click the button below to set a new password:</p>
                <a href="<?php echo htmlspecialchars($reset_link); ?>">Reset My Password</a>
            </div>
        <?php else: ?>
            <p style="color:#636e72; font-size:14px; margin:0 0 20px;">Enter the email address and username associated with your account.</p>
            <form method="POST">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="you@example.com">

                <label>Username</label>
                <input type="text" name="username" required placeholder="your username">

                <button type="submit" class="btn-submit">Verify Identity</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">← Back to Sign In</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
