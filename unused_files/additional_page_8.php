<?php
// 1. Find the session
session_start();

// 2. Clear all session variables (removes user_id, first_name, etc.)
session_unset();

// 3. Destroy the session itself (kills the session ID on the server)
session_destroy();

// 4. Redirect to the login page
header("Location: login.php");
exit();
?>