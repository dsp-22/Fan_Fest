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
    <title>Book Venue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <a href="index.php">Home</a> | <a href="search.php">Find Venues</a> | <a href="login.php">Logout</a>
    </nav>

    <main>
        <h1>Book: The Grand Ballroom</h1>

        <form>
            <label>Event Date:</label><br>
            <input type="date"><br><br>

            <label>Start Time:</label><br>
            <input type="time"><br><br>

            <label>Number of Guests:</label><br>
            <input type="number" placeholder="e.g. 100"><br><br>

            <label>Special Requests:</label><br>
            <textarea rows="4" style="width:100%"></textarea><br><br>

            <button type="button" onclick="alert('In the final version, this will save the booking!')">Confirm Booking</button>
        </form>
    </main>
</body>
</html>
