<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <a href="index.php">Home</a> | <a href="search.php">Find Venues</a> | <a href="login.php">Logout</a>
    </nav>

    <main>
        <h1>The Grand Ballroom</h1>
        <div class="venue-image" style="background:#ddd; height:200px; display:flex; align-items:center; justify-content:center;">
            [Placeholder Image of Venue]
        </div>

        <h3>Description</h3>
        <p>This is a beautiful venue located in the heart of downtown. Perfect for weddings and corporate events.</p>

        <h3>Amenities</h3>
        <ul>
            <li>Free WiFi</li>
            <li>Catering Available</li>
            <li>Parking On-site</li>
        </ul>

        <a href="booking.php"><button style="background-color:green; color:white; padding:10px 20px;">Book This Venue</button></a>
    </main>
</body>
</html>
