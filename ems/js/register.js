document.addEventListener('DOMContentLoaded', function() {
    // Form elements
    const form = document.getElementById('registration-form');
    const errorDisplay = document.getElementById('registration-error');
    const licenseKeyError = document.getElementById('license-key-error');
    
    // Check for URL error parameters
    const urlParams = new URLSearchParams(window.location.search);
    const errorType = urlParams.get('error');
    
    // Show appropriate error message if present
    if (errorType) {
        errorDisplay.style.display = 'block';
        
        switch(errorType) {
            case 'invalid_license':
                errorDisplay.textContent = 'The license key you entered is invalid or already in use.';
                // Highlight the license key field
                document.getElementById('license_key').classList.add('is-invalid');
                licenseKeyError.textContent = 'Invalid or already used license key';
                licenseKeyError.style.display = 'block';
                break;
            case 'registration_failed':
                errorDisplay.textContent = 'Registration failed. Please try again.';
                break;
            default:
                errorDisplay.textContent = 'An error occurred during registration. Please try again.';
        }
    }
    
    // Form submission handling
    form.addEventListener('submit', function(e) {
        // Clear previous errors
        errorDisplay.style.display = 'none';
        licenseKeyError.style.display = 'none';
        document.getElementById('license_key').classList.remove('is-invalid');
        
        // Client-side validation can be added here if needed
    });
    
    // Customer/Employee toggle
    document.getElementById('customer-reg-btn').addEventListener('click', function() {
        document.getElementById('user-type').value = 'customer';
        document.getElementById('customer-fields').style.display = 'block';
        document.getElementById('employee-fields').style.display = 'none';
        document.getElementById('customer-reg-btn').classList.add('active-option');
        document.getElementById('employee-reg-btn').classList.remove('active-option');
        
        // Set required fields for customer
        document.getElementById('job_title').required = true;
        document.getElementById('customer_serial').required = true;
        document.getElementById('os').required = true;
        
        // Remove required from employee fields
        document.getElementById('position').required = false;
        document.getElementById('expertise').required = false;
    });
    
    document.getElementById('employee-reg-btn').addEventListener('click', function() {
        document.getElementById('user-type').value = 'employee';
        document.getElementById('customer-fields').style.display = 'none';
        document.getElementById('employee-fields').style.display = 'block';
        document.getElementById('employee-reg-btn').classList.add('active-option');
        document.getElementById('customer-reg-btn').classList.remove('active-option');
        
        // Set required fields for employee
        document.getElementById('position').required = true;
        document.getElementById('expertise').required = true;
        
        // Remove required from customer fields
        document.getElementById('job_title').required = false;
        document.getElementById('customer_serial').required = false;
        document.getElementById('os').required = false;
    });

    // Initialize required fields based on default selection (customer)
    document.getElementById('job_title').required = true;
    document.getElementById('customer_serial').required = true;
    document.getElementById('os').required = true;
});