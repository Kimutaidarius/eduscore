<?php
session_start();
require_once '../includes/config.php';

$page_title = "Login - EduScore Finance System";

// Redirect if already logged in
if (!empty($_SESSION['is_logged_in']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'finance') {
    header('Location: dashboard.php');
    exit;
}

// Check if user was logged out due to timeout
$timeout_message = '';
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $timeout_message = '<div class="alert alert-warning" style="background:#fff3cd; color:#856404; display:block; padding:12px; border-radius:8px; margin-bottom:15px;">
        <i class="fas fa-clock"></i> Your session has expired due to inactivity. Please log in again.
    </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduScore | Finance Login</title>

<link rel="icon" href="../images/logo.png">
<link rel="apple-touch-icon" href="../images/logo.png">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --primary-blue: #0b2c4d;
    --secondary-blue: #143a63;
    --accent-yellow: #f4c430;
    --bg-light: #f9fafb;
    --bg-white: #ffffff;
    --border: #e5e7eb;
    --text-dark: #1f2937;
    --text-muted: #6b7280;
    --shadow: 0 25px 60px rgba(0, 0, 0, 0.08);
    --input-bg: #ffffff;
    --card-bg: #ffffff;
    --toggle-color: #6b7280;
    --toggle-hover: #0b2c4d;
}

body.dark-mode {
    --primary-blue: #3b82f6;
    --secondary-blue: #2563eb;
    --accent-yellow: #facc15;
    --bg-light: #0f172a;
    --bg-white: #1e293b;
    --border: #334155;
    --text-dark: #f1f5f9;
    --text-muted: #94a3b8;
    --shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
    --input-bg: #1e293b;
    --card-bg: #1e293b;
    --toggle-color: #94a3b8;
    --toggle-hover: #facc15;
}

/* RESET */
*{margin:0;padding:0;box-sizing:border-box}

/* BODY */
body{
    font-family:'Inter',sans-serif;
    background:var(--bg-light);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    transition: background-color 0.3s ease;
}

/* LAYOUT */
.container{
    width:100%;
    max-width:1100px;
    background:var(--card-bg);
    display:grid;
    grid-template-columns:1fr 1fr;
    border-radius:18px;
    overflow:hidden;
    box-shadow:var(--shadow);
    transition: background-color 0.3s ease, box-shadow 0.3s ease;
}

/* LEFT PANEL */
.img{
    background:linear-gradient(135deg,var(--primary-blue),var(--secondary-blue));
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    transition: background 0.3s ease;
}
.img::after{
    content:"";
    position:absolute;
    bottom:0;
    width:100%;
    height:6px;
    background:var(--accent-yellow);
}
.img img{
    max-width:85%;
}

/* RIGHT PANEL */
.login-content{
    padding:60px 50px;
    display:flex;
    justify-content:center;
    align-items:center;
}

form{
    width:100%;
    max-width:380px;
    position:relative;
}

/* BACK BUTTON */
.back-btn{
    position:absolute;
    top:-45px;
    left:0;
    color:var(--primary-blue);
    font-size:.9rem;
    text-decoration:none;
    transition: color 0.3s ease;
}
.back-btn:hover{
    color:var(--accent-yellow);
}

/* AVATAR */
.avatar{
    height:85px;
    display:block;
    margin:0 auto 15px;
}

/* HEADINGS */
h2{
    text-align:center;
    color:var(--primary-blue);
    font-size:1.9rem;
    margin-bottom:6px;
    transition: color 0.3s ease;
}
.subtitle{
    text-align:center;
    color:var(--text-muted);
    font-size:.95rem;
    margin-bottom:25px;
    transition: color 0.3s ease;
}

/* INPUTS */
.input-group{
    position:relative;
    margin-bottom:18px;
}
.input-group input{
    width:100%;
    padding:14px 45px 14px 15px;
    border-radius:10px;
    border:1px solid var(--border);
    font-size:.95rem;
    background:var(--input-bg);
    color:var(--text-dark);
    transition: all 0.3s ease;
}
.input-group input:focus{
    outline:none;
    border-color:var(--primary-blue);
    box-shadow:0 0 0 3px rgba(59,130,246,0.1);
}
.input-group i:not(.toggle-password){
    position:absolute;
    right:15px;
    top:50%;
    transform:translateY(-50%);
    color:var(--text-muted);
    transition: color 0.3s ease;
}

/* Password toggle specific styling */
.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--toggle-color);
    cursor: pointer;
    z-index: 10;
    transition: color 0.3s ease;
}

.toggle-password:hover {
    color: var(--toggle-hover);
}

.toggle-password.active {
    color: var(--accent-yellow);
}

/* LINKS */
.forgot{
    display:block;
    text-align:right;
    font-size:.85rem;
    color:var(--primary-blue);
    margin-bottom:15px;
    text-decoration: none;
    transition: color 0.3s ease;
}
.forgot:hover{
    color:var(--accent-yellow);
}

/* BUTTON */
.btn{
    width:100%;
    height:48px;
    border-radius:30px;
    background:var(--primary-blue);
    color:#fff;
    border:none;
    font-weight:600;
    cursor:pointer;
    position:relative;
    transition: background 0.3s ease, transform 0.2s ease;
}
.btn:hover{
    background:var(--secondary-blue);
    transform: translateY(-2px);
}
.btn::after{
    content:"";
    position:absolute;
    bottom:0;
    left:25%;
    width:50%;
    height:3px;
    background:var(--accent-yellow);
    border-radius:10px;
    transition: width 0.3s ease;
}
.btn:hover::after{
    width:70%;
    left:15%;
}
.btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* ALERT */
.alert{
    display:none;
    padding:12px;
    border-radius:8px;
    font-size:.85rem;
    margin-bottom:15px;
}
.alert-error{
    background:#fdecec;
    color:#b91c1c;
}
body.dark-mode .alert-error{
    background:rgba(185,28,28,0.2);
    color:#f87171;
    border:1px solid rgba(248,113,113,0.3);
}
.alert-warning{
    background:#fff3cd;
    color:#856404;
}
body.dark-mode .alert-warning{
    background:rgba(133,100,4,0.2);
    color:#fbbf24;
    border:1px solid rgba(251,191,36,0.3);
}
.alert-success{
    background:#d4edda;
    color:#155724;
    display:block;
}
body.dark-mode .alert-success{
    background:rgba(21,87,36,0.2);
    color:#4ade80;
    border:1px solid rgba(74,222,128,0.3);
}

/* DIVIDER */
.or-divider{
    display:flex;
    align-items:center;
    margin:25px 0;
    color:var(--text-muted);
    transition: color 0.3s ease;
}
.or-divider::before,
.or-divider::after{
    content:"";
    flex:1;
    height:1px;
    background:var(--border);
    transition: background 0.3s ease;
}
.or-divider span{
    padding:0 12px;
    font-size:.8rem;
}

/* SIGNUP */
.signup-btn{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:8px;
    padding:12px;
    border-radius:25px;
    border:1px solid var(--border);
    color:var(--primary-blue);
    font-size:.9rem;
    text-decoration:none;
    transition: all 0.3s ease;
    background:transparent;
}
.signup-btn:hover{
    border-color:var(--accent-yellow);
    background:rgba(244,196,48,.08);
    transform: translateY(-2px);
}
body.dark-mode .signup-btn:hover{
    background:rgba(250,204,21,0.1);
}

/* Theme Toggle Button */
.theme-toggle-login {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 50px;
    padding: 10px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-dark);
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: var(--shadow);
}
.theme-toggle-login:hover {
    transform: translateY(-2px);
    border-color: var(--accent-yellow);
}
.theme-toggle-login i {
    font-size: 1rem;
}
.theme-toggle-login .fa-sun {
    display: none;
}
body.dark-mode .theme-toggle-login .fa-moon {
    display: none;
}
body.dark-mode .theme-toggle-login .fa-sun {
    display: inline-block;
}

/* Loading Spinner Overlay */
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}
.loading-overlay.show {
    display: flex;
}
.loading-spinner {
    text-align: center;
    background: var(--card-bg);
    padding: 30px 40px;
    border-radius: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}
.loading-spinner i {
    font-size: 3rem;
    color: var(--accent-yellow);
    margin-bottom: 15px;
}
.loading-spinner p {
    color: var(--text-dark);
    font-size: 1rem;
    margin-top: 10px;
}

/* MOBILE */
@media(max-width:900px){
    .container{grid-template-columns:1fr}
    .img{display:none}
    .login-content{padding:50px 30px}
    .theme-toggle-login{top: 15px; right: 15px; padding: 8px 14px;}
}

@media(max-width:480px){
    .login-content{padding:40px 20px}
    h2{font-size:1.6rem}
    .theme-toggle-login{top: 10px; right: 10px; padding: 6px 12px; font-size: 0.8rem;}
}
</style>
</head>

<body>

<!-- Theme Toggle Button -->
<button class="theme-toggle-login" id="themeToggleLogin">
    <i class="fas fa-moon"></i>
    <i class="fas fa-sun"></i>
    <span>Dark Mode</span>
</button>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-pulse"></i>
        <p>Logging in...</p>
    </div>
</div>

<div class="container">
    <div class="img">
        <img src="../images/bg.svg" alt="Finance Management">
    </div>

    <div class="login-content">
        <?php if ($timeout_message) echo $timeout_message; ?>
        <form id="loginForm">
            <a href="../index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>

            <img src="../images/avatar.svg" class="avatar" alt="Login">

            <h2>Finance Portal</h2>
            <p class="subtitle">Sign in to manage school finances</p>

            <div class="alert alert-error" id="errorAlert">
                <span id="errorMessage"></span>
            </div>

            <div class="input-group">
                <input type="text" id="username" required placeholder="School Registration Number or Email">
                <i class="fas fa-building"></i>
            </div>

            <div class="input-group">
                <input type="password" id="password" required placeholder="Password">
                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>

            <a href="forgot-password.php" class="forgot">Forgot password?</a>

            <button class="btn" id="loginBtn" type="submit">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>

            <div class="or-divider"><span>OR</span></div>

            <a href="register.php" class="signup-btn">
                <i class="fas fa-user-plus"></i> Create New Account
            </a>
        </form>
    </div>
</div>

<script>
// Theme Toggle functionality
const themeToggleBtn = document.getElementById('themeToggleLogin');
const body = document.body;
const themeText = themeToggleBtn.querySelector('span');

// Check for saved theme preference
const savedTheme = localStorage.getItem('financeLoginTheme');
if (savedTheme === 'dark') {
    body.classList.add('dark-mode');
    updateThemeButton(true);
} else if (savedTheme === 'light') {
    body.classList.remove('dark-mode');
    updateThemeButton(false);
} else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    body.classList.add('dark-mode');
    updateThemeButton(true);
}

function updateThemeButton(isDark) {
    if (isDark) {
        themeText.textContent = 'Light Mode';
    } else {
        themeText.textContent = 'Dark Mode';
    }
}

themeToggleBtn.addEventListener('click', () => {
    body.classList.toggle('dark-mode');
    const isDark = body.classList.contains('dark-mode');
    localStorage.setItem('financeLoginTheme', isDark ? 'dark' : 'light');
    updateThemeButton(isDark);
});

// Password toggle functionality
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function() {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
    this.classList.toggle('active');
});

// Login form functionality
const form = document.getElementById('loginForm');
const errorAlert = document.getElementById('errorAlert');
const errorMessage = document.getElementById('errorMessage');
const loginBtn = document.getElementById('loginBtn');
const loadingOverlay = document.getElementById('loadingOverlay');

function showError(msg) {
    errorMessage.textContent = msg;
    errorAlert.style.display = 'block';
    setTimeout(() => {
        errorAlert.style.display = 'none';
    }, 5000);
}

function showSpinner() {
    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
    loginBtn.disabled = true;
    loadingOverlay.classList.add('show');
}

function resetBtn() {
    loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
    loginBtn.disabled = false;
    loadingOverlay.classList.remove('show');
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorAlert.style.display = 'none';

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();

    if (!username || !password) {
        showError('Please enter both school registration number/email and password');
        return;
    }

    showSpinner();

    try {
        const res = await fetch('api/feesystem_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                username: username, 
                password: password
            })
        });

        const data = await res.json();

        if (data.success) {
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.href = 'dashboard.php';
            }
        } else {
            showError(data.message || 'Invalid credentials. Please check your school registration number/email and password.');
            resetBtn();
        }

    } catch (err) {
        console.error('Login error:', err);
        showError('Login failed. Please check your connection and try again.');
        resetBtn();
    }
});

// Allow Enter key to submit
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();
        if (username && password) {
            form.dispatchEvent(new Event('submit'));
        }
    }
});

// Clear error on input
document.getElementById('username').addEventListener('input', () => {
    errorAlert.style.display = 'none';
});

document.getElementById('password').addEventListener('input', () => {
    errorAlert.style.display = 'none';
});
</script>

</body>
</html>