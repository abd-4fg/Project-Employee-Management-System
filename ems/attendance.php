<?php
// attendance.php - Attendance Management
require_once 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = '';
$success = '';

// Handle manual attendance entry (HR/Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_entry']) && $role != 'employee') {
    $emp_id = (int)$_POST['user_id'];
    $date = $_POST['date'];
    $clock_in = $_POST['clock_in'];
    $clock_out = $_POST['clock_out'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    // Calculate hours
    $clock_in_time = strtotime($clock_in);
    $clock_out_time = strtotime($clock_out);
    
    // Validate times
    if ($clock_out_time <= $clock_in_time) {
        $error = "Clock out time must be after clock in time";
    } elseif (empty($emp_id) || empty($date) || empty($clock_in) || empty($clock_out)) {
        $error = "All fields are required";
    } else {
        $total_hours = round(($clock_out_time - $clock_in_time) / 3600, 2);
        $overtime = max(0, $total_hours - 8);
        
        $stmt = $conn->prepare("
            INSERT INTO attendance (
                user_id, date, clock_in_time, clock_out_time, total_hours, 
                overtime_hours, status, manual_entry, approved_by, notes
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE
                clock_in_time = VALUES(clock_in_time), 
                clock_out_time = VALUES(clock_out_time),
                total_hours = VALUES(total_hours), 
                overtime_hours = VALUES(overtime_hours),
                status = VALUES(status), 
                manual_entry = 1, 
                approved_by = VALUES(approved_by),
                notes = VALUES(notes)
        ");
        
        $stmt->bind_param("isssddsss", 
            $emp_id,                // i - user_id
            $date,                  // s - date
            $clock_in,              // s - clock_in_time
            $clock_out,             // s - clock_out_time
            $total_hours,           // d - total_hours
            $overtime,              // d - overtime_hours
            $status,                // s - status
            $_SESSION['user_id'],   // i - approved_by
            $notes                  // s - notes
        );
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'manual_attendance', "Added manual attendance for user $emp_id on $date");
            $success = "Attendance recorded successfully";
        } else {
            $error = "Failed to record attendance: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get current month attendance
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if ($role == 'employee') {
    $attendance_records = $conn->prepare("
        SELECT * FROM attendance 
        WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
        ORDER BY date DESC
    ");
    $attendance_records->bind_param("iii", $user_id, $current_month, $current_year);
} else {
    // For HR/Admin, show all employees or filtered
    $filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
    if ($filter_user) {
        $attendance_records = $conn->prepare("
            SELECT a.*, u.first_name, u.last_name, u.employee_id 
            FROM attendance a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.user_id = ? AND MONTH(a.date) = ? AND YEAR(a.date) = ?
            ORDER BY a.date DESC
        ");
        $attendance_records->bind_param("iii", $filter_user, $current_month, $current_year);
    } else {
        $attendance_records = $conn->prepare("
            SELECT a.*, u.first_name, u.last_name, u.employee_id 
            FROM attendance a
            JOIN users u ON a.user_id = u.user_id
            WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?
            ORDER BY a.date DESC
        ");
        $attendance_records->bind_param("ii", $current_month, $current_year);
    }
}
$attendance_records->execute();
$attendance_list = $attendance_records->get_result();

// Get summary statistics with COALESCE to prevent NULL values
if ($role == 'employee') {
    $summary = $conn->prepare("
        SELECT 
            COALESCE(COUNT(*), 0) as total_days,
            COALESCE(SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END), 0) as present_days,
            COALESCE(SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END), 0) as late_days,
            COALESCE(SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END), 0) as absent_days,
            COALESCE(SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END), 0) as half_days,
            COALESCE(SUM(total_hours), 0) as total_hours,
            COALESCE(SUM(overtime_hours), 0) as total_overtime
        FROM attendance 
        WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
    ");
    $summary->bind_param("iii", $user_id, $current_month, $current_year);
} else {
    $filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
    if ($filter_user) {
        $summary = $conn->prepare("
            SELECT 
                COALESCE(COUNT(*), 0) as total_days,
                COALESCE(SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END), 0) as present_days,
                COALESCE(SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END), 0) as late_days,
                COALESCE(SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END), 0) as absent_days,
                COALESCE(SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END), 0) as half_days,
                COALESCE(SUM(total_hours), 0) as total_hours,
                COALESCE(SUM(overtime_hours), 0) as total_overtime
            FROM attendance 
            WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
        ");
        $summary->bind_param("iii", $filter_user, $current_month, $current_year);
    } else {
        $summary = $conn->prepare("
            SELECT 
                COALESCE(COUNT(*), 0) as total_days,
                COALESCE(SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END), 0) as present_days,
                COALESCE(SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END), 0) as late_days,
                COALESCE(SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END), 0) as absent_days,
                COALESCE(SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END), 0) as half_days,
                COALESCE(SUM(total_hours), 0) as total_hours,
                COALESCE(SUM(overtime_hours), 0) as total_overtime
            FROM attendance 
            WHERE MONTH(date) = ? AND YEAR(date) = ?
        ");
        $summary->bind_param("ii", $current_month, $current_year);
    }
}
$summary->execute();
$stats = $summary->get_result()->fetch_assoc();

// Ensure all stats have default values
$stats = $stats ?: [
    'total_days' => 0,
    'present_days' => 0,
    'late_days' => 0,
    'absent_days' => 0,
    'half_days' => 0,
    'total_hours' => 0,
    'total_overtime' => 0
];

// Get today's clock status for employee
$today = date('Y-m-d');
$today_attendance = null;
if ($role == 'employee') {
    $today_stmt = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $today_stmt->bind_param("is", $user_id, $today);
    $today_stmt->execute();
    $today_attendance = $today_stmt->get_result()->fetch_assoc();
    $today_stmt->close();
}

// Get employees list for HR/Admin filter
$employees = [];
if ($role != 'employee') {
    $emp_result = $conn->query("SELECT user_id, first_name, last_name, employee_id FROM users WHERE role = 'employee' AND is_active = 1 ORDER BY first_name");
    while ($emp = $emp_result->fetch_assoc()) {
        $employees[] = $emp;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance - EMS</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { background: #f4f6f9; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100%; width: 260px; background: #2c3e50; color: white; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu ul { list-style: none; padding: 0; }
        .sidebar-menu li a { display: block; padding: 12px 20px; color: #ecf0f1; text-decoration: none; }
        .sidebar-menu li a:hover { background: #34495e; }
        .sidebar-menu li a.active { background: #1abc9c; }
        .sidebar-menu li a i { margin-right: 10px; width: 20px; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-navbar { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .clock-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 30px; text-align: center; margin-bottom: 20px; }
        .clock-time { font-size: 48px; font-weight: bold; font-family: monospace; }
        .clock-btn { padding: 15px 40px; font-size: 18px; border-radius: 50px; }
        .stat-box { background: white; border-radius: 5px; padding: 15px; text-align: center; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-value { font-size: 28px; font-weight: bold; }
        .status-present { color: #27ae60; }
        .status-late { color: #e67e22; }
        .status-absent { color: #e74c3c; }
        .status-halfday { color: #3498db; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>Attendance System</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <?php if ($role != 'employee'): ?>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <?php endif; ?>
                <li><a href="attendance.php" class="active"><i class="fa fa-clock-o"></i> Attendance</a></li>
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
        <div class="top-navbar">
            <div>
                <h4 style="margin: 0;">Attendance Management</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Track and manage employee attendance</p>
            </div>
            <div>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa fa-sign-out"></i> Logout</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($role == 'employee'): ?>
        <!-- Employee Clock In/Out Card -->
        <div class="clock-card">
            <h3><i class="fa fa-calendar"></i> <?php echo date('l, F j, Y'); ?></h3>
            <div class="clock-time" id="currentTime"></div>
            <div style="margin-top: 30px;">
                <?php if (!$today_attendance): ?>
                    <a href="attendance_clock.php?action=in" class="btn btn-success clock-btn">
                        <i class="fa fa-sign-in"></i> Clock In
                    </a>
                <?php elseif ($today_attendance && !$today_attendance['clock_out_time']): ?>
                    <div class="alert alert-info" style="background: rgba(255,255,255,0.2); border: none;">
                        Clocked in at: <?php echo date('h:i A', strtotime($today_attendance['clock_in_time'])); ?>
                    </div>
                    <a href="attendance_clock.php?action=out&id=<?php echo $today_attendance['attendance_id']; ?>" class="btn btn-danger clock-btn">
                        <i class="fa fa-sign-out"></i> Clock Out
                    </a>
                <?php else: ?>
                    <div class="alert alert-success" style="background: rgba(255,255,255,0.2); border: none;">
                        Clocked in: <?php echo date('h:i A', strtotime($today_attendance['clock_in_time'])); ?><br>
                        Clocked out: <?php echo date('h:i A', strtotime($today_attendance['clock_out_time'])); ?><br>
                        Total Hours: <?php echo number_format($today_attendance['total_hours'], 2); ?> hours
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Section for HR/Admin -->
        <?php if ($role != 'employee'): ?>
        <div class="panel panel-default">
            <div class="panel-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <label>Select Employee: </label>
                        <select name="user" class="form-control" onchange="this.form.submit()">
                            <option value="0">All Employees</option>
                            <?php foreach($employees as $emp): ?>
                                <option value="<?php echo $emp['user_id']; ?>" <?php echo (isset($_GET['user']) && $_GET['user'] == $emp['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Month: </label>
                        <select name="month" class="form-control" onchange="this.form.submit()">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year: </label>
                        <select name="year" class="form-control" onchange="this.form.submit()">
                            <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#manualEntryModal">
                        <i class="fa fa-pencil"></i> Manual Entry
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Row -->
        <div class="row">
            <div class="col-md-2 col-xs-6">
                <div class="stat-box">
                    <div class="stat-value <?php echo ($stats['present_days'] ?? 0) > 0 ? 'status-present' : ''; ?>">
                        <?php echo number_format($stats['present_days'] ?? 0); ?>
                    </div>
                    <div>Present</div>
                </div>
            </div>
            <div class="col-md-2 col-xs-6">
                <div class="stat-box">
                    <div class="stat-value <?php echo ($stats['late_days'] ?? 0) > 0 ? 'status-late' : ''; ?>">
                        <?php echo number_format($stats['late_days'] ?? 0); ?>
                    </div>
                    <div>Late</div>
                </div>
            </div>
            <div class="col-md-2 col-xs-6">
                <div class="stat-box">
                    <div class="stat-value <?php echo ($stats['absent_days'] ?? 0) > 0 ? 'status-absent' : ''; ?>">
                        <?php echo number_format($stats['absent_days'] ?? 0); ?>
                    </div>
                    <div>Absent</div>
                </div>
            </div>
            <div class="col-md-2 col-xs-6">
                <div class="stat-box">
                    <div class="stat-value <?php echo ($stats['half_days'] ?? 0) > 0 ? 'status-halfday' : ''; ?>">
                        <?php echo number_format($stats['half_days'] ?? 0); ?>
                    </div>
                    <div>Half Days</div>
                </div>
            </div>
            <div class="col-md-2 col-xs-6">
                <div class="stat-box">
                    <div class="stat-value">
                        <?php 
                        $total = (int)($stats['total_days'] ?? 0);
                        $present = (int)($stats['present_days'] ?? 0);
                        if ($total > 0) {
                            $percentage = round(($present / $total) * 100, 0);
                            echo $percentage;
                        } else {
                            echo "0";
                        }
                        ?>%
                    </div>
                    <div>Attendance %</div>
                </div>
            </div>
            <div class="col-md-2 col-xs-6">
                <div class="stat-box">
                    <div class="stat-value">
                        <?php echo number_format($stats['total_hours'] ?? 0, 1); ?>
                    </div>
                    <div>Total Hours</div>
                </div>
            </div>
            <div class="col-md-2 col-xs-6">
                <div class="stat-box">
                    <div class="stat-value">
                        <?php echo number_format($stats['total_overtime'] ?? 0, 1); ?>
                    </div>
                    <div>Overtime</div>
                </div>
            </div>
        </div>

        <!-- Attendance History Table -->
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-list"></i> Attendance History - <?php echo date('F Y', mktime(0,0,0,$current_month,1,$current_year)); ?></h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <?php if ($role != 'employee'): ?>
                                <th>Employee</th>
                                <th>Employee ID</th>
                                <?php endif; ?>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Total Hours</th>
                                <th>Overtime</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($attendance_list && $attendance_list->num_rows > 0): ?>
                                <?php while($record = $attendance_list->fetch_assoc()): ?>
                                    <tr>
                                        <?php if ($role != 'employee'): ?>
                                        <td><?php echo htmlspecialchars(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($record['employee_id'] ?? ''); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                        <td><?php echo date('l', strtotime($record['date'])); ?></td>
                                        <td><?php echo $record['clock_in_time'] ? date('h:i A', strtotime($record['clock_in_time'])) : '-'; ?></td>
                                        <td><?php echo $record['clock_out_time'] ? date('h:i A', strtotime($record['clock_out_time'])) : '-'; ?></td>
                                        <td><?php echo $record['total_hours'] ? number_format($record['total_hours'], 2) : '-'; ?></td>
                                        <td><?php echo $record['overtime_hours'] ? number_format($record['overtime_hours'], 2) : '0'; ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'present' => 'success',
                                                'late' => 'warning',
                                                'absent' => 'danger',
                                                'half_day' => 'info',
                                                'holiday' => 'primary',
                                                'weekend' => 'default'
                                            ];
                                            $status_text = [
                                                'present' => 'Present',
                                                'late' => 'Late',
                                                'absent' => 'Absent',
                                                'half_day' => 'Half Day',
                                                'holiday' => 'Holiday',
                                                'weekend' => 'Weekend'
                                            ];
                                            $status = $record['status'] ?? 'absent';
                                            ?>
                                            <span class="label label-<?php echo $status_class[$status] ?? 'default'; ?>">
                                                <?php echo $status_text[$status] ?? 'Unknown'; ?>
                                            </span>
                                            <?php if ($record['manual_entry'] ?? false): ?>
                                                <span class="label label-info">Manual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($role != 'employee') ? '10' : '8'; ?>" class="text-center">
                                        No attendance records found for this period
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Entry Modal (HR/Admin only) -->
    <?php if ($role != 'employee'): ?>
    <div id="manualEntryModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Manual Attendance Entry</h4>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="manual_entry" value="1">
                        <div class="form-group">
                            <label>Employee <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-control" required>
                                <option value="">Select Employee</option>
                                <?php foreach($employees as $emp): ?>
                                    <option value="<?php echo $emp['user_id']; ?>">
                                        <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Clock In Time <span class="text-danger">*</span></label>
                                    <input type="time" name="clock_in" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Clock Out Time <span class="text-danger">*</span></label>
                                    <input type="time" name="clock_out" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="present">Present</option>
                                <option value="late">Late</option>
                                <option value="half_day">Half Day</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Update clock every second for employee
        <?php if ($role == 'employee'): ?>
        function updateTime() {
            var now = new Date();
            var timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('currentTime').innerHTML = timeString;
        }
        updateTime();
        setInterval(updateTime, 1000);
        <?php endif; ?>
    </script>
</body>
</html>