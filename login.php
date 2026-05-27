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
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet">
    
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
        
        /* Premium Airy Gradient Background */
        body {
            background: linear-gradient(
                155deg,
                #fdfaf0 0%,
                #fffcf5 25%,
                #fffef7 50%,
                #fffaf2 75%,
                #fff6ea 100%
            );
            background-color: #fdfaf0;
            font-family: 'Inter', sans-serif;
            color: #2a2418;
            font-size: 1.4rem;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* Headings use Merriweather */
        h1, .form-title {
            font-family: 'Merriweather', serif;
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
            background: linear-gradient(
                155deg,
                #2a2a22 0%,
                #22221a 25%,
                #1e1e18 50%,
                #1a1a15 75%,
                #161612 100%
            );
            background-color: #2a2a22;
            color: #e8e2d4;
        }
        
        /* Container */
.container { 
    max-width: 420px; 
    width: 100%;
    margin: 0 auto; 
    padding: 15px;
}
.login-section {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
    min-height: 100vh;
}    
        /* Form Card - Glass Morphism */
.form-card {
    background: rgba(255, 253, 248, 0.92);
    backdrop-filter: blur(12px);
    border-radius: 28px;
    padding: 30px 25px;
    box-shadow: var(--shadow-3);
    border: 1px solid rgba(230, 200, 140, 0.3);
    transition: all 0.3s ease;
    width: 100%;
    position: relative;
    /* Square aspect ratio */
    aspect-ratio: 1 / 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    /* Prevent overflow */
    overflow: hidden;
}
.form-content {
    width: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 5px;
}

/* Custom scrollbar for form content */
.form-content::-webkit-scrollbar {
    width: 4px;
}

.form-content::-webkit-scrollbar-track {
    background: rgba(230, 200, 140, 0.2);
    border-radius: 10px;
}

.form-content::-webkit-scrollbar-thumb {
    background: #00BFFF;
    border-radius: 10px;
}

/* For screens where square might be too tall, limit max-height */
@media (max-width: 500px) {
    .form-card {
        aspect-ratio: auto;
        min-height: 520px;
        max-height: 90vh;
    }
}
        
        body.dark-mode .form-card {
            background: rgba(30, 28, 22, 0.92);
            border-color: rgba(210, 170, 90, 0.2);
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
            color: #5c4b34;
            text-decoration: none;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            background: rgba(255, 253, 248, 0.9);
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid rgba(230, 200, 140, 0.5);
        }
        
        body.dark-mode .back-home {
            background: rgba(50, 45, 38, 0.9);
            color: #cfc3a8;
            border-color: rgba(210, 170, 90, 0.3);
        }
        
        .back-home:hover {
            color: #00BFFF;
            gap: 10px;
            border-color: #00BFFF;
            transform: translateX(-3px);
        }
        
        /* Avatar Section - Modern Design */
/* Avatar Section - Static, no animation */
.avatar-section {
    text-align: center;
    margin-bottom: 15px;
    position: relative;
}

.avatar-wrapper {
    display: inline-block;
    position: relative;
}

.avatar-circle {
    width: 65px;
    height: 65px;
    background: #94a3b8; /* Grey color */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    /* Removed animation */
}

.avatar-circle i {
    font-size: 2.2rem;
    color: white;
}

/* Remove the ring animation */
.avatar-ring {
    display: none;
}

/* Optional: Remove the status dot if you want a cleaner look */
.avatar-status {
    display: none;
}

/* Dark mode support for grey avatar */
body.dark-mode .avatar-circle {
    background: #64748b; /* Darker grey for dark mode */
}

/* Optional hover effect instead of bouncing */
.avatar-circle:hover {
    transform: scale(1.05);
    background: #7e8b9c;
}
        
        .form-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #2c2418;
            text-align: center;
            margin-bottom: 6px;
        }
        
        body.dark-mode .form-title {
            color: #f7e5c2;
        }
        
        .form-subtitle {
            text-align: center;
            color: #5c4b34;
            margin-bottom: 25px;
            font-size: 0.8rem;
        }
        
        body.dark-mode .form-subtitle {
            color: #cfc3a8;
        }
        
 /* Container - Square Form */
.container { 
    max-width: 420px; 
    width: 100%;
    margin: 0 auto; 
    padding: 15px;
}

/* Login Section - Centered */
.login-section {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
    min-height: 100vh;
}

/* Form Card - Square Shape */
.form-card {
    background: rgba(255, 253, 248, 0.92);
    backdrop-filter: blur(12px);
    border-radius: 28px;
    padding: 30px 25px;
    box-shadow: var(--shadow-3);
    border: 1px solid rgba(230, 200, 140, 0.3);
    transition: all 0.3s ease;
    width: 100%;
    position: relative;
    /* Square aspect ratio */
    aspect-ratio: 1 / 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* For screens where square might be too tall, limit max-height */
@media (max-width: 500px) {
    .form-card {
        aspect-ratio: auto;
        min-height: 500px;
    }
}

/* Avatar Section - Smaller and centered */
.avatar-section {
    text-align: center;
    margin-bottom: 15px;
    position: relative;
}

.avatar-wrapper {
    display: inline-block;
    position: relative;
}

.avatar-circle {
    width: 65px;
    height: 65px;
    background: linear-gradient(135deg, #00BFFF, #009ac9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 8px 20px rgba(0, 191, 255, 0.25);
    transition: all 0.3s ease;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

.avatar-circle i {
    font-size: 2.2rem;
    color: white;
}

.avatar-ring {
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(0, 191, 255, 0.3), rgba(0, 191, 255, 0.1));
    z-index: -1;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.05); }
}

body.dark-mode .avatar-circle {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.avatar-status {
    position: absolute;
    bottom: 3px;
    right: 3px;
    width: 14px;
    height: 14px;
    background: #10B981;
    border: 2px solid rgba(255, 253, 248, 0.92);
    border-radius: 50%;
}

body.dark-mode .avatar-status {
    border-color: rgba(30, 28, 22, 0.92);
}

/* Logo Section - Smaller */


.form-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2c2418;
    text-align: center;
    margin-bottom: 4px;
}

body.dark-mode .form-title {
    color: #f7e5c2;
}

.form-subtitle {
    text-align: center;
    color: #5c4b34;
    margin-bottom: 20px;
    font-size: 0.7rem;
}

body.dark-mode .form-subtitle {
    color: #cfc3a8;
}

/* Input Groups - Compact */
.input-group {
    margin-bottom: 14px;
    width: 100%;
    /* Prevent overflow */
    overflow: hidden;
}

.input-group label {
    display: block;
    margin-bottom: 4px;
    font-weight: 500;
    color: #2c2418;
    font-size: 0.7rem;
    /* Ensure text doesn't overflow */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Responsive label handling */
@media (max-width: 400px) {
    .input-group label {
        white-space: normal;
        font-size: 0.65rem;
    }
}

body.dark-mode .input-group label {
    color: #f7e5c2;
}

.input-group label .required {
    color: var(--radical-red);
}

.input-group input {
    width: 100%;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1.5px solid rgba(230, 200, 140, 0.5);
    font-size: 0.75rem;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s ease;
    background: rgba(255, 253, 248, 0.85);
    color: #2c2418;
    /* Ensure input doesn't overflow */
    box-sizing: border-box;
    display: block;
}

body.dark-mode .input-group input {
    background: rgba(50, 45, 38, 0.85);
    color: #f7e5c2;
    border-color: rgba(210, 170, 90, 0.3);
}

.input-group input:focus {
    outline: none;
    border-color: var(--kappel);
    box-shadow: 0 0 0 3px var(--kappel_15);
}

.input-group input::placeholder {
    color: #a89880;
    font-size: 0.7rem;
    /* Ensure placeholder doesn't cause overflow */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Responsive placeholder handling */
@media (max-width: 400px) {
    .input-group input::placeholder {
        font-size: 0.65rem;
        white-space: normal;
    }
}

body.dark-mode .input-group input::placeholder {
    color: #8a7a62;
}

/* Password Field with Toggle - Compact */
.password-wrapper {
    position: relative;
    width: 100%;
}

.password-wrapper input {
    padding-right: 35px;
    width: 100%;
    box-sizing: border-box;
}

.password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #a89880;
    cursor: pointer;
    font-size: 0.8rem;
    transition: color 0.3s ease;
    /* Ensure button doesn't overflow */
    z-index: 1;
}

.password-toggle:hover {
    color: var(--kappel);
}

body.dark-mode .password-toggle {
    color: #8a7a62;
}


/* Links - Compact */
.forgot-link {
    display: block;
    text-align: right;
    font-size: 0.65rem;
    color: var(--kappel);
    text-decoration: none;
    margin-bottom: 16px;
    transition: color 0.3s ease;
}

.forgot-link:hover {
    color: #009ac9;
    text-decoration: underline;
}

/* Submit Button - Compact */
.btn-submit {
    width: 100%;
    padding: 8px;
    font-size: 0.75rem;
    justify-content: center;
    background: var(--kappel);
    color: var(--white);
    border: none;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    /* Ensure button doesn't overflow */
    box-sizing: border-box;
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

/* Divider - Compact */
.divider {
    display: flex;
    align-items: center;
    margin: 16px 0;
    color: #5c4b34;
    font-size: 0.65rem;
    width: 100%;
}

body.dark-mode .divider {
    color: #cfc3a8;
}

.divider::before,
.divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: rgba(230, 200, 140, 0.4);
}

body.dark-mode .divider::before,
body.dark-mode .divider::after {
    background: rgba(210, 170, 90, 0.2);
}

.divider span {
    padding: 0 8px;
}
/* Sign Up Button - Compact */
.signup-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    padding: 8px;
    border-radius: 40px;
    border: 1.5px solid rgba(230, 200, 140, 0.5);
    color: var(--kappel);
    font-size: 0.7rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: transparent;
    font-weight: 500;
    /* Ensure button doesn't overflow */
    box-sizing: border-box;
    width: 100%;
}

body.dark-mode .signup-btn {
    border-color: rgba(210, 170, 90, 0.3);
}

.signup-btn:hover {
    border-color: var(--kappel);
    background: var(--kappel_15);
    transform: translateY(-2px);
}
/* Alert Messages - Compact */
.alert {
    padding: 8px 12px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 0.7rem;
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

/* Responsive adjustments */
@media (max-width: 480px) {
    .container { padding: 10px; }
    .form-card { padding: 25px 20px; }
    .avatar-circle { width: 55px; height: 55px; }
    .avatar-circle i { font-size: 1.8rem; }
    .form-title { font-size: 1.2rem; }
    .input-group input { padding: 7px 10px; }
    .btn-submit, .signup-btn { padding: 7px; }
}
        
        /* Theme Toggle Button */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 253, 248, 0.9);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(230, 200, 140, 0.5);
            border-radius: 50px;
            padding: 8px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #2c2418;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: var(--shadow-2);
        }
        
        body.dark-mode .theme-toggle {
            background: rgba(50, 45, 38, 0.9);
            color: #f7e5c2;
            border-color: rgba(210, 170, 90, 0.3);
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
            .form-card { padding: 30px 22px; }
            .avatar-circle { width: 70px; height: 70px; }
            .avatar-circle i { font-size: 2.5rem; }
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
        /* Error message styling */
.error-message {
    color: #dc2626;
    font-size: 0.65rem;
    margin-top: 5px;
    display: none;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.error-message.show {
    display: block;
}

/* Error state for input fields */
.input-group input.error {
    border-color: #dc2626 !important;
    background: rgba(220, 38, 38, 0.05) !important;
}

.input-group input.error:focus {
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
}

/* Dark mode error states */
body.dark-mode .input-group input.error {
    border-color: #f87171 !important;
    background: rgba(248, 113, 113, 0.1) !important;
}

body.dark-mode .error-message {
    color: #f87171;
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
            
            <!-- Modern Avatar Section -->
<div class="avatar-section">
    <div class="avatar-wrapper">
        <div class="avatar-circle">
            <i class="fas fa-user"></i>
        </div>
    </div>
</div>
            
            
            <h1 class="form-title">Welcome Back</h1>
            <p class="form-subtitle">Sign in to your EduScore account</p>
            
            <?php echo $timeout_message; ?>
            
            <div class="alert alert-error" id="errorAlert">
                <span id="errorMessage"></span>
            </div>
            
<form id="loginForm" class="form-content">
    <div class="input-group">
        <label>Email or Phone Number <span class="required">*</span></label>
        <input type="text" id="username" name="username" required placeholder="Enter your email or phone number">
        <div class="error-message" id="usernameError"></div>
    </div>
    
    <div class="input-group">
        <label>Password <span class="required">*</span></label>
        <div class="password-wrapper">
            <input type="password" id="password" name="password" required placeholder="Enter your password">
            <button type="button" class="password-toggle" id="togglePassword">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        <div class="error-message" id="passwordError"></div>
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
    // ============================================
    // THEME TOGGLE FUNCTIONALITY
    // ============================================
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
    
    // ============================================
    // PASSWORD VISIBILITY TOGGLE
    // ============================================
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });
    
    // ============================================
    // FORM ELEMENTS
    // ============================================
    const form = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const usernameInput = document.getElementById('username');
    const passwordInputField = document.getElementById('password');
    const usernameError = document.getElementById('usernameError');
    const passwordError = document.getElementById('passwordError');
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');
    
    // ============================================
    // ERROR HANDLING FUNCTIONS
    // ============================================
    
    // Clear all field errors
    function clearFieldErrors() {
        usernameInput.classList.remove('error');
        passwordInputField.classList.remove('error');
        usernameError.classList.remove('show');
        passwordError.classList.remove('show');
        usernameError.textContent = '';
        passwordError.textContent = '';
    }
    
    // Show error for a specific field
    function showFieldError(field, message) {
        if (field === 'username') {
            usernameInput.classList.add('error');
            usernameError.textContent = message;
            usernameError.classList.add('show');
        } else if (field === 'password') {
            passwordInputField.classList.add('error');
            passwordError.textContent = message;
            passwordError.classList.add('show');
        }
    }
    
    // Show top-level error (for non-field specific errors)
    function showTopError(msg) {
        errorMessage.textContent = msg;
        errorAlert.classList.add('show');
        setTimeout(() => {
            errorAlert.classList.remove('show');
        }, 5000);
    }
    
    function hideTopError() {
        errorAlert.classList.remove('show');
    }
    
    // ============================================
    // REAL-TIME ERROR CLEARING
    // ============================================
    usernameInput.addEventListener('input', function() {
        if (this.classList.contains('error')) {
            this.classList.remove('error');
            usernameError.classList.remove('show');
        }
    });
    
    passwordInputField.addEventListener('input', function() {
        if (this.classList.contains('error')) {
            this.classList.remove('error');
            passwordError.classList.remove('show');
        }
    });
    
    // ============================================
    // CLIENT-SIDE VALIDATION
    // ============================================
    function validateEmailOrPhone(value) {
        const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        const isPhone = /^[0-9+\-\s()]{10,15}$/.test(value);
        return isEmail || isPhone || value.length >= 3;
    }
    
    // ============================================
    // FORM SUBMISSION
    // ============================================
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Clear previous errors
        clearFieldErrors();
        hideTopError();
        
        const username = usernameInput.value.trim();
        const password = passwordInputField.value.trim();
        
        // Client-side validation
        if (!username) {
            showFieldError('username', 'Please enter your email or phone number');
            usernameInput.focus();
            return;
        }
        
        if (!password) {
            showFieldError('password', 'Please enter your password');
            passwordInputField.focus();
            return;
        }
        
        // Validate email/phone format
        if (!validateEmailOrPhone(username)) {
            showFieldError('username', 'Please enter a valid email address or phone number');
            usernameInput.focus();
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
            
            // Handle redirects
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            
            // Handle successful login
            if (data.success) {
                window.location.href = data.redirect || 'dashboard.php';
                return;
            }
            
            // Handle error response
            const errorMsg = data.message || 'Invalid credentials. Please try again.';
            
            // Intelligent error parsing - determine which field the error relates to
            const errorLower = errorMsg.toLowerCase();
            
            if (errorLower.includes('email') || errorLower.includes('phone') || errorLower.includes('username')) {
                showFieldError('username', errorMsg);
                usernameInput.focus();
            } 
            else if (errorLower.includes('password')) {
                showFieldError('password', errorMsg);
                passwordInputField.focus();
            }
            else if (errorLower.includes('subscription') || errorLower.includes('expired') || errorLower.includes('activation')) {
                showTopError(errorMsg);
            }
            else {
                // Default to top error
                showTopError(errorMsg);
            }
            
            // Re-enable button
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
            
        } catch (err) {
            console.error('Login error:', err);
            showTopError('Login failed. Please check your connection and try again.');
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
        }
    });
    
    // ============================================
    // SCROLL REVEAL ANIMATION
    // ============================================
    const reveals = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            }
        });
    }, { threshold: 0.1 });
    reveals.forEach(el => revealObserver.observe(el));
    
    // ============================================
    // ENTER KEY SUBMIT HANDLER
    // ============================================
    usernameInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (!passwordInputField.value.trim()) {
                passwordInputField.focus();
            } else {
                form.dispatchEvent(new Event('submit'));
            }
        }
    });
    
    passwordInputField.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });
</script>
</body>
</html>