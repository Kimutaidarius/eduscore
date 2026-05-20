<?php
session_start();
require_once 'includes/config.php';

$page_title = "Login - EduScore System";

// Redirect if already logged in
if (!empty($_SESSION['is_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

// Check if user was logged out due to timeout
$timeout_message = '';
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $timeout_message = '<div class="alert alert-warning show">
        <i class="fas fa-clock"></i> Your session has expired due to inactivity. Please log in again.
    </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Login to your EduScore account to access school management features including exam analysis, fee management, and parent portal.">
    <meta name="author" content="EduScore Kenya">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --kappel: #00BFFF;
            --kappel_15: rgba(0, 191, 255, 0.1);
            --selective-yellow: #facc15;
            --eerie-black-1: #1f2937;
            --eerie-black-2: #0f172a;
            --quick-silver: #6b7280;
            --radical-red: #ef4444;
            --light-gray: #e5e7eb;
            --isabelline: #f9fafb;
            --gray-x-11: #94a3b8;
            --platinum: #e2e8f0;
            --gray-web: #6b7280;
            --black_80: rgba(0, 0, 0, 0.8);
            --white: #ffffff;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --shadow-2: 0 10px 30px rgba(0, 0, 0, 0.06);
            --shadow-3: 0 10px 50px 0 rgba(0, 0, 0, 0.1);
        }
        
        /* Dark Mode */
        body.dark-mode {
            --kappel: #3b82f6;
            --kappel_15: rgba(59, 130, 246, 0.1);
            --selective-yellow: #facc15;
            --eerie-black-1: #f1f5f9;
            --eerie-black-2: #0f172a;
            --quick-silver: #94a3b8;
            --radical-red: #f87171;
            --light-gray: #334155;
            --isabelline: #1e293b;
            --gray-x-11: #94a3b8;
            --platinum: #334155;
            --gray-web: #94a3b8;
            --white: #1e293b;
            --text-dark: #f1f5f9;
            --text-muted: #94a3b8;
            --shadow-3: 0 10px 50px 0 rgba(0, 0, 0, 0.3);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--isabelline) 0%, var(--white) 100%);
            color: var(--gray-web);
            font-size: 1.4rem;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* Container - Wider */
        .container { 
            max-width: 480px; 
            width: 100%;
            margin: 0 auto; 
            padding: 20px;
        }
        
        /* Login Section */
        .login-section {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 0;
        }
        
        /* Form Card */
        .form-card {
            background: var(--white);
            border-radius: 24px;
            padding: 35px;
            box-shadow: var(--shadow-3);
            border: 1px solid var(--platinum);
            transition: all 0.3s ease;
            width: 100%;
            position: relative;
        }
        
        .form-card:hover {
            transform: translateY(-3px);
        }
        
        /* Back Button */
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--gray-web);
            text-decoration: none;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            background: var(--white);
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid var(--platinum);
        }
        
        .back-home:hover {
            color: var(--kappel);
            gap: 10px;
            border-color: var(--kappel);
            transform: translateX(-3px);
        }
        
        .logo-center {
            text-align: center;
            margin-bottom: 25px;
            margin-top: 15px;
        }
        
        .logo-center img {
            height: 45px;
            width: auto;
        }
        
        .form-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--eerie-black-1);
            text-align: center;
            margin-bottom: 8px;
        }
        
        .form-subtitle {
            text-align: center;
            color: var(--gray-web);
            margin-bottom: 25px;
            font-size: 0.8rem;
        }
        
        /* Input Groups */
        .input-group {
            margin-bottom: 18px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.75rem;
        }
        
        .input-group label .required {
            color: var(--radical-red);
        }
        
        .input-group input {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--platinum);
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--text-dark);
        }
        
        .input-group input:focus {
            outline: none;
            border-color: var(--kappel);
            box-shadow: 0 0 0 3px var(--kappel_15);
        }
        
        .input-group input::placeholder {
            color: var(--gray-web);
        }
        
        /* Password Field with Toggle */
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            padding-right: 40px;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-web);
            cursor: pointer;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--kappel);
        }
        
        /* Links */
        .forgot-link {
            display: block;
            text-align: right;
            font-size: 0.7rem;
            color: var(--kappel);
            text-decoration: none;
            margin-bottom: 20px;
            transition: color 0.3s ease;
        }
        
        .forgot-link:hover {
            color: #009ac9;
            text-decoration: underline;
        }
        
        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 10px;
            font-size: 0.85rem;
            justify-content: center;
            background: var(--kappel);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .btn-submit:hover {
            background: #009ac9;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,191,255,0.2);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: var(--gray-web);
            font-size: 0.7rem;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--platinum);
        }
        
        .divider span {
            padding: 0 10px;
        }
        
        /* Sign Up Button */
        .signup-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 10px;
            border-radius: 10px;
            border: 1.5px solid var(--platinum);
            color: var(--kappel);
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.3s ease;
            background: transparent;
            font-weight: 500;
        }
        
        .signup-btn:hover {
            border-color: var(--kappel);
            background: var(--kappel_15);
            transform: translateY(-2px);
        }
        
        /* Alert Messages */
        .alert {
            padding: 10px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.75rem;
            display: none;
        }
        
        .alert-error {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border-left: 4px solid #f59e0b;
        }
        
        .alert.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--white);
            border: 1px solid var(--platinum);
            border-radius: 50px;
            padding: 8px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-dark);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: var(--shadow-2);
        }
        
        .theme-toggle:hover {
            transform: translateY(-2px);
            border-color: var(--selective-yellow);
        }
        
        .theme-toggle i {
            font-size: 0.85rem;
        }
        
        .theme-toggle .fa-sun {
            display: none;
        }
        
        body.dark-mode .theme-toggle .fa-moon {
            display: none;
        }
        
        body.dark-mode .theme-toggle .fa-sun {
            display: inline-block;
        }
        
        /* Responsive */
        @media (max-width: 568px) {
            .container { padding: 15px; }
            .form-card { padding: 25px 20px; }
            .form-title { font-size: 1.4rem; }
            .input-group input { padding: 8px 12px; font-size: 0.8rem; }
            .btn-submit { padding: 8px; font-size: 0.8rem; }
            .signup-btn { padding: 8px; font-size: 0.75rem; }
            .theme-toggle { top: 15px; right: 15px; padding: 6px 12px; font-size: 0.7rem; }
            .back-home { top: 15px; left: 15px; padding: 4px 10px; font-size: 0.65rem; }
        }
        
        /* Animation */
        .reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }
        
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

<!-- Theme Toggle Button -->
<button class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
    <i class="fas fa-sun"></i>
    <span>Dark Mode</span>
</button>

<section class="login-section">
    <div class="container">
        <div class="form-card reveal">
            <!-- Back to Homepage Button -->
            <a href="index.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
            
            <div class="logo-center">
                <img src="/images/logo.png" alt="EduScore logo">
            </div>
            
            <h1 class="form-title">Welcome Back</h1>
            <p class="form-subtitle">Sign in to your EduScore account</p>
            
            <?php echo $timeout_message; ?>
            
            <div class="alert alert-error" id="errorAlert">
                <span id="errorMessage"></span>
            </div>
            
            <form id="loginForm">
                <div class="input-group">
                    <label>Email or Phone Number <span class="required">*</span></label>
                    <input type="text" id="username" name="username" required placeholder="Enter your email or phone number">
                </div>
                
                <div class="input-group">
                    <label>Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                
                <button type="submit" class="btn-submit" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <a href="register.php" class="signup-btn">
                    <i class="fas fa-user-plus"></i> Create New Account
                </a>
            </form>
        </div>
    </div>
</section>

<script>
    // Theme Toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const themeText = themeToggle.querySelector('span');
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('loginTheme');
    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
        themeText.textContent = 'Light Mode';
    } else {
        themeText.textContent = 'Dark Mode';
    }
    
    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        localStorage.setItem('loginTheme', isDark ? 'dark' : 'light');
        themeText.textContent = isDark ? 'Light Mode' : 'Dark Mode';
    });
    
    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });
    
    // Form submission
    const form = document.getElementById('loginForm');
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');
    const loginBtn = document.getElementById('loginBtn');
    
    function showError(msg) {
        errorMessage.textContent = msg;
        errorAlert.classList.add('show');
        setTimeout(() => errorAlert.classList.remove('show'), 5000);
    }
    
    function hideError() {
        errorAlert.classList.remove('show');
    }
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError();
        
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();
        
        if (!username || !password) {
            showError('Please enter both email/phone and password');
            return;
        }
        
        // Disable button and show spinner
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
        
        try {
            const response = await fetch('api/check_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: username, password: password })
            });
            
            const data = await response.json();
            
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            
            if (data.success) {
                window.location.href = data.redirect || 'dashboard.php';
            } else {
                showError(data.message || 'Invalid credentials. Please try again.');
                loginBtn.disabled = false;
                loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
            }
        } catch (err) {
            console.error('Login error:', err);
            showError('Login failed. Please check your connection and try again.');
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
        }
    });
    
    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('active');
        });
    }, { threshold: 0.1 });
    reveals.forEach(el => revealObserver.observe(el));
</script>
</body>
</html>