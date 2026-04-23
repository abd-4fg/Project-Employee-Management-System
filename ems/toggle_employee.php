<?php
// toggle_employee.php - Activate/Deactivate employee
require_once 'config.php';
requireRole(['admin', 'hr']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid employee ID";
    header("Location: employees.php");
    exit();
}

$user_id = (int)$_GET['id'];
$action = $_GET['action'] ?? '';

// Don't allow deactivating yourself
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = "You cannot change your own status";
    header("Location: employees.php");
    exit();
}

if ($action == 'deactivate') {
    $is_active = 0;
    $action_text = "deactivated";
} elseif ($action == 'activate') {
    $is_active = 1;
    $action_text = "activated";
} else {
    $_SESSION['error'] = "Invalid action";
    header("Location: employees.php");
    exit();
}

$stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
$stmt->bind_param("ii", $is_active, $user_id);

if ($stmt->execute()) {
    // Get employee name for notification
    $emp_result = $conn->query("SELECT first_name, last_name, email FROM users WHERE user_id = $user_id");
    $employee = $emp_result->fetch_assoc();
    
    logActivity($_SESSION['user_id'], 'toggle_employee', ucfirst($action_text) . " employee: " . $employee['first_name'] . ' ' . $employee['last_name']);
    
    // Notify the employee
    $status_text = $action == 'activate' ? 'activated' : 'deactivated';
    addNotification($user_id, 'Account ' . ucfirst($status_text), 
        "Your account has been {$status_text} by HR. " . ($action == 'deactivate' ? 'Please contact HR for assistance.' : 'You can now log in again.'), 
        'account');
    
    $_SESSION['success'] = "Employee " . $action_text . " successfully";
} else {
    $_SESSION['error'] = "Failed to " . $action . " employee";
}

$stmt->close();
header("Location: employees.php");
exit();
?>