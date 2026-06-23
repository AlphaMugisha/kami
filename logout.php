<?php
// 1. Start the session to access it
session_start();

// 2. Unset all of the session variables
$_SESSION = array();

// 3. Destroy the session entirely
session_destroy();

// 4. Redirect to login.php using a clean relative path (NO forward slash!)
header("Location: login.php");
exit();
?>