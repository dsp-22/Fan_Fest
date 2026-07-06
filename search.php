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
    <title>Find a Venue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <a href="index.php">Home</a> | 
        <a href="search.php">Find Venues</a> | 
        <a href="users.php">Users</a> | 
        <a href="login.php">Logout</a>
    </nav>

    <main>
        <h1>Find Your Perfect Venue</h1>

        <div class="search-box">
            <input type="text" placeholder="City, State, or Zip...">
            <button>Search</button>
        </div>

        <hr>

        <div class="venue-list">
            <div class="venue-item">
                <h3>The Grand Ballroom</h3>
                <p>Downtown | Capacity: 200</p>
                <a href="venue.php"><button>View Details</button></a>
            </div>

            <div class="venue-item">
                <h3>Sunny Garden</h3>
                <p>West Side | Capacity: 50</p>
                <a href="venue.php"><button>View Details</button></a>
            </div>
        </div>
    </main>
</body>
</html>
