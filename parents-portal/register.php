<?php
session_start();
require_once '../includes/config.php';

$page_title = "Parents Portal Registration - EduScore";

// Redirect if already logged in
if (!empty($_SESSION['is_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Register for Parents Portal to access your child's academic progress, exam results, fee balances, and school announcements.">
    <meta name="author" content="EduScore Kenya">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/logo.png">
    <link rel="apple-touch-icon" href="../images/logo.png">
    
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
            --success-green: #10b981;
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
            max-width: 550px; 
            width: 100%;
            margin: 0 auto; 
            padding: 20px;
        }
        
        /* Register Section */
        .register-section {
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
        
        /* Progress Steps */
        .form-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
            max-width: 90%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .form-steps::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--platinum);
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .step-number {
            width: 36px;
            height: 36px;
            background: var(--platinum);
            color: var(--gray-web);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .step-label {
            font-size: 0.7rem;
            color: var(--gray-web);
            font-weight: 500;
        }
        
        .step.active .step-number {
            background: var(--kappel);
            color: white;
            box-shadow: 0 0 0 3px var(--kappel_15);
        }
        
        .step.active .step-label {
            color: var(--kappel);
            font-weight: 600;
        }
        
        .step.completed .step-number {
            background: #10b981;
            color: white;
        }
        
        /* Form Sections */
        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .form-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .input-group input,
        .input-group select {
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
        
        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: var(--kappel);
            box-shadow: 0 0 0 3px var(--kappel_15);
        }
        
        .input-group input::placeholder {
            color: var(--gray-web);
        }
        
        /* Phone Input */
        .phone-wrapper {
            position: relative;
        }
        
        .phone-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dark);
            font-weight: 500;
            font-size: 0.85rem;
            pointer-events: none;
            z-index: 1;
        }
        
        .phone-wrapper input {
            padding-left: 55px;
        }
        
        /* Student Card */
        .student-card {
            background: var(--isabelline);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--platinum);
            transition: all 0.3s ease;
        }
        
        .student-card.selected {
            border-color: var(--kappel);
            background: var(--kappel_15);
        }
        
        .student-card:hover {
            border-color: var(--kappel);
        }
        
        .student-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .student-details {
            font-size: 0.75rem;
            color: var(--gray-web);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .student-details span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .radio-select {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .radio-select input {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .radio-select label {
            font-size: 0.75rem;
            color: var(--text-dark);
            cursor: pointer;
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
        
        /* Password Strength Meter */
        .password-strength {
            margin-top: 8px;
        }
        
        .strength-meter {
            height: 4px;
            background: var(--platinum);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 6px;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-text {
            font-size: 0.65rem;
            color: var(--gray-web);
        }
        
        .strength-weak { background: #dc2626; }
        .strength-fair { background: #f59e0b; }
        .strength-good { background: #3b82f6; }
        .strength-strong { background: #10b981; }
        
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
        
        .btn-secondary {
            background: #6c757d;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Checkbox */
        .checkbox-group {
            margin: 20px 0;
        }
        
        .checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
        }
        
        .checkbox-label input {
            width: 16px;
            height: 16px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: var(--kappel);
        }
        
        .checkbox-label span {
            color: var(--gray-web);
            font-size: 0.7rem;
            line-height: 1.4;
        }
        
        .checkbox-label a {
            color: var(--kappel);
            text-decoration: none;
        }
        
        .checkbox-label a:hover {
            text-decoration: underline;
        }
        
        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--platinum);
        }
        
        .login-link p {
            color: var(--gray-web);
            font-size: 0.75rem;
        }
        
        .login-link a {
            color: var(--kappel);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
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
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-left: 4px solid #10b981;
        }
        
        .alert-info {
            background: rgba(0, 191, 255, 0.1);
            color: var(--kappel);
            border-left: 4px solid var(--kappel);
        }
        
        .alert.show {
            display: block;
            animation: fadeIn 0.3s ease;
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
        
        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 568px) {
            .container { padding: 15px; }
            .form-card { padding: 25px 20px; }
            .form-title { font-size: 1.4rem; }
            .input-group input { padding: 8px 12px; font-size: 0.8rem; }
            .phone-wrapper input { padding-left: 50px; }
            .phone-prefix { font-size: 0.75rem; left: 12px; }
            .btn-submit { padding: 8px; font-size: 0.8rem; }
            .theme-toggle { top: 15px; right: 15px; padding: 6px 12px; font-size: 0.7rem; }
            .back-home { top: 15px; left: 15px; padding: 4px 10px; font-size: 0.65rem; }
            .step-label { font-size: 0.6rem; }
            .step-number { width: 30px; height: 30px; font-size: 0.8rem; }
            .form-steps { max-width: 100%; }
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

<section class="register-section">
    <div class="container">
        <div class="form-card reveal">
            <!-- Back to Homepage Button -->
            <a href="../index.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
            
            <div class="logo-center">
                <img src="../images/logo.png" alt="EduScore logo">
            </div>
            
            <h1 class="form-title">Parents Portal</h1>
            <p class="form-subtitle">Register to track your child's academic progress</p>
            
            <div class="alert alert-error" id="errorAlert">
                <span id="errorMessage"></span>
            </div>
            
            <div class="alert alert-success" id="successAlert">
                <span id="successMessage"></span>
            </div>
            
            <!-- Progress Steps -->
            <div class="form-steps">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Phone Number</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Select Child</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Create Password</div>
                </div>
            </div>
            
            <form id="registerForm">
                <!-- Step 1: Phone Number Input -->
                <div id="step1" class="form-section active">
                    <div class="input-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <div class="phone-wrapper">
                            <span class="phone-prefix">+254</span>
                            <input type="tel" id="phone" name="phone" required placeholder="712345678">
                        </div>
                        <small style="display: block; margin-top: 5px; font-size: 0.65rem; color: var(--gray-web);">
                            Enter the phone number registered with your child's school
                        </small>
                    </div>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="terms" required>
                            <span>I agree to the <a href="../terms.php" target="_blank">Terms of Service</a> and <a href="../privacy.php" target="_blank">Privacy Policy</a></span>
                        </label>
                    </div>
                    
                    <button type="button" class="btn-submit" id="findStudentsBtn">
                        <i class="fas fa-search"></i> Find My Children
                    </button>
                </div>
                
                <!-- Step 2: Select Child -->
                <div id="step2" class="form-section">
                    <div class="alert alert-info" id="studentsInfoAlert">
                        <i class="fas fa-info-circle"></i> Select the child you want to link to your account.
                    </div>
                    <div id="studentsList"></div>
                    <button type="button" class="btn-submit" id="confirmStudentBtn" style="display: none;">
                        <i class="fas fa-check-circle"></i> Confirm & Continue
                    </button>
                    <button type="button" class="btn-submit btn-secondary" id="backToPhoneBtn">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
                
                <!-- Step 3: Create Password -->
                <div id="step3" class="form-section">
                    <div class="alert alert-info">
                        <i class="fas fa-lock"></i> Create a secure password for your account.
                    </div>
                    
                    <div class="input-group">
                        <label>Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required placeholder="Min. 6 characters">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Enter a strong password</div>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-submit" id="createAccountBtn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                    <button type="button" class="btn-submit btn-secondary" id="backToStudentsBtn">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
            </form>
            
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
            </div>
        </div>
    </div>
</section>

<script>
    // DOM Elements
    const phoneElement = document.getElementById('phone');
    const termsElement = document.getElementById('terms');
    const findStudentsBtn = document.getElementById('findStudentsBtn');
    const confirmStudentBtn = document.getElementById('confirmStudentBtn');
    const createAccountBtn = document.getElementById('createAccountBtn');
    const backToPhoneBtn = document.getElementById('backToPhoneBtn');
    const backToStudentsBtn = document.getElementById('backToStudentsBtn');
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    const steps = document.querySelectorAll('.step');
    const studentsList = document.getElementById('studentsList');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const errorAlert = document.getElementById('errorAlert');
    const successAlert = document.getElementById('successAlert');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    
    let selectedStudentId = null;
    let selectedStudentData = null;
    let foundStudents = [];
    
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const themeText = themeToggle.querySelector('span');
    
    const savedTheme = localStorage.getItem('parentRegisterTheme');
    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
        themeText.textContent = 'Light Mode';
    } else {
        themeText.textContent = 'Dark Mode';
    }
    
    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        localStorage.setItem('parentRegisterTheme', isDark ? 'dark' : 'light');
        themeText.textContent = isDark ? 'Light Mode' : 'Dark Mode';
    });
    
    // Password visibility toggles
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
    
    if (toggleConfirmPassword) {
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
    
    // Password strength calculation
    function calculatePasswordStrength(password) {
        let score = 0;
        if (password.length >= 6) score += 20;
        if (password.length >= 8) score += 10;
        if (/[A-Z]/.test(password)) score += 20;
        if (/[a-z]/.test(password)) score += 20;
        if (/[0-9]/.test(password)) score += 20;
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) score += 10;
        return Math.min(100, score);
    }
    
    function getStrengthText(score) {
        if (score <= 40) return { text: 'Weak password', class: 'strength-weak' };
        if (score <= 60) return { text: 'Fair password', class: 'strength-fair' };
        if (score <= 80) return { text: 'Good password', class: 'strength-good' };
        return { text: 'Strong password', class: 'strength-strong' };
    }
    
    function updatePasswordStrength(password) {
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        if (!password) {
            strengthBar.style.width = '0%';
            strengthBar.className = 'strength-bar';
            strengthText.textContent = 'Enter a strong password';
            return;
        }
        
        const score = calculatePasswordStrength(password);
        const result = getStrengthText(score);
        
        strengthBar.style.width = score + '%';
        strengthBar.className = 'strength-bar ' + result.class;
        strengthText.textContent = result.text;
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value);
        });
    }
    
    // Navigation functions
    function goToStep(stepNumber) {
        step1.classList.remove('active');
        step2.classList.remove('active');
        step3.classList.remove('active');
        
        if (stepNumber === 1) step1.classList.add('active');
        else if (stepNumber === 2) step2.classList.add('active');
        else if (stepNumber === 3) step3.classList.add('active');
        
        steps.forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'completed');
            if (stepNum === stepNumber) {
                step.classList.add('active');
            } else if (stepNum < stepNumber) {
                step.classList.add('completed');
            }
        });
    }
    
    function showError(msg) {
        errorMessage.textContent = msg;
        errorAlert.classList.add('show');
        successAlert.classList.remove('show');
        setTimeout(() => errorAlert.classList.remove('show'), 5000);
    }
    
    function showSuccess(msg) {
        successMessage.textContent = msg;
        successAlert.classList.add('show');
        errorAlert.classList.remove('show');
        setTimeout(() => successAlert.classList.remove('show'), 3000);
    }
    
    function showSpinner(button, text) {
        button.innerHTML = '<span class="loading-spinner"></span> ' + text;
        button.disabled = true;
    }
    
    function resetButton(button, icon, text) {
        button.innerHTML = '<i class="fas fa-' + icon + '"></i> ' + text;
        button.disabled = false;
    }
    
    // Find students by phone number
    if (findStudentsBtn) {
        findStudentsBtn.addEventListener('click', async () => {
            let phone = phoneElement ? phoneElement.value.trim() : '';
            const terms = termsElement ? termsElement.checked : false;
            
            if (!phone) {
                showError('Please enter your phone number');
                phoneElement.focus();
                return;
            }
            
            if (!terms) {
                showError('Please accept the Terms of Service');
                return;
            }
            
            showSpinner(findStudentsBtn, 'Searching...');
            
            try {
                const response = await fetch('api/parents_find_students.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: phone })
                });
                
                const data = await response.json();
                
                if (data.success && data.students && data.students.length > 0) {
                    foundStudents = data.students;
                    studentsList.innerHTML = '';
                    
                    foundStudents.forEach((student, index) => {
                        // Mask student name (show only first name and first letter of last name)
                        let maskedName = student.FirstName;
                        if (student.LastName) {
                            maskedName += ' ' + student.LastName.charAt(0) + '***';
                        }
                        
                        const studentCard = document.createElement('div');
                        studentCard.className = 'student-card';
                        studentCard.innerHTML = `
                            <div class="student-name">${maskedName}</div>
                            <div class="student-details">
                                <span><i class="fas fa-id-card"></i> Adm: ${student.AdmNo || 'N/A'}</span>
                                <span><i class="fas fa-graduation-cap"></i> Class: ${student.class_name || 'N/A'}</span>
                                <span><i class="fas fa-venus-mars"></i> ${student.Gender || 'N/A'}</span>
                            </div>
                            <div class="radio-select">
                                <input type="radio" name="selectedStudent" value="${student.id}" id="student_${student.id}">
                                <label for="student_${student.id}">This is my child</label>
                            </div>
                        `;
                        
                        const radio = studentCard.querySelector('input');
                        radio.addEventListener('change', () => {
                            selectedStudentId = student.id;
                            selectedStudentData = student;
                            confirmStudentBtn.style.display = 'flex';
                            document.querySelectorAll('.student-card').forEach(card => {
                                card.classList.remove('selected');
                            });
                            studentCard.classList.add('selected');
                        });
                        
                        studentsList.appendChild(studentCard);
                    });
                    
                    goToStep(2);
                    showSuccess(`Found ${foundStudents.length} student(s) linked to this phone number`);
                } else {
                    showError(data.message || 'No students found with this phone number. Please contact your school administrator.');
                }
            } catch (err) {
                console.error('Error:', err);
                showError('Network error. Please try again.');
            } finally {
                resetButton(findStudentsBtn, 'search', 'Find My Children');
            }
        });
    }
    
    // Confirm student selection
    if (confirmStudentBtn) {
        confirmStudentBtn.addEventListener('click', () => {
            if (!selectedStudentId) {
                showError('Please select your child from the list');
                return;
            }
            goToStep(3);
        });
    }
    
    // Back to phone step
    if (backToPhoneBtn) {
        backToPhoneBtn.addEventListener('click', () => {
            goToStep(1);
            selectedStudentId = null;
            selectedStudentData = null;
            confirmStudentBtn.style.display = 'none';
        });
    }
    
    // Back to students step
    if (backToStudentsBtn) {
        backToStudentsBtn.addEventListener('click', () => {
            goToStep(2);
        });
    }
    
    // Create account
    if (createAccountBtn) {
        createAccountBtn.addEventListener('click', async () => {
            const password = passwordInput ? passwordInput.value : '';
            const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
            let phone = phoneElement ? phoneElement.value.trim() : '';
            
            if (!password) {
                showError('Please create a password');
                passwordInput.focus();
                return;
            }
            
            if (password.length < 6) {
                showError('Password must be at least 6 characters');
                passwordInput.focus();
                return;
            }
            
            if (password !== confirmPassword) {
                showError('Passwords do not match');
                confirmPasswordInput.focus();
                return;
            }
            
            if (!selectedStudentId) {
                showError('Please select your child first');
                goToStep(2);
                return;
            }
            
            // Clean phone number
            let cleanPhone = phone.replace(/\D/g, '');
            if (cleanPhone.startsWith('0')) {
                cleanPhone = cleanPhone.substring(1);
            }
            if (!cleanPhone.startsWith('254') && cleanPhone.length === 9) {
                cleanPhone = '254' + cleanPhone;
            }
            
            showSpinner(createAccountBtn, 'Creating Account...');
            
            try {
                const response = await fetch('api/parents_create_account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        phone: cleanPhone,
                        student_id: selectedStudentId,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Account created successfully! Redirecting to login...');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showError(data.message || 'Failed to create account. Please try again.');
                    resetButton(createAccountBtn, 'user-plus', 'Create Account');
                }
            } catch (err) {
                console.error('Error:', err);
                showError('Network error. Please try again.');
                resetButton(createAccountBtn, 'user-plus', 'Create Account');
            }
        });
    }
    
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