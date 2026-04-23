<?php
// get_employee.php - Get employee details for modal view
require_once 'config.php';
requireAuth();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$user_id = (int)$_GET['id'];

// Check authorization
if ($_SESSION['role'] == 'employee' && $_SESSION['user_id'] != $user_id) {
    die("Unauthorized access");
}

$stmt = $conn->prepare("
    SELECT u.*, d.dept_name 
    FROM users u
    LEFT JOIN departments d ON u.department = d.dept_name
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found");
}
?>

<div class="row">
    <div class="col-md-6">
        <h4>Personal Information</h4>
        <table class="table table-bordered">
            <tr><th width="40%">Employee ID</th><td><?php echo htmlspecialchars($employee['employee_id']); ?></td></tr>
            <tr><th>Full Name</th><td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td></tr>
            <tr><th>Email</th><td><?php echo htmlspecialchars($employee['email']); ?></td></tr>
            <tr><th>Phone Number</th><td><?php echo htmlspecialchars($employee['phone_number'] ?: 'N/A'); ?></td></tr>
            <tr><th>Address</th><td><?php echo nl2br(htmlspecialchars($employee['address'] ?: 'N/A')); ?></td></tr>
            <tr><th>Emergency Contact</th><td><?php echo htmlspecialchars($employee['emergency_contact'] ?: 'N/A'); ?></td></tr>
            <tr><th>Emergency Phone</th><td><?php echo htmlspecialchars($employee['emergency_phone'] ?: 'N/A'); ?></td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <h4>Employment Information</h4>
        <table class="table table-bordered">
            <tr><th width="40%">Department</th><td><?php echo htmlspecialchars($employee['department'] ?: 'N/A'); ?></td></tr>
            <tr><th>Job Title</th><td><?php echo htmlspecialchars($employee['job_title'] ?: 'N/A'); ?></td></tr>
            <tr><th>Role</th><td><?php echo ucfirst($employee['role']); ?></td></tr>
            <tr><th>Hire Date</th><td><?php echo date('F j, Y', strtotime($employee['hire_date'])); ?></td></tr>
            <tr><th>Salary</th><td>$<?php echo number_format($employee['salary'], 2); ?></td></tr>
            <tr><th>Bank Account</th><td><?php echo htmlspecialchars($employee['bank_account'] ?: 'N/A'); ?></td></tr>
            <tr><th>Status</th><td>
                <?php if($employee['is_active']): ?>
                    <span class="label label-success">Active</span>
                <?php else: ?>
                    <span class="label label-danger">Inactive</span>
                <?php endif; ?>
            </td></tr>
        </table>
    </div>
</div>

<?php
// Get leave balances
$current_year = date('Y');
$balances = $conn->prepare("
    SELECT lt.name, lb.remaining_days, lb.used_days, lb.total_days
    FROM leave_balances lb
    JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
    WHERE lb.user_id = ? AND lb.year = ?
");
$balances->bind_param("ii", $user_id, $current_year);
$balances->execute();
$balance_result = $balances->get_result();

if ($balance_result->num_rows > 0):
?>
<h4>Leave Balances (<?php echo $current_year; ?>)</h4>
<table class="table table-bordered">
    <thead>
        <tr><th>Leave Type</th><th>Used</th><th>Remaining</th><th>Total</th></tr>
    </thead>
    <tbody>
        <?php while($bal = $balance_result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($bal['name']); ?></td>
            <td><?php echo $bal['used_days']; ?></td>
            <td><?php echo $bal['remaining_days']; ?></td>
            <td><?php echo $bal['total_days']; ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Recent Payslips Section -->
<h4>Recent Payslips</h4>
<?php
$recent_payslips = $conn->prepare("
    SELECT payroll_id, month, year, net_salary, status, payment_date
    FROM payroll 
    WHERE user_id = ? 
    ORDER BY year DESC, month DESC 
    LIMIT 3
");
$recent_payslips->bind_param("i", $user_id);
$recent_payslips->execute();
$payslip_result = $recent_payslips->get_result();

if ($payslip_result->num_rows > 0):
?>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Period</th>
            <th>Net Salary</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php while($payslip = $payslip_result->fetch_assoc()): ?>
        <tr>
            <td><?php echo date('F Y', mktime(0,0,0,$payslip['month'],1,$payslip['year'])); ?></td>
            <td>$<?php echo number_format($payslip['net_salary'], 2); ?></td>
            <td>
                <span class="label label-<?php echo $payslip['status'] === 'paid' ? 'success' : 'warning'; ?>">
                    <?php echo ucfirst($payslip['status']); ?>
                </span>
            </td>
            <td>
                <a href="payslip.php?id=<?php echo $payslip['payroll_id']; ?>" class="btn btn-xs btn-info" target="_blank">
                    <i class="fa fa-eye"></i> View
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<div class="text-right">
    <a href="payroll.php?user=<?php echo $user_id; ?>" class="btn btn-sm btn-primary">
        <i class="fa fa-list"></i> View All Payroll Records
    </a>
</div>
<?php else: ?>
    <p class="text-muted">No payslips generated yet for this employee.</p>
<?php endif; ?>