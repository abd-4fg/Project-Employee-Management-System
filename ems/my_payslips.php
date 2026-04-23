<?php
// my_payslips.php - Employee Payslip Access
require_once 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get employee's payroll history
$payroll_records = $conn->prepare("
    SELECT p.*, 
           MONTHNAME(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01')) as month_name
    FROM payroll p
    WHERE p.user_id = ?
    ORDER BY p.year DESC, p.month DESC
");
$payroll_records->bind_param("i", $user_id);
$payroll_records->execute();
$payroll_list = $payroll_records->get_result();

// Get employee info
$emp_info = $conn->prepare("SELECT first_name, last_name, employee_id, department FROM users WHERE user_id = ?");
$emp_info->bind_param("i", $user_id);
$emp_info->execute();
$employee = $emp_info->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Payslips - EMS</title>
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
        .payslip-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .payslip-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-paid { color: #27ae60; }
        .status-pending { color: #f39c12; }
        .status-draft { color: #95a5a6; }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>My Payslips</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="my_payslips.php" class="active"><i class="fa fa-money"></i> My Payslips</a></li>
                <li><a href="performance_employee.php"><i class="fa fa-star"></i> My Performance</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div>
                <h4 style="margin: 0;">My Payslips</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">View and download your salary slips</p>
            </div>
            <div>
                <span style="margin-right: 15px;">
                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                    <br><small><?php echo htmlspecialchars($employee['employee_id']); ?></small>
                </span>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa fa-sign-out"></i> Logout</a>
            </div>
        </div>

        <?php if ($payroll_list->num_rows > 0): ?>
            <!-- Summary Card -->
            <?php
            // Calculate YTD totals
            $ytd_earnings = 0;
            $ytd_deductions = 0;
            $ytd_net = 0;
            $payroll_list->data_seek(0);
            while($p = $payroll_list->fetch_assoc()) {
                $ytd_earnings += $p['total_earnings'];
                $ytd_deductions += $p['total_deductions'];
                $ytd_net += $p['net_salary'];
            }
            $payroll_list->data_seek(0);
            ?>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="summary-card" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);">
                        <h5>Year-to-Date Earnings</h5>
                        <h2>$<?php echo number_format($ytd_earnings, 2); ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                        <h5>Year-to-Date Deductions</h5>
                        <h2>$<?php echo number_format($ytd_deductions, 2); ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                        <h5>Year-to-Date Net Pay</h5>
                        <h2>$<?php echo number_format($ytd_net, 2); ?></h2>
                    </div>
                </div>
            </div>

            <h4><i class="fa fa-history"></i> Payslip History</h4>
            <div class="row">
                <?php while($payslip = $payroll_list->fetch_assoc()): ?>
                    <div class="col-md-4">
                        <div class="payslip-card">
                            <h4>
                                <i class="fa fa-file-text-o"></i> 
                                <?php echo $payslip['month_name'] . ' ' . $payslip['year']; ?>
                            </h4>
                            <hr>
                            <div class="row">
                                <div class="col-xs-6">
                                    <small>Gross Earnings:</small><br>
                                    <strong>$<?php echo number_format($payslip['total_earnings'], 2); ?></strong>
                                </div>
                                <div class="col-xs-6">
                                    <small>Deductions:</small><br>
                                    <strong>$<?php echo number_format($payslip['total_deductions'], 2); ?></strong>
                                </div>
                            </div>
                            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                <div class="row">
                                    <div class="col-xs-6">
                                        <strong>Net Salary:</strong>
                                    </div>
                                    <div class="col-xs-6 text-right">
                                        <strong style="font-size: 18px; color: #27ae60;">
                                            $<?php echo number_format($payslip['net_salary'], 2); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>
                                    Status: 
                                    <?php
                                    $status_class = [
                                        'paid' => 'status-paid',
                                        'draft' => 'status-draft',
                                        'approved' => 'status-pending'
                                    ];
                                    ?>
                                    <span class="<?php echo $status_class[$payslip['status']] ?? 'status-pending'; ?>">
                                        <strong><?php echo ucfirst($payslip['status']); ?></strong>
                                    </span>
                                    <?php if ($payslip['payment_date']): ?>
                                        <br><small>Paid: <?php echo date('M d, Y', strtotime($payslip['payment_date'])); ?></small>
                                    <?php endif; ?>
                                </span>
                                <a href="payslip.php?id=<?php echo $payslip['payroll_id']; ?>" 
                                   class="btn btn-primary btn-sm" target="_blank">
                                    <i class="fa fa-download"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" style="padding: 50px;">
                <i class="fa fa-file-text-o fa-4x"></i>
                <h4>No Payslips Available</h4>
                <p>Your payroll has not been processed yet for any period.</p>
                <p class="text-muted">Please contact HR if you believe this is an error.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>