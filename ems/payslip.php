<?php
// payslip.php - Generate Payslip
require_once 'config.php';
requireAuth();

$payroll_id = (int)$_GET['id'] ?? 0;

// Get payroll details
$payroll = $conn->prepare("
    SELECT p.*, u.first_name, u.last_name, u.employee_id, u.department, u.job_title, u.bank_account
    FROM payroll p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.payroll_id = ?
");
$payroll->bind_param("i", $payroll_id);
$payroll->execute();
$pay = $payroll->get_result()->fetch_assoc();

if (!$pay) {
    die("Invalid payslip");
}

// Check authorization
if ($_SESSION['role'] == 'employee' && $pay['user_id'] != $_SESSION['user_id']) {
    die("Unauthorized access");
}

$month_name = date('F', mktime(0,0,0,$pay['month'],1,$pay['year']));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payslip - <?php echo $pay['employee_id']; ?> - <?php echo $month_name . ' ' . $pay['year']; ?></title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 20px; }
            .payslip-container { box-shadow: none; }
        }
        body { background: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .payslip-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .payslip-header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .payslip-header h2 { margin: 0; }
        .payslip-header p { margin: 10px 0 0; opacity: 0.8; }
        .payslip-body { padding: 30px; }
        .company-info {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .employee-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .salary-table { width: 100%; margin-bottom: 20px; }
        .salary-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .salary-table tr:last-child td { border-bottom: none; }
        .total-row { font-weight: bold; background: #f0f0f0; }
        .net-salary {
            background: #27ae60;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 5px;
            margin-top: 20px;
        }
        .net-salary h3 { margin: 0; }
        .net-salary .amount { font-size: 32px; font-weight: bold; }
        .footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="payslip-container">
        <div class="payslip-header">
            <h2><?php echo getCompanySetting('company_name'); ?></h2>
            <p>Employee Management System</p>
            <h3>SALARY SLIP</h3>
            <p><?php echo $month_name . ' ' . $pay['year']; ?></p>
        </div>
        
        <div class="payslip-body">
            <div class="company-info">
                <strong><?php echo getCompanySetting('company_name'); ?></strong><br>
                Payroll Department<br>
                payslip@ems.com
            </div>
            
            <div class="employee-info">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Employee ID:</strong> <?php echo $pay['employee_id']; ?><br>
                        <strong>Name:</strong> <?php echo $pay['first_name'] . ' ' . $pay['last_name']; ?><br>
                        <strong>Department:</strong> <?php echo $pay['department']; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Job Title:</strong> <?php echo $pay['job_title']; ?><br>
                        <strong>Bank Account:</strong> <?php echo $pay['bank_account'] ?? 'N/A'; ?><br>
                        <strong>Payment Date:</strong> <?php echo $pay['payment_date'] ? date('F d, Y', strtotime($pay['payment_date'])) : 'Pending'; ?>
                    </div>
                </div>
            </div>
            
            <table class="salary-table">
                <tr style="background: #2c3e50; color: white;">
                    <td colspan="2"><strong>EARNINGS</strong></td>
                    <td class="text-right"><strong>AMOUNT</strong></td>
                </tr>
                <tr><td colspan="3">&nbsp;</td></tr>
                <tr><td colspan="2">Basic Salary</td><td class="text-right">$<?php echo number_format($pay['basic_salary'], 2); ?></td></tr>
                <tr><td colspan="2">House Rent Allowance (HRA)</td><td class="text-right">$<?php echo number_format($pay['hra'], 2); ?></td></tr>
                <tr><td colspan="2">Dearness Allowance (DA)</td><td class="text-right">$<?php echo number_format($pay['da'], 2); ?></td></tr>
                <tr><td colspan="2">Special Allowances</td><td class="text-right">$<?php echo number_format($pay['allowances'], 2); ?></td></tr>
                <tr><td colspan="2">Bonus</td><td class="text-right">$<?php echo number_format($pay['bonus'], 2); ?></td></tr>
                <tr><td colspan="2">Overtime Pay</td><td class="text-right">$<?php echo number_format($pay['overtime_pay'], 2); ?></td></tr>
                <tr class="total-row"><td colspan="2">Total Earnings</td><td class="text-right">$<?php echo number_format($pay['total_earnings'], 2); ?></td></tr>
                
                <tr style="background: #2c3e50; color: white;">
                    <td colspan="2"><strong>DEDUCTIONS</strong></td>
                    <td class="text-right"><strong>AMOUNT</strong></td>
                </tr>
                <tr><td colspan="3">&nbsp;</td></tr>
                <tr><td colspan="2">Tax Deduction (TDS)</td><td class="text-right">$<?php echo number_format($pay['tax_deduction'], 2); ?></td></tr>
                <tr><td colspan="2">Social Security</td><td class="text-right">$<?php echo number_format($pay['social_security'], 2); ?></td></tr>
                <tr><td colspan="2">Health Insurance</td><td class="text-right">$<?php echo number_format($pay['health_insurance'], 2); ?></td></tr>
                <tr class="total-row"><td colspan="2">Total Deductions</td><td class="text-right">$<?php echo number_format($pay['total_deductions'], 2); ?></td></tr>
            </table>
            
            <div class="net-salary">
                <h3>NET SALARY</h3>
                <div class="amount">$<?php echo number_format($pay['net_salary'], 2); ?></div>
                <p>(<?php echo ucwords(str_replace('_', ' ', $pay['payment_method'] ?? 'Bank Transfer')); ?>)</p>
            </div>
            
            <div class="footer">
                <p>This is a computer-generated document. No signature is required.</p>
                <p>For any discrepancies, please contact HR within 7 days.</p>
                <button class="btn btn-primary no-print" onclick="window.print()">
                    <i class="fa fa-print"></i> Print / Save as PDF
                </button>
                <a href="payroll.php" class="btn btn-default no-print">Back to Payroll</a>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>
</body>
</html>