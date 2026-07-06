<?php
session_start();
// Enable error reporting to debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

$message = "";

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_id'] === 'guest') {
        // Break the loop: clear the guest data so the login form actually loads
        session_unset(); 
    } else {
        header("Location: index.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- GUEST LOGIN LOGIC ---
    if (isset($_POST['guest_login'])) {
        $_SESSION['user_id'] = 'guest'; 
        $_SESSION['first_name'] = 'Guest';
        $_SESSION['username'] = 'guest_fan';
        $_SESSION['role'] = 'guest';
        $_SESSION['profile_pic'] = 'default_avatar.png';
        header("Location: index.php");
        exit();
    }
    // -------------------------

    include 'db_connect.php'; 

    $email = $_POST['email'];
    $password = $_POST['password'];

    // 1. Get the user from the database
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // 2. Verify Password
        if ($user && password_verify($password, $user['password'])) {
            
            // Login Success! Save data to session
            $_SESSION['user_id'] = $user['id']; 
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['username'] = $user['username']; 
            $_SESSION['role'] = $user['role'];          
            
            // Redirect to dashboard
            header("Location: index.php");
            exit();
        } else {
            $message = "Invalid email or password.";
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
    }
}
?>

<?php include 'header.php'; ?>

    <div class="hero-banner" style="padding-bottom: 80px;">
        <h1>Welcome Back</h1>
        <p>Sign in to access your dashboard</p>
    </div>

    <div class="main-container narrow-container">
        <div class="card">
            <?php if($message): ?>
                <p style="color: red; text-align: center;"><?php echo $message; ?></p>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="prof@iu.edu">

                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••">

                <button type="submit">Sign In</button>
                <button type="submit" name="guest_login" formnovalidate style="margin-top: 10px; background: #f1f2f6; color: #2d3436; border: 1px solid #dfe6e9; font-weight: 700;">Continue as Guest</button>
            </form>
            <div style="text-align:center; margin-top: 12px;">
                <a href="forgot_password.php" style="color:#0984e3; font-size:13px; font-weight:600; text-decoration:none;">Forgot your password?</a>
            </div>
            <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                <p>Don't have an account yet?</p>
                <a href="register.php" class="button-secondary" style="text-decoration: none; color: #0984e3; font-weight: bold;">Create an Account</a>
            </div>
        </div>
    </div>
</body>
</html>
