<?php
// payroll.php - Payroll Management
require_once 'config.php';
requireRole(['admin', 'hr']);

$error = '';
$success = '';
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

// Get salary structure
$salary_structure = $conn->query("SELECT * FROM salary_structures LIMIT 1")->fetch_assoc();

// Generate payroll for all employees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {
    $employees = $conn->query("SELECT user_id, salary, department, job_title FROM users WHERE role = 'employee' AND is_active = 1");
    
    $conn->begin_transaction();
    
    try {
        while ($emp = $employees->fetch_assoc()) {
            $user_id = $emp['user_id'];
            
            // Get attendance for the month
            $attendance = $conn->prepare("
                SELECT SUM(total_hours) as total_hours, SUM(overtime_hours) as overtime 
                FROM attendance 
                WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
            ");
            $attendance->bind_param("iii", $user_id, $month, $year);
            $attendance->execute();
            $att_data = $attendance->get_result()->fetch_assoc();
            $attendance->close();
            
            $total_hours = $att_data['total_hours'] ?? 0;
            $overtime = $att_data['overtime'] ?? 0;
            
            // Calculate earnings based on salary structure
            $basic_salary = $emp['salary'];
            $hra = $basic_salary * ($salary_structure['hra_percent'] / 100);
            $da = $basic_salary * ($salary_structure['da_percent'] / 100);
            $allowances = $basic_salary * ($salary_structure['allowance_percent'] / 100);
            
            // Calculate overtime pay (1.5x hourly rate)
            $hourly_rate = $basic_salary / (20 * 8);
            $overtime_pay = $overtime * $hourly_rate * 1.5;
            $bonus = 0;
            
            $total_earnings = $basic_salary + $hra + $da + $allowances + $bonus + $overtime_pay;
            
            // Calculate deductions
            $tax_deduction = $total_earnings * ($salary_structure['tax_percent'] / 100);
            $social_security = $basic_salary * ($salary_structure['social_security_percent'] / 100);
            $health_insurance = $salary_structure['health_insurance_fixed'];
            $total_deductions = $tax_deduction + $social_security + $health_insurance;
            $net_salary = $total_earnings - $total_deductions;
            
            // Check if payroll already exists
            $check = $conn->prepare("SELECT payroll_id FROM payroll WHERE user_id = ? AND month = ? AND year = ?");
            $check->bind_param("iii", $user_id, $month, $year);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                // Update existing
                $stmt = $conn->prepare("
                    UPDATE payroll SET 
                        basic_salary = ?, hra = ?, da = ?, allowances = ?, bonus = ?, overtime_pay = ?,
                        total_earnings = ?, tax_deduction = ?, social_security = ?,
                        health_insurance = ?, total_deductions = ?, net_salary = ?
                    WHERE user_id = ? AND month = ? AND year = ?
                ");
                $stmt->bind_param("ddddddddddddiii", 
                    $basic_salary, $hra, $da, $allowances, $bonus, $overtime_pay,
                    $total_earnings, $tax_deduction, $social_security,
                    $health_insurance, $total_deductions, $net_salary,
                    $user_id, $month, $year
                );
            } else {
                // Insert new
                $stmt = $conn->prepare("
                    INSERT INTO payroll 
                    (user_id, month, year, basic_salary, hra, da, allowances, bonus, overtime_pay,
                     total_earnings, tax_deduction, social_security, health_insurance,
                     total_deductions, net_salary, status, generated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
                ");
                $stmt->bind_param("iiiddddddddddddi", 
                    $user_id, $month, $year, $basic_salary, $hra, $da, $allowances, $bonus, $overtime_pay,
                    $total_earnings, $tax_deduction, $social_security, $health_insurance,
                    $total_deductions, $net_salary, $_SESSION['user_id']
                );
            }
            $stmt->execute();
            $stmt->close();
            $check->close();
        }
        
        $conn->commit();
        $success = "Payroll generated successfully for " . date('F Y', mktime(0,0,0,$month,1,$year));
        logActivity($_SESSION['user_id'], 'generate_payroll', "Generated payroll for $month/$year");
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to generate payroll: " . $e->getMessage();
    }
}

// Process payroll payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payroll_id = (int)$_POST['payroll_id'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $bank_reference = $_POST['bank_reference'] ?? '';
    
    $stmt = $conn->prepare("
        UPDATE payroll 
        SET status = 'paid', payment_date = ?, payment_method = ?, bank_reference = ?
        WHERE payroll_id = ?
    ");
    $stmt->bind_param("sssi", $payment_date, $payment_method, $bank_reference, $payroll_id);
    
    if ($stmt->execute()) {
        // Get user_id for notification
        $pay = $conn->query("SELECT user_id FROM payroll WHERE payroll_id = $payroll_id")->fetch_assoc();
        addNotification($pay['user_id'], 'Salary Paid', "Your salary for " . date('F Y', mktime(0,0,0,$month,1,$year)) . " has been credited", 'payroll');
        $success = "Payment processed successfully";
        logActivity($_SESSION['user_id'], 'process_payment', "Processed payroll #$payroll_id");
    } else {
        $error = "Failed to process payment";
    }
    $stmt->close();
}

// Get payroll records
$payroll_records = $conn->prepare("
    SELECT p.*, u.first_name, u.last_name, u.employee_id, u.department, u.job_title
    FROM payroll p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.month = ? AND p.year = ?
    ORDER BY u.department, u.last_name
");
$payroll_records->bind_param("ii", $month, $year);
$payroll_records->execute();
$payroll_list = $payroll_records->get_result();

// Calculate totals
$totals = $conn->prepare("
    SELECT 
        SUM(basic_salary) as total_basic,
        SUM(hra) as total_hra,
        SUM(da) as total_da,
        SUM(allowances) as total_allowances,
        SUM(bonus) as total_bonus,
        SUM(overtime_pay) as total_overtime,
        SUM(total_earnings) as total_earnings,
        SUM(tax_deduction) as total_tax,
        SUM(social_security) as total_ss,
        SUM(health_insurance) as total_health,
        SUM(total_deductions) as total_deductions,
        SUM(net_salary) as total_net
    FROM payroll 
    WHERE month = ? AND year = ?
");
$totals->bind_param("ii", $month, $year);
$totals->execute();
$summary = $totals->get_result()->fetch_assoc();

// Get employees for filter
$employees = $conn->query("SELECT user_id, first_name, last_name, employee_id FROM users WHERE role = 'employee' ORDER BY first_name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payroll Management - EMS</title>
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
        .summary-card { background: white; border-radius: 5px; padding: 15px; margin-bottom: 15px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .summary-value { font-size: 24px; font-weight: bold; color: #2c3e50; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>Payroll System</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="payroll.php" class="active"><i class="fa fa-money"></i> Payroll</a></li>
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
                <h4 style="margin: 0;">Payroll Management</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Process monthly payroll and generate payslips</p>
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

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-value">$<?php echo number_format($summary['total_earnings'] ?? 0, 2); ?></div>
                    <div>Total Earnings</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-value">$<?php echo number_format($summary['total_deductions'] ?? 0, 2); ?></div>
                    <div>Total Deductions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-value">$<?php echo number_format($summary['total_net'] ?? 0, 2); ?></div>
                    <div>Net Payroll Amount</div>
                </div>
            </div>
            <div class="col-md-3">
                <form method="POST" style="margin-top: 5px;">
                    <input type="hidden" name="generate_payroll" value="1">
                    <button type="submit" class="btn btn-success btn-block" onclick="return confirm('Generate payroll for <?php echo date('F Y', mktime(0,0,0,$month,1,$year)); ?>?')">
                        <i class="fa fa-refresh"></i> Generate Payroll
                    </button>
                </form>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="panel panel-default">
            <div class="panel-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <label>Month: </label>
                        <select name="month" class="form-control" onchange="this.form.submit()">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year: </label>
                        <select name="year" class="form-control" onchange="this.form.submit()">
                            <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payroll Table -->
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-list"></i> Payroll Register - <?php echo date('F Y', mktime(0,0,0,$month,1,$year)); ?></h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Basic</th>
                                <th>HRA</th>
                                <th>DA</th>
                                <th>Allowances</th>
                                <th>Overtime</th>
                                <th>Gross</th>
                                <th>Deductions</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($payroll_list->num_rows > 0): ?>
                                <?php while($pay = $payroll_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $pay['employee_id']; ?></td>
                                        <td><?php echo $pay['first_name'] . ' ' . $pay['last_name']; ?><br>
                                            <small><?php echo $pay['job_title']; ?></small>
                                        </td>
                                        <td><?php echo $pay['department']; ?></td>
                                        <td>$<?php echo number_format($pay['basic_salary'], 2); ?></td>
                                        <td>$<?php echo number_format($pay['hra'], 2); ?></td>
                                        <td>$<?php echo number_format($pay['da'], 2); ?></td>
                                        <td>$<?php echo number_format($pay['allowances'], 2); ?></td>
                                        <td>$<?php echo number_format($pay['overtime_pay'], 2); ?></td>
                                        <td>$<?php echo number_format($pay['total_earnings'], 2); ?></td>
                                        <td>$<?php echo number_format($pay['total_deductions'], 2); ?></td>
                                        <td><strong>$<?php echo number_format($pay['net_salary'], 2); ?></strong></td>
                                        <td>
                                            <span class="label label-<?php echo $pay['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($pay['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="payslip.php?id=<?php echo $pay['payroll_id']; ?>" class="btn btn-info btn-xs" target="_blank">
                                                <i class="fa fa-file-pdf-o"></i> Payslip
                                            </a>
                                            <?php if ($pay['status'] !== 'paid'): ?>
                                                <button onclick="processPayment(<?php echo $pay['payroll_id']; ?>)" class="btn btn-success btn-xs">
                                                    <i class="fa fa-check"></i> Mark Paid
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="13" class="text-center">
                                        No payroll records found. Click "Generate Payroll" to create.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Process Payment</h4>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="process_payment" value="1">
                        <input type="hidden" name="payroll_id" id="payroll_id">
                        <div class="form-group">
                            <label>Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" class="form-control" required>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Bank Reference / Transaction ID</label>
                            <input type="text" name="bank_reference" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Process Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function processPayment(id) {
            $('#payroll_id').val(id);
            $('#paymentModal').modal('show');
        }
    </script>
</body>
</html>