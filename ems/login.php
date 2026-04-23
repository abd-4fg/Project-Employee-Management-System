<?php
// login.php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password, role, department, job_title, is_active FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (!$user['is_active']) {
            $error = "Your account is deactivated. Please contact HR.";
        } else {
            // Check password (plain text or hashed)
            $password_valid = false;
            
            if (strpos($user['password'], '$2y$') === 0) {
                $password_valid = password_verify($password, $user['password']);
            } else {
                $password_valid = ($password === $user['password']);
                if ($password_valid) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $update_stmt->bind_param("si", $hashed, $user['user_id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            }
            
            if ($password_valid) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['job_title'] = $user['job_title'];
                
                logActivity($user['user_id'], 'login', 'User logged in');
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        }
    } else {
        $error = "Invalid email or password";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EMS - Employee Management System</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container { max-width: 450px; margin: 0 auto; }
        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h2 { margin: 0; font-size: 28px; }
        .login-header p { margin: 10px 0 0; opacity: 0.8; }
        .login-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-control { height: 45px; border-radius: 5px; }
        .btn-login {
            background: #2c3e50;
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            border-radius: 5px;
        }
        .btn-login:hover { background: #1a252f; }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="fa fa-users"></i> EMS Portal</h2>
                <p><?php echo getCompanySetting('company_name'); ?></p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" placeholder="Email Address" required autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" placeholder="Password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-login">Login <i class="fa fa-sign-in"></i></button>
                </form>
                
            </div>
        </div>
    </div>
</body>
</html>