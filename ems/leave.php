<?php
// leave.php - Leave Management
require_once 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = '';
$success = '';

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type_id = (int)$_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    // Calculate total days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $total_days = $interval->days + 1;
    
    if ($start_date > $end_date) {
        $error = "Start date cannot be after end date";
    } elseif ($start_date < date('Y-m-d')) {
        $error = "Cannot apply for leave in the past";
    } else {
        // Check for overlapping leave requests
        $overlap = $conn->prepare("
            SELECT COUNT(*) as count FROM leave_applications 
            WHERE user_id = ? AND status != 'rejected' 
            AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?))
        ");
        $overlap->bind_param("issss", $user_id, $start_date, $end_date, $start_date, $end_date);
        $overlap->execute();
        $overlap_count = $overlap->get_result()->fetch_assoc()['count'];
        $overlap->close();
        
        if ($overlap_count > 0) {
            $error = "You already have a leave request for these dates";
        } else {
            // Check balance
            $current_year = date('Y');
            $balance_check = $conn->prepare("
                SELECT remaining_days FROM leave_balances 
                WHERE user_id = ? AND leave_type_id = ? AND year = ?
            ");
            $balance_check->bind_param("iii", $user_id, $leave_type_id, $current_year);
            $balance_check->execute();
            $balance = $balance_check->get_result()->fetch_assoc();
            $balance_check->close();
            
            if ($balance && $balance['remaining_days'] < $total_days) {
                $error = "Insufficient leave balance. Available: {$balance['remaining_days']} days";
            } else {
                // Submit leave application
                $stmt = $conn->prepare("
                    INSERT INTO leave_applications 
                    (user_id, leave_type_id, start_date, end_date, total_days, reason, status, applied_date)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("iissis", $user_id, $leave_type_id, $start_date, $end_date, $total_days, $reason);
                
                if ($stmt->execute()) {
                    logActivity($user_id, 'leave_applied', "Applied for $total_days days leave");
                    
                    // Notify HR/Admin
                    $hr_users = $conn->query("SELECT user_id FROM users WHERE role IN ('admin', 'hr')");
                    while ($hr = $hr_users->fetch_assoc()) {
                        addNotification($hr['user_id'], 'New Leave Request', 
                            "{$_SESSION['first_name']} has requested {$total_days} days leave from " . date('M d', strtotime($start_date)), 
                            'leave_request', "leave_approve.php?id=" . $stmt->insert_id);
                    }
                    
                    $success = "Leave application submitted successfully!";
                } else {
                    $error = "Failed to submit leave application";
                }
                $stmt->close();
            }
        }
    }
}

// Handle leave approval (HR/Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_leave']) && $role != 'employee') {
    $leave_id = (int)$_POST['leave_id'];
    $action = $_POST['approve_action'];
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("
            UPDATE leave_applications 
            SET status = ?, approved_by = ?, approved_date = NOW(), rejection_reason = ?
            WHERE leave_id = ?
        ");
        $stmt->bind_param("sisi", $status, $_SESSION['user_id'], $rejection_reason, $leave_id);
        $stmt->execute();
        $stmt->close();
        
        // If approved, update leave balance
        if ($status === 'approved') {
            $leave = $conn->query("SELECT user_id, leave_type_id, total_days FROM leave_applications WHERE leave_id = $leave_id")->fetch_assoc();
            
            $update_balance = $conn->prepare("
                UPDATE leave_balances 
                SET used_days = used_days + ?, remaining_days = remaining_days - ?
                WHERE user_id = ? AND leave_type_id = ? AND year = ?
            ");
            $current_year = date('Y');
            $update_balance->bind_param("ddiii", $leave['total_days'], $leave['total_days'], $leave['user_id'], $leave['leave_type_id'], $current_year);
            $update_balance->execute();
            $update_balance->close();
            
            // Notify employee
            addNotification($leave['user_id'], 'Leave Approved', 
                "Your leave request for {$leave['total_days']} days has been approved", 
                'leave_approved', "leave.php");
        } else {
            // Notify employee about rejection
            $leave = $conn->query("SELECT user_id, total_days FROM leave_applications WHERE leave_id = $leave_id")->fetch_assoc();
            addNotification($leave['user_id'], 'Leave Rejected', 
                "Your leave request for {$leave['total_days']} days has been rejected. Reason: $rejection_reason", 
                'leave_rejected', "leave.php");
        }
        
        $conn->commit();
        logActivity($_SESSION['user_id'], 'leave_' . $status, "Leave application #$leave_id $status");
        $success = "Leave application " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to process request";
    }
}

// Get leave types
$leave_types = $conn->query("SELECT * FROM leave_types ORDER BY name");

// Get leave balances
$current_year = date('Y');
$leave_balances = $conn->prepare("
    SELECT lb.*, lt.name as leave_type_name, lt.days_per_year
    FROM leave_balances lb
    JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
    WHERE lb.user_id = ? AND lb.year = ?
");
$leave_balances->bind_param("ii", $user_id, $current_year);
$leave_balances->execute();
$balance_result = $leave_balances->get_result();

// Get leave history
if ($role == 'employee') {
    $leave_history = $conn->prepare("
        SELECT la.*, lt.name as leave_type_name 
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
        WHERE la.user_id = ?
        ORDER BY la.applied_date DESC
    ");
    $leave_history->bind_param("i", $user_id);
} else {
    $leave_history = $conn->prepare("
        SELECT la.*, lt.name as leave_type_name, u.first_name, u.last_name, u.employee_id
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
        JOIN users u ON la.user_id = u.user_id
        ORDER BY la.applied_date DESC
    ");
}
$leave_history->execute();
$history_result = $leave_history->get_result();

// Get pending leaves for HR/Admin
$pending_leaves = null;
if ($role != 'employee') {
    $pending_leaves = $conn->query("
        SELECT la.*, lt.name as leave_type_name, u.first_name, u.last_name, u.employee_id, u.department
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
        JOIN users u ON la.user_id = u.user_id
        WHERE la.status = 'pending'
        ORDER BY la.applied_date ASC
    ");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Leave Management - EMS</title>
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
        .balance-card { background: white; border-radius: 5px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .status-approved { color: #27ae60; }
        .status-pending { color: #f39c12; }
        .status-rejected { color: #e74c3c; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>Leave Management</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <?php if ($role != 'employee'): ?>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <?php endif; ?>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php" class="active"><i class="fa fa-umbrella"></i> Leave</a></li>
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
                <h4 style="margin: 0;">Leave Management</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Apply for leave and track your requests</p>
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
            <!-- Leave Application Form (Employee only) -->
            <?php if ($role == 'employee'): ?>
            <div class="col-md-5">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-pencil-square-o"></i> Apply for Leave</h3>
                    </div>
                    <div class="panel-body">
                        <form method="POST" action="">
                            <input type="hidden" name="apply_leave" value="1">
                            <div class="form-group">
                                <label>Leave Type <span class="text-danger">*</span></label>
                                <select name="leave_type_id" class="form-control" required>
                                    <option value="">Select Leave Type</option>
                                    <?php while($type = $leave_types->fetch_assoc()): ?>
                                        <option value="<?php echo $type['leave_type_id']; ?>">
                                            <?php echo $type['name']; ?> 
                                            <?php if($type['is_paid']): ?>(Paid)<?php else: ?>(Unpaid)<?php endif; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Start Date <span class="text-danger">*</span></label>
                                        <input type="date" name="start_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>End Date <span class="text-danger">*</span></label>
                                        <input type="date" name="end_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Reason <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control" rows="4" required placeholder="Please provide detailed reason..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Submit Application</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Leave Balances -->
            <div class="col-md-<?php echo $role == 'employee' ? '7' : '12'; ?>">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-pie-chart"></i> Your Leave Balances - <?php echo $current_year; ?></h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <?php while($balance = $balance_result->fetch_assoc()): ?>
                                <div class="col-md-4">
                                    <div class="balance-card">
                                        <strong><?php echo $balance['leave_type_name']; ?></strong>
                                        <span class="pull-right">
                                            <?php echo $balance['remaining_days']; ?> / <?php echo $balance['days_per_year']; ?> days
                                        </span>
                                        <div class="progress" style="margin: 10px 0; height: 8px;">
                                            <div class="progress-bar progress-bar-success" 
                                                 style="width: <?php echo ($balance['used_days'] / $balance['days_per_year']) * 100; ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            Used: <?php echo $balance['used_days']; ?> days
                                        </small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Leave Requests for HR/Admin -->
        <?php if ($role != 'employee' && $pending_leaves && $pending_leaves->num_rows > 0): ?>
        <div class="panel panel-warning">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-clock-o"></i> Pending Leave Requests</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($leave = $pending_leaves->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?><br>
                                    <small><?php echo $leave['employee_id']; ?></small>
                                </td>
                                <td><?php echo $leave['department']; ?></td>
                                <td><?php echo $leave['leave_type_name']; ?></td>
                                <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d', strtotime($leave['end_date'])); ?></td>
                                <td><?php echo $leave['total_days']; ?></td>
                                <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)); ?></td>
                                <td><?php echo date('M d, Y', strtotime($leave['applied_date'])); ?></td>
                                <td>
                                    <button onclick="approveLeave(<?php echo $leave['leave_id']; ?>)" class="btn btn-success btn-xs">Approve</button>
                                    <button onclick="rejectLeave(<?php echo $leave['leave_id']; ?>)" class="btn btn-danger btn-xs">Reject</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Leave History -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-history"></i> Leave History</h3>
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
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th>Approved/Rejected By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history_result->num_rows > 0): ?>
                                <?php while($leave = $history_result->fetch_assoc()): ?>
                                    <tr>
                                        <?php if ($role != 'employee'): ?>
                                        <td><?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?></td>
                                        <td><?php echo $leave['employee_id']; ?></td>
                                        <?php endif; ?>
                                        <td><?php echo $leave['leave_type_name']; ?></td>
                                        <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d', strtotime($leave['end_date'])); ?></td>
                                        <td><?php echo $leave['total_days']; ?></td>
                                        <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($leave['applied_date'])); ?></td>
                                        <td>
                                            <span class="label label-<?php echo $leave['status'] == 'approved' ? 'success' : ($leave['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($leave['approved_by']) {
                                                $approver = $conn->query("SELECT first_name, last_name FROM users WHERE user_id = {$leave['approved_by']}")->fetch_assoc();
                                                echo $approver['first_name'] . ' ' . $approver['last_name'];
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $role != 'employee' ? '9' : '7'; ?>" class="text-center">
                                        No leave applications found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve/Reject Modal -->
    <div id="approveModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Approve Leave Request</h4>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="approve_leave" value="1">
                        <input type="hidden" name="leave_id" id="approve_leave_id">
                        <input type="hidden" name="approve_action" value="approve">
                        <p>Are you sure you want to approve this leave request?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="rejectModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Reject Leave Request</h4>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="approve_leave" value="1">
                        <input type="hidden" name="leave_id" id="reject_leave_id">
                        <input type="hidden" name="approve_action" value="reject">
                        <div class="form-group">
                            <label>Reason for Rejection</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function approveLeave(id) {
            $('#approve_leave_id').val(id);
            $('#approveModal').modal('show');
        }
        
        function rejectLeave(id) {
            $('#reject_leave_id').val(id);
            $('#rejectModal').modal('show');
        }
    </script>
</body>
</html>