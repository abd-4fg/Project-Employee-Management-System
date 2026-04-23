<?php
// dashboard.php - Role-based dashboard
require_once 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Common stats for all roles
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');

// Get today's attendance status
$attendance_today = $conn->query("SELECT * FROM attendance WHERE user_id = $user_id AND date = '$today'")->fetch_assoc();
$is_clocked_in = $attendance_today && !$attendance_today['clock_out_time'];

// Get notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");
$unread_count = getUnreadNotificationCount($user_id);

// Role-specific data
if ($role == 'admin') {
    // Total employees
    $total_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch_assoc()['count'];
    $active_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee' AND is_active = 1")->fetch_assoc()['count'];
    $inactive_employees = $total_employees - $active_employees;
    
    // Today's attendance summary
    $today_present = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'present'")->fetch_assoc()['count'];
    $today_late = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'late'")->fetch_assoc()['count'];
    $today_absent = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'absent'")->fetch_assoc()['count'];
    
    // Pending leave requests
    $pending_leaves = $conn->query("SELECT COUNT(*) as count FROM leave_applications WHERE status = 'pending'")->fetch_assoc()['count'];
    
    // Payroll status
    $payroll_processed = $conn->query("SELECT COUNT(*) as count FROM payroll WHERE month = $current_month AND year = $current_year AND status = 'paid'")->fetch_assoc()['count'];
    $total_employees_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee' AND is_active = 1")->fetch_assoc()['count'];
    $payroll_pending = $total_employees_count - $payroll_processed;
    
    // Recent leave requests
    $recent_leaves = $conn->query("
        SELECT la.*, u.first_name, u.last_name, lt.name as leave_type_name
        FROM leave_applications la
        JOIN users u ON la.user_id = u.user_id
        JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
        WHERE la.status = 'pending'
        ORDER BY la.applied_date DESC
        LIMIT 5
    ");
    
    // Recent activities
    $activities = $conn->query("
        SELECT al.*, u.first_name, u.last_name 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.user_id 
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    
} elseif ($role == 'hr') {
    // Total employees
    $total_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch_assoc()['count'];
    $new_hires = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee' AND MONTH(hire_date) = $current_month AND YEAR(hire_date) = $current_year")->fetch_assoc()['count'];
    
    // Pending approvals
    $pending_leaves = $conn->query("SELECT COUNT(*) as count FROM leave_applications WHERE status = 'pending'")->fetch_assoc()['count'];
    $pending_manual_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE manual_entry = 1 AND approved_by IS NULL")->fetch_assoc()['count'];
    
    // Today's attendance
    $today_present = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'present'")->fetch_assoc()['count'];
    $today_late = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'late'")->fetch_assoc()['count'];
    
    // Recent leave requests
    $recent_leaves = $conn->query("
        SELECT la.*, u.first_name, u.last_name, lt.name as leave_type_name
        FROM leave_applications la
        JOIN users u ON la.user_id = u.user_id
        JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
        WHERE la.status = 'pending'
        ORDER BY la.applied_date DESC
        LIMIT 5
    ");
    
} else {
    // Employee stats
    // Get attendance summary for current month
    $month_attendance = $conn->query("
        SELECT 
            COALESCE(COUNT(*), 0) as total,
            COALESCE(SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END), 0) as present,
            COALESCE(SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END), 0) as late,
            COALESCE(SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END), 0) as absent
        FROM attendance 
        WHERE user_id = $user_id 
        AND MONTH(date) = $current_month 
        AND YEAR(date) = $current_year
    ")->fetch_assoc();
    
    // Get leave balances
    $leave_balances = $conn->query("
        SELECT lb.*, lt.name as leave_type_name, lt.days_per_year
        FROM leave_balances lb
        JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
        WHERE lb.user_id = $user_id AND lb.year = $current_year
    ");
    
    // Get pending leave requests
    $pending_leaves = $conn->query("
        SELECT COALESCE(COUNT(*), 0) as count 
        FROM leave_applications 
        WHERE user_id = $user_id AND status = 'pending'
    ")->fetch_assoc()['count'];
    
    // Get recent payslip
    $last_payslip = $conn->query("
        SELECT * FROM payroll 
        WHERE user_id = $user_id AND status = 'paid'
        ORDER BY year DESC, month DESC 
        LIMIT 1
    ")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - EMS</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            width: 260px;
            background: #2c3e50;
            color: white;
            transition: all 0.3s;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }
        .sidebar-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.7;
            margin: 5px 0 0;
        }
        .sidebar-menu {
            padding: 20px 0;
        }
        .sidebar-menu ul {
            list-style: none;
        }
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        .sidebar-menu li a {
            display: block;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu li a:hover {
            background: #34495e;
            padding-left: 25px;
        }
        .sidebar-menu li a.active {
            background: #1abc9c;
            color: white;
        }
        .sidebar-menu li a i {
            margin-right: 10px;
            width: 20px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }
        
        /* Top Navbar */
        .top-navbar {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-text h4 {
            margin: 0;
            color: #2c3e50;
        }
        .welcome-text p {
            margin: 5px 0 0;
            color: #7f8c8d;
            font-size: 12px;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .notification-badge {
            position: relative;
            cursor: pointer;
        }
        .notification-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #1abc9c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 40px;
            float: right;
            opacity: 0.3;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin: 0;
        }
        .stat-label {
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        /* Panel Styles */
        .panel {
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .panel-heading {
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            font-weight: bold;
        }
        .panel-body {
            padding: 20px;
        }
        
        /* Clock Button */
        .clock-btn {
            padding: 15px 30px;
            font-size: 18px;
            border-radius: 50px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -260px;
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.active {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p><?php echo getCompanySetting('company_name'); ?></p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php" class="active"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                
                <?php if ($role != 'employee'): ?>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <?php endif; ?>
                
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <?php if ($role == 'employee'): ?>
                <li><a href="my_payslips.php"><i class="fa fa-money"></i> My Payslips</a></li>
                <?php else: ?>
                <li><a href="payroll.php"><i class="fa fa-money"></i> Payroll</a></li>
                <?php endif; ?>   
                <?php if ($role != 'employee'): ?>
                <li><a href="performance.php"><i class="fa fa-star"></i> Performance</a></li>
                <li><a href="reports.php"><i class="fa fa-bar-chart"></i> Reports</a></li>
                <?php else: ?>
                <li><a href="performance_employee.php"><i class="fa fa-star"></i> Performance</a></li>
                <?php endif; ?>
                
                <?php if ($role == 'admin'): ?>
                <li><a href="settings.php"><i class="fa fa-cogs"></i> Settings</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="welcome-text">
                <h4>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>!</h4>
                <p><?php echo ucfirst($role); ?> | <?php echo htmlspecialchars($_SESSION['department'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($_SESSION['job_title'] ?? 'N/A'); ?></p>
            </div>
            <div class="user-menu">
                <div class="notification-badge" onclick="location.href='notifications.php'">
                    <i class="fa fa-bell fa-lg"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-info" onclick="location.href='profile.php'">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)) . strtoupper(substr($_SESSION['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong><br>
                        <small><?php echo ucfirst($role); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($role == 'admin'): ?>
        <!-- ADMIN DASHBOARD -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-users"></i></div>
                    <div class="stat-number"><?php echo $total_employees ?? 0; ?></div>
                    <div class="stat-label">Total Employees</div>
                    <small>Active: <?php echo $active_employees ?? 0; ?> | Inactive: <?php echo $inactive_employees ?? 0; ?></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-clock-o"></i></div>
                    <div class="stat-number"><?php echo ($today_present ?? 0) . ' / ' . ($total_employees ?? 0); ?></div>
                    <div class="stat-label">Present Today</div>
                    <small>Late: <?php echo $today_late ?? 0; ?> | Absent: <?php echo $today_absent ?? 0; ?></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-calendar-check-o"></i></div>
                    <div class="stat-number"><?php echo $pending_leaves ?? 0; ?></div>
                    <div class="stat-label">Pending Leave Requests</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-money"></i></div>
                    <div class="stat-number"><?php echo ($payroll_processed ?? 0) . ' / ' . (($payroll_processed ?? 0) + ($payroll_pending ?? 0)); ?></div>
                    <div class="stat-label">Payroll Processed</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-primary">
                    <div class="panel-heading">Pending Leave Requests</div>
                    <div class="panel-body">
                        <?php if ($recent_leaves && $recent_leaves->num_rows > 0): ?>
                            <table class="table table-bordered">
                                <thead>
                                    <tr><th>Employee</th><th>Leave Type</th><th>Duration</th><th>Days</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($leave = $recent_leaves->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                        <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d', strtotime($leave['end_date'])); ?></td>
                                        <td><?php echo $leave['total_days']; ?></td>
                                        <td><a href="leave_approve.php?id=<?php echo $leave['leave_id']; ?>" class="btn btn-xs btn-primary">Review</a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No pending leave requests.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading">Recent Activity Log</div>
                    <div class="panel-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if ($activities && $activities->num_rows > 0): ?>
                            <?php while($log = $activities->fetch_assoc()): ?>
                                <div style="border-bottom: 1px solid #eee; padding: 8px 0;">
                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></small>
                                    <div><strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong> - <?php echo htmlspecialchars($log['action']); ?></div>
                                    <small><?php echo htmlspecialchars($log['details']); ?></small>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>No recent activities.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif ($role == 'hr'): ?>
        <!-- HR DASHBOARD -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-users"></i></div>
                    <div class="stat-number"><?php echo $total_employees ?? 0; ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-user-plus"></i></div>
                    <div class="stat-number"><?php echo $new_hires ?? 0; ?></div>
                    <div class="stat-label">New Hires (This Month)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-clock-o"></i></div>
                    <div class="stat-number"><?php echo ($today_present ?? 0) . ' / ' . ($total_employees ?? 0); ?></div>
                    <div class="stat-label">Present Today</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-calendar-check-o"></i></div>
                    <div class="stat-number"><?php echo $pending_leaves ?? 0; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-primary">
                    <div class="panel-heading">Pending Leave Requests</div>
                    <div class="panel-body">
                        <?php if ($recent_leaves && $recent_leaves->num_rows > 0): ?>
                            <table class="table table-bordered">
                                <thead>
                                    <tr><th>Employee</th><th>Leave Type</th><th>Duration</th><th>Days</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($leave = $recent_leaves->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                        <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d', strtotime($leave['end_date'])); ?></td>
                                        <td><?php echo $leave['total_days']; ?></td>
                                        <td><a href="leave_approve.php?id=<?php echo $leave['leave_id']; ?>" class="btn btn-xs btn-primary">Review</a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No pending leave requests.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- EMPLOYEE DASHBOARD -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-success" style="text-align: center;">
                    <div class="panel-body">
                        <h3><?php echo date('l, F j, Y'); ?></h3>
                        <?php if (!$attendance_today): ?>
                            <a href="attendance_clock.php?action=in" class="btn btn-success clock-btn">
                                <i class="fa fa-sign-in"></i> Clock In
                            </a>
                        <?php elseif ($attendance_today && !$attendance_today['clock_out_time']): ?>
                            <div class="alert alert-info">
                                You clocked in at <?php echo date('h:i A', strtotime($attendance_today['clock_in_time'])); ?>
                            </div>
                            <a href="attendance_clock.php?action=out&id=<?php echo $attendance_today['attendance_id']; ?>" class="btn btn-danger clock-btn">
                                <i class="fa fa-sign-out"></i> Clock Out
                            </a>
                        <?php else: ?>
                            <div class="alert alert-success">
                                You completed your work day on <?php echo date('M d, Y'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-calendar"></i></div>
                    <div class="stat-number"><?php echo $month_attendance['present'] ?? 0; ?> / <?php echo $month_attendance['total'] ?? 0; ?></div>
                    <div class="stat-label">Days Present (This Month)</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-umbrella"></i></div>
                    <div class="stat-number"><?php echo $pending_leaves ?? 0; ?></div>
                    <div class="stat-label">Pending Leave Requests</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-file-text-o"></i></div>
                    <div class="stat-number"><?php echo $last_payslip ? date('F Y', mktime(0,0,0,$last_payslip['month'],1,$last_payslip['year'])) : 'N/A'; ?></div>
                    <div class="stat-label">Last Payslip</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading">Leave Balances</div>
                    <div class="panel-body">
                        <?php if ($leave_balances && $leave_balances->num_rows > 0): ?>
                            <?php while($balance = $leave_balances->fetch_assoc()): ?>
                                <div style="margin-bottom: 15px;">
                                    <strong><?php echo htmlspecialchars($balance['leave_type_name']); ?>:</strong>
                                    <span class="pull-right"><?php echo $balance['remaining_days']; ?> / <?php echo $balance['days_per_year']; ?> days</span>
                                    <div class="progress" style="margin: 5px 0;">
                                        <?php $percentage = ($balance['days_per_year'] > 0) ? ($balance['used_days'] / $balance['days_per_year']) * 100 : 0; ?>
                                        <div class="progress-bar progress-bar-success" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>No leave balances found.</p>
                        <?php endif; ?>
                        <a href="leave.php" class="btn btn-primary btn-sm">Apply for Leave</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-warning">
                    <div class="panel-heading">Recent Notifications</div>
                    <div class="panel-body" style="max-height: 250px; overflow-y: auto;">
                        <?php if ($notifications && $notifications->num_rows > 0): ?>
                            <?php while($notif = $notifications->fetch_assoc()): ?>
                                <div style="border-bottom: 1px solid #eee; padding: 8px 0;">
                                    <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                    <p style="margin: 5px 0 0; font-size: 12px;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></small>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>No notifications</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>