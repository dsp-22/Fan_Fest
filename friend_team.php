<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Linking Team as Friends...</h1>";

$my_id = 5;
$team_ids = [1, 6, 7]; 

$success = 0;
foreach ($team_ids as $f_id) {
    $low = min($my_id, $f_id);
    $high = max($my_id, $f_id);
    
    
    $conn->query("INSERT IGNORE INTO friends (user_id_1, user_id_2, status) VALUES ($low, $high, 'accepted')");
    $success++;
}

echo "<p>✅ You are now officially friends with your whole team in the database!</p>";
?>