<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    logAction('User logged out', $_SESSION['user_id']);
}

// Destroy session
session_destroy();

// Redirect to home page
header('Location: index.php');
exit();
?>