# Employee Management System

The employee management system provides a complete way to handle human resource tasks inside organizations. It uses a PHP-based interface that is built to manage employee profiles, track leave and attendance, process payroll, and evaluate performance. These functions are brought together in one central place so that administrators and HR professionals can manage the workforce more effectively, improve productivity and make decisions based on accurate data.

## Table of Contents

- [Introduction](#introduction)
- [Technologies Used](#technologies-used)
- [Usage](#usage)
- [Features](#features)
  - [User Authentication](#1-user-authentication)
  - [Dashboard](#2-dashboard)
  - [Employee Profiles](#3-employee-profiles)
  - [Leave and Attendance](#4-leave-and-attendance)
  - [Performance Evaluation](#5-performance-evaluation)
  - [Payroll Management](#6-payroll-management)
  - [Reports and Analytics](#7-reports-and-analytics)
  - [Notifications](#8-notifications)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
- [Database Schema](#database-schema)
- [Default Credentials](#default-credentials)
- [Contributing](#contributing)
- [License](#license)

## Introduction

The employee management system helps organizations keep track of their employees. It handles hiring, attendance tracking, performance reviews, and payroll. The system uses PHP and MySQL. There are three types of users: administrators, HR personnel and regular employees. Each user type has different access levels. This prevents people from seeing information that is not meant for them.

## Technologies Used

The following technologies have been used in the development of Employee Management System (EMS):

- **HTML**
- **PHP**
- **MySQL (phpMyAdmin)**
- **CSS**
- **Javascript**
- **XAMPP Software**

## Usage

01. Log in to access the system using role-based credentials (Admin, HR, or Employee).
02. Administrators and HR can add employees and provide necessary details.
03. Employees can clock in/out for daily attendance tracking.
04. Manage leave requests, approve or reject applications, and track leave balances.
05. Generate monthly payroll automatically based on attendance records.
06. Conduct performance reviews and submit self-assessments.
07. Export reports to CSV/Excel for data analysis.

## Features

##### **01. User Authentication**

The system manages user access through a secure login process. Three roles are available: admin, HR, and employee. Each role has different permissions. Passwords are protected using bcrypt hashing for better security.

##### **02. Dashboard**

After logging in, users see a dashboard based on their role. The dashboard shows important information like total employees, attendance for the day, pending leave requests, and recent activities. This main screen gives users quick access to the important parts of the system.

##### **03. Employee Profiles**

Each employee has a detailed profile in the system. The profile includes personal information, contact details, job history, department, job title, salary, emergency contacts, bank account information, and stored documents.

##### **04. Leave and Attendance**

Attendance tracking is done through clock in and clock out features. The system automatically detects late arrivals based on company working hours. HR staff can also enter attendance manually. For leave management, the system supports different types of leave, tracks available balances, and uses an approval process.

##### **05. Performance Evaluation**

Performance reviews are conducted inside the system. This helps managers provide feedback and set goals on time. Employees can submit self-assessments. Managers can give structured reviews using ratings from one to five.

##### **06. Payroll Management**

Payroll processing is simplified through automated salary calculations. The system considers attendance, overtime, and salary structure parts like HRA, DA, allowances, tax deductions, social security, and health insurance. Printable payslips can be generated for employees.

##### **07. Reports and Analytics**

The system generates reports on attendance, leave, and payroll. Data is shown visually using Chart.js graphs. All reports can be exported to CSV or Excel format for further analysis or record keeping.

##### **08. Notifications**

Users receive in-app notifications for leave approvals, payroll updates, attendance confirmations, and account status changes. Notifications can be marked as read or deleted as needed.

## Getting Started

Follow these instructions to get a copy of the Employee Management System project up and running on your local machine for development and testing purposes.

#### Prerequisites

Before you proceed, ensure you have the following software installed:

- **XAMPP Software**
- **PHP** (Version 7.4 or higher)
- **MySQL** (Version 5.7 or higher)
- **Web Browser** (Chrome, Firefox, etc.)

#### Installation

01. **Start XAMPP Services**
```bash
Launch XAMPP Control Panel and start Apache and MySQL services
```

02. **Clone or copy the Employee Management System repository to your local machine**:
```bash
Copy the project folder to C:\xampp\htdocs\ems (Windows) or /var/www/html/ems (Linux/Mac)
```

03. **Create a new MySQL database for Employee Management System**:
```bash
Open phpMyAdmin at http://localhost/phpmyadmin
Create a new database named "ems"
Select utf8_general_ci as collation
```

04. **Import the database schema**:
```bash
Click on the "ems" database
Go to the Import tab
Select the "ems_structure.sql" file from the project
Click Go to execute the import
```

05. **Configure database connection**:
```bash
Open config.php from the project root
Verify database credentials:
$conn = new mysqli('localhost', 'root', '', 'ems');
```

06. **Access the application**:
```bash
Open your browser and navigate to http://localhost/ems/
```

Employee Management System should now be up and running.

## Database Schema

The system includes the following key database tables:

- **users** : Employee and admin account information
- **departments** : Department master list
- **job_titles** : Job titles with salary ranges
- **attendance** : Daily attendance clock in/out records
- **leave_types** : Available leave categories
- **leave_balances** : Employee leave quotas per year
- **leave_applications** : Leave request submissions
- **payroll** : Monthly salary records
- **salary_structures** : Pay calculation rules and percentages
- **performance_reviews** : Manager performance evaluations
- **self_assessments** : Employee self-evaluations
- **notifications** : System notification messages
- **activity_logs** : User activity tracking for audit trail
- **holidays** : Company holiday list
- **company_settings** : System configuration parameters

## Default Credentials

After installation, you can log in using the following demo accounts:

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@ems.com | Admin@123 |
| **HR** | hr@ems.com | Hr@123 |
| **Employee** | employee@ems.com | Emp@123 |

> **Note:** When adding new employees through the system, the default password is `Welcome@123`.

## License

This Employee Management System is distributed under the **MIT License**. You can find the full text of the license in the `LICENSE` file.
