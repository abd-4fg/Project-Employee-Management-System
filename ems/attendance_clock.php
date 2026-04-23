<?php
// attendance_clock.php - Process clock in/out
require_once 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$attendance_id = $_POST['id'] ?? $_GET['id'] ?? 0;

$today = date('Y-m-d');
$current_time = date('Y-m-d H:i:s');
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

// Check if today is holiday
$is_holiday = isHoliday($today);

if ($action === 'in') {
    // Check if already clocked in today
    $check = $conn->prepare("SELECT attendance_id FROM attendance WHERE user_id = ? AND date = ?");
    $check->bind_param("is", $user_id, $today);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "You have already clocked in today";
        header("Location: attendance.php");
        exit();
    }
    
    // Determine status (check if late based on company working hours)
    $work_start = getCompanySetting('working_hours_start');
    $grace_minutes = (int)getCompanySetting('late_grace_minutes');
    $current_hour = date('H:i');
    
    $status = 'present';
    if (!$is_holiday) {
        $start_time = strtotime($work_start);
        $grace_time = strtotime("+$grace_minutes minutes", $start_time);
        if (strtotime($current_hour) > $grace_time) {
            $status = 'late';
        }
    } else {
        $status = 'holiday';
    }
    
    $stmt = $conn->prepare("
        INSERT INTO attendance (user_id, date, clock_in_time, clock_in_ip, status) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $user_id, $today, $current_time, $ip_address, $status);
    
    if ($stmt->execute()) {
        logActivity($user_id, 'clock_in', "Clocked in at $current_time");
        $_SESSION['success'] = "Clocked in successfully";
        addNotification($user_id, 'Clock In Successful', "You clocked in at " . date('h:i A'), 'attendance');
    } else {
        $_SESSION['error'] = "Failed to clock in";
    }
    $stmt->close();
    
} elseif ($action === 'out') {
    // Get attendance record
    $stmt = $conn->prepare("
        SELECT attendance_id, clock_in_time 
        FROM attendance 
        WHERE attendance_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $attendance_id, $user_id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$record) {
        $_SESSION['error'] = "Invalid attendance record";
        header("Location: attendance.php");
        exit();
    }
    
    // Calculate total hours
    $clock_in = new DateTime($record['clock_in_time']);
    $clock_out = new DateTime($current_time);
    $interval = $clock_in->diff($clock_out);
    $total_hours = $interval->h + ($interval->i / 60);
    
    // Calculate overtime (hours beyond 8)
    $overtime = max(0, $total_hours - 8);
    
    $update = $conn->prepare("
        UPDATE attendance 
        SET clock_out_time = ?, clock_out_ip = ?, total_hours = ?, overtime_hours = ?
        WHERE attendance_id = ? AND user_id = ?
    ");
    $update->bind_param("ssddii", $current_time, $ip_address, $total_hours, $overtime, $attendance_id, $user_id);
    
    if ($update->execute()) {
        logActivity($user_id, 'clock_out', "Clocked out at $current_time - Total hours: $total_hours");
        $_SESSION['success'] = "Clocked out successfully. Total hours: " . number_format($total_hours, 2);
        addNotification($user_id, 'Clock Out Successful', "You clocked out at " . date('h:i A') . ". Total hours: $total_hours", 'attendance');
    } else {
        $_SESSION['error'] = "Failed to clock out";
    }
    $update->close();
}

header("Location: attendance.php");
exit();
?>