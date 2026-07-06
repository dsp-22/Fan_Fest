<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_id'] === 'guest') {
        // Destroy the guest session so they can actually sign up
        session_unset();
        session_destroy();
    } else {
        header("Location: index.php");
        exit();
    }
}

require 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match!";
    } else {
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "That email is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);

            if ($stmt->execute()) {
                header("Location: login.php?registered=true");
                exit();
            } else {
                $message = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// header.php handles the <html>, <head>, and the Home/About nav
include 'header.php'; 
?>

<div class="hero-banner" style="padding-bottom: 80px;">
    <h1>Join the Team</h1>
    <p>Create an account to access the dashboard</p>
</div>

<div class="main-container narrow-container" style="max-width: 480px; margin: 0 auto; padding-bottom: 100px;">
    <div class="card">
        <?php if($message): ?>
            <p style="color: #d63031; text-align: center; font-weight: 800; background: #fff5f5; padding: 12px; border-radius: 10px; border: 1px solid #fab1a0; font-size: 0.85em; margin-bottom: 25px;">
                ⚠️ <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="font-weight: 800; font-size: 0.75em; color: #b2bec3; display: block; margin-bottom: 8px;">FIRST NAME</label>
                    <input type="text" name="first_name" required placeholder="Jane" style="width: 100%; padding: 12px; border: 1px solid #dfe6e9; border-radius: 8px; box-sizing: border-box; font-family: inherit;">
                </div>
                <div>
                    <label style="font-weight: 800; font-size: 0.75em; color: #b2bec3; display: block; margin-bottom: 8px;">LAST NAME</label>
                    <input type="text" name="last_name" required placeholder="Doe" style="width: 100%; padding: 12px; border: 1px solid #dfe6e9; border-radius: 8px; box-sizing: border-box; font-family: inherit;">
                </div>
            </div>

            <label style="font-weight: 800; font-size: 0.75em; color: #b2bec3; display: block; margin-bottom: 8px;">EMAIL ADDRESS</label>
            <input type="email" name="email" required placeholder="jane@iu.edu" style="width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #dfe6e9; border-radius: 8px; box-sizing: border-box; font-family: inherit;">

            <label style="font-weight: 800; font-size: 0.75em; color: #b2bec3; display: block; margin-bottom: 8px;">PASSWORD</label>
            <input type="password" name="password" required placeholder="Min. 8 characters" style="width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #dfe6e9; border-radius: 8px; box-sizing: border-box; font-family: inherit;">
            
            <label style="font-weight: 800; font-size: 0.75em; color: #b2bec3; display: block; margin-bottom: 8px;">CONFIRM PASSWORD</label>
            <input type="password" name="confirm_password" required placeholder="Repeat password" style="width: 100%; padding: 12px; margin-bottom: 30px; border: 1px solid #dfe6e9; border-radius: 8px; box-sizing: border-box; font-family: inherit;">

            <button type="submit" style="width: 100%; padding: 14px; background-color: #2d3436; color: white; border: none; border-radius: 10px; font-weight: 800; font-size: 0.9em; cursor: pointer; transition: transform 0.2s;">CREATE ACCOUNT</button>
        </form>
        
        <p style="text-align: center; margin-top: 25px; font-size: 0.85em; color: #636e72; font-weight: 600;">
            Already a member? <a href="login.php" style="color: #0984e3; text-decoration: none; font-weight: 800;">Log in here</a>
        </p>
    </div>
</div>

</body>
</html>
