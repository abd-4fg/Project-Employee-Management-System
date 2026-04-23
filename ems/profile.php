<?php
// profile.php - User Profile
require_once 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];
    $emergency_contact = $_POST['emergency_contact'];
    $emergency_phone = $_POST['emergency_phone'];
    
    $update_stmt = $conn->prepare("
        UPDATE users SET 
            first_name = ?, last_name = ?, phone_number = ?, 
            address = ?, emergency_contact = ?, emergency_phone = ?
        WHERE user_id = ?
    ");
    $update_stmt->bind_param("ssssssi", $first_name, $last_name, $phone_number, $address, $emergency_contact, $emergency_phone, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $success = "Profile updated successfully";
        
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        logActivity($user_id, 'profile_update', 'Updated profile information');
    } else {
        $error = "Error updating profile";
    }
    $update_stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Check if password is hashed or plain
    $stored_password = $user['password'];
    $password_valid = false;
    
    if (strpos($stored_password, '$2y$') === 0) {
        $password_valid = password_verify($current_password, $stored_password);
    } else {
        $password_valid = ($current_password === $stored_password);
    }
    
    if (!$password_valid) {
        $error = "Current password is incorrect";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $pass_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $pass_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($pass_stmt->execute()) {
            $success = "Password changed successfully";
            logActivity($user_id, 'password_change', 'Changed password');
        } else {
            $error = "Error changing password";
        }
        $pass_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile - EMS</title>
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
        .sidebar-menu li a i { margin-right: 10px; width: 20px; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-navbar { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .profile-header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .profile-avatar { width: 100px; height: 100px; background: #1abc9c; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 48px; }
        .nav-tabs { margin-bottom: 20px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>My Profile</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <?php if ($_SESSION['role'] != 'employee'): ?>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <?php endif; ?>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="payroll.php"><i class="fa fa-money"></i> Payroll</a></li>
                <?php if ($_SESSION['role'] != 'employee'): ?>
                <li><a href="performance.php"><i class="fa fa-star"></i> Performance</a></li>
                <li><a href="reports.php"><i class="fa fa-bar-chart"></i> Reports</a></li>
                <?php else: ?>
                <li><a href="performance_employee.php"><i class="fa fa-star"></i> Performance</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <li><a href="settings.php"><i class="fa fa-cogs"></i> Settings</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div>
                <h4 style="margin: 0;">My Profile</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Manage your personal information</p>
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

        <div class="panel panel-primary">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fa fa-user"></i>
                </div>
                <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <p><?php echo ucfirst($user['role']); ?> | <?php echo htmlspecialchars($user['department']); ?> | <?php echo htmlspecialchars($user['job_title']); ?></p>
                <p class="text-muted">Employee ID: <?php echo htmlspecialchars($user['employee_id']); ?></p>
            </div>
            <div class="panel-body">
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#info" data-toggle="tab">Personal Information</a></li>
                    <li><a href="#employment" data-toggle="tab">Employment Details</a></li>
                    <li><a href="#password" data-toggle="tab">Change Password</a></li>
                </ul>
                
                <div class="tab-content">
                    <!-- Personal Information Tab -->
                    <div class="tab-pane active" id="info">
                        <form method="post" style="margin-top: 20px;">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Emergency Contact Name</label>
                                        <input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($user['emergency_contact']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Emergency Contact Phone</label>
                                        <input type="text" name="emergency_phone" class="form-control" value="<?php echo htmlspecialchars($user['emergency_phone']); ?>">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                    
                    <!-- Employment Details Tab -->
                    <div class="tab-pane" id="employment">
                        <div class="row" style="margin-top: 20px;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Employee ID</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['employee_id']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Department</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['department']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Job Title</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['job_title']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Hire Date</label>
                                    <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($user['hire_date'])); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Basic Salary</label>
                                    <input type="text" class="form-control" value="$<?php echo number_format($user['salary'], 2); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Bank Account</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['bank_account']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change Password Tab -->
                    <div class="tab-pane" id="password">
                        <form method="post" style="margin-top: 20px;">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-warning">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>