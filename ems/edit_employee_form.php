<?php
// edit_employee_form.php - Edit employee form for modal
require_once 'config.php';
requireRole(['admin', 'hr']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$user_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found");
}

// Get departments and job titles for dropdowns
$departments = $conn->query("SELECT dept_name FROM departments ORDER BY dept_name");
$job_titles = $conn->query("SELECT job_title FROM job_titles ORDER BY job_title");
?>

<form method="POST" action="employees.php">
    <input type="hidden" name="edit_employee" value="1">
    <input type="hidden" name="user_id" value="<?php echo $employee['user_id']; ?>">
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label>Email</label>
        <input type="email" class="form-control" value="<?php echo htmlspecialchars($employee['email']); ?>" readonly>
        <small class="text-muted">Email cannot be changed</small>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control" required>
                    <option value="employee" <?php echo $employee['role'] == 'employee' ? 'selected' : ''; ?>>Employee</option>
                    <option value="hr" <?php echo $employee['role'] == 'hr' ? 'selected' : ''; ?>>HR</option>
                    <?php if($_SESSION['role'] == 'admin'): ?>
                    <option value="admin" <?php echo $employee['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Department</label>
                <select name="department" class="form-control" required>
                    <option value="">Select Department</option>
                    <?php while($dept = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $dept['dept_name']; ?>" <?php echo $employee['department'] == $dept['dept_name'] ? 'selected' : ''; ?>>
                            <?php echo $dept['dept_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Job Title</label>
                <select name="job_title" class="form-control" required>
                    <option value="">Select Job Title</option>
                    <?php while($job = $job_titles->fetch_assoc()): ?>
                        <option value="<?php echo $job['job_title']; ?>" <?php echo $employee['job_title'] == $job['job_title'] ? 'selected' : ''; ?>>
                            <?php echo $job['job_title']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Salary ($)</label>
                <input type="number" name="salary" class="form-control" step="0.01" value="<?php echo $employee['salary']; ?>" required>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label>Phone Number</label>
        <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($employee['phone_number']); ?>">
    </div>
    
    <div class="form-group">
        <label>Address</label>
        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($employee['address']); ?></textarea>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($employee['emergency_contact']); ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Emergency Phone</label>
                <input type="text" name="emergency_phone" class="form-control" value="<?php echo htmlspecialchars($employee['emergency_phone']); ?>">
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label>Bank Account</label>
        <input type="text" name="bank_account" class="form-control" value="<?php echo htmlspecialchars($employee['bank_account']); ?>">
    </div>
    
    <div class="checkbox">
        <label>
            <input type="checkbox" name="is_active" <?php echo $employee['is_active'] ? 'checked' : ''; ?>> 
            Active Account
        </label>
    </div>
    
    <hr>
    <button type="submit" class="btn btn-primary">Save Changes</button>
    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
</form>