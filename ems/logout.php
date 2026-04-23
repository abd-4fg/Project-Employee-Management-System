<?php
// logout.php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>