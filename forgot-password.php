<?php
require_once 'includes/config.php';

$page_title = "Forgot Password - EduScore System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" href="images/logo.png">
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary-blue: #0b2c4d;
        --secondary-blue: #143a63;
        --accent-yellow: #f4c430;
        --bg-light: #f9fafb;
        --border: #e5e7eb;
        --text-dark: #1f2937;
        --text-muted: #6b7280;
        --success-green: #10b981;
        --error-red: #ef4444;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg-light);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .container {
        width: 100%;
        max-width: 1100px;
        background: #fff;
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(0, 0, 0, .08);
    }

    .img {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .img::after {
        content: "";
        position: absolute;
        bottom: 0;
        width: 100%;
        height: 6px;
        background: var(--accent-yellow);
    }

    .img img {
        max-width: 85%;
    }

    .login-content {
        padding: 60px 50px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    form {
        width: 100%;
        max-width: 380px;
        position: relative;
    }

    .back-btn {
        position: absolute;
        top: -45px;
        left: 0;
        color: var(--primary-blue);
        font-size: .9rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .back-btn:hover {
        color: var(--secondary-blue);
    }

    .avatar {
        height: 85px;
        display: block;
        margin: 0 auto 15px;
    }

    h2 {
        text-align: center;
        color: var(--primary-blue);
        font-size: 1.9rem;
        margin-bottom: 6px;
    }

    .subtitle {
        text-align: center;
        color: var(--text-muted);
        font-size: .95rem;
        margin-bottom: 25px;
    }

    .input-group {
        position: relative;
        margin-bottom: 18px;
    }

    .input-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: var(--text-dark);
        font-size: .9rem;
    }

    .input-group input {
        width: 100%;
        padding: 14px 15px;
        border-radius: 10px;
        border: 1px solid var(--border);
        font-size: .95rem;
        transition: all 0.3s ease;
    }

    .input-group input:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(11, 44, 77, .1);
    }

    .input-group i {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }

    /* OTP Input Styling */
    .otp-container {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-bottom: 25px;
    }

    .otp-input {
        width: 50px;
        height: 60px;
        text-align: center;
        font-size: 1.5rem;
        font-weight: 600;
        border: 2px solid var(--border);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .otp-input:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(11, 44, 77, .1);
        outline: none;
    }

    .otp-input.filled {
        border-color: var(--success-green);
    }

    .btn {
        width: 100%;
        height: 48px;
        border-radius: 30px;
        background: var(--primary-blue);
        color: #fff;
        border: none;
        font-weight: 600;
        cursor: pointer;
        position: relative;
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 25%;
        width: 50%;
        height: 3px;
        background: var(--accent-yellow);
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .btn:hover {
        background: var(--secondary-blue);
        transform: translateY(-1px);
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-spinner {
        display: none;
        width: 18px;
        height: 18px;
        border: 2px solid transparent;
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .alert {
        padding: 12px;
        border-radius: 8px;
        font-size: .85rem;
        margin-bottom: 15px;
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .alert-error {
        background: #fdecec;
        color: var(--error-red);
        border-left: 4px solid var(--error-red);
    }

    .alert-success {
        background: #d1fae5;
        color: var(--success-green);
        border-left: 4px solid var(--success-green);
    }

    .alert.show {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .info-box {
        background: linear-gradient(135deg, #f0f9ff, #f0f9ff);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        text-align: center;
        border-left: 4px solid var(--primary-blue);
        animation: slideIn 0.4s ease;
    }

    .info-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        color: white;
        font-size: 1.3rem;
    }

    .step-indicator {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-bottom: 30px;
        position: relative;
    }

    .step-indicator::before {
        content: '';
        position: absolute;
        top: 15px;
        left: 50px;
        right: 50px;
        height: 2px;
        background: var(--border);
        z-index: 1;
    }

    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        z-index: 2;
        position: relative;
    }

    .step-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--bg-light);
        border: 2px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 8px;
        transition: all 0.3s ease;
    }

    .step.active .step-circle {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
        color: white;
    }

    .step.completed .step-circle {
        background: var(--success-green);
        border-color: var(--success-green);
        color: white;
    }

    .step-label {
        font-size: .8rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    .step.active .step-label {
        color: var(--primary-blue);
        font-weight: 600;
    }

    .step.completed .step-label {
        color: var(--success-green);
    }

    /* Form Steps */
    .form-step {
        display: none;
        animation: fadeIn 0.4s ease;
    }

    .form-step.active {
        display: block;
    }

    /* Success State */
    .success-state {
        text-align: center;
        padding: 20px 0;
        animation: fadeIn 0.4s ease;
    }

    .success-icon {
        width: 70px;
        height: 70px;
        background: var(--success-green);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        color: white;
        font-size: 1.8rem;
        box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        animation: bounceIn 0.6s ease-out;
    }

    @keyframes bounceIn {
        0% { opacity: 0; transform: scale(0.3); }
        50% { opacity: 1; transform: scale(1.05); }
        70% { transform: scale(0.9); }
        100% { opacity: 1; transform: scale(1); }
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 12px;
        margin-top: 25px;
    }

    .action-buttons .btn {
        flex: 1;
        margin-top: 0;
    }

    .btn-secondary {
        background: transparent;
        color: var(--primary-blue);
        border: 1px solid var(--primary-blue);
    }

    .btn-secondary::after {
        display: none;
    }

    .btn-secondary:hover {
        background: var(--primary-blue);
        color: white;
    }

    .timer {
        text-align: center;
        font-size: .85rem;
        color: var(--text-muted);
        margin: 15px 0;
        font-weight: 500;
    }

    .timer span {
        color: var(--primary-blue);
        font-weight: 600;
    }

    /* Password Strength */
    .password-strength {
        margin-top: 8px;
        height: 4px;
        background: var(--border);
        border-radius: 2px;
        overflow: hidden;
        position: relative;
    }

    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s ease, background-color 0.3s ease;
    }

    .strength-weak { background: #ef4444; }
    .strength-fair { background: #f59e0b; }
    .strength-good { background: #10b981; }
    .strength-strong { background: #059669; }

    .password-rules {
        font-size: .8rem;
        color: var(--text-muted);
        margin-top: 8px;
        padding-left: 15px;
    }

    .password-rules li {
        margin-bottom: 4px;
        list-style-type: disc;
    }

    .password-rules li.valid {
        color: var(--success-green);
    }

    /* Form Footer */
    .form-footer {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        text-align: center;
        color: var(--text-muted);
        font-size: .85rem;
    }

    .form-footer a {
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 500;
    }

    .form-footer a:hover {
        color: var(--secondary-blue);
        text-decoration: underline;
    }

    @media (max-width: 900px) {
        .container {
            grid-template-columns: 1fr;
        }
        
        .img {
            display: none;
        }
        
        .login-content {
            padding: 50px 30px;
        }
        
        .action-buttons {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .login-content {
            padding: 40px 20px;
        }
        
        h2 {
            font-size: 1.6rem;
        }
        
        .info-box {
            padding: 15px;
        }
        
        .otp-input {
            width: 40px;
            height: 50px;
            font-size: 1.3rem;
        }
        
        .step-indicator {
            gap: 20px;
        }
        
        .step-label {
            font-size: .7rem;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="img">
            <img src="images/bg.svg" alt="EduScore System">
        </div>

        <div class="login-content">
            <form id="resetForm">
                <a href="login.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login</a>
                
                <img src="images/avatar.svg" class="avatar" alt="Avatar">
                
                <h2>Reset Password</h2>
                <p class="subtitle" id="subtitle">Enter your email to receive reset instructions</p>

                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" id="step1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Email</div>
                    </div>
                    <div class="step" id="step2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Verify OTP</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-circle">3</div>
                        <div class="step-label">New Password</div>
                    </div>
                </div>

                <!-- Alerts -->
                <div class="alert alert-error" id="errorAlert"></div>
                <div class="alert alert-success" id="successAlert"></div>

                <!-- Step 1: Email Input -->
                <div class="form-step active" id="emailStep">
                    <div class="info-box">
                        <div class="info-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <h3>Forgot Your Password?</h3>
                        <p>Enter your email address and we'll send you a one-time password (OTP) via email and WhatsApp.</p>
                    </div>
                    
                    <div class="input-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="Enter your email address">
                        <i class="fas fa-envelope"></i>
                    </div>
                    
                    <button type="button" class="btn" id="sendOtpBtn">
                        <i class="fas fa-paper-plane"></i>
                        <span id="sendOtpText">Send OTP</span>
                        <div class="btn-spinner" id="sendOtpSpinner"></div>
                    </button>

                    <div class="form-footer">
                        <p>Remember your password? <a href="login.php">Back to login</a></p>
                    </div>
                </div>

                <!-- Step 2: OTP Verification -->
                <div class="form-step" id="otpStep">
                    <div class="info-box">
                        <div class="info-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Verify Your Identity</h3>
                        <p>Enter the 6-digit OTP sent to your email and WhatsApp. OTP expires in 10 minutes.</p>
                    </div>

                    <div class="input-group">
                        <label for="otp">One-Time Password</label>
                        <div class="otp-container" id="otpContainer">
                            <!-- OTP inputs will be generated here -->
                        </div>
                    </div>

                    <div class="timer" id="timer">
                        Time remaining: <span id="timeRemaining">10:00</span>
                    </div>

                    <div class="action-buttons">
                        <button type="button" class="btn btn-secondary" id="resendOtpBtn">
                            <i class="fas fa-redo"></i> Resend OTP
                        </button>
                        <button type="button" class="btn" id="verifyOtpBtn">
                            <i class="fas fa-check-circle"></i>
                            <span id="verifyOtpText">Verify OTP</span>
                            <div class="btn-spinner" id="verifyOtpSpinner"></div>
                        </button>
                    </div>
                </div>

                <!-- Step 3: New Password -->
                <div class="form-step" id="passwordStep">
                    <div class="info-box">
                        <div class="info-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3>Create New Password</h3>
                        <p>Create a strong password that you haven't used before.</p>
                    </div>

                    <div class="input-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="newPassword" required 
                               placeholder="Enter new password">
                        <i class="fas fa-lock"></i>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <ul class="password-rules" id="passwordRules">
                            <li id="ruleLength">At least 8 characters</li>
                            <li id="ruleUppercase">One uppercase letter</li>
                            <li id="ruleLowercase">One lowercase letter</li>
                            <li id="ruleNumber">One number</li>
                            <li id="ruleSpecial">One special character</li>
                        </ul>
                    </div>

                    <div class="input-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required 
                               placeholder="Confirm new password">
                        <i class="fas fa-lock"></i>
                    </div>

                    <div class="action-buttons">
                        <button type="button" class="btn btn-secondary" id="backToOtpBtn">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="btn" id="resetPasswordBtn">
                            <i class="fas fa-sync-alt"></i>
                            <span id="resetPasswordText">Reset Password</span>
                            <div class="btn-spinner" id="resetPasswordSpinner"></div>
                        </button>
                    </div>
                </div>

                <!-- Success State -->
                <div class="form-step" id="successStep">
                    <div class="success-state">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3>Password Reset Successful!</h3>
                        <p>Your password has been reset successfully. You can now login with your new password.</p>
                        
                        <div class="action-buttons">
                            <a href="login.php" class="btn">
                                <i class="fas fa-sign-in-alt"></i> Login Now
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // DOM Elements
        const emailInput = document.getElementById('email');
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const sendOtpText = document.getElementById('sendOtpText');
        const sendOtpSpinner = document.getElementById('sendOtpSpinner');
        const verifyOtpBtn = document.getElementById('verifyOtpBtn');
        const verifyOtpText = document.getElementById('verifyOtpText');
        const verifyOtpSpinner = document.getElementById('verifyOtpSpinner');
        const resendOtpBtn = document.getElementById('resendOtpBtn');
        const resetPasswordBtn = document.getElementById('resetPasswordBtn');
        const resetPasswordText = document.getElementById('resetPasswordText');
        const resetPasswordSpinner = document.getElementById('resetPasswordSpinner');
        const backToOtpBtn = document.getElementById('backToOtpBtn');
        const errorAlert = document.getElementById('errorAlert');
        const successAlert = document.getElementById('successAlert');
        const timerElement = document.getElementById('timeRemaining');
        const otpContainer = document.getElementById('otpContainer');
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordStrengthBar = document.getElementById('passwordStrengthBar');
        
        // Step Elements
        const emailStep = document.getElementById('emailStep');
        const otpStep = document.getElementById('otpStep');
        const passwordStep = document.getElementById('passwordStep');
        const successStep = document.getElementById('successStep');
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');

        // State Variables
        let timerInterval;
        let otp = '';
        let email = '';
        let timeLeft = 600; // 10 minutes in seconds
        let otpInputs = [];

        // Initialize OTP Inputs
        function initOtpInputs() {
            otpContainer.innerHTML = '';
            otpInputs = [];
            
            for (let i = 0; i < 6; i++) {
                const input = document.createElement('input');
                input.type = 'text';
                input.maxLength = 1;
                input.className = 'otp-input';
                input.dataset.index = i;
                
                input.addEventListener('input', (e) => {
                    handleOtpInput(e, i);
                });
                
                input.addEventListener('keydown', (e) => {
                    handleOtpKeydown(e, i);
                });
                
                input.addEventListener('paste', handleOtpPaste);
                
                otpContainer.appendChild(input);
                otpInputs.push(input);
            }
            
            otpInputs[0].focus();
        }

        // OTP Input Handlers
        function handleOtpInput(e, index) {
            const value = e.target.value;
            
            if (value.match(/[0-9]/)) {
                otpInputs[index].classList.add('filled');
                otpInputs[index].value = value;
                
                if (index < 5) {
                    otpInputs[index + 1].focus();
                } else {
                    otpInputs[index].blur();
                }
                
                updateOtpValue();
            } else {
                otpInputs[index].value = '';
            }
        }

        function handleOtpKeydown(e, index) {
            if (e.key === 'Backspace') {
                e.preventDefault();
                
                if (otpInputs[index].value) {
                    otpInputs[index].value = '';
                    otpInputs[index].classList.remove('filled');
                } else if (index > 0) {
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                    otpInputs[index - 1].classList.remove('filled');
                }
                
                updateOtpValue();
            } else if (e.key === 'ArrowLeft' && index > 0) {
                otpInputs[index - 1].focus();
            } else if (e.key === 'ArrowRight' && index < 5) {
                otpInputs[index + 1].focus();
            }
        }

        function handleOtpPaste(e) {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').trim();
            
            if (pasteData.match(/^[0-9]{6}$/)) {
                for (let i = 0; i < 6; i++) {
                    otpInputs[i].value = pasteData[i];
                    otpInputs[i].classList.add('filled');
                }
                otpInputs[5].focus();
                updateOtpValue();
            }
        }

        function updateOtpValue() {
            otp = otpInputs.map(input => input.value).join('');
        }

        // Timer Functions
        function startTimer() {
            clearInterval(timerInterval);
            timeLeft = 600;
            updateTimerDisplay();
            
            timerInterval = setInterval(() => {
                timeLeft--;
                updateTimerDisplay();
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    showError('OTP has expired. Please request a new one.');
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        // UI Functions
        function showError(message) {
            errorAlert.textContent = message;
            errorAlert.classList.add('show');
            successAlert.classList.remove('show');
            
            setTimeout(() => {
                errorAlert.classList.remove('show');
            }, 5000);
        }

        function showSuccess(message) {
            successAlert.textContent = message;
            successAlert.classList.add('show');
            errorAlert.classList.remove('show');
            
            setTimeout(() => {
                successAlert.classList.remove('show');
            }, 5000);
        }

        function setLoading(button, isLoading) {
            const btn = button;
            const text = btn.querySelector('span');
            const spinner = btn.querySelector('.btn-spinner');
            
            if (isLoading) {
                btn.disabled = true;
                spinner.style.display = 'block';
                text.textContent = 'Processing...';
            } else {
                btn.disabled = false;
                spinner.style.display = 'none';
                
                switch(btn.id) {
                    case 'sendOtpBtn':
                        text.textContent = 'Send OTP';
                        break;
                    case 'verifyOtpBtn':
                        text.textContent = 'Verify OTP';
                        break;
                    case 'resetPasswordBtn':
                        text.textContent = 'Reset Password';
                        break;
                }
            }
        }

        function goToStep(stepNumber) {
            // Hide all steps
            [emailStep, otpStep, passwordStep, successStep].forEach(step => {
                step.classList.remove('active');
            });
            
            // Reset step indicators
            [step1, step2, step3].forEach(step => {
                step.classList.remove('active', 'completed');
            });
            
            // Show target step and update indicators
            switch(stepNumber) {
                case 1:
                    emailStep.classList.add('active');
                    step1.classList.add('active');
                    break;
                case 2:
                    otpStep.classList.add('active');
                    step1.classList.add('completed');
                    step2.classList.add('active');
                    initOtpInputs();
                    startTimer();
                    break;
                case 3:
                    passwordStep.classList.add('active');
                    step1.classList.add('completed');
                    step2.classList.add('completed');
                    step3.classList.add('active');
                    break;
                case 4:
                    successStep.classList.add('active');
                    step1.classList.add('completed');
                    step2.classList.add('completed');
                    step3.classList.add('completed');
                    break;
            }
        }

        // Password Strength Checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const rules = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };

            // Update rule indicators
            Object.keys(rules).forEach((rule, index) => {
                const ruleElement = document.getElementById(`rule${rule.charAt(0).toUpperCase() + rule.slice(1)}`);
                if (ruleElement) {
                    if (rules[rule]) {
                        ruleElement.classList.add('valid');
                        strength += 20;
                    } else {
                        ruleElement.classList.remove('valid');
                    }
                }
            });

            // Update strength bar
            let strengthClass = 'strength-weak';
            if (strength >= 80) {
                strengthClass = 'strength-strong';
            } else if (strength >= 60) {
                strengthClass = 'strength-good';
            } else if (strength >= 40) {
                strengthClass = 'strength-fair';
            }

            passwordStrengthBar.style.width = `${strength}%`;
            passwordStrengthBar.className = `password-strength-bar ${strengthClass}`;

            return strength >= 60; // At least "good" strength
        }

        // Validation Functions
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function validatePassword(password) {
            return password.length >= 8 &&
                   /[A-Z]/.test(password) &&
                   /[a-z]/.test(password) &&
                   /\d/.test(password) &&
                   /[!@#$%^&*(),.?":{}|<>]/.test(password);
        }

        // Event Listeners
        // Step 1: Send OTP
        sendOtpBtn.addEventListener('click', async () => {
            email = emailInput.value.trim();
            
            if (!email) {
                showError('Please enter your email address');
                emailInput.focus();
                return;
            }
            
            if (!validateEmail(email)) {
                showError('Please enter a valid email address');
                emailInput.focus();
                return;
            }
            
            setLoading(sendOtpBtn, true);
            
            try {
                const response = await fetch('ajax/send-reset.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ email })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('OTP has been sent to your email and WhatsApp!');
                    goToStep(2);
                } else {
                    showError(data.message || 'Failed to send OTP. Please try again.');
                }
            } catch (error) {
                showError('Network error. Please check your connection and try again.');
            } finally {
                setLoading(sendOtpBtn, false);
            }
        });

        // Step 2: Verify OTP
        verifyOtpBtn.addEventListener('click', async () => {
            if (otp.length !== 6) {
                showError('Please enter the complete 6-digit OTP');
                otpInputs[0].focus();
                return;
            }
            
            if (timeLeft <= 0) {
                showError('OTP has expired. Please request a new one.');
                return;
            }
            
            setLoading(verifyOtpBtn, true);
            
            try {
                const response = await fetch('ajax/verify-otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ 
                        email: email,
                        otp: otp 
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('OTP verified successfully!');
                    goToStep(3);
                } else {
                    showError(data.message || 'Invalid OTP. Please try again.');
                }
            } catch (error) {
                showError('Network error. Please try again.');
            } finally {
                setLoading(verifyOtpBtn, false);
            }
        });

        // Resend OTP
        resendOtpBtn.addEventListener('click', async () => {
            setLoading(resendOtpBtn, true);
            
            try {
                const response = await fetch('ajax/send-reset.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ email })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('New OTP has been sent!');
                    clearInterval(timerInterval);
                    startTimer();
                    initOtpInputs();
                } else {
                    showError(data.message || 'Failed to resend OTP.');
                }
            } catch (error) {
                showError('Network error. Please try again.');
            } finally {
                setLoading(resendOtpBtn, false);
            }
        });

        // Step 3: Reset Password
        resetPasswordBtn.addEventListener('click', async () => {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (!password) {
                showError('Please enter a new password');
                newPasswordInput.focus();
                return;
            }
            
            if (!validatePassword(password)) {
                showError('Password must be at least 8 characters with uppercase, lowercase, number, and special character');
                newPasswordInput.focus();
                return;
            }
            
            if (password !== confirmPassword) {
                showError('Passwords do not match');
                confirmPasswordInput.focus();
                return;
            }
            
            setLoading(resetPasswordBtn, true);
            
            try {
                const response = await fetch('ajax/reset-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ 
                        email: email,
                        otp: otp,
                        newPassword: password
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    goToStep(4);
                } else {
                    showError(data.message || 'Failed to reset password. Please try again.');
                }
            } catch (error) {
                showError('Network error. Please try again.');
            } finally {
                setLoading(resetPasswordBtn, false);
            }
        });

        // Back to OTP Step
        backToOtpBtn.addEventListener('click', () => {
            goToStep(2);
        });

        // Password Strength Check
        newPasswordInput.addEventListener('input', (e) => {
            checkPasswordStrength(e.target.value);
        });

        // Enter key support
        emailInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendOtpBtn.click();
            }
        });

        // Initialize
        initOtpInputs();
        checkPasswordStrength('');
    });
    </script>
</body>
</html>