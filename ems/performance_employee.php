<?php
// performance_employee.php - Employee Self Assessment
require_once 'config.php';
requireRole('employee');

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle self-assessment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    $review_period = trim($_POST['review_period']);
    $achievements = trim($_POST['achievements']);
    $challenges = trim($_POST['challenges']);
    $skills_developed = trim($_POST['skills_developed']);
    $future_goals = trim($_POST['future_goals']);
    $rating_self = (int)$_POST['rating_self'];
    
    $stmt = $conn->prepare("
        INSERT INTO self_assessments 
        (user_id, review_period, achievements, challenges, skills_developed, future_goals, rating_self, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')
    ");
    $stmt->bind_param("isssssi", $user_id, $review_period, $achievements, $challenges, $skills_developed, $future_goals, $rating_self);
    
    if ($stmt->execute()) {
        $assessment_id = $stmt->insert_id;
        
        // Notify HR with direct link to assessment
        $hr_users = $conn->query("SELECT user_id FROM users WHERE role IN ('admin', 'hr')");
        while ($hr = $hr_users->fetch_assoc()) {
            addNotification($hr['user_id'], 'Self Assessment Submitted', 
                "{$_SESSION['first_name']} {$_SESSION['last_name']} has submitted their self assessment for review.", 
                'performance', 
                "performance.php?assessment=" . $assessment_id);
        }
        $success = "Self assessment submitted successfully! Your manager will review it soon.";
        logActivity($user_id, 'self_assessment', "Submitted self assessment");
    } else {
        $error = "Failed to submit assessment: " . $stmt->error;
    }
    $stmt->close();
}

// Get previous assessments
$assessments = $conn->prepare("
    SELECT * FROM self_assessments 
    WHERE user_id = ? 
    ORDER BY submitted_date DESC
");
$assessments->bind_param("i", $user_id);
$assessments->execute();
$assessment_list = $assessments->get_result();

// Get performance reviews from managers
$reviews = $conn->prepare("
    SELECT pr.*, r.first_name as reviewer_first, r.last_name as reviewer_last
    FROM performance_reviews pr
    JOIN users r ON pr.reviewer_id = r.user_id
    WHERE pr.user_id = ?
    ORDER BY pr.review_date DESC
");
$reviews->bind_param("i", $user_id);
$reviews->execute();
$review_list = $reviews->get_result();

// Get employee details
$emp_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$emp_stmt->bind_param("i", $user_id);
$emp_stmt->execute();
$employee = $emp_stmt->get_result()->fetch_assoc();
$emp_stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Performance - EMS</title>
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
        .rating-star { color: #f39c12; font-size: 18px; }
        .assessment-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .review-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>My Performance</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="my_payslips.php"><i class="fa fa-money"></i> My Payslips</a></li>
                <li><a href="performance_employee.php" class="active"><i class="fa fa-star"></i> Performance</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div>
                <h4 style="margin: 0;">My Performance</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Self assessment and performance feedback</p>
            </div>
            <div>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa fa-sign-out"></i> Logout</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-pencil-square-o"></i> Self Assessment Form</h3>
                    </div>
                    <div class="panel-body">
                        <div class="alert alert-info">
                            <strong>Employee:</strong> <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?><br>
                            <strong>Department:</strong> <?php echo htmlspecialchars($employee['department']); ?> | 
                            <strong>Job Title:</strong> <?php echo htmlspecialchars($employee['job_title']); ?>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="submit_assessment" value="1">
                            
                            <div class="form-group">
                                <label>Review Period <span class="text-danger">*</span></label>
                                <input type="text" name="review_period" class="form-control" 
                                       value="<?php echo date('F Y'); ?> - Quarterly Review" required>
                                <small class="text-muted">Example: "January 2026 - March 2026"</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Key Achievements <span class="text-danger">*</span></label>
                                <textarea name="achievements" class="form-control" rows="4" required 
                                    placeholder="List your key achievements during this period...&#10;&#10;Examples:&#10;- Completed Project X ahead of schedule&#10;- Improved process Y resulting in 20% efficiency gain&#10;- Received positive client feedback on Project Z"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Challenges Faced <span class="text-danger">*</span></label>
                                <textarea name="challenges" class="form-control" rows="3" required 
                                    placeholder="Describe any challenges you faced and how you overcame them..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Skills Developed <span class="text-danger">*</span></label>
                                <textarea name="skills_developed" class="form-control" rows="3" required 
                                    placeholder="What new skills have you learned or improved?&#10;&#10;Examples:&#10;- Learned Python programming&#10;- Improved presentation skills&#10;- Completed leadership training"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Future Goals <span class="text-danger">*</span></label>
                                <textarea name="future_goals" class="form-control" rows="3" required 
                                    placeholder="What are your goals for the next review period?&#10;&#10;Examples:&#10;- Lead a project team&#10;- Achieve certification in your field&#10;- Mentor junior team members"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Self Rating (1-5) <span class="text-danger">*</span></label>
                                <select name="rating_self" class="form-control" required>
                                    <option value="">Select Rating</option>
                                    <option value="5">5 - Excellent (Exceeded all expectations)</option>
                                    <option value="4">4 - Very Good (Often exceeded expectations)</option>
                                    <option value="3">3 - Good (Met expectations consistently)</option>
                                    <option value="2">2 - Needs Improvement (Sometimes fell short)</option>
                                    <option value="1">1 - Poor (Failed to meet expectations)</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Submit Self Assessment</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-history"></i> My Previous Assessments</h3>
                    </div>
                    <div class="panel-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($assessment_list->num_rows > 0): ?>
                            <?php while($assess = $assessment_list->fetch_assoc()): ?>
                                <div class="assessment-card">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <strong><?php echo htmlspecialchars($assess['review_period']); ?></strong>
                                        <span>
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <?php if($i <= $assess['rating_self']): ?>
                                                    <i class="fa fa-star rating-star"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-star-o"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                    <hr style="margin: 10px 0;">
                                    <small><strong>Achievements:</strong> <?php echo htmlspecialchars(substr($assess['achievements'], 0, 100)); ?>...</small><br>
                                    <small><strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($assess['submitted_date'])); ?></small>
                                    <br>
                                    <span class="label label-<?php echo $assess['status'] === 'submitted' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($assess['status']); ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No self assessments submitted yet</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-users"></i> Manager Feedback</h3>
                    </div>
                    <div class="panel-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if ($review_list->num_rows > 0): ?>
                            <?php while($review = $review_list->fetch_assoc()): ?>
                                <div class="review-card">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <strong><?php echo date('M d, Y', strtotime($review['review_date'])); ?></strong>
                                        <span>
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <?php if($i <= $review['rating']): ?>
                                                    <i class="fa fa-star rating-star"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-star-o"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                    <hr style="margin: 10px 0;">
                                    <small><strong>Strengths:</strong> <?php echo htmlspecialchars(substr($review['strengths'], 0, 80)); ?>...</small><br>
                                    <small><strong>Areas to Improve:</strong> <?php echo htmlspecialchars(substr($review['weaknesses'], 0, 80)); ?>...</small><br>
                                    <?php if ($review['goals']): ?>
                                        <small><strong>Goals:</strong> <?php echo htmlspecialchars(substr($review['goals'], 0, 80)); ?>...</small><br>
                                    <?php endif; ?>
                                    <small class="text-muted">Reviewed by: <?php echo htmlspecialchars($review['reviewer_first'] . ' ' . $review['reviewer_last']); ?></small>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No manager feedback available yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>