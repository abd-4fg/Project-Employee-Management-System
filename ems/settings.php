<?php
// settings.php - System Settings (Admin only)
require_once 'config.php';
requireRole('admin');

$error = '';
$success = '';

// Handle company settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = [
        'company_name' => $_POST['company_name'],
        'working_hours_start' => $_POST['working_hours_start'],
        'working_hours_end' => $_POST['working_hours_end'],
        'late_grace_minutes' => $_POST['late_grace_minutes'],
        'weekend_days' => $_POST['weekend_days'],
        'payroll_day' => $_POST['payroll_day']
    ];
    
    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("UPDATE company_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
        $stmt->close();
    }
    
    $success = "Settings updated successfully";
    logActivity($_SESSION['user_id'], 'update_settings', "Updated company settings");
}

// Handle leave type management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave_type'])) {
    $name = $_POST['name'];
    $days_per_year = $_POST['days_per_year'];
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO leave_types (name, days_per_year, is_paid) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $name, $days_per_year, $is_paid);
    
    if ($stmt->execute()) {
        $success = "Leave type added successfully";
        logActivity($_SESSION['user_id'], 'add_leave_type', "Added leave type: $name");
    } else {
        $error = "Failed to add leave type";
    }
    $stmt->close();
}

// Handle holiday management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
    $holiday_name = $_POST['holiday_name'];
    $holiday_date = $_POST['holiday_date'];
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO holidays (holiday_name, holiday_date, is_recurring) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $holiday_name, $holiday_date, $is_recurring);
    
    if ($stmt->execute()) {
        $success = "Holiday added successfully";
        logActivity($_SESSION['user_id'], 'add_holiday', "Added holiday: $holiday_name");
    } else {
        $error = "Failed to add holiday";
    }
    $stmt->close();
}

// Get current settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM company_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get leave types
$leave_types = $conn->query("SELECT * FROM leave_types ORDER BY name");

// Get holidays
$holidays = $conn->query("SELECT * FROM holidays WHERE YEAR(holiday_date) = YEAR(CURDATE()) ORDER BY holiday_date");
?>

<!DOCTYPE html>
<html>
<head>
    <title>System Settings - EMS</title>
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
        .settings-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>System Settings</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="payroll.php"><i class="fa fa-money"></i> Payroll</a></li>
                <li><a href="performance.php"><i class="fa fa-star"></i> Performance</a></li>
                <li><a href="reports.php"><i class="fa fa-bar-chart"></i> Reports</a></li>
                <li><a href="settings.php" class="active"><i class="fa fa-cogs"></i> Settings</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div>
                <h4 style="margin: 0;">System Settings</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Configure company policies and system preferences</p>
            </div>
            <div>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa fa-sign-out"></i> Logout</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <!-- Company Settings -->
                <div class="settings-card">
                    <h4><i class="fa fa-building"></i> Company Settings</h4>
                    <hr>
                    <form method="POST">
                        <input type="hidden" name="update_settings" value="1">
                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo $settings['company_name']; ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Working Hours Start</label>
                                    <input type="time" name="working_hours_start" class="form-control" value="<?php echo $settings['working_hours_start']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Working Hours End</label>
                                    <input type="time" name="working_hours_end" class="form-control" value="<?php echo $settings['working_hours_end']; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Late Grace Period (minutes)</label>
                                    <input type="number" name="late_grace_minutes" class="form-control" value="<?php echo $settings['late_grace_minutes']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Payroll Processing Day</label>
                                    <input type="number" name="payroll_day" class="form-control" value="<?php echo $settings['payroll_day']; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Weekend Days (comma separated)</label>
                            <input type="text" name="weekend_days" class="form-control" value="<?php echo $settings['weekend_days']; ?>" placeholder="Saturday,Sunday">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- Leave Types Management -->
                <div class="settings-card">
                    <h4><i class="fa fa-umbrella"></i> Leave Types</h4>
                    <hr>
                    <form method="POST" class="form-inline" style="margin-bottom: 20px;">
                        <input type="hidden" name="add_leave_type" value="1">
                        <div class="form-group">
                            <input type="text" name="name" class="form-control" placeholder="Leave Type Name" required style="width: 200px;">
                        </div>
                        <div class="form-group">
                            <input type="number" name="days_per_year" class="form-control" placeholder="Days per Year" required style="width: 120px;">
                        </div>
                        <div class="checkbox" style="margin: 10px;">
                            <label><input type="checkbox" name="is_paid"> Paid</label>
                        </div>
                        <button type="submit" class="btn btn-success">Add</button>
                    </form>
                    
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Leave Type</th><th>Days/Year</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php while($lt = $leave_types->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $lt['name']; ?></td>
                                <td><?php echo $lt['days_per_year']; ?></td>
                                <td><?php echo $lt['is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <!-- Holiday Management -->
                <div class="settings-card">
                    <h4><i class="fa fa-calendar"></i> Company Holidays - <?php echo date('Y'); ?></h4>
                    <hr>
                    <form method="POST" class="form-inline" style="margin-bottom: 20px;">
                        <input type="hidden" name="add_holiday" value="1">
                        <div class="form-group">
                            <input type="text" name="holiday_name" class="form-control" placeholder="Holiday Name" required style="width: 250px;">
                        </div>
                        <div class="form-group">
                            <input type="date" name="holiday_date" class="form-control" required>
                        </div>
                        <div class="checkbox" style="margin: 10px;">
                            <label><input type="checkbox" name="is_recurring"> Recurring Yearly</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Holiday</button>
                    </form>
                    
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Holiday Name</th><th>Date</th><th>Day</th><th>Recurring</th></tr>
                        </thead>
                        <tbody>
                            <?php while($holiday = $holidays->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $holiday['holiday_name']; ?></td>
                                <td><?php echo date('F j, Y', strtotime($holiday['holiday_date'])); ?></td>
                                <td><?php echo date('l', strtotime($holiday['holiday_date'])); ?></td>
                                <td><?php echo $holiday['is_recurring'] ? 'Yes' : 'No'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>