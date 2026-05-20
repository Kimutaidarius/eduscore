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
    <title>Login - <?php echo APP_NAME; ?></title>
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

        .login-wrapper {
            width: 100%;
            padding: 20px;
        }

        .login-container {
            max-width: 440px;
            margin: 0 auto;
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            object-fit: contain;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 700;
            color: #333333;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
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
        .login-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background-color: #1e3a8a;
            padding: 24px 32px;
            border-bottom: 1px solid #152b63;
        }

        .card-header h5 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            font-size: 24px;
            color: #ffffff;
        }

        .card-body {
            padding: 32px;
        }

        .card-footer {
            background-color: #f8f9fa;
            padding: 20px 32px;
            border-top: 1px solid #e0e0e0;
        }

        /* Form Elements */
        .form-label {
            font-weight: 500;
            color: #333333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control {
            height: 48px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0 16px;
            font-size: 15px;
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
            font-size: 14px;
        }

        /* Password toggle */
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

        .form-check {
            margin: 24px 0;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 0;
            border: 2px solid #e0e0e0;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: #1e3a8a;
            border-color: #152b63;
        }

        .form-check-input:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .form-check-label {
            color: #333333;
            font-size: 14px;
            cursor: pointer;
            padding-left: 8px;
        }

        /* Button */
        .btn-login {
            background-color: #1e3a8a;
            border: 1px solid #152b63;
            color: #ffffff;
            height: 52px;
            font-weight: 600;
            font-size: 16px;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover {
            background-color: #152b63;
            border-color: #0f1f4a;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login i {
            font-size: 18px;
        }

        .btn-login:disabled {
            background-color: #cccccc;
            border-color: #bbbbbb;
            cursor: not-allowed;
            transform: none;
        }

        /* Links */
        .forgot-link {
            color: #333333;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }

        .register-link {
            color: #333333;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .register-link:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }

        .register-link i {
            transition: transform 0.2s ease;
        }

        .register-link:hover i {
            transform: translateX(4px);
        }

        .admin-link {
            color: #666666;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .admin-link:hover {
            color: #1e3a8a;
        }

        .admin-link i {
            font-size: 14px;
        }

        /* Alert */
        .alert {
            border: none;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 14px;
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
            background-color: #e0f2fe;
            color: #0369a1;
            border-left: 4px solid #0369a1;
        }

        .alert i {
            font-size: 16px;
        }

        /* Yellow Loading Spinner */
        .spinner-yellow {
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 215, 0, 0.3);
            border-top: 3px solid #ffd700;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-login .spinner-yellow {
            width: 20px;
            height: 20px;
            border-width: 2px;
            margin-right: 8px;
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

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }

        .divider span {
            padding: 0 10px;
            color: #999999;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }
            
            .card-body {
                padding: 24px;
            }
            
            .card-header,
            .card-footer {
                padding: 20px 24px;
            }
            
            .logo-text {
                font-size: 24px;
            }
            
            .logo-img {
                width: 64px;
                height: 64px;
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
            <p>Redirecting to dashboard...</p>
        </div>
    </div>

    <div class="login-wrapper">
        <div class="login-container">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="images/logo.png" alt="<?php echo APP_NAME; ?>" class="logo-img" onerror="this.style.display='none'">
                <h1 class="logo-text">
                    EduScore<span>SMS</span>
                </h1>
                <p class="tagline">Professional Bulk SMS API Management</p>
            </div>
            
            <!-- Login Card -->
            <div class="login-card">
                <div class="card-header">
                    <h5>
                        <i class="bi bi-box-arrow-in-right"></i>
                        Welcome Back
                    </h5>
                </div>
                
                <div class="card-body">
                    <!-- Alert container for messages -->
                    <div id="alertContainer"></div>
                    
                    <form id="loginForm">
                        <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-4">
                            <label class="form-label">Username or Email</label>
                            <input type="text" 
                                   name="username" 
                                   class="form-control" 
                                   placeholder="Enter your username or email"
                                   required 
                                   autofocus
                                   id="username">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" 
                                       name="password" 
                                       class="form-control" 
                                       placeholder="Enter your password"
                                       required
                                       id="password">
                                <button type="button" 
                                        class="password-toggle"
                                        onclick="togglePassword()">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" name="remember" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Remember me for 30 days</label>
                        </div>
                        
                        <button type="submit" class="btn-login" id="submitBtn">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Sign In
                        </button>
                    </form>
                </div>
                
                <div class="card-footer">
                    <div class="text-center mb-3">
                        <a href="forgot-password.php" class="forgot-link">
                            <i class="bi bi-key me-1"></i>
                            Forgot Password?
                        </a>
                    </div>
                    
                    <div class="divider">
                        <span>New here?</span>
                    </div>
                    
                    <div class="text-center">
                        <a href="register.php" class="register-link">
                            Create an account
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Admin Link -->
            <div class="text-center mt-4">
                <a href="admin/login.php" class="admin-link">
                    <i class="bi bi-shield-lock"></i>
                    Administrator Access
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

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
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        // Function to hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Handle login form submission with AJAX
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const remember = document.getElementById('remember').checked;
            const csrf_token = document.getElementById('csrf_token').value;
            
            // Basic validation
            if (!username || !password) {
                showAlert('Please fill in all fields', 'danger');
                return;
            }
            
            // Disable submit button and show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="spinner-yellow"></div> Signing In...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            formData.append('remember', remember ? '1' : '0');
            formData.append('csrf_token', csrf_token);
            
            // Send AJAX request
            fetch('ajax/login_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                    
                    // Show loading overlay with yellow spinner
                    setTimeout(() => {
                        showLoading();
                        // Redirect after showing loading
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    }, 500);
                } else {
                    showAlert(data.message, 'danger');
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Add smooth transition for the register link arrow
        document.querySelector('.register-link')?.addEventListener('mouseenter', function() {
            const icon = this.querySelector('i');
            icon.style.transform = 'translateX(4px)';
        });
        
        document.querySelector('.register-link')?.addEventListener('mouseleave', function() {
            const icon = this.querySelector('i');
            icon.style.transform = 'translateX(0)';
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>