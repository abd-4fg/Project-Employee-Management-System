<?php
// get_assessment.php - Get self-assessment details for modal
require_once 'config.php';
requireRole(['admin', 'hr']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$assessment_id = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT sa.*, u.first_name, u.last_name, u.employee_id, u.department, u.job_title
    FROM self_assessments sa
    JOIN users u ON sa.user_id = u.user_id
    WHERE sa.assessment_id = ?
");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assessment) {
    die("Assessment not found");
}

// Check if a review already exists for this employee recently
$existing_review = $conn->prepare("
    SELECT review_id, review_date FROM performance_reviews 
    WHERE user_id = ? AND review_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    ORDER BY review_date DESC LIMIT 1
");
$existing_review->bind_param("i", $assessment['user_id']);
$existing_review->execute();
$review_exists = $existing_review->get_result()->fetch_assoc();
$existing_review->close();
?>

<div class="row">
    <div class="col-md-12">
        <div class="alert alert-info">
            <strong>Employee:</strong> <?php echo htmlspecialchars($assessment['first_name'] . ' ' . $assessment['last_name']); ?> 
            (<?php echo htmlspecialchars($assessment['employee_id']); ?>)<br>
            <strong>Department:</strong> <?php echo htmlspecialchars($assessment['department']); ?> | 
            <strong>Job Title:</strong> <?php echo htmlspecialchars($assessment['job_title']); ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label><strong>Review Period:</strong></label>
            <p><?php echo htmlspecialchars($assessment['review_period']); ?></p>
        </div>
        
        <div class="form-group">
            <label><strong>Self Rating:</strong></label>
            <p>
                <?php for($i=1; $i<=5; $i++): ?>
                    <?php if($i <= $assessment['rating_self']): ?>
                        <i class="fa fa-star" style="color: #f39c12; font-size: 20px;"></i>
                    <?php else: ?>
                        <i class="fa fa-star-o" style="font-size: 20px;"></i>
                    <?php endif; ?>
                <?php endfor; ?>
                <span style="margin-left: 10px;">(<?php echo $assessment['rating_self']; ?>/5)</span>
            </p>
        </div>
        
        <div class="form-group">
            <label><strong>Key Achievements:</strong></label>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; max-height: 150px; overflow-y: auto;">
                <?php echo nl2br(htmlspecialchars($assessment['achievements'])); ?>
            </div>
        </div>
        
        <div class="form-group">
            <label><strong>Challenges Faced:</strong></label>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; max-height: 100px; overflow-y: auto;">
                <?php echo nl2br(htmlspecialchars($assessment['challenges'])); ?>
            </div>
        </div>
        
        <div class="form-group">
            <label><strong>Skills Developed:</strong></label>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; max-height: 100px; overflow-y: auto;">
                <?php echo nl2br(htmlspecialchars($assessment['skills_developed'])); ?>
            </div>
        </div>
        
        <div class="form-group">
            <label><strong>Future Goals:</strong></label>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; max-height: 100px; overflow-y: auto;">
                <?php echo nl2br(htmlspecialchars($assessment['future_goals'])); ?>
            </div>
        </div>
        
        <div class="form-group">
            <label><strong>Submitted Date:</strong></label>
            <p><?php echo date('F j, Y g:i A', strtotime($assessment['submitted_date'])); ?></p>
        </div>
    </div>
</div>

<hr>

<?php if ($review_exists): ?>
    <div class="alert alert-warning">
        <i class="fa fa-exclamation-triangle"></i> 
        <strong>Note:</strong> A performance review already exists for this employee from 
        <?php echo date('F j, Y', strtotime($review_exists['review_date'])); ?>.
        <a href="performance.php?employee=<?php echo $assessment['user_id']; ?>" class="alert-link">
            View existing reviews
        </a>
    </div>
<?php endif; ?>

<div style="display: flex; gap: 10px; justify-content: flex-end;">
    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
    
    <!-- Quick Feedback Button - Opens review form with pre-filled data -->
    <button type="button" class="btn btn-success" onclick="createReviewFromAssessment(<?php echo $assessment['user_id']; ?>, <?php echo $assessment['rating_self']; ?>)">
        <i class="fa fa-star"></i> Create Review with This Data
    </button>
    
    <a href="performance.php?employee=<?php echo $assessment['user_id']; ?>#newReviewTab" class="btn btn-primary" target="_blank">
        <i class="fa fa-external-link"></i> Open Full Review Page
    </a>
</div>

<script>
// This function will be called when "Create Review" is clicked
// It switches to the New Review tab and pre-fills data
function createReviewFromAssessment(userId, selfRating) {
    // Close the modal
    $('#assessmentModal').modal('hide');
    
    // Switch to the New Review tab
    $('.nav-tabs a[href="#newReviewTab"]').tab('show');
    
    // Set the employee dropdown to this employee
    $('select[name="employee"]').val(userId);
    
    // Optionally pre-fill the rating based on self-assessment
    setTimeout(function() {
        $('select[name="rating"]').val(selfRating);
        
        // Pre-fill strengths from achievements
        var achievements = <?php echo json_encode($assessment['achievements']); ?>;
        var skills = <?php echo json_encode($assessment['skills_developed']); ?>;
        var combined = "Based on self-assessment:\n\nAchievements:\n" + achievements + "\n\nSkills Developed:\n" + skills;
        $('textarea[name="strengths"]').val(combined);
        
        // Pre-fill weaknesses from challenges
        var challenges = <?php echo json_encode($assessment['challenges']); ?>;
        $('textarea[name="weaknesses"]').val("Areas identified by employee:\n" + challenges);
        
        // Pre-fill goals
        var goals = <?php echo json_encode($assessment['future_goals']); ?>;
        $('textarea[name="goals"]').val("Employee's stated goals:\n" + goals);
        
        // Scroll to the form
        $('html, body').animate({
            scrollTop: $('#newReviewTab').offset().top - 100
        }, 500);
    }, 300);
    
    // Trigger the employee selection to load any additional data
    setTimeout(function() {
        $('select[name="employee"]').trigger('change');
    }, 100);
}
</script>