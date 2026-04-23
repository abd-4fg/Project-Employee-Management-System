<?php
// employees.php - Employee Management
require_once 'config.php';
requireRole(['admin', 'hr']);

$error = '';
$success = '';
$search = $_GET['search'] ?? '';
$dept_filter = $_GET['dept'] ?? '';

// Handle employee addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $employee_id = $_POST['employee_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $department = $_POST['department'];
    $job_title = $_POST['job_title'];
    $salary = $_POST['salary'];
    $hire_date = $_POST['hire_date'];
    $phone_number = $_POST['phone_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $emergency_contact = $_POST['emergency_contact'] ?? '';
    $emergency_phone = $_POST['emergency_phone'] ?? '';
    $bank_account = $_POST['bank_account'] ?? '';
    
    // Check if email exists
    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $error = "Email already exists";
    } else {
        $password = password_hash('Welcome@123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (employee_id, first_name, last_name, email, password, role, department, job_title, salary, hire_date, phone_number, address, emergency_contact, emergency_phone, bank_account, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("ssssssssdssssss", 
            $employee_id, $first_name, $last_name, $email, $password, $role, 
            $department, $job_title, $salary, $hire_date, $phone_number, 
            $address, $emergency_contact, $emergency_phone, $bank_account
        );
        
        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            logActivity($_SESSION['user_id'], 'add_employee', "Added employee: $first_name $last_name");
            
            // Initialize leave balances
            $current_year = date('Y');
            $leave_types = $conn->query("SELECT leave_type_id, days_per_year FROM leave_types WHERE days_per_year > 0");
            while ($lt = $leave_types->fetch_assoc()) {
                $balance_stmt = $conn->prepare("
                    INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days, remaining_days)
                    VALUES (?, ?, ?, ?, 0, ?)
                ");
                $balance_stmt->bind_param("iiiii", $new_user_id, $lt['leave_type_id'], $current_year, $lt['days_per_year'], $lt['days_per_year']);
                $balance_stmt->execute();
                $balance_stmt->close();
            }
            
            addNotification($new_user_id, 'Welcome to EMS', 'Your account has been created. Default password: Welcome@123', 'welcome');
            $success = "Employee added successfully. Default password: Welcome@123";
        } else {
            $error = "Failed to add employee";
        }
        $stmt->close();
    }
    $check->close();
}

// Handle employee edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $user_id = $_POST['user_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $role = $_POST['role'];
    $department = $_POST['department'];
    $job_title = $_POST['job_title'];
    $salary = $_POST['salary'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];
    $emergency_contact = $_POST['emergency_contact'];
    $emergency_phone = $_POST['emergency_phone'];
    $bank_account = $_POST['bank_account'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $conn->prepare("
        UPDATE users SET 
            first_name = ?, last_name = ?, role = ?, department = ?, job_title = ?,
            salary = ?, phone_number = ?, address = ?, emergency_contact = ?,
            emergency_phone = ?, bank_account = ?, is_active = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("sssssdsssssii", 
        $first_name, $last_name, $role, $department, $job_title,
        $salary, $phone_number, $address, $emergency_contact,
        $emergency_phone, $bank_account, $is_active, $user_id
    );
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'edit_employee', "Edited employee: $first_name $last_name");
        $success = "Employee updated successfully";
    } else {
        $error = "Failed to update employee";
    }
    $stmt->close();
}

// Get employees list
$query = "
    SELECT u.*, d.dept_name 
    FROM users u
    LEFT JOIN departments d ON u.department = d.dept_name
    WHERE u.role != 'admin'
";
if ($search) {
    $query .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.employee_id LIKE '%$search%')";
}
if ($dept_filter) {
    $query .= " AND u.department = '$dept_filter'";
}
$query .= " ORDER BY u.first_name ASC";
$employees = $conn->query($query);

// Get departments for filter
$departments = $conn->query("SELECT dept_name FROM departments ORDER BY dept_name");

// Get single employee for edit modal
$edit_employee = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_result = $conn->query("SELECT * FROM users WHERE user_id = $edit_id");
    $edit_employee = $edit_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Management - EMS</title>
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
        .employee-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s; }
        .employee-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn-action { margin: 2px; }
        .modal-lg { width: 800px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>Employee Management</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="employees.php" class="active"><i class="fa fa-users"></i> Employees</a></li>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="payroll.php"><i class="fa fa-money"></i> Payroll</a></li>
                <li><a href="performance.php"><i class="fa fa-star"></i> Performance</a></li>
                <li><a href="reports.php"><i class="fa fa-bar-chart"></i> Reports</a></li>
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
                <h4 style="margin: 0;">Employee Management</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Manage employee records, roles, and information</p>
            </div>
            <div>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa fa-sign-out"></i> Logout</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-plus-circle"></i> Add New Employee</h3>
                    </div>
                    <div class="panel-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="add_employee" value="1">
                            <div class="form-group">
                                <label>Employee ID</label>
                                <input type="text" name="employee_id" class="form-control" required placeholder="EMP001">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" name="first_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" name="last_name" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <select name="role" class="form-control" required>
                                    <option value="employee">Employee</option>
                                    <option value="hr">HR</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department" class="form-control" required>
                                    <option value="">Select Department</option>
                                    <?php 
                                    $depts = $conn->query("SELECT dept_name FROM departments");
                                    while($dept = $depts->fetch_assoc()): ?>
                                        <option value="<?php echo $dept['dept_name']; ?>"><?php echo $dept['dept_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Job Title</label>
                                <select name="job_title" class="form-control" required>
                                    <option value="">Select Job Title</option>
                                    <?php 
                                    $jobs = $conn->query("SELECT job_title FROM job_titles");
                                    while($job = $jobs->fetch_assoc()): ?>
                                        <option value="<?php echo $job['job_title']; ?>"><?php echo $job['job_title']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Salary ($)</label>
                                <input type="number" name="salary" class="form-control" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Hire Date</label>
                                <input type="date" name="hire_date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone_number" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Emergency Contact</label>
                                        <input type="text" name="emergency_contact" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Emergency Phone</label>
                                        <input type="text" name="emergency_phone" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Bank Account</label>
                                <input type="text" name="bank_account" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Add Employee</button>
                        </form>
                        <div class="alert alert-info" style="margin-top: 15px;">
                            <small><i class="fa fa-info-circle"></i> Default password: <strong>Welcome@123</strong></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-list"></i> Employee Directory</h3>
                        <div class="pull-right">
                            <form method="GET" class="form-inline">
                                <select name="dept" class="form-control input-sm" style="width: 150px;">
                                    <option value="">All Departments</option>
                                    <?php 
                                    $depts = $conn->query("SELECT dept_name FROM departments");
                                    while($dept = $depts->fetch_assoc()): ?>
                                        <option value="<?php echo $dept['dept_name']; ?>" <?php echo $dept_filter == $dept['dept_name'] ? 'selected' : ''; ?>>
                                            <?php echo $dept['dept_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control input-sm" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default btn-sm" type="submit"><i class="fa fa-search"></i></button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="panel-body">
                        <?php if ($employees->num_rows > 0): ?>
                            <?php while($emp = $employees->fetch_assoc()): ?>
                                <div class="employee-card">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <h4 style="margin: 0;">
                                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                                <small>(<?php echo $emp['employee_id']; ?>)</small>
                                                <?php if($emp['is_active']): ?>
                                                    <span class="label label-success">Active</span>
                                                <?php else: ?>
                                                    <span class="label label-danger">Inactive</span>
                                                <?php endif; ?>
                                            </h4>
                                            <p style="margin: 5px 0;">
                                                <i class="fa fa-envelope"></i> <?php echo $emp['email']; ?> |
                                                <i class="fa fa-building"></i> <?php echo $emp['department']; ?> |
                                                <i class="fa fa-briefcase"></i> <?php echo $emp['job_title']; ?>
                                            </p>
                                            <p class="text-muted" style="margin: 0;">
                                                Role: <?php echo ucfirst($emp['role']); ?> |
                                                Salary: $<?php echo number_format($emp['salary'], 2); ?> |
                                                Hired: <?php echo date('M d, Y', strtotime($emp['hire_date'])); ?>
                                            </p>
                                            <?php if ($emp['phone_number']): ?>
                                                <p><i class="fa fa-phone"></i> <?php echo $emp['phone_number']; ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-5 text-right">
                                            <button onclick="viewEmployee(<?php echo $emp['user_id']; ?>)" class="btn btn-info btn-sm">
                                                <i class="fa fa-eye"></i> View
                                            </button>
                                            <button onclick="editEmployee(<?php echo $emp['user_id']; ?>)" class="btn btn-warning btn-sm">
                                                <i class="fa fa-edit"></i> Edit
                                            </button>
                                            <?php if($emp['is_active']): ?>
                                                <button onclick="toggleEmployee(<?php echo $emp['user_id']; ?>, 'deactivate')" class="btn btn-danger btn-sm">
                                                    <i class="fa fa-ban"></i> Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button onclick="toggleEmployee(<?php echo $emp['user_id']; ?>, 'activate')" class="btn btn-success btn-sm">
                                                    <i class="fa fa-check"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="resetPassword(<?php echo $emp['user_id']; ?>)" class="btn btn-default btn-sm">
                                                <i class="fa fa-key"></i> Reset Pwd
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No employees found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Employee Modal -->
    <div id="viewModal" class="modal fade">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Employee Details</h4>
                </div>
                <div class="modal-body" id="viewModalBody">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div id="editModal" class="modal fade">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Edit Employee</h4>
                </div>
                <div class="modal-body" id="editModalBody">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function viewEmployee(id) {
            $.get('get_employee.php?id=' + id, function(data) {
                $('#viewModalBody').html(data);
                $('#viewModal').modal('show');
            });
        }
        
        function editEmployee(id) {
            $.get('edit_employee_form.php?id=' + id, function(data) {
                $('#editModalBody').html(data);
                $('#editModal').modal('show');
            });
        }
        
        function toggleEmployee(id, action) {
            if (confirm('Are you sure you want to ' + action + ' this employee?')) {
                window.location.href = 'toggle_employee.php?id=' + id + '&action=' + action;
            }
        }
        
        function resetPassword(id) {
            if (confirm('Reset password to default (Welcome@123)?')) {
                window.location.href = 'reset_password.php?id=' + id;
            }
        }
    </script>
</body>
</html>