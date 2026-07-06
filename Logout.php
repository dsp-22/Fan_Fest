<?php
session_start();
session_unset();
session_destroy();

// The "?v=cleared" tricks the browser into bypassing its cached redirect loop
header("Location: login.php?v=cleared"); 
exit();
?>
