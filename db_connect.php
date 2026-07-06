<?php
// For local testing: explicitly set Railway credentials
// On Railway, these env vars will override
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');
$database = getenv('MYSQLDATABASE');
$port = getenv('MYSQLPORT');

// Fallback to explicit values if env vars are empty
if (!$host) $host = 'thomas.proxy.rlwy.net';
if (!$user) $user = 'root';
if (!$password) $password = 'ALsrkxBauUCRQqcCijfRQrkjTytEkDxB';
if (!$database) $database = 'railway';
if (!$port) $port = 10301;

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Disable strict SQL mode for compatibility
$conn->query("SET SESSION sql_mode = ''");
?>
