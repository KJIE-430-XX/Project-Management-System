<?php
session_start();

// Clear session data
 $_SESSION = [];
 // Expire the session cookie
 if (ini_get('session.use_cookies')) {
     $params = session_get_cookie_params();
     setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
 }
 
// Destroy session
session_destroy();

// Redirect to login
header("Location: login.php");
exit;
?>
