<?php
require_once 'config/config.php';

// Clear session
$_SESSION = array();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

header('Location: login.php');
exit();
?>