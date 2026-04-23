<?php
// leave_approve.php - Single leave approval page
require_once 'config.php';
requireRole(['admin', 'hr']);

$leave_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$leave_id) {
    header("Location: leave.php");
    exit();
}

// Get leave details
$leave = $conn->prepare("
    SELECT la.*, lt.name as leave_type_name, u.first_name, u.last_name, u.employee_id, u.department
    FROM leave_applications la
    JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
    JOIN users u ON la.user_id = u.user_id
    WHERE la.leave_id = ?
");
$leave->bind_param("i", $leave_id);
$leave->execute();
$leave_data = $leave->get_result()->fetch_assoc();

if (!$leave_data) {
    header("Location: leave.php");
    exit();
}

$error = '';
$success = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    if ($action == 'approve') {
        $status = 'approved';
    } elseif ($action == 'reject') {
        $status = 'rejected';
    } else {
        $error = "Invalid action";
    }
    
    if (empty($error)) {
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("
                UPDATE leave_applications 
                SET status = ?, approved_by = ?, approved_date = NOW(), rejection_reason = ?
                WHERE leave_id = ?
            ");
            $stmt->bind_param("sisi", $status, $_SESSION['user_id'], $rejection_reason, $leave_id);
            $stmt->execute();
            $stmt->close();
            
            // If approved, update leave balance
            if ($status === 'approved') {
                $current_year = date('Y');
                $update_balance = $conn->prepare("
                    UPDATE leave_balances 
                    SET used_days = used_days + ?, remaining_days = remaining_days - ?
                    WHERE user_id = ? AND leave_type_id = ? AND year = ?
                ");
                $update_balance->bind_param("ddiii", $leave_data['total_days'], $leave_data['total_days'], $leave_data['user_id'], $leave_data['leave_type_id'], $current_year);
                $update_balance->execute();
                $update_balance->close();
                
                addNotification($leave_data['user_id'], 'Leave Approved', 
                    "Your leave request for {$leave_data['total_days']} days from " . date('M d', strtotime($leave_data['start_date'])) . " has been approved", 
                    'leave_approved', "leave.php");
            } else {
                addNotification($leave_data['user_id'], 'Leave Rejected', 
                    "Your leave request for {$leave_data['total_days']} days has been rejected. Reason: $rejection_reason", 
                    'leave_rejected', "leave.php");
            }
            
            $conn->commit();
            logActivity($_SESSION['user_id'], 'leave_' . $status, "Leave application #$leave_id $status");
            $success = "Leave application " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully";
            
            // Redirect after 2 seconds
            header("refresh:2;url=leave.php");
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to process request: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Approve Leave - EMS</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { background: #f4f6f9; }
        .container { max-width: 800px; margin: 50px auto; }
        .card { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { background: #2c3e50; color: white; padding: 20px; }
        .card-body { padding: 30px; }
        .info-row { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .info-label { font-weight: bold; width: 150px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-umbrella"></i> Leave Request Review</h3>
                <p>Review and process employee leave application</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <div class="info-row">
                    <span class="info-label">Employee:</span>
                    <span><?php echo htmlspecialchars($leave_data['first_name'] . ' ' . $leave_data['last_name']); ?> (<?php echo $leave_data['employee_id']; ?>)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Department:</span>
                    <span><?php echo htmlspecialchars($leave_data['department']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Leave Type:</span>
                    <span><?php echo htmlspecialchars($leave_data['leave_type_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Duration:</span>
                    <span><?php echo date('F j, Y', strtotime($leave_data['start_date'])); ?> - <?php echo date('F j, Y', strtotime($leave_data['end_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Days:</span>
                    <span><?php echo $leave_data['total_days']; ?> days</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Reason:</span>
                    <p><?php echo nl2br(htmlspecialchars($leave_data['reason'])); ?></p>
                </div>
                <div class="info-row">
                    <span class="info-label">Applied On:</span>
                    <span><?php echo date('F j, Y g:i A', strtotime($leave_data['applied_date'])); ?></span>
                </div>
                
                <hr>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-block" onclick="return confirm('Approve this leave request?')">
                                <i class="fa fa-check"></i> Approve Leave
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#rejectModal">
                                <i class="fa fa-times"></i> Reject Leave
                            </button>
                        </div>
                    </div>
                </form>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="leave.php" class="btn btn-default">Back to Leave Management</a>
                </div>
                
                <!-- Reject Modal -->
                <div id="rejectModal" class="modal fade">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">Reject Leave Request</h4>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="reject">
                                    <div class="form-group">
                                        <label>Reason for Rejection</label>
                                        <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="Please provide a reason for rejecting this leave request..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Reject Leave</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>