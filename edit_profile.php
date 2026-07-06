<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';
include 'header.php';

// Force session check
if (!isset($_SESSION['user_id'])) {
    echo "<div style='padding:20px;text-align:center;'>Please <a href='login.php'>login</a>.</div>";
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// HANDLE FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Safe inputs
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $bio      = isset($_POST['bio']) ? trim($_POST['bio']) : '';
    $seat     = isset($_POST['seat_location']) ? trim($_POST['seat_location']) : '';
    $team     = isset($_POST['favorite_team']) ? trim($_POST['favorite_team']) : '';
    $theme    = isset($_POST['theme_color']) ? $_POST['theme_color'] : 'red';

    // Check Duplicate
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "Username taken.";
    } else {
        // Update
        $up = $conn->prepare("UPDATE users SET username=?, bio=?, seat_location=?, favorite_team=?, theme_color=? WHERE id=?");
        $up->bind_param("sssssi", $username, $bio, $seat, $team, $theme, $user_id);
        
        if ($up->execute()) {
            echo "<script>window.location.href='profile.php';</script>";
            exit();
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}

// GET DATA
$res = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $res->fetch_assoc();

// Safe Variables
$u_name = isset($user['username']) ? $user['username'] : '';
$u_bio  = isset($user['bio']) ? $user['bio'] : '';
$u_seat = isset($user['seat_location']) ? $user['seat_location'] : '';
$u_team = isset($user['favorite_team']) ? $user['favorite_team'] : '';
$u_theme = isset($user['theme_color']) ? $user['theme_color'] : 'red';
?>

<style>
.edit-container { max-width: 500px; margin: 40px auto; padding: 20px; background: white; border: 1px solid #ddd; }
input, textarea, select { width: 100%; padding: 10px; margin-bottom: 15px; box-sizing: border-box; }
button { width: 100%; padding: 10px; background: #0984e3; color: white; border: none; cursor: pointer; }
</style>

<div class="main-container">
    <div class="edit-container">
        <h2>Edit Profile</h2>
        <?php if($message) echo "<p style='color:red'>$message</p>"; ?>
        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($u_name); ?>" required>
            
            <label>Bio</label>
            <textarea name="bio"><?php echo htmlspecialchars($u_bio); ?></textarea>
            
            <label>Seat</label>
            <input type="text" name="seat_location" value="<?php echo htmlspecialchars($u_seat); ?>">
            
            <label>Team</label>
            <input type="text" name="favorite_team" value="<?php echo htmlspecialchars($u_team); ?>">
            
            <label>Theme</label>
            <select name="theme_color">
                <option value="red" <?php if($u_theme=='red') echo 'selected'; ?>>Red</option>
                <option value="blue" <?php if($u_theme=='blue') echo 'selected'; ?>>Blue</option>
                <option value="gold" <?php if($u_theme=='gold') echo 'selected'; ?>>Gold</option>
                <option value="black" <?php if($u_theme=='black') echo 'selected'; ?>>Dark</option>
            </select>
            
            <button type="submit">Save Changes</button>
            <a href="profile.php" style="display:block; text-align:center; margin-top:10px;">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
