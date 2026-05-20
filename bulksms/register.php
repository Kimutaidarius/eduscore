<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    header('Location: user/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #ffffff;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: #333333;
        }

        .register-wrapper {
            width: 100%;
            padding: 20px;
        }

        .register-container {
            max-width: 520px;
            margin: 0 auto;
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-img {
            width: 70px;
            height: 70px;
            margin-bottom: 10px;
            object-fit: contain;
        }

        .logo-text {
            font-size: 26px;
            font-weight: 700;
            color: #333333;
            letter-spacing: -0.5px;
            margin-bottom: 5px;
        }

        .logo-text span {
            color: #1e3a8a;
            background-color: #333333;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 4px;
        }

        .tagline {
            color: #666666;
            font-size: 14px;
            font-weight: 400;
        }

        /* Card Styling */
        .register-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background-color: #1e3a8a;
            padding: 20px 28px;
            border-bottom: 1px solid #152b63;
        }

        .card-header h5 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            font-size: 22px;
            color: #ffffff;
        }

        .card-body {
            padding: 28px;
        }

        .card-footer {
            background-color: #f8f9fa;
            padding: 16px 28px;
            border-top: 1px solid #e0e0e0;
        }

        /* Form Elements */
        .form-label {
            font-weight: 500;
            color: #333333;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .form-control {
            height: 44px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 14px;
            color: #333333;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #1e3a8a;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .form-control::placeholder {
            color: #999999;
            font-size: 13px;
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background-color: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 4px;
            color: #666666;
        }

        /* Input group for password toggle */
        .input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666666;
            cursor: pointer;
            z-index: 10;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .password-toggle:hover {
            color: #1e3a8a;
        }

        /* Button */
        .btn-register {
            background-color: #1e3a8a;
            border: 1px solid #152b63;
            color: #ffffff;
            height: 48px;
            font-weight: 600;
            font-size: 15px;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-register:hover {
            background-color: #152b63;
            border-color: #0f1f4a;
            transform: translateY(-1px);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .btn-register i {
            font-size: 16px;
        }

        .btn-register:disabled {
            background-color: #cccccc;
            border-color: #bbbbbb;
            cursor: not-allowed;
            transform: none;
        }

        /* Links */
        .login-link {
            color: #333333;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .login-link:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }

        .home-link {
            color: #666666;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .home-link:hover {
            color: #1e3a8a;
        }

        /* Alert */
        .alert {
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .alert-info {
            background-color: #dbeafe;
            color: #1e3a8a;
            border-left: 4px solid #1e3a8a;
        }

        .alert i {
            font-size: 16px;
        }

        /* Yellow Loading Spinner */
        .spinner-yellow {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 215, 0, 0.3);
            border-top: 3px solid #ffd700;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-register .spinner-yellow {
            width: 18px;
            height: 18px;
            border-width: 2px;
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-content {
            text-align: center;
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .loading-content .spinner-yellow {
            width: 50px;
            height: 50px;
            border-width: 4px;
            margin-bottom: 15px;
        }

        .loading-content p {
            color: #333333;
            font-size: 16px;
            font-weight: 500;
            margin: 0;
        }

        /* Benefits Section */
        .benefits-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .benefits-title {
            font-size: 14px;
            font-weight: 600;
            color: #333333;
            margin-bottom: 12px;
        }

        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .benefits-list li {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666666;
            font-size: 12px;
        }

        .benefits-list li i {
            color: #1e3a8a;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .register-container {
                max-width: 100%;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .card-header,
            .card-footer {
                padding: 16px 20px;
            }
            
            .logo-text {
                font-size: 22px;
            }
            
            .benefits-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <!-- Add Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-yellow"></div>
            <p id="loadingMessage">Processing your registration...</p>
        </div>
    </div>

    <div class="register-wrapper">
        <div class="register-container">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="images/logo.png" alt="<?php echo APP_NAME; ?>" class="logo-img" onerror="this.style.display='none'">
                <h1 class="logo-text">
                    EduScore<span>SMS</span>
                </h1>
                <p class="tagline">Create your account to get started</p>
            </div>
            
            <!-- Register Card -->
            <div class="register-card">
                <div class="card-header">
                    <h5>
                        <i class="bi bi-person-plus"></i>
                        Create New Account
                    </h5>
                </div>
                
                <div class="card-body">
                    <!-- Alert container for messages -->
                    <div id="alertContainer"></div>
                    
                    <form id="registerForm">
                        <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" 
                                       name="username" 
                                       class="form-control" 
                                       placeholder="johndoe"
                                       id="username"
                                       required
                                       pattern="[a-zA-Z0-9_]{3,20}"
                                       title="Username must be 3-20 characters and can only contain letters, numbers, and underscores">
                                <small class="text-muted" id="usernameFeedback"></small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" 
                                       name="full_name" 
                                       class="form-control" 
                                       placeholder="John Doe"
                                       id="full_name"
                                       required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="john@example.com"
                                   id="email"
                                   required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" 
                                       name="phone" 
                                       class="form-control" 
                                       placeholder="254712345678"
                                       id="phone">
                                <small class="text-muted">International format (e.g., 254712345678)</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" 
                                       name="company_name" 
                                       class="form-control" 
                                       placeholder="Your Company Ltd"
                                       id="company_name">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" 
                                           name="password" 
                                           class="form-control" 
                                           placeholder="••••••••"
                                           required
                                           id="password"
                                           minlength="8">
                                    <button type="button" 
                                            class="password-toggle"
                                            onclick="togglePassword('password')">
                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                                <div class="password-strength">
                                    <div class="strength-bar" id="strengthBar"></div>
                                </div>
                                <div class="strength-text" id="strengthText"></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <input type="password" 
                                           name="confirm_password" 
                                           class="form-control" 
                                           placeholder="••••••••"
                                           required
                                           id="confirm_password">
                                    <button type="button" 
                                            class="password-toggle"
                                            onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye" id="toggleConfirmIcon"></i>
                                    </button>
                                </div>
                                <small class="text-muted" id="passwordMatch"></small>
                            </div>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms" style="font-size: 13px;">
                                I agree to the <a href="#" style="color: #1e3a8a; text-decoration: none;">Terms of Service</a> 
                                and <a href="#" style="color: #1e3a8a; text-decoration: none;">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-register" id="submitBtn">
                            <i class="bi bi-person-plus"></i>
                            Create Account
                        </button>
                    </form>
                    
                    <!-- Benefits Section -->
<!-- Benefits Section -->
<div class="benefits-section">
    <div class="benefits-title">What you get:</div>
    <ul class="benefits-list">
        <li>
            <i class="bi bi-check-circle-fill"></i>
            Free API Key
        </li>
        <li>
            <i class="bi bi-check-circle-fill"></i>
            Pay As You Go
        </li>
        <li>
            <i class="bi bi-check-circle-fill"></i>
            Bulk SMS Sending
        </li>
        <li>
            <i class="bi bi-check-circle-fill"></i>
            Delivery Reports
        </li>
        <li>
            <i class="bi bi-check-circle-fill"></i>
            Contact Management
        </li>
        <li>
            <i class="bi bi-check-circle-fill"></i>
            24/7 Support
        </li>
    </ul>
</div>
                
                <div class="card-footer text-center">
                    <span style="color: #666666; font-size: 14px;">Already have an account?</span>
                    <a href="login.php" class="login-link ms-2">
                        Sign In
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Home Link -->
            <div class="text-center mt-4">
                <a href="index.php" class="home-link">
                    <i class="bi bi-house-door"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId === 'password' ? 'togglePasswordIcon' : 'toggleConfirmIcon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Password strength checker
        const password = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        password.addEventListener('input', function() {
            const val = this.value;
            let strength = 0;
            
            if (val.length >= 8) strength += 25;
            if (val.match(/[a-z]+/)) strength += 25;
            if (val.match(/[A-Z]+/)) strength += 25;
            if (val.match(/[0-9]+/)) strength += 25;
            if (val.match(/[$@#&!]+/)) strength += 25;
            
            strength = Math.min(strength, 100);
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 25) {
                strengthBar.style.backgroundColor = '#dc2626';
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#dc2626';
            } else if (strength < 50) {
                strengthBar.style.backgroundColor = '#f59e0b';
                strengthText.textContent = 'Fair password';
                strengthText.style.color = '#f59e0b';
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#3b82f6';
                strengthText.textContent = 'Good password';
                strengthText.style.color = '#3b82f6';
            } else {
                strengthBar.style.backgroundColor = '#10b981';
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#10b981';
            }
            
            checkPasswordMatch();
        });
        
        // Password match checker
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        
        function checkPasswordMatch() {
            if (confirmPassword.value) {
                if (password.value === confirmPassword.value) {
                    passwordMatch.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Passwords match';
                    passwordMatch.style.color = '#10b981';
                } else {
                    passwordMatch.innerHTML = '<i class="bi bi-exclamation-circle-fill text-danger"></i> Passwords do not match';
                    passwordMatch.style.color = '#dc2626';
                }
            } else {
                passwordMatch.innerHTML = '';
            }
        }
        
        password.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);

        // Function to show alert messages
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="bi ${type === 'danger' ? 'bi-exclamation-triangle-fill' : type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill'}"></i>
                ${message}
            `;
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alertDiv);
            
            // Scroll to top to show alert
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 500);
            }, 5000);
        }

        // Function to show loading overlay
        function showLoading(message = 'Processing your registration...') {
            document.getElementById('loadingMessage').textContent = message;
            document.getElementById('loadingOverlay').classList.add('active');
        }

        // Function to hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Username availability check
        let usernameTimeout;
        document.getElementById('username').addEventListener('input', function() {
            clearTimeout(usernameTimeout);
            const username = this.value;
            const feedback = document.getElementById('usernameFeedback');
            
            if (username.length >= 3) {
                feedback.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking availability...';
                feedback.style.color = '#666';
                
                usernameTimeout = setTimeout(() => {
                    // Create form data
                    const formData = new FormData();
                    formData.append('username', username);
                    formData.append('check_only', 'username');
                    formData.append('csrf_token', document.getElementById('csrf_token').value);
                    
                    // Send AJAX request to check username
                    fetch('ajax/check_availability.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            feedback.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Username available';
                            feedback.style.color = '#10b981';
                        } else {
                            feedback.innerHTML = '<i class="bi bi-exclamation-circle-fill text-danger"></i> Username already taken';
                            feedback.style.color = '#dc2626';
                        }
                    })
                    .catch(error => {
                        feedback.innerHTML = '';
                    });
                }, 500);
            } else {
                feedback.innerHTML = '';
            }
        });

        // Email availability check
        let emailTimeout;
        document.getElementById('email').addEventListener('input', function() {
            clearTimeout(emailTimeout);
            const email = this.value;
            
            if (email.length >= 5 && email.includes('@')) {
                emailTimeout = setTimeout(() => {
                    // Create form data
                    const formData = new FormData();
                    formData.append('email', email);
                    formData.append('check_only', 'email');
                    formData.append('csrf_token', document.getElementById('csrf_token').value);
                    
                    // Send AJAX request to check email
                    fetch('ajax/check_availability.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Optional: Show email availability feedback
                        console.log('Email available:', data.available);
                    })
                    .catch(error => {
                        console.error('Error checking email:', error);
                    });
                }, 500);
            }
        });

        // Handle registration form submission with AJAX
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const full_name = document.getElementById('full_name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const company_name = document.getElementById('company_name').value.trim();
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            const csrf_token = document.getElementById('csrf_token').value;
            
            // Validation
            if (!username || !email || !full_name || !password || !confirm_password) {
                showAlert('Please fill in all required fields', 'danger');
                return;
            }
            
            if (password !== confirm_password) {
                showAlert('Passwords do not match', 'danger');
                return;
            }
            
            if (password.length < 8) {
                showAlert('Password must be at least 8 characters', 'danger');
                return;
            }
            
            if (!terms) {
                showAlert('Please accept the Terms of Service', 'danger');
                return;
            }
            
            // Disable submit button and show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="spinner-yellow"></div> Creating Account...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('username', username);
            formData.append('email', email);
            formData.append('full_name', full_name);
            formData.append('phone', phone);
            formData.append('company_name', company_name);
            formData.append('password', password);
            formData.append('confirm_password', confirm_password);
            formData.append('terms', terms ? '1' : '0');
            formData.append('csrf_token', csrf_token);
            
            // Send AJAX request
            fetch('ajax/register_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                    
                    // Show loading overlay with yellow spinner
                    showLoading('Registration successful! Redirecting to login...');
                    
                    // Redirect after showing loading
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                } else {
                    showAlert(data.message, 'danger');
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    hideLoading();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                hideLoading();
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>