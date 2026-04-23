CREATE DATABASE IF NOT EXISTS ems;
USE ems;


CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hr', 'employee') DEFAULT 'employee',
    department VARCHAR(50),
    job_title VARCHAR(100),
    phone_number VARCHAR(20),
    hire_date DATE,
    salary DECIMAL(10,2),
    bank_account VARCHAR(50),
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    address TEXT,
    profile_pic VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL UNIQUE,
    dept_head INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_head) REFERENCES users(user_id)
);


CREATE TABLE IF NOT EXISTS job_titles (
    job_id INT AUTO_INCREMENT PRIMARY KEY,
    job_title VARCHAR(100) NOT NULL UNIQUE,
    job_description TEXT,
    min_salary DECIMAL(10,2),
    max_salary DECIMAL(10,2)
);


CREATE TABLE IF NOT EXISTS company_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    description TEXT
);


CREATE TABLE IF NOT EXISTS holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(100) NOT NULL,
    holiday_date DATE NOT NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    clock_in_time DATETIME,
    clock_out_time DATETIME,
    clock_in_ip VARCHAR(45),
    clock_out_ip VARCHAR(45),
    total_hours DECIMAL(5,2),
    overtime_hours DECIMAL(5,2) DEFAULT 0,
    status ENUM('present', 'absent', 'late', 'half_day', 'holiday', 'weekend') DEFAULT 'present',
    manual_entry BOOLEAN DEFAULT FALSE,
    approved_by INT,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id),
    UNIQUE KEY unique_attendance (user_id, date)
);


CREATE TABLE IF NOT EXISTS leave_types (
    leave_type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    days_per_year INT DEFAULT 0,
    is_paid BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT TRUE,
    requires_document BOOLEAN DEFAULT FALSE,
    gender_restriction ENUM('all', 'male', 'female') DEFAULT 'all'
);

CREATE TABLE IF NOT EXISTS leave_balances (
    balance_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    total_days INT DEFAULT 0,
    used_days INT DEFAULT 0,
    remaining_days INT DEFAULT 0,
    carried_over INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(leave_type_id),
    UNIQUE KEY unique_balance (user_id, leave_type_id, year)
);

CREATE TABLE IF NOT EXISTS leave_applications (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    reason TEXT,
    document_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    applied_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_by INT,
    approved_date DATETIME,
    rejection_reason TEXT,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(leave_type_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS salary_structures (
    structure_id INT AUTO_INCREMENT PRIMARY KEY,
    job_title VARCHAR(100),
    department VARCHAR(50),
    basic_percent DECIMAL(5,2) DEFAULT 50,
    hra_percent DECIMAL(5,2) DEFAULT 20,
    da_percent DECIMAL(5,2) DEFAULT 10,
    allowance_percent DECIMAL(5,2) DEFAULT 10,
    tax_percent DECIMAL(5,2) DEFAULT 10,
    social_security_percent DECIMAL(5,2) DEFAULT 5,
    health_insurance_fixed DECIMAL(10,2) DEFAULT 500,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payroll (
    payroll_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    hra DECIMAL(10,2) DEFAULT 0,
    da DECIMAL(10,2) DEFAULT 0,
    allowances DECIMAL(10,2) DEFAULT 0,
    bonus DECIMAL(10,2) DEFAULT 0,
    overtime_pay DECIMAL(10,2) DEFAULT 0,
    total_earnings DECIMAL(10,2),
    tax_deduction DECIMAL(10,2) DEFAULT 0,
    social_security DECIMAL(10,2) DEFAULT 0,
    health_insurance DECIMAL(10,2) DEFAULT 0,
    loan_deduction DECIMAL(10,2) DEFAULT 0,
    other_deductions DECIMAL(10,2) DEFAULT 0,
    total_deductions DECIMAL(10,2),
    net_salary DECIMAL(10,2),
    payment_date DATE,
    payment_method ENUM('bank_transfer', 'cash', 'check') DEFAULT 'bank_transfer',
    bank_reference VARCHAR(100),
    status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
    generated_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (generated_by) REFERENCES users(user_id),
    UNIQUE KEY unique_payroll (user_id, month, year)
);

CREATE TABLE IF NOT EXISTS performance_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    review_date DATE NOT NULL,
    review_period_start DATE,
    review_period_end DATE,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    strengths TEXT,
    weaknesses TEXT,
    goals TEXT,
    achievements TEXT,
    improvement_plan TEXT,
    status ENUM('draft', 'submitted', 'completed') DEFAULT 'draft',
    reviewed_by INT,
    review_feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS self_assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    review_period VARCHAR(50),
    achievements TEXT,
    challenges TEXT,
    skills_developed TEXT,
    future_goals TEXT,
    rating_self INT CHECK (rating_self BETWEEN 1 AND 5),
    submitted_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('draft', 'submitted') DEFAULT 'draft',
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50),
    title VARCHAR(255),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    link VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS employee_documents (
    doc_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type VARCHAR(50),
    document_name VARCHAR(255),
    file_path VARCHAR(500),
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);

INSERT INTO leave_types (name, description, days_per_year, is_paid, requires_approval) VALUES
('Annual Leave', 'Regular paid vacation days', 20, TRUE, TRUE),
('Sick Leave', 'Medical and health-related leave', 12, TRUE, TRUE),
('Unpaid Leave', 'Leave without pay', 0, FALSE, TRUE),
('Maternity Leave', 'Maternity leave for female employees', 90, TRUE, TRUE),
('Paternity Leave', 'Paternity leave for male employees', 10, TRUE, TRUE),
('Bereavement Leave', 'Family emergency leave', 5, TRUE, TRUE),
('Study Leave', 'Educational purposes', 10, TRUE, TRUE),
('Work From Home', 'Remote work arrangement', 0, TRUE, TRUE);

INSERT INTO departments (dept_name, description) VALUES
('IT', 'Information Technology Department'),
('Human Resources', 'HR Department'),
('Sales', 'Sales and Business Development'),
('Marketing', 'Marketing and Communications'),
('Finance', 'Finance and Accounting'),
('Operations', 'Operations Management'),
('Customer Support', 'Customer Service Department');

INSERT INTO job_titles (job_title, job_description, min_salary, max_salary) VALUES
('Software Engineer', 'Develops and maintains software applications', 40000, 80000),
('Senior Software Engineer', 'Leads development projects', 60000, 100000),
('HR Manager', 'Manages HR operations', 50000, 90000),
('Sales Representative', 'Handles sales and client relationships', 30000, 60000),
('Marketing Specialist', 'Manages marketing campaigns', 35000, 65000),
('Accountant', 'Handles financial transactions', 35000, 60000),
('System Administrator', 'Manages IT infrastructure', 45000, 75000),
('Customer Support Agent', 'Handles customer inquiries', 25000, 40000);

INSERT INTO salary_structures (job_title, basic_percent, hra_percent, da_percent, allowance_percent, tax_percent, social_security_percent, health_insurance_fixed) VALUES
('default', 50, 20, 10, 10, 10, 5, 500);

INSERT INTO company_settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'EMS Corporation', 'text', 'Company Name'),
('company_logo', '', 'image', 'Company Logo'),
('working_hours_start', '09:00:00', 'time', 'Office Start Time'),
('working_hours_end', '18:00:00', 'time', 'Office End Time'),
('late_grace_minutes', '15', 'number', 'Grace period for late arrival'),
('weekend_days', 'Saturday,Sunday', 'text', 'Weekend days'),
('payroll_day', '28', 'number', 'Monthly payroll processing day');

INSERT INTO holidays (holiday_name, holiday_date, is_recurring) VALUES
('New Year\'s Day', CONCAT(YEAR(CURDATE()), '-01-01'), TRUE),
('Republic Day', CONCAT(YEAR(CURDATE()), '-01-26'), TRUE),
('Independence Day', CONCAT(YEAR(CURDATE()), '-08-15'), TRUE),
('Gandhi Jayanti', CONCAT(YEAR(CURDATE()), '-10-02'), TRUE),
('Christmas Day', CONCAT(YEAR(CURDATE()), '-12-25'), TRUE);

INSERT INTO users (employee_id, first_name, last_name, email, password, role, department, job_title, hire_date, salary, is_active) 
VALUES ('EMP001', 'System', 'Administrator', 'admin@ems.com', 'Admin@123', 'admin', 'IT', 'System Administrator', CURDATE(), 90000, 1);

INSERT INTO users (employee_id, first_name, last_name, email, password, role, department, job_title, hire_date, salary, is_active) 
VALUES ('EMP002', 'Demo', 'HR', 'hr@ems.com', 'Hr@123', 'hr', 'Human Resources', 'HR Manager', CURDATE(), 70000, 1);

INSERT INTO users (employee_id, first_name, last_name, email, password, role, department, job_title, hire_date, salary, is_active) 
VALUES ('EMP003', 'Demo', 'Employee1', 'employee@ems.com', 'Emp@123', 'employee', 'IT', 'Software Engineer', CURDATE(), 55000, 1);

INSERT INTO users (employee_id, first_name, last_name, email, password, role, department, job_title, hire_date, salary, is_active) 
VALUES ('EMP004', 'Demo', 'Employee2', 'employee2@ems.com', 'Emp@123', 'employee', 'Sales', 'Sales Representative', CURDATE(), 45000, 1);

INSERT INTO users (employee_id, first_name, last_name, email, password, role, department, job_title, hire_date, salary, is_active) 
VALUES ('EMP005', 'Demo', 'Employee3', 'employee3@ems.com', 'Emp@123', 'employee', 'Marketing', 'Marketing Specialist', CURDATE(), 48000, 1);

SET @current_year = YEAR(CURDATE());

INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days, remaining_days)
SELECT u.user_id, lt.leave_type_id, @current_year, lt.days_per_year, 0, lt.days_per_year
FROM users u
CROSS JOIN leave_types lt
WHERE u.role IN ('employee', 'hr')
AND lt.days_per_year > 0;

INSERT INTO notifications (user_id, type, title, message)
SELECT user_id, 'welcome', 'Welcome to EMS', 'Your account has been created successfully. You can now manage your attendance and leaves.'
FROM users;

SELECT '✅ Database setup complete!' as Status;
SELECT 'Demo Users:' as Info;
SELECT email, role, 'Use password:' as Password,
    CASE email
        WHEN 'admin@ems.com' THEN 'Admin@123'
        WHEN 'hr@ems.com' THEN 'Hr@123'
        ELSE 'Emp@123'
    END as demo_password
FROM users;