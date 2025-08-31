<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_NAME', '');

// Create connection
function getConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Flash message functions
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /index.php');
        exit();
    }
}

// Require admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /user/dashboard.php');
        exit();
    }
}

// Format currency
function formatCurrency($amount) {
    return 'Ksh. ' . number_format($amount, 2);
}

// Calculate maturity date (3 days from purchase)
function calculateMaturityDate($buyDate) {
    return date('Y-m-d H:i:s', strtotime($buyDate . ' +3 days'));
}

// Calculate profit (30%)
function calculateProfit($amount) {
    return $amount * 0.30;
}

// Check if share is matured
function isMatured($maturityDate) {
    return strtotime($maturityDate) <= time();
}

// Log action
function logAction($action, $userId, $details = null) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("INSERT INTO logs (action, user_id, details) VALUES (?, ?, ?)");
        $stmt->execute([$action, $userId, $details]);
    } catch(Exception $e) {
        // Silent fail for logging
    }
}
?>
