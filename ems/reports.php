<?php
// reports.php - Reports and Analytics
require_once 'config.php';
requireRole(['admin', 'hr']);

$year = $_GET['year'] ?? date('Y');
$report_type = $_GET['type'] ?? 'attendance';

// Get departments for filter
$departments = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL");

// Attendance Report Data
$attendance_data = $conn->query("
    SELECT 
        u.department,
        u.job_title,
        COUNT(DISTINCT a.user_id) as total_employees,
        COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) as present_days,
        COALESCE(SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END), 0) as late_days,
        COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) as absent_days,
        COALESCE(AVG(a.total_hours), 0) as avg_hours
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    WHERE YEAR(a.date) = $year AND u.role = 'employee'
    GROUP BY u.department, u.job_title
");

// Leave Report Data
$leave_data = $conn->query("
    SELECT 
        lt.name as leave_type,
        COUNT(*) as total_applications,
        COALESCE(SUM(CASE WHEN la.status = 'approved' THEN 1 ELSE 0 END), 0) as approved,
        COALESCE(SUM(CASE WHEN la.status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN la.status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected,
        COALESCE(SUM(la.total_days), 0) as total_days_used
    FROM leave_applications la
    JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
    WHERE YEAR(la.applied_date) = $year
    GROUP BY lt.name
");

// Payroll Report Data
$payroll_data = $conn->query("
    SELECT 
        u.department,
        COALESCE(SUM(p.basic_salary), 0) as total_basic,
        COALESCE(SUM(p.hra), 0) as total_hra,
        COALESCE(SUM(p.da), 0) as total_da,
        COALESCE(SUM(p.allowances), 0) as total_allowances,
        COALESCE(SUM(p.overtime_pay), 0) as total_overtime,
        COALESCE(SUM(p.total_earnings), 0) as total_earnings,
        COALESCE(SUM(p.total_deductions), 0) as total_deductions,
        COALESCE(SUM(p.net_salary), 0) as total_net,
        COUNT(*) as employee_count
    FROM payroll p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.year = $year
    GROUP BY u.department
");

// Monthly Trend Data
$monthly_trend = $conn->query("
    SELECT 
        month,
        COALESCE(SUM(net_salary), 0) as total_payroll,
        COALESCE(AVG(net_salary), 0) as avg_salary,
        COUNT(*) as employee_count
    FROM payroll
    WHERE year = $year
    GROUP BY month
    ORDER BY month
");

// Top Performers
$top_performers = $conn->query("
    SELECT 
        u.first_name, u.last_name, u.employee_id, u.department,
        COALESCE(AVG(pr.rating), 0) as avg_rating,
        COUNT(pr.review_id) as review_count
    FROM performance_reviews pr
    JOIN users u ON pr.user_id = u.user_id
    WHERE pr.rating >= 4
    GROUP BY u.user_id
    ORDER BY avg_rating DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports & Analytics - EMS</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .report-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .export-btn { margin: 10px 0; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>Reports & Analytics</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="payroll.php"><i class="fa fa-money"></i> Payroll</a></li>
                <li><a href="performance.php"><i class="fa fa-star"></i> Performance</a></li>
                <li><a href="reports.php" class="active"><i class="fa fa-bar-chart"></i> Reports</a></li>
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
                <h4 style="margin: 0;">Reports & Analytics</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Comprehensive business intelligence and reporting</p>
            </div>
            <div>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa fa-sign-out"></i> Logout</a>
            </div>
        </div>

        <!-- Year Filter -->
        <div class="panel panel-default">
            <div class="panel-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <label>Select Year: </label>
                        <select name="year" class="form-control" onchange="this.form.submit()">
                            <?php for($y=date('Y')-3; $y<=date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Report Type: </label>
                        <select name="type" class="form-control" onchange="this.form.submit()">
                            <option value="attendance" <?php echo $report_type == 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                            <option value="leave" <?php echo $report_type == 'leave' ? 'selected' : ''; ?>>Leave Report</option>
                            <option value="payroll" <?php echo $report_type == 'payroll' ? 'selected' : ''; ?>>Payroll Report</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($report_type == 'attendance'): ?>
        <!-- Attendance Report -->
        <div class="report-card">
            <h4><i class="fa fa-calendar"></i> Attendance Report - <?php echo $year; ?></h4>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <canvas id="attendanceChart" height="250"></canvas>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed">
                            <thead>
                                <tr><th>Department</th><th>Job Title</th><th>Employees</th><th>Present %</th><th>Late Days</th><th>Absent Days</th></tr>
                            </thead>
                            <tbody>
                                <?php while($row = $attendance_data->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td><?php echo htmlspecialchars($row['job_title']); ?></td>
                                    <td><?php echo $row['total_employees']; ?></td>
                                    <td>
                                        <?php 
                                        $total_possible_days = max($row['total_employees'] * 240, 1);
                                        $percentage = ($row['present_days'] / $total_possible_days) * 100;
                                        echo round($percentage, 1);
                                        ?>%
                                    </td>
                                    <td><?php echo $row['late_days']; ?></td>
                                    <td><?php echo $row['absent_days']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="export-btn">
                <a href="export_report.php?type=attendance&year=<?php echo $year; ?>" class="btn btn-success">
                    <i class="fa fa-file-excel-o"></i> Export to Excel
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($report_type == 'leave'): ?>
        <!-- Leave Report -->
        <div class="report-card">
            <h4><i class="fa fa-umbrella"></i> Leave Report - <?php echo $year; ?></h4>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <canvas id="leaveChart" height="250"></canvas>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed">
                            <thead>
                                <tr><th>Leave Type</th><th>Applications</th><th>Approved</th><th>Pending</th><th>Rejected</th><th>Days Used</th></tr>
                            </thead>
                            <tbody>
                                <?php while($row = $leave_data->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                    <td><?php echo $row['total_applications']; ?></td>
                                    <td><?php echo $row['approved']; ?></td>
                                    <td><?php echo $row['pending']; ?></td>
                                    <td><?php echo $row['rejected']; ?></td>
                                    <td><?php echo $row['total_days_used']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="export-btn">
                <a href="export_report.php?type=leave&year=<?php echo $year; ?>" class="btn btn-success">
                    <i class="fa fa-file-excel-o"></i> Export to Excel
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($report_type == 'payroll'): ?>
        <!-- Payroll Report -->
        <div class="report-card">
            <h4><i class="fa fa-money"></i> Payroll Report - <?php echo $year; ?></h4>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <canvas id="payrollChart" height="250"></canvas>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed">
                            <thead>
                                <tr><th>Department</th><th>Employees</th><th>Total Earnings</th><th>Total Deductions</th><th>Net Payroll</th></tr>
                            </thead>
                            <tbody>
                                <?php while($row = $payroll_data->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td><?php echo $row['employee_count']; ?></td>
                                    <td>$<?php echo number_format($row['total_earnings'], 2); ?></td>
                                    <td>$<?php echo number_format($row['total_deductions'], 2); ?></td>
                                    <td><strong>$<?php echo number_format($row['total_net'], 2); ?></strong></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <h5>Monthly Payroll Trend</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr><th>Month</th><th>Employees Processed</th><th>Total Payroll</th><th>Average Salary</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $monthly_trend->data_seek(0);
                        while($row = $monthly_trend->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo date('F', mktime(0,0,0,$row['month'],1)); ?></td>
                            <td><?php echo $row['employee_count']; ?></td>
                            <td>$<?php echo number_format($row['total_payroll'], 2); ?></td>
                            <td>$<?php echo number_format($row['avg_salary'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="export-btn">
                <a href="export_report.php?type=payroll&year=<?php echo $year; ?>" class="btn btn-success">
                    <i class="fa fa-file-excel-o"></i> Export to Excel
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Performers Section -->
        <div class="report-card">
            <h4><i class="fa fa-trophy"></i> Top Performers - <?php echo $year; ?></h4>
            <hr>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr><th>Employee</th><th>Department</th><th>Average Rating</th><th>Reviews</th></tr>
                    </thead>
                    <tbody>
                        <?php while($row = $top_performers->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?><br>
                                <small><?php echo htmlspecialchars($row['employee_id']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <?php if($i <= round($row['avg_rating'])): ?>
                                        <i class="fa fa-star" style="color: #f39c12;"></i>
                                    <?php else: ?>
                                        <i class="fa fa-star-o"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                (<?php echo round($row['avg_rating'], 1); ?>)
                            </td>
                            <td><?php echo $row['review_count']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Attendance Chart
        var depts = <?php 
            $attendance_data->data_seek(0);
            $dept_names = [];
            $attendance_rates = [];
            while($d = $attendance_data->fetch_assoc()) {
                $dept_names[] = $d['department'];
                $total_possible_days = max($d['total_employees'] * 240, 1);
                $attendance_rates[] = round(($d['present_days'] / $total_possible_days) * 100, 1);
            }
            echo json_encode($dept_names);
        ?>;
        var attendanceRates = <?php echo json_encode($attendance_rates); ?>;
        
        if (document.getElementById('attendanceChart')) {
            new Chart(document.getElementById('attendanceChart'), {
                type: 'bar',
                data: { labels: depts, datasets: [{ label: 'Attendance %', data: attendanceRates, backgroundColor: '#3498db' }] },
                options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } }
            });
        }
        
        // Leave Chart
        var leaveTypes = <?php 
            $leave_data->data_seek(0);
            $leave_names = [];
            $leave_counts = [];
            while($l = $leave_data->fetch_assoc()) {
                $leave_names[] = $l['leave_type'];
                $leave_counts[] = $l['total_days_used'];
            }
            echo json_encode($leave_names);
        ?>;
        var leaveDays = <?php echo json_encode($leave_counts); ?>;
        
        if (document.getElementById('leaveChart')) {
            new Chart(document.getElementById('leaveChart'), {
                type: 'pie',
                data: { labels: leaveTypes, datasets: [{ data: leaveDays, backgroundColor: ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#1abc9c'] }] }
            });
        }
        
        // Payroll Chart
        var payrollMonths = [];
        var payrollAmounts = [];
        <?php 
        $monthly_trend->data_seek(0);
        for($i=1; $i<=12; $i++) {
            $found = false;
            while($p = $monthly_trend->fetch_assoc()) {
                if($p['month'] == $i) {
                    echo "payrollMonths.push('" . date('F', mktime(0,0,0,$i,1)) . "');";
                    echo "payrollAmounts.push(" . ($p['total_payroll'] / 1000) . ");";
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                echo "payrollMonths.push('" . date('F', mktime(0,0,0,$i,1)) . "');";
                echo "payrollAmounts.push(0);";
            }
            $monthly_trend->data_seek(0);
        }
        ?>
        
        if (document.getElementById('payrollChart')) {
            new Chart(document.getElementById('payrollChart'), {
                type: 'line',
                data: { labels: payrollMonths, datasets: [{ label: 'Payroll ($K)', data: payrollAmounts, borderColor: '#e74c3c', fill: true }] }
            });
        }
    </script>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>