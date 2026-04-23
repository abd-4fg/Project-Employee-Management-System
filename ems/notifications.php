<?php
// notifications.php - Notification Management
require_once 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Mark single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notif_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Delete all read notifications
if (isset($_GET['delete_all_read'])) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query based on filter
$where_condition = "user_id = $user_id";
if ($filter === 'unread') {
    $where_condition .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where_condition .= " AND is_read = 1";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM notifications WHERE $where_condition";
$total_result = $conn->query($count_query);
$total_notifications = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $per_page);

// Get notifications with pagination
$notifications = $conn->query("
    SELECT * FROM notifications 
    WHERE $where_condition 
    ORDER BY created_at DESC 
    LIMIT $offset, $per_page
");

// Get counts for badges
$unread_count = getUnreadNotificationCount($user_id);
$total_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications - EMS</title>
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
        .notification-item {
            background: white;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
        }
        .notification-item:hover { transform: translateX(5px); box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .notification-unread { background: #e8f4fd; border-left: 4px solid #3498db; }
        .notification-read { background: white; border-left: 4px solid #bdc3c7; opacity: 0.8; }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            float: left;
            margin-right: 15px;
        }
        .notification-content { margin-left: 55px; }
        .notification-title { font-weight: bold; margin: 0 0 5px; }
        .notification-message { color: #666; margin: 0 0 5px; }
        .notification-time { font-size: 11px; color: #999; }
        .notification-actions {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }
        .notification-actions a { margin-left: 10px; color: #999; }
        .notification-actions a:hover { color: #333; }
        .filter-badge { margin-right: 10px; }
        .pagination { margin-top: 20px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>Notifications</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <?php if ($_SESSION['role'] != 'employee'): ?>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <?php endif; ?>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="payroll.php"><i class="fa fa-money"></i> Payroll</a></li>
                <?php if ($_SESSION['role'] != 'employee'): ?>
                <li><a href="performance.php"><i class="fa fa-star"></i> Performance</a></li>
                <li><a href="reports.php"><i class="fa fa-bar-chart"></i> Reports</a></li>
                <?php else: ?>
                <li><a href="performance_employee.php"><i class="fa fa-star"></i> Performance</a></li>
                <?php endif; ?>
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
                <h4 style="margin: 0;">Notifications</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Stay updated with system alerts and messages</p>
            </div>
            <div>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa fa-sign-out"></i> Logout</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="alert alert-info" style="margin-bottom: 15px;">
                    <i class="fa fa-bell"></i> 
                    <strong><?php echo $unread_count; ?></strong> Unread Notifications
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-default" style="margin-bottom: 15px; background: #ecf0f1;">
                    <i class="fa fa-envelope"></i> 
                    <strong><?php echo $total_count; ?></strong> Total Notifications
                </div>
            </div>
            <div class="col-md-4 text-right">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn btn-info btn-sm" onclick="return confirm('Mark all notifications as read?')">
                        <i class="fa fa-check-circle"></i> Mark All Read
                    </a>
                <?php endif; ?>
                <?php if ($total_count > 0): ?>
                    <a href="?delete_all_read=1" class="btn btn-danger btn-sm" onclick="return confirm('Delete all read notifications?')">
                        <i class="fa fa-trash"></i> Clear Read
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="panel panel-default">
            <div class="panel-body">
                <div class="btn-group" role="group">
                    <a href="?filter=all" class="btn btn-sm <?php echo $filter == 'all' ? 'btn-primary' : 'btn-default'; ?>">
                        <i class="fa fa-list"></i> All 
                        <?php if($total_count > 0): ?>
                            <span class="badge"><?php echo $total_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=unread" class="btn btn-sm <?php echo $filter == 'unread' ? 'btn-primary' : 'btn-default'; ?>">
                        <i class="fa fa-envelope"></i> Unread
                        <?php if($unread_count > 0): ?>
                            <span class="badge badge-danger" style="background: #e74c3c;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=read" class="btn btn-sm <?php echo $filter == 'read' ? 'btn-primary' : 'btn-default'; ?>">
                        <i class="fa fa-check-circle"></i> Read
                    </a>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <?php if ($notifications->num_rows > 0): ?>
            <?php while($notif = $notifications->fetch_assoc()): ?>
                <div class="notification-item <?php echo $notif['is_read'] ? 'notification-read' : 'notification-unread'; ?>">
                    <div class="notification-icon" style="background: <?php echo getNotificationColor($notif['type']); ?>">
                        <i class="fa <?php echo getNotificationIcon($notif['type']); ?>" style="color: white;"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">
                            <?php echo htmlspecialchars($notif['title']); ?>
                            <?php if (!$notif['is_read']): ?>
                                <span class="label label-primary" style="margin-left: 10px;">New</span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                        <div class="notification-time">
                            <i class="fa fa-clock-o"></i> 
                            <?php echo timeAgo(strtotime($notif['created_at'])); ?>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <?php if (!$notif['is_read']): ?>
                            <a href="?mark_read=<?php echo $notif['notification_id']; ?>" title="Mark as read">
                                <i class="fa fa-check-circle"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($notif['link']): ?>
                            <a href="<?php echo $notif['link']; ?>" title="View details">
                                <i class="fa fa-external-link"></i>
                            </a>
                        <?php endif; ?>
                        <a href="?delete=<?php echo $notif['notification_id']; ?>" title="Delete" onclick="return confirm('Delete this notification?')">
                            <i class="fa fa-trash"></i>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li><a href="?filter=<?php echo $filter; ?>&page=<?php echo $page-1; ?>">&laquo; Previous</a></li>
                        <?php endif; ?>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li><a href="?filter=<?php echo $filter; ?>&page=<?php echo $page+1; ?>">Next &raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info text-center" style="padding: 50px;">
                <i class="fa fa-bell-slash fa-4x"></i>
                <h4>No notifications found</h4>
                <p>You're all caught up! New notifications will appear here.</p>
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>

<?php
// Helper functions for notifications page

function getNotificationIcon($type) {
    switch($type) {
        case 'leave_request':
        case 'leave_approved':
        case 'leave_rejected':
            return 'fa-umbrella';
        case 'attendance':
            return 'fa-clock-o';
        case 'payroll':
            return 'fa-money';
        case 'performance':
            return 'fa-star';
        case 'welcome':
            return 'fa-smile-o';
        default:
            return 'fa-bell';
    }
}

function getNotificationColor($type) {
    switch($type) {
        case 'leave_request':
            return '#f39c12';
        case 'leave_approved':
            return '#27ae60';
        case 'leave_rejected':
            return '#e74c3c';
        case 'attendance':
            return '#3498db';
        case 'payroll':
            return '#1abc9c';
        case 'performance':
            return '#9b59b6';
        case 'welcome':
            return '#2c3e50';
        default:
            return '#7f8c8d';
    }
}

function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y g:i A', $timestamp);
    }
}
?>