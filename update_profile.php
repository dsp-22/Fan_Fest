<?php
session_start();
include 'db_connect.php';

// check if form actually sent
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // need user id from session
    if (!isset($_SESSION['user_id'])) {
        die("login first");
    }

    $user_id = $_SESSION['user_id'];
    
    // clean inputs so it doesnt crash on names with apostrophes
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);

    // the actual update
    $sql = "UPDATE users SET first_name = '$first_name', bio = '$bio' WHERE id = '$user_id'";
    
    if (mysqli_query($conn, $sql)) {
        // back to profile
        header("Location: profile.php?update=success");
        exit();
    } else {
        die("error: " . mysqli_error($conn));
    }
} else {
    header("Location: profile.php");
    exit();
}
