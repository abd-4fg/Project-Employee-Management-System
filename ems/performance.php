<?php
// performance.php - Performance Reviews Management
require_once 'config.php';
requireRole(['admin', 'hr']);

$error = '';
$success = '';
$employee_id = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;

// Handle performance review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $user_id = (int)$_POST['user_id'];
    $review_date = $_POST['review_date'];
    $rating = (int)$_POST['rating'];
    $strengths = trim($_POST['strengths']);
    $weaknesses = trim($_POST['weaknesses']);
    $goals = trim($_POST['goals']);
    $achievements = trim($_POST['achievements']);
    $improvement_plan = trim($_POST['improvement_plan']);
    
    $stmt = $conn->prepare("
        INSERT INTO performance_reviews 
        (user_id, reviewer_id, review_date, rating, strengths, weaknesses, goals, achievements, improvement_plan, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
    ");
    $stmt->bind_param("iisisssss", $user_id, $_SESSION['user_id'], $review_date, $rating, $strengths, $weaknesses, $goals, $achievements, $improvement_plan);
    
    if ($stmt->execute()) {
        // Get employee name for notification
        $emp = $conn->query("SELECT first_name, last_name FROM users WHERE user_id = $user_id")->fetch_assoc();
        addNotification($user_id, 'Performance Review Completed', 
            "Your performance review has been completed by " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . ". Rating: $rating/5", 
            'performance', "performance_employee.php");
        $success = "Performance review submitted successfully";
        logActivity($_SESSION['user_id'], 'performance_review', "Reviewed employee #$user_id ($emp[first_name] $emp[last_name])");
        
        // Redirect to clear the form and show success message
        header("Location: performance.php?employee=$user_id&success=1#reviewTab");
        exit();
    } else {
        $error = "Failed to submit review: " . $stmt->error;
    }
    $stmt->close();
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = "Performance review submitted successfully";
}

// Get employees list
$employees = $conn->query("SELECT user_id, first_name, last_name, employee_id, department, job_title FROM users WHERE role = 'employee' AND is_active = 1 ORDER BY first_name");

// Get performance reviews
if ($employee_id) {
    $reviews = $conn->prepare("
        SELECT pr.*, u.first_name, u.last_name, u.employee_id, u.department,
               r.first_name as reviewer_first, r.last_name as reviewer_last
        FROM performance_reviews pr
        JOIN users u ON pr.user_id = u.user_id
        JOIN users r ON pr.reviewer_id = r.user_id
        WHERE pr.user_id = ?
        ORDER BY pr.review_date DESC
    ");
    $reviews->bind_param("i", $employee_id);
} else {
    $reviews = $conn->prepare("
        SELECT pr.*, u.first_name, u.last_name, u.employee_id, u.department,
               r.first_name as reviewer_first, r.last_name as reviewer_last
        FROM performance_reviews pr
        JOIN users u ON pr.user_id = u.user_id
        JOIN users r ON pr.reviewer_id = r.user_id
        ORDER BY pr.review_date DESC
        LIMIT 50
    ");
}
$reviews->execute();
$review_list = $reviews->get_result();

// Get employee for review form
$selected_employee = null;
if ($employee_id) {
    $emp_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $selected_employee = $emp_stmt->get_result()->fetch_assoc();
    $emp_stmt->close();
}

// Get self-assessments
if ($employee_id) {
    $assessments = $conn->prepare("
        SELECT sa.*, u.first_name, u.last_name, u.employee_id, u.department, u.job_title
        FROM self_assessments sa
        JOIN users u ON sa.user_id = u.user_id
        WHERE sa.user_id = ?
        ORDER BY sa.submitted_date DESC
    ");
    $assessments->bind_param("i", $employee_id);
} else {
    $assessments = $conn->prepare("
        SELECT sa.*, u.first_name, u.last_name, u.employee_id, u.department, u.job_title
        FROM self_assessments sa
        JOIN users u ON sa.user_id = u.user_id
        WHERE u.role = 'employee'
        ORDER BY sa.submitted_date DESC
        LIMIT 50
    ");
}
$assessments->execute();
$assessment_list = $assessments->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Performance Reviews - EMS</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { background: #f4f6f9; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100%; width: 260px; background: #2c3e50; color: white; z-index: 1000; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu ul { list-style: none; padding: 0; }
        .sidebar-menu li a { display: block; padding: 12px 20px; color: #ecf0f1; text-decoration: none; }
        .sidebar-menu li a:hover { background: #34495e; }
        .sidebar-menu li a.active { background: #1abc9c; }
        .sidebar-menu li a i { margin-right: 10px; width: 20px; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-navbar { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .rating-star { color: #f39c12; font-size: 20px; }
        .review-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .nav-tabs { margin-bottom: 20px; }
        .tab-pane { padding-top: 15px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fa fa-users"></i> EMS</h3>
            <p>Performance Management</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="employees.php"><i class="fa fa-users"></i> Employees</a></li>
                <li><a href="attendance.php"><i class="fa fa-clock-o"></i> Attendance</a></li>
                <li><a href="leave.php"><i class="fa fa-umbrella"></i> Leave</a></li>
                <li><a href="payroll.php"><i class="fa fa-money"></i> Payroll</a></li>
                <li><a href="performance.php" class="active"><i class="fa fa-star"></i> Performance</a></li>
                <li><a href="reports.php"><i class="fa fa-bar-chart"></i> Reports</a></li>
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
                <h4 style="margin: 0;">Performance Reviews</h4>
                <p style="margin: 5px 0 0; color: #7f8c8d;">Conduct and manage employee performance reviews</p>
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

        <ul class="nav nav-tabs">
            <li class="active"><a href="#reviewTab" data-toggle="tab"><i class="fa fa-star"></i> Performance Reviews</a></li>
            <li><a href="#assessmentTab" data-toggle="tab"><i class="fa fa-pencil-square-o"></i> Self-Assessments</a></li>
            <li><a href="#newReviewTab" data-toggle="tab"><i class="fa fa-plus-circle"></i> New Review</a></li>
        </ul>

        <div class="tab-content">
            <!-- Performance Reviews Tab -->
            <div class="tab-pane active" id="reviewTab">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-list"></i> 
                            <?php echo $employee_id ? 'Performance Reviews for ' . htmlspecialchars($selected_employee['first_name'] . ' ' . $selected_employee['last_name']) : 'Recent Performance Reviews'; ?>
                        </h3>
                        <div class="pull-right" style="margin-top: -25px;">
                            <form method="GET" class="form-inline">
                                <select name="employee" class="form-control input-sm" onchange="this.form.submit()" style="width: 250px;">
                                    <option value="0">-- All Employees --</option>
                                    <?php 
                                    $emps = $conn->query("SELECT user_id, first_name, last_name, employee_id FROM users WHERE role = 'employee' ORDER BY first_name");
                                    while($emp = $emps->fetch_assoc()): ?>
                                        <option value="<?php echo $emp['user_id']; ?>" <?php echo $employee_id == $emp['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <?php if ($employee_id): ?>
                                    <a href="performance.php" class="btn btn-default btn-sm">Clear Filter</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="panel-body">
                        <?php if ($review_list->num_rows > 0): ?>
                            <?php while($review = $review_list->fetch_assoc()): ?>
                                <div class="review-card">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                            <small>(<?php echo htmlspecialchars($review['employee_id']); ?>)</small>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($review['department']); ?></small>
                                        </div>
                                        <div class="col-md-4 text-right">
                                            <div class="rating-star">
                                                <?php for($i=1; $i<=5; $i++): ?>
                                                    <?php if($i <= $review['rating']): ?>
                                                        <i class="fa fa-star"></i>
                                                    <?php else: ?>
                                                        <i class="fa fa-star-o"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            <small><?php echo date('M d, Y', strtotime($review['review_date'])); ?></small>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Strengths:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($review['strengths'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Areas to Improve:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($review['weaknesses'])); ?></p>
                                        </div>
                                    </div>
                                    <div>
                                        <strong>Goals:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($review['goals'])); ?></p>
                                    </div>
                                    <?php if ($review['achievements']): ?>
                                    <div>
                                        <strong>Achievements:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($review['achievements'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($review['improvement_plan']): ?>
                                    <div>
                                        <strong>Improvement Plan:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($review['improvement_plan'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        Reviewed by: <?php echo htmlspecialchars($review['reviewer_first'] . ' ' . $review['reviewer_last']); ?>
                                    </small>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No performance reviews found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Self-Assessments Tab -->
            <div class="tab-pane" id="assessmentTab">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-pencil-square-o"></i> 
                            <?php echo $employee_id ? 'Self-Assessments for ' . htmlspecialchars($selected_employee['first_name'] . ' ' . $selected_employee['last_name']) : 'Recent Self-Assessments'; ?>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <?php if ($assessment_list->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <?php if (!$employee_id): ?>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <?php endif; ?>
                                            <th>Review Period</th>
                                            <th>Self Rating</th>
                                            <th>Submitted Date</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($assessment = $assessment_list->fetch_assoc()): ?>
                                            <tr>
                                                <?php if (!$employee_id): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($assessment['first_name'] . ' ' . $assessment['last_name']); ?>
                                                    <br><small><?php echo htmlspecialchars($assessment['employee_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($assessment['department']); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo htmlspecialchars($assessment['review_period']); ?></td>
                                                <td>
                                                    <?php for($i=1; $i<=5; $i++): ?>
                                                        <?php if($i <= $assessment['rating_self']): ?>
                                                            <i class="fa fa-star" style="color: #f39c12;"></i>
                                                        <?php else: ?>
                                                            <i class="fa fa-star-o"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                    (<?php echo $assessment['rating_self']; ?>/5)
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($assessment['submitted_date'])); ?></td>
                                                <td>
                                                    <span class="label label-<?php echo $assessment['status'] === 'submitted' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($assessment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-info btn-xs" 
                                                            onclick="viewAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                                        <i class="fa fa-eye"></i> View Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> 
                                <?php echo $employee_id ? 'No self-assessments found for this employee.' : 'No self-assessments available.'; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- New Review Tab -->
            <div class="tab-pane" id="newReviewTab">
                <div class="row">
                    <div class="col-md-6">
                        <div class="panel panel-primary">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="fa fa-pencil-square-o"></i> New Performance Review</h3>
                            </div>
                            <div class="panel-body">
                                <form method="GET">
                                    <div class="form-group">
                                        <label>Select Employee</label>
                                        <select name="employee" class="form-control" onchange="this.form.submit()">
                                            <option value="">-- Select Employee --</option>
                                            <?php 
                                            $emps = $conn->query("SELECT user_id, first_name, last_name, employee_id FROM users WHERE role = 'employee' AND is_active = 1 ORDER BY first_name");
                                            while($emp = $emps->fetch_assoc()): ?>
                                                <option value="<?php echo $emp['user_id']; ?>" <?php echo $employee_id == $emp['user_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </form>
                                
                                <?php if ($selected_employee): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="submit_review" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo $selected_employee['user_id']; ?>">
                                    
                                    <div class="alert alert-info">
                                        <strong>Employee:</strong> <?php echo htmlspecialchars($selected_employee['first_name'] . ' ' . $selected_employee['last_name']); ?><br>
                                        <strong>Department:</strong> <?php echo htmlspecialchars($selected_employee['department']); ?><br>
                                        <strong>Job Title:</strong> <?php echo htmlspecialchars($selected_employee['job_title']); ?>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Review Date <span class="text-danger">*</span></label>
                                        <input type="date" name="review_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Rating (1-5) <span class="text-danger">*</span></label>
                                        <select name="rating" class="form-control" required>
                                            <option value="">Select Rating</option>
                                            <option value="5">5 - Excellent</option>
                                            <option value="4">4 - Very Good</option>
                                            <option value="3">3 - Good</option>
                                            <option value="2">2 - Needs Improvement</option>
                                            <option value="1">1 - Poor</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Strengths <span class="text-danger">*</span></label>
                                        <textarea name="strengths" class="form-control" rows="3" required placeholder="What are the employee's key strengths?"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Areas for Improvement / Weaknesses <span class="text-danger">*</span></label>
                                        <textarea name="weaknesses" class="form-control" rows="3" required placeholder="What areas need improvement?"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Goals for Next Period <span class="text-danger">*</span></label>
                                        <textarea name="goals" class="form-control" rows="3" required placeholder="Set specific goals for the next review period"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Achievements</label>
                                        <textarea name="achievements" class="form-control" rows="3" placeholder="List key achievements since last review"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Improvement Plan</label>
                                        <textarea name="improvement_plan" class="form-control" rows="3" placeholder="Action plan for improvement"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-block">Submit Review</button>
                                </form>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-arrow-up"></i> Please select an employee above to start a performance review.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="panel panel-warning">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="fa fa-lightbulb-o"></i> Review Guidelines</h3>
                            </div>
                            <div class="panel-body">
                                <h5>Rating Scale:</h5>
                                <ul>
                                    <li><strong>5 - Excellent:</strong> Consistently exceeds expectations</li>
                                    <li><strong>4 - Very Good:</strong> Often exceeds expectations</li>
                                    <li><strong>3 - Good:</strong> Meets expectations consistently</li>
                                    <li><strong>2 - Needs Improvement:</strong> Sometimes falls short</li>
                                    <li><strong>1 - Poor:</strong> Consistently fails to meet expectations</li>
                                </ul>
                                <hr>
                                <h5>Tips for Effective Reviews:</h5>
                                <ul>
                                    <li>Be specific with examples</li>
                                    <li>Focus on behaviors, not personality</li>
                                    <li>Set SMART goals (Specific, Measurable, Achievable, Relevant, Time-bound)</li>
                                    <li>Balance positive feedback with constructive criticism</li>
                                    <li>Review employee's self-assessment before completing review</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assessment View Modal -->
    <div id="assessmentModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-pencil-square-o"></i> Self-Assessment Details</h4>
                </div>
                <div class="modal-body" id="assessmentModalBody">
                    <div class="text-center">
                        <i class="fa fa-spinner fa-spin fa-2x"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Function to create review from assessment data
        function createReviewFromAssessment(userId, selfRating) {
            // Close the assessment modal
            $('#assessmentModal').modal('hide');
            
            // Switch to the New Review tab
            $('.nav-tabs a[href="#newReviewTab"]').tab('show');
            
            // Set the employee dropdown to this employee and trigger change
            $('select[name="employee"]').val(userId).trigger('change');
            
            // Wait for the form to load, then pre-fill
            setTimeout(function() {
                // Pre-fill rating based on self-assessment
                $('select[name="rating"]').val(selfRating);
                
                // Get the assessment data from the modal
                var achievements = '';
                var skills = '';
                var challenges = '';
                var goals = '';
                
                // Extract text from the modal body
                var modalText = $('#assessmentModalBody').text();
                
                // Use the data attributes if available, or extract from DOM
                var achievementsDiv = $('#assessmentModalBody').find('.assessment-achievements').text();
                var skillsDiv = $('#assessmentModalBody').find('.assessment-skills').text();
                var challengesDiv = $('#assessmentModalBody').find('.assessment-challenges').text();
                var goalsDiv = $('#assessmentModalBody').find('.assessment-goals').text();
                
                achievements = achievementsDiv || '';
                skills = skillsDiv || '';
                challenges = challengesDiv || '';
                goals = goalsDiv || '';
                
                // Pre-fill strengths from achievements and skills
                var strengthsText = "Based on employee self-assessment:\n\n";
                if (achievements) {
                    strengthsText += "ACHIEVEMENTS:\n" + achievements + "\n\n";
                }
                if (skills) {
                    strengthsText += "SKILLS DEVELOPED:\n" + skills;
                }
                $('textarea[name="strengths"]').val(strengthsText);
                
                // Pre-fill weaknesses from challenges
                if (challenges) {
                    $('textarea[name="weaknesses"]').val("AREAS FOR IMPROVEMENT (identified by employee):\n" + challenges);
                }
                
                // Pre-fill goals
                if (goals) {
                    $('textarea[name="goals"]').val("EMPLOYEE'S GOALS:\n" + goals);
                }
                
                // Pre-fill achievements field
                if (achievements) {
                    $('textarea[name="achievements"]').val(achievements);
                }
                
                // Scroll to the form
                $('html, body').animate({
                    scrollTop: $('#newReviewTab').offset().top - 100
                }, 500);
                
            }, 500);
        }
        
        // Function to view assessment in modal
        function viewAssessment(assessmentId) {
            $('#assessmentModalBody').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i> Loading...</div>');
            $('#assessmentModal').modal('show');
            
            $.ajax({
                url: 'get_assessment.php',
                type: 'GET',
                data: { id: assessmentId },
                success: function(data) {
                    $('#assessmentModalBody').html(data);
                },
                error: function() {
                    $('#assessmentModalBody').html('<div class="alert alert-danger">Error loading assessment details.</div>');
                }
            });
        }
        
        // Keep the selected tab active after page reload
        $(document).ready(function() {
            var hash = window.location.hash;
            if (hash) {
                $('.nav-tabs a[href="' + hash + '"]').tab('show');
            }
            
            // If employee is selected, switch to appropriate tab
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('employee') && !hash) {
                $('.nav-tabs a[href="#reviewTab"]').tab('show');
            }
            
            // Auto-open assessment modal if assessment ID is in URL
            var assessmentId = urlParams.get('assessment');
            if (assessmentId) {
                viewAssessment(assessmentId);
                $('.nav-tabs a[href="#assessmentTab"]').tab('show');
            }
        });
    </script>
</body>
</html>