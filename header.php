<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_guest = (isset($_SESSION['user_id']) && $_SESSION['user_id'] === 'guest');

if (isset($_SESSION['user_id']) && !$is_guest && !isset($_SESSION['profile_pic'])) {
    if (!isset($conn)) require_once 'db_connect.php';
    $pid = $_SESSION['user_id'];
    $row = $conn->query("SELECT profile_pic FROM users WHERE id = $pid")->fetch_assoc();
    $_SESSION['profile_pic'] = $row['profile_pic'] ?? '';
}

$nav_accent = $accent ?? '#0984e3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FanFest - Stadium Command Center</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        body { margin: 0; font-family: 'Inter', sans-serif; }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fff;
            padding: 0 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-bottom: 1px solid #f1f2f6;
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 70px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .navbar a {
            text-decoration: none;
            color: #2d3436;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s;
        }

        .navbar a:hover { color: <?php echo $nav_accent; ?>; }

        .profile-btn {
            width: 38px;
            height: 38px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid <?php echo $nav_accent; ?>;
            transition: transform 0.2s;
            overflow: hidden;
        }
        .profile-btn:hover { transform: scale(1.05); }
        .profile-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* Gatekeeper Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
        .guest-modal { background: white; padding: 30px; border-radius: 16px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .guest-modal h2 { margin-top: 0; color: #1a1a2e; }
        .guest-modal p { color: #636e72; margin-bottom: 25px; line-height: 1.5; }
        .btn-modal-primary { display: block; width: 100%; background: <?php echo $nav_accent; ?>; color: white; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-bottom: 10px; box-sizing: border-box; border: none; font-size: 14px; }
        .btn-modal-secondary { display: block; width: 100%; background: #f1f2f6; color: #2d3436; padding: 12px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; box-sizing: border-box; font-size: 14px; }
    </style>
</head>
<body>

<nav class="navbar">

    <div class="nav-left" style="display: flex; align-items: center; height: 100%;">
        <a href="index.php" style="display: flex; align-items: center;">
            <img src="images/logo2.png" alt="FanFest Logo" style="height: 48px; width: auto; display: block;">
        </a>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>

        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="checkin.php">Check-In</a>
            <a href="maps.php">Maps</a>
            <a href="concessions.php">Order</a>
            <a href="rewards.php" class="rewards-link">Rewards</a>
            <a href="friends.php">Friends</a>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="users.php">Users</a>
            <?php endif; ?>

            <a href="team.php">About</a>
        </div>

        <div class="nav-right">
            <?php if ($is_guest): ?>
                <a href="login.php" style="background: #2d3436; color: #fff; padding: 8px 18px; border-radius: 8px; font-weight: 700; font-size: 12px; text-transform: uppercase;">Sign Up</a>
            <?php else: ?>
                <a href="profile.php" class="profile-btn">
                    <?php
                    $pic = $_SESSION['profile_pic'] ?? '';
                    if (!empty($pic) && $pic !== 'default_avatar.png' && file_exists($pic)):
                    ?>
                        <img src="<?php echo htmlspecialchars($pic); ?>" alt="Profile">
                    <?php else: ?>
                        <span style="font-size: 1.1em;">👤</span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <div class="nav-links">
            <a href="login.php">Home</a>
            <a href="team.php">About</a>
        </div>

        <div class="nav-right">
            <a href="register.php" style="background: #2d3436; color: #fff; padding: 8px 18px; border-radius: 8px; font-weight: 700; font-size: 12px; text-transform: uppercase;">Sign Up</a>
        </div>

    <?php endif; ?>

</nav>

<div id="guestModal" class="modal-overlay" style="display: none;">
    <div class="guest-modal">
        <h2>Unlock Features</h2>
        <p id="guestModalMessage">To save your progress, please log in or create an account.</p>
        <a href="login.php" class="btn-modal-primary">Log In / Sign Up</a>
        <button class="btn-modal-secondary" onclick="document.getElementById('guestModal').style.display='none'">Maybe Later</button>
    </div>
</div>

<script>
function requireLogin(customMessage) {
    document.getElementById('guestModalMessage').innerText = "To " + customMessage + ", create an account or log in.";
    document.getElementById('guestModal').style.display = 'flex';
}
</script>
