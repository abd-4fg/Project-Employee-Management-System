<?php
// export_report.php - Export reports to CSV/Excel
require_once 'config.php';
requireRole(['admin', 'hr']);

$type = $_GET['type'] ?? '';
$year = (int)($_GET['year'] ?? date('Y'));
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

if (!in_array($type, ['attendance', 'leave', 'payroll', 'employees'])) {
    die("Invalid report type");
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_report_' . $year . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type == 'attendance') {
    // Attendance Report
    fputcsv($output, ['Department', 'Job Title', 'Employee ID', 'Employee Name', 'Present Days', 'Late Days', 'Absent Days', 'Total Hours', 'Overtime Hours']);
    
    $result = $conn->query("
        SELECT 
            u.department,
            u.job_title,
            u.employee_id,
            u.first_name,
            u.last_name,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(a.total_hours) as total_hours,
            SUM(a.overtime_hours) as overtime_hours
        FROM attendance a
        JOIN users u ON a.user_id = u.user_id
        WHERE YEAR(a.date) = $year AND u.role = 'employee'
        GROUP BY u.user_id
        ORDER BY u.department, u.last_name
    ");
    
    while($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['department'],
            $row['job_title'],
            $row['employee_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['present_days'],
            $row['late_days'],
            $row['absent_days'],
            number_format($row['total_hours'], 2),
            number_format($row['overtime_hours'], 2)
        ]);
    }
    
} elseif ($type == 'leave') {
    // Leave Report
    fputcsv($output, ['Employee ID', 'Employee Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Total Days', 'Status', 'Applied Date', 'Approved/Rejected By']);
    
    $result = $conn->query("
        SELECT 
            u.employee_id,
            u.first_name,
            u.last_name,
            u.department,
            lt.name as leave_type,
            la.start_date,
            la.end_date,
            la.total_days,
            la.status,
            la.applied_date,
            CONCAT(apr.first_name, ' ', apr.last_name) as approved_by
        FROM leave_applications la
        JOIN users u ON la.user_id = u.user_id
        JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
        LEFT JOIN users apr ON la.approved_by = apr.user_id
        WHERE YEAR(la.applied_date) = $year
        ORDER BY la.applied_date DESC
    ");
    
    while($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['employee_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['department'],
            $row['leave_type'],
            $row['start_date'],
            $row['end_date'],
            $row['total_days'],
            ucfirst($row['status']),
            $row['applied_date'],
            $row['approved_by'] ?? 'N/A'
        ]);
    }
    
} elseif ($type == 'payroll') {
    // Payroll Report
    fputcsv($output, ['Employee ID', 'Employee Name', 'Department', 'Basic Salary', 'HRA', 'DA', 'Allowances', 'Overtime', 'Total Earnings', 'Tax', 'Social Security', 'Health Insurance', 'Total Deductions', 'Net Salary', 'Status']);
    
    $result = $conn->query("
        SELECT 
            u.employee_id,
            u.first_name,
            u.last_name,
            u.department,
            p.basic_salary,
            p.hra,
            p.da,
            p.allowances,
            p.overtime_pay,
            p.total_earnings,
            p.tax_deduction,
            p.social_security,
            p.health_insurance,
            p.total_deductions,
            p.net_salary,
            p.status
        FROM payroll p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.year = $year
        ORDER BY u.department, u.last_name
    ");
    
    while($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['employee_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['department'],
            $row['basic_salary'],
            $row['hra'],
            $row['da'],
            $row['allowances'],
            $row['overtime_pay'],
            $row['total_earnings'],
            $row['tax_deduction'],
            $row['social_security'],
            $row['health_insurance'],
            $row['total_deductions'],
            $row['net_salary'],
            ucfirst($row['status'])
        ]);
    }
    
} elseif ($type == 'employees') {
    // Employees Report
    fputcsv($output, ['Employee ID', 'Name', 'Email', 'Department', 'Job Title', 'Role', 'Hire Date', 'Salary', 'Phone', 'Status']);
    
    $result = $conn->query("
        SELECT 
            employee_id,
            first_name,
            last_name,
            email,
            department,
            job_title,
            role,
            hire_date,
            salary,
            phone_number,
            is_active
        FROM users
        WHERE role != 'admin'
        ORDER BY department, last_name
    ");
    
    while($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['employee_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['email'],
            $row['department'],
            $row['job_title'],
            ucfirst($row['role']),
            $row['hire_date'],
            $row['salary'],
            $row['phone_number'],
            $row['is_active'] ? 'Active' : 'Inactive'
        ]);
    }
}

fclose($output);
exit();
?>