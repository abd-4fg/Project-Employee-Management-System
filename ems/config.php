<?php
// config.php - Database configuration
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ems');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('Asia/Dubai');

// Company settings cache
$company_settings = [];
$settings_result = $conn->query("SELECT setting_key, setting_value FROM company_settings");
while ($row = $settings_result->fetch_assoc()) {
    $company_settings[$row['setting_key']] = $row['setting_value'];
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check user role
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['role'], $roles);
}

// Function to redirect if not authorized
function requireAuth() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireRole($roles) {
    requireAuth();
    if (!hasRole($roles)) {
        header("Location: dashboard.php");
        exit();
    }
}

// Function to log activities
function logActivity($user_id, $action, $details = null) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

// Function to add notification
function addNotification($user_id, $title, $message, $type = 'general', $link = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    $stmt->execute();
    $stmt->close();
}

// Function to get unread notification count
function getUnreadNotificationCount($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}

// Function to mark notifications as read
function markNotificationsRead($user_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Function to get company setting
function getCompanySetting($key) {
    global $company_settings;
    return $company_settings[$key] ?? '';
}

// Function to check if today is holiday
function isHoliday($date = null) {
    global $conn;
    if (!$date) $date = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM holidays WHERE holiday_date = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count > 0;
}
?>