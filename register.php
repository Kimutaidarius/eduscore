<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'includes/config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Define base URL
$base_url = "https://eduscore.co.ke";

$page_title = "Register | EduScore - Join 500+ Kenyan Schools";
$page_description = "Register your school with EduScore and get access to comprehensive school management tools including exam analysis, fee management, and parent portal.";

// All 47 Counties of Kenya
$kenyan_counties = [
    'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo-Marakwet', 'Embu', 'Garissa', 
    'Homa Bay', 'Isiolo', 'Kajiado', 'Kakamega', 'Kericho', 'Kiambu', 'Kilifi', 
    'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui', 'Kwale', 'Laikipia', 'Lamu', 
    'Machakos', 'Makueni', 'Mandera', 'Marsabit', 'Meru', 'Migori', 'Mombasa', 
    "Murang'a", 'Nairobi', 'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua', 
    'Nyeri', 'Samburu', 'Siaya', 'Taita Taveta', 'Tana River', 'Tharaka-Nithi', 
    'Trans Nzoia', 'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot'
];
sort($kenyan_counties);

// Institution Level Options
$institution_level_options = [
    'primary' => 'Primary School',
    'secondary' => 'Secondary School',
    'college' => 'College',
    'university' => 'University',
    'mixed' => 'Mixed Levels'
];

// Product Type Options
$product_type_options = [
    'exam_analysis' => 'Exam Analysis System',
    'fee_management' => 'Fee Management System'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow">
    
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Keep your existing styles - they are fine */
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
            --shadow-3: 0 10px 40px 0 rgba(0, 0, 0, 0.08);
            --shadow-2: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        body {
            background: linear-gradient(155deg, #fdfaf0 0%, #fffcf5 25%, #fffef7 50%, #fffaf2 75%, #fff6ea 100%);
            background-color: #fdfaf0;
            font-family: 'Inter', sans-serif;
            color: #2a2418;
            font-size: 1.3rem;
            line-height: 1.5;
            min-height: 100vh;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        h1, .form-title {
            font-family: 'Merriweather', serif;
        }
        
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
            --shadow-3: 0 10px 40px 0 rgba(0, 0, 0, 0.2);
            background: linear-gradient(155deg, #2a2a22 0%, #22221a 25%, #1e1e18 50%, #1a1a15 75%, #161612 100%);
            background-color: #2a2a22;
            color: #e8e2d4;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            background: rgba(255, 253, 248, 0.9);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(230, 200, 140, 0.5);
            border-radius: 50px;
            padding: 6px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
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
        
        .theme-toggle .fa-sun { display: none; }
        body.dark-mode .theme-toggle .fa-moon { display: none; }
        body.dark-mode .theme-toggle .fa-sun { display: inline-block; }
        
        .container { 
            max-width: 850px; 
            width: 100%;
            margin: 0 auto; 
            padding: 15px;
        }
        
        .register-section {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px 0;
            min-height: 100vh;
        }
        
        .form-card {
            background: rgba(255, 253, 248, 0.92);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 25px;
            box-shadow: var(--shadow-3);
            border: 1px solid rgba(230, 200, 140, 0.3);
            transition: all 0.3s ease;
            width: 100%;
        }
        
        body.dark-mode .form-card {
            background: rgba(30, 28, 22, 0.92);
            border-color: rgba(210, 170, 90, 0.2);
        }
        
        .form-card:hover {
            transform: translateY(-2px);
        }
        
        .logo-center {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .logo-center img {
            height: 35px;
            width: auto;
        }
        
        .form-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c2418;
            text-align: center;
            margin-bottom: 5px;
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
        
        .form-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative;
            max-width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .form-steps::before {
            content: '';
            position: absolute;
            top: 14px;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(230, 200, 140, 0.4);
            z-index: 1;
        }
        
        body.dark-mode .form-steps::before {
            background: rgba(210, 170, 90, 0.2);
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .step-number {
            width: 28px;
            height: 28px;
            background: #fef0d4;
            color: #5c4b34;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.3s ease;
        }
        
        body.dark-mode .step-number {
            background: #6b5538;
            color: #cfc3a8;
        }
        
        .step-label {
            font-size: 0.6rem;
            color: #5c4b34;
            font-weight: 500;
        }
        
        body.dark-mode .step-label {
            color: #cfc3a8;
        }
        
        .step.active .step-number {
            background: #00BFFF;
            color: white;
            box-shadow: 0 0 0 3px rgba(0, 191, 255, 0.1);
        }
        
        .step.active .step-label {
            color: #00BFFF;
            font-weight: 600;
        }
        
        .step.completed .step-number {
            background: #10b981;
            color: white;
        }
        
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
        
        .input-group {
            margin-bottom: 12px;
            position: relative;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            color: #2c2418;
            font-size: 0.65rem;
        }
        
        body.dark-mode .input-group label {
            color: #f7e5c2;
        }
        
        .input-group label .required {
            color: var(--radical-red);
        }
        
        .input-group input,
        .input-group select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1.5px solid rgba(230, 200, 140, 0.5);
            font-size: 0.75rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: rgba(255, 253, 248, 0.85);
            color: #2c2418;
        }
        
        body.dark-mode .input-group input,
        body.dark-mode .input-group select {
            background: rgba(50, 45, 38, 0.85);
            color: #f7e5c2;
            border-color: rgba(210, 170, 90, 0.3);
        }
        
        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: var(--kappel);
            box-shadow: 0 0 0 3px var(--kappel_15);
        }
        
        .input-group input.error,
        .input-group select.error,
        .multi-select-trigger.error {
            border-color: #dc2626 !important;
            background: rgba(220, 38, 38, 0.05) !important;
        }
        
        body.dark-mode .input-group input.error,
        body.dark-mode .input-group select.error,
        body.dark-mode .multi-select-trigger.error {
            border-color: #f87171 !important;
            background: rgba(248, 113, 113, 0.1) !important;
        }
        
        .field-error {
            color: #dc2626;
            font-size: 0.6rem;
            margin-top: 4px;
            display: none;
            align-items: center;
            gap: 4px;
        }
        
        body.dark-mode .field-error {
            color: #f87171;
        }
        
        .field-error.show {
            display: flex;
        }
        
        .field-error i {
            font-size: 0.5rem;
        }
        
        /* Multi-Select Styles */
        .multi-select-container {
            position: relative;
            width: 100%;
        }
        
        .multi-select-trigger {
            width: 100%;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1.5px solid rgba(230, 200, 140, 0.5);
            background: rgba(255, 253, 248, 0.85);
            text-align: left;
            color: #2c2418;
            font-size: 0.75rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        body.dark-mode .multi-select-trigger {
            background: rgba(50, 45, 38, 0.85);
            color: #f7e5c2;
            border-color: rgba(210, 170, 90, 0.3);
        }
        
        .multi-select-trigger:hover {
            border-color: var(--kappel);
        }
        
        .multi-select-trigger .selected-text {
            flex: 1;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            min-width: 0;
            font-size: 0.7rem;
        }
        
        .multi-select-trigger .placeholder {
            color: #a89880;
        }
        
        body.dark-mode .multi-select-trigger .placeholder {
            color: #8a7a62;
        }
        
        .multi-select-trigger .selected-count {
            background: #00BFFF;
            color: #fff;
            font-size: 0.6rem;
            padding: 2px 5px;
            border-radius: 20px;
            margin-left: 6px;
            flex-shrink: 0;
            min-width: 18px;
            text-align: center;
        }
        
        .multi-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(255, 253, 248, 0.98);
            border: 1px solid rgba(230, 200, 140, 0.5);
            border-radius: 10px;
            padding: 6px;
            z-index: 1000;
            display: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            max-height: 180px;
            overflow-y: auto;
            margin-top: 4px;
        }
        
        body.dark-mode .multi-select-dropdown {
            background: rgba(50, 45, 38, 0.98);
            border-color: rgba(210, 170, 90, 0.3);
        }
        
        .multi-select-dropdown.show {
            display: block;
        }
        
        .multi-select-option {
            padding: 6px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #2c2418;
            font-size: 0.7rem;
        }
        
        body.dark-mode .multi-select-option {
            color: #f7e5c2;
        }
        
        .multi-select-option:hover {
            background: rgba(0, 191, 255, 0.1);
        }
        
        .multi-select-option.selected {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .checkbox-custom {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(230, 200, 140, 0.5);
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .multi-select-option.selected .checkbox-custom {
            background: #00BFFF;
            border-color: #00BFFF;
        }
        
        .multi-select-option.selected .checkbox-custom::after {
            content: "✓";
            color: #fff;
            font-size: 8px;
            font-weight: bold;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            padding-right: 35px;
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
        }
        
        .password-toggle:hover {
            color: #00BFFF;
        }
        
        body.dark-mode .password-toggle {
            color: #8a7a62;
        }
        
        .password-strength {
            margin-top: 6px;
        }
        
        .strength-meter {
            height: 3px;
            background: rgba(230, 200, 140, 0.3);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-text {
            font-size: 0.6rem;
            color: #5c4b34;
        }
        
        body.dark-mode .strength-text {
            color: #cfc3a8;
        }
        
        .strength-weak { background: #dc2626; }
        .strength-fair { background: #f59e0b; }
        .strength-good { background: #3b82f6; }
        .strength-strong { background: #10b981; }
        
        .password-requirements {
            margin-top: 8px;
            padding: 8px;
            background: rgba(255, 253, 248, 0.7);
            border-radius: 10px;
            font-size: 0.6rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 10px;
        }
        
        body.dark-mode .password-requirements {
            background: rgba(50, 45, 38, 0.7);
        }
        
        .requirement {
            display: flex;
            align-items: center;
            color: #5c4b34;
        }
        
        body.dark-mode .requirement {
            color: #cfc3a8;
        }
        
        .requirement i {
            width: 10px;
            font-size: 0.5rem;
            margin-right: 5px;
        }
        
        .requirement.met { color: #10b981; }
        .requirement.unmet { color: #dc2626; }
        
        .form-navigation {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 20px;
        }
        
        .btn-nav {
            flex: 1;
            padding: 8px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            border: none;
        }
        
        .btn-prev {
            background: rgba(255, 253, 248, 0.85);
            border: 1.5px solid rgba(230, 200, 140, 0.5);
            color: #2c2418;
        }
        
        body.dark-mode .btn-prev {
            background: rgba(50, 45, 38, 0.85);
            color: #f7e5c2;
            border-color: rgba(210, 170, 90, 0.3);
        }
        
        .btn-prev:hover {
            border-color: #00BFFF;
            color: #00BFFF;
        }
        
        .btn-next {
            background: #00BFFF;
            color: white;
        }
        
        .btn-next:hover {
            background: #009ac9;
            transform: translateY(-2px);
        }
        
        .btn-submit {
            background: #10b981;
            color: white;
        }
        
        .btn-submit:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .checkbox-group {
            margin: 15px 0;
        }
        
        .checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            cursor: pointer;
        }
        
        .checkbox-label input {
            width: 14px;
            height: 14px;
            margin-top: 1px;
            cursor: pointer;
        }
        
        .checkbox-label span {
            color: #5c4b34;
            font-size: 0.65rem;
            line-height: 1.3;
        }
        
        body.dark-mode .checkbox-label span {
            color: #cfc3a8;
        }
        
        .checkbox-label a {
            color: #00BFFF;
            text-decoration: none;
        }
        
        .login-link {
            text-align: center;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid rgba(230, 200, 140, 0.4);
        }
        
        body.dark-mode .login-link {
            border-top-color: rgba(210, 170, 90, 0.2);
        }
        
        .login-link p {
            color: #5c4b34;
            font-size: 0.65rem;
        }
        
        body.dark-mode .login-link p {
            color: #cfc3a8;
        }
        
        .login-link a {
            color: #00BFFF;
            text-decoration: none;
            font-weight: 600;
        }
        
        .alert {
            padding: 8px 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 0.65rem;
            display: none;
        }
        
        .alert-error {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .container { padding: 12px; }
            .form-card { padding: 20px 15px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .form-title { font-size: 1.2rem; }
            .step-label { font-size: 0.55rem; }
            .step-number { width: 24px; height: 24px; font-size: 0.65rem; }
            .form-steps { max-width: 100%; }
            .password-requirements { grid-template-columns: 1fr; }
            .theme-toggle { top: 10px; right: 10px; padding: 4px 10px; font-size: 0.65rem; }
        }
        
        @media (max-width: 480px) {
            .form-card { padding: 15px 12px; }
            .form-title { font-size: 1.1rem; }
            .input-group input,
            .input-group select,
            .multi-select-trigger { padding: 6px 10px; font-size: 0.7rem; }
            .btn-nav { padding: 6px; font-size: 0.7rem; }
        }
        
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
            <div class="logo-center">
                <img src="/images/logo.png" alt="EduScore logo">
            </div>
            
            <h1 class="form-title">Create an account</h1>
            <p class="form-subtitle">Enter your details below to create your account</p>
            
            <div class="alert alert-error" id="errorAlert">
                <span id="errorMessage"></span>
            </div>
            
            <!-- Progress Steps -->
            <div class="form-steps">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">School Details</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Admin Details</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Password Setup</div>
                </div>
            </div>
            
            <form id="registerForm" method="POST" action="api/register_school.php">
                <!-- SECTION 1: School Details -->
                <div class="form-section active" id="section-1">
                    <div class="input-group">
                        <label>School Name <span class="required">*</span></label>
                        <input type="text" id="school_name" name="school_name" required placeholder="Enter school name">
                        <div class="field-error" id="school_name_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>School Email <span class="required">*</span></label>
                            <input type="email" id="school_email" name="school_email" required placeholder="email@example.com">
                            <div class="field-error" id="school_email_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                        <div class="input-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" id="school_phone" name="school_phone" required placeholder="Phone Number">
                            <div class="field-error" id="school_phone_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>Institution Type <span class="required">*</span></label>
                            <select id="institution_type" name="institution_type" required>
                                <option value="">Select Institution Type</option>
                                <option value="public_boarding">Public Boarding School</option>
                                <option value="public_day">Public Day School</option>
                                <option value="private_boarding">Private Boarding School</option>
                                <option value="private_day">Private Day School</option>
                                <option value="international">International School</option>
                            </select>
                            <div class="field-error" id="institution_type_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                        <div class="input-group">
                            <label>Institution Level <span class="required">*</span></label>
                            <div class="multi-select-container" id="institution_level_container">
                                <input type="hidden" id="institution_level" name="institution_level" value="">
                                <button type="button" class="multi-select-trigger" id="institution_level_trigger">
                                    <span class="selected-text">
                                        <span class="placeholder">Select institution level(s)</span>
                                    </span>
                                    <span class="selected-count" id="institution_level_count">0</span>
                                </button>
                                <div class="multi-select-dropdown" id="institution_level_dropdown">
                                    <?php foreach ($institution_level_options as $value => $label): ?>
                                        <div class="multi-select-option" data-value="<?php echo htmlspecialchars($value); ?>">
                                            <div class="checkbox-custom"></div>
                                            <span class="option-text"><?php echo htmlspecialchars($label); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="field-error" id="institution_level_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>Product Type <span class="required">*</span></label>
                            <div class="multi-select-container" id="product_type_container">
                                <input type="hidden" id="product_type" name="product_type" value="">
                                <button type="button" class="multi-select-trigger" id="product_type_trigger">
                                    <span class="selected-text">
                                        <span class="placeholder">Select product type(s)</span>
                                    </span>
                                    <span class="selected-count" id="product_type_count">0</span>
                                </button>
                                <div class="multi-select-dropdown" id="product_type_dropdown">
                                    <?php foreach ($product_type_options as $value => $label): ?>
                                        <div class="multi-select-option" data-value="<?php echo htmlspecialchars($value); ?>">
                                            <div class="checkbox-custom"></div>
                                            <span class="option-text"><?php echo htmlspecialchars($label); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="field-error" id="product_type_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                        <div class="input-group">
                            <label>County <span class="required">*</span></label>
                            <select id="county" name="county" required>
                                <option value="">Select County</option>
                                <?php foreach ($kenyan_counties as $county): ?>
                                    <option value="<?php echo strtolower(str_replace("'", "", $county)); ?>"><?php echo htmlspecialchars($county); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error" id="county_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>Students Population <span class="required">*</span></label>
                            <select id="total_students" name="total_students" required>
                                <option value="">Select Students Population</option>
                                <option value="100">Up to 100 Students</option>
                                <option value="250">Up to 250 Students</option>
                                <option value="500">Up to 500 Students</option>
                                <option value="1000">Up to 1000 Students</option>
                                <option value="2000">Up to 2000 Students</option>
                                <option value="5000">5000+ Students</option>
                            </select>
                            <div class="field-error" id="total_students_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                        <div class="input-group">
                            <label>School Motto <span class="required">*</span></label>
                            <input type="text" id="school_motto" name="school_motto" required placeholder="School motto">
                            <div class="field-error" id="school_motto_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>School Address</label>
                        <input type="text" id="school_address" name="school_address" placeholder="School address (Optional)">
                        <div class="field-error" id="school_address_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                    </div>
                </div>
                
                <!-- SECTION 2: Admin Details -->
                <div class="form-section" id="section-2">
                    <div class="input-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" id="admin_name" name="admin_name" required placeholder="Enter full name">
                        <div class="field-error" id="admin_name_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" id="admin_email" name="admin_email" required placeholder="admin@example.com">
                            <div class="field-error" id="admin_email_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                        <div class="input-group">
                            <label>Phone Number</label>
                            <input type="tel" id="admin_phone" name="admin_phone" placeholder="Optional">
                            <div class="field-error" id="admin_phone_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                        </div>
                    </div>
                </div>
                
                <!-- SECTION 3: Password Setup -->
                <div class="form-section" id="section-3">
                    <div class="input-group">
                        <label>Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required placeholder="Create password">
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
                        <div class="password-requirements">
                            <div class="requirement" id="reqLength">
                                <i class="fas fa-circle"></i> <span>8-15 characters</span>
                            </div>
                            <div class="requirement" id="reqUppercase">
                                <i class="fas fa-circle"></i> <span>At least one uppercase letter</span>
                            </div>
                            <div class="requirement" id="reqLowercase">
                                <i class="fas fa-circle"></i> <span>At least one lowercase letter</span>
                            </div>
                            <div class="requirement" id="reqNumber">
                                <i class="fas fa-circle"></i> <span>At least one number</span>
                            </div>
                            <div class="requirement" id="reqSpecial">
                                <i class="fas fa-circle"></i> <span>At least one special character</span>
                            </div>
                        </div>
                        <div class="field-error" id="password_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                    </div>
                    
                    <div class="input-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="field-error" id="confirm_password_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                    </div>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="terms" name="terms" required>
                            <span>By submitting, you agree to EduScore's <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a></span>
                        </label>
                        <div class="field-error" id="terms_error"><i class="fas fa-exclamation-circle"></i> <span></span></div>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <div class="form-navigation">
                    <button type="button" class="btn-nav btn-prev" id="prevBtn" style="display: none;">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn-nav btn-next" id="nextBtn">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn-nav btn-submit" id="submitBtn" style="display: none;">
                        Create account <i class="fas fa-check-circle"></i>
                    </button>
                </div>
                
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Log in</a></p>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const themeText = themeToggle.querySelector('span');
    
    const savedTheme = localStorage.getItem('registerTheme');
    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
        themeText.textContent = 'Light Mode';
    } else {
        themeText.textContent = 'Dark Mode';
    }
    
    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        localStorage.setItem('registerTheme', isDark ? 'dark' : 'light');
        themeText.textContent = isDark ? 'Light Mode' : 'Dark Mode';
    });
    
    // Multi-step form logic
    let currentStep = 1;
    const totalSteps = 3;
    
    const sections = document.querySelectorAll('.form-section');
    const steps = document.querySelectorAll('.step');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    function updateSteps() {
        sections.forEach((section, index) => {
            section.classList.toggle('active', index + 1 === currentStep);
        });
        
        steps.forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'completed');
            if (stepNum === currentStep) {
                step.classList.add('active');
            } else if (stepNum < currentStep) {
                step.classList.add('completed');
            }
        });
        
        prevBtn.style.display = currentStep === 1 ? 'none' : 'flex';
        nextBtn.style.display = currentStep === totalSteps ? 'none' : 'flex';
        submitBtn.style.display = currentStep === totalSteps ? 'flex' : 'none';
    }
    
    // Field Error Functions
    function showFieldError(fieldId, message) {
        const errorDiv = document.getElementById(fieldId + '_error');
        const inputField = document.getElementById(fieldId);
        
        if (errorDiv) {
            const span = errorDiv.querySelector('span');
            if (span) span.textContent = message;
            errorDiv.style.display = 'flex';
            errorDiv.classList.add('show');
        }
        
        if (inputField) {
            inputField.classList.add('error');
        }
        
        // Special handling for multi-select triggers
        if (fieldId === 'institution_level') {
            const trigger = document.getElementById('institution_level_trigger');
            if (trigger) trigger.classList.add('error');
        }
        if (fieldId === 'product_type') {
            const trigger = document.getElementById('product_type_trigger');
            if (trigger) trigger.classList.add('error');
        }
    }
    
    function hideFieldError(fieldId) {
        const errorDiv = document.getElementById(fieldId + '_error');
        const inputField = document.getElementById(fieldId);
        
        if (errorDiv) {
            errorDiv.style.display = 'none';
            errorDiv.classList.remove('show');
        }
        
        if (inputField) {
            inputField.classList.remove('error');
        }
        
        if (fieldId === 'institution_level') {
            const trigger = document.getElementById('institution_level_trigger');
            if (trigger) trigger.classList.remove('error');
        }
        if (fieldId === 'product_type') {
            const trigger = document.getElementById('product_type_trigger');
            if (trigger) trigger.classList.remove('error');
        }
    }
    
    function clearAllFieldErrors() {
        const fieldIds = ['school_name', 'school_email', 'school_phone', 'institution_type', 'institution_level', 'product_type', 'county', 'total_students', 'school_motto', 'admin_name', 'admin_email', 'password', 'confirm_password', 'terms'];
        fieldIds.forEach(id => hideFieldError(id));
    }
    
    // Clear error on input
    function setupInputListeners() {
        const inputs = ['school_name', 'school_email', 'school_phone', 'institution_type', 'county', 'total_students', 'school_motto', 'admin_name', 'admin_email', 'password', 'confirm_password'];
        inputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', () => hideFieldError(id));
                element.addEventListener('change', () => hideFieldError(id));
            }
        });
        
        const termsCheckbox = document.getElementById('terms');
        if (termsCheckbox) {
            termsCheckbox.addEventListener('change', () => hideFieldError('terms'));
        }
    }
    
    setupInputListeners();
    
    // Multi-select class
    class MultiSelect {
        constructor(containerId, inputId, triggerId, dropdownId, countId) {
            this.container = document.getElementById(containerId);
            this.input = document.getElementById(inputId);
            this.trigger = document.getElementById(triggerId);
            this.dropdown = document.getElementById(dropdownId);
            this.countSpan = document.getElementById(countId);
            
            if (!this.container || !this.input || !this.trigger || !this.dropdown) return;
            
            this.options = this.dropdown.querySelectorAll('.multi-select-option');
            this.selectedValues = this.input.value ? this.input.value.split(',').filter(v => v) : [];
            this.init();
        }
        
        init() {
            this.updateUI();
            this.trigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleDropdown();
            });
            this.options.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleOption(option);
                    hideFieldError(this.input.id);
                });
            });
        }
        
        toggleDropdown() {
            const isOpen = this.dropdown.classList.contains('show');
            document.querySelectorAll('.multi-select-dropdown.show').forEach(dropdown => {
                if (dropdown !== this.dropdown) dropdown.classList.remove('show');
            });
            if (isOpen) this.closeDropdown();
            else this.openDropdown();
        }
        
        openDropdown() { this.dropdown.classList.add('show'); }
        closeDropdown() { this.dropdown.classList.remove('show'); }
        
        toggleOption(option) {
            const value = option.dataset.value;
            const index = this.selectedValues.indexOf(value);
            if (index > -1) this.selectedValues.splice(index, 1);
            else this.selectedValues.push(value);
            this.updateUI();
        }
        
        updateUI() {
            this.input.value = this.selectedValues.join(',');
            this.countSpan.textContent = this.selectedValues.length;
            const selectedText = this.selectedValues.length > 0 ? this.getDisplayText() : '<span class="placeholder">Select options</span>';
            this.trigger.querySelector('.selected-text').innerHTML = selectedText;
            this.options.forEach(option => {
                option.classList.toggle('selected', this.selectedValues.includes(option.dataset.value));
            });
            
            if (this.selectedValues.length > 0) {
                hideFieldError(this.input.id);
            }
            
            if (this.input.id === 'institution_level') {
                document.getElementById('institution_level_json')?.remove();
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'institution_level_json';
                hiddenInput.name = 'institution_level_json';
                hiddenInput.value = JSON.stringify(this.selectedValues);
                this.container.appendChild(hiddenInput);
            } else if (this.input.id === 'product_type') {
                document.getElementById('product_type_json')?.remove();
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'product_type_json';
                hiddenInput.name = 'product_type_json';
                hiddenInput.value = JSON.stringify(this.selectedValues);
                this.container.appendChild(hiddenInput);
            }
        }
        
        getDisplayText() {
            const labels = [];
            this.options.forEach(option => {
                if (this.selectedValues.includes(option.dataset.value)) {
                    labels.push(option.querySelector('.option-text').textContent);
                }
            });
            if (labels.length <= 2) return labels.join(', ');
            return labels.slice(0, 2).join(', ') + ' +' + (labels.length - 2);
        }
        
        validate() {
            if (this.selectedValues.length === 0) {
                showFieldError(this.input.id, 'Please select at least one option');
                return false;
            }
            hideFieldError(this.input.id);
            return true;
        }
        
        getValues() {
            return this.selectedValues;
        }
    }
    
    // Initialize multi-selects
    const institutionLevelSelect = new MultiSelect('institution_level_container', 'institution_level', 'institution_level_trigger', 'institution_level_dropdown', 'institution_level_count');
    const productTypeSelect = new MultiSelect('product_type_container', 'product_type', 'product_type_trigger', 'product_type_dropdown', 'product_type_count');
    
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.multi-select-container')) {
            document.querySelectorAll('.multi-select-dropdown.show').forEach(dropdown => dropdown.classList.remove('show'));
        }
    });
    
    function validateStep(step) {
        let isValid = true;
        
        if (step === 1) {
            const schoolName = document.getElementById('school_name').value.trim();
            const schoolEmail = document.getElementById('school_email').value.trim();
            const schoolPhone = document.getElementById('school_phone').value.trim();
            const institutionType = document.getElementById('institution_type').value;
            const county = document.getElementById('county').value;
            const totalStudents = document.getElementById('total_students').value;
            const schoolMotto = document.getElementById('school_motto').value.trim();
            
            if (!schoolName) { showFieldError('school_name', 'School name is required'); isValid = false; }
            if (!schoolEmail || !isValidEmail(schoolEmail)) { showFieldError('school_email', 'Valid school email is required'); isValid = false; }
            if (!schoolPhone) { showFieldError('school_phone', 'Phone number is required'); isValid = false; }
            if (!institutionType) { showFieldError('institution_type', 'Please select institution type'); isValid = false; }
            if (!institutionLevelSelect.validate()) { isValid = false; }
            if (!productTypeSelect.validate()) { isValid = false; }
            if (!county) { showFieldError('county', 'Please select county'); isValid = false; }
            if (!totalStudents) { showFieldError('total_students', 'Please select students population'); isValid = false; }
            if (!schoolMotto) { showFieldError('school_motto', 'School motto is required'); isValid = false; }
        } else if (step === 2) {
            const adminName = document.getElementById('admin_name').value.trim();
            const adminEmail = document.getElementById('admin_email').value.trim();
            if (!adminName) { showFieldError('admin_name', 'Admin full name is required'); isValid = false; }
            if (!adminEmail || !isValidEmail(adminEmail)) { showFieldError('admin_email', 'Valid admin email is required'); isValid = false; }
        } else if (step === 3) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            const hasLength = password.length >= 8 && password.length <= 15;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            
            if (!password) { showFieldError('password', 'Password is required'); isValid = false; }
            else if (!hasLength || !hasUppercase || !hasLowercase || !hasNumber || !hasSpecial) {
                showFieldError('password', 'Password must meet all requirements');
                isValid = false;
            }
            if (password !== confirmPassword) { showFieldError('confirm_password', 'Passwords do not match'); isValid = false; }
            if (!terms) { showFieldError('terms', 'You must agree to the Terms of Service and Privacy Policy'); isValid = false; }
        }
        
        return isValid;
    }
    
    function nextStep() { if (validateStep(currentStep)) { currentStep++; updateSteps(); } }
    function prevStep() { if (currentStep > 1) { currentStep--; updateSteps(); } }
    
    nextBtn.addEventListener('click', nextStep);
    prevBtn.addEventListener('click', prevStep);
    
    function setupPasswordToggle(toggleId, inputId) {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);
        if (toggle && input) {
            toggle.addEventListener('click', function() {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
    }
    
    function calculatePasswordStrength(password) {
        let score = 0;
        if (password.length >= 8 && password.length <= 15) score += 20;
        if (/[A-Z]/.test(password)) score += 20;
        if (/[a-z]/.test(password)) score += 20;
        if (/[0-9]/.test(password)) score += 20;
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) score += 20;
        return score;
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
        hideFieldError('password');
    }
    
    function updatePasswordRequirements(password) {
        const reqs = {
            length: document.getElementById('reqLength'),
            uppercase: document.getElementById('reqUppercase'),
            lowercase: document.getElementById('reqLowercase'),
            number: document.getElementById('reqNumber'),
            special: document.getElementById('reqSpecial')
        };
        Object.values(reqs).forEach(req => req?.classList.remove('met', 'unmet'));
        if (!password) return;
        if (password.length >= 8 && password.length <= 15) reqs.length?.classList.add('met');
        else reqs.length?.classList.add('unmet');
        if (/[A-Z]/.test(password)) reqs.uppercase?.classList.add('met');
        else reqs.uppercase?.classList.add('unmet');
        if (/[a-z]/.test(password)) reqs.lowercase?.classList.add('met');
        else reqs.lowercase?.classList.add('unmet');
        if (/[0-9]/.test(password)) reqs.number?.classList.add('met');
        else reqs.number?.classList.add('unmet');
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) reqs.special?.classList.add('met');
        else reqs.special?.classList.add('unmet');
    }
    
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value);
            updatePasswordRequirements(this.value);
        });
    }
    
    setupPasswordToggle('togglePassword', 'password');
    setupPasswordToggle('toggleConfirmPassword', 'confirm_password');
    
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');
    function showError(message) { errorMessage.textContent = message; errorAlert.classList.add('show'); setTimeout(() => errorAlert.classList.remove('show'), 5000); }
    function hideError() { errorAlert.classList.remove('show'); }
    function isValidEmail(email) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email); }
    
    // FIELD -> STEP MAPPING
    const fieldStepMap = {
        // STEP 1
        school_name: 1,
        school_email: 1,
        school_phone: 1,
        institution_type: 1,
        institution_level: 1,
        product_type: 1,
        county: 1,
        total_students: 1,
        school_motto: 1,
        school_address: 1,

        // STEP 2
        admin_name: 2,
        admin_email: 2,
        admin_phone: 2,

        // STEP 3
        password: 3,
        confirm_password: 3,
        terms: 3
    };
    
    // Form submission with server-side error handling - IMPROVED
    const form = document.getElementById('registerForm');
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!validateStep(3)) return;
        
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
        
        clearAllFieldErrors();
        hideError();
        
        try {
            const formDataToSend = {
                school_name: document.getElementById('school_name').value.trim(),
                school_email: document.getElementById('school_email').value.trim(),
                school_phone: document.getElementById('school_phone').value.trim(),
                institution_type: document.getElementById('institution_type').value,
                institution_level: institutionLevelSelect.getValues(),
                county: document.getElementById('county').value,
                total_students: document.getElementById('total_students').value,
                product_type: productTypeSelect.getValues(),
                school_motto: document.getElementById('school_motto').value.trim(),
                school_address: document.getElementById('school_address').value.trim(),
                admin_name: document.getElementById('admin_name').value.trim(),
                admin_email: document.getElementById('admin_email').value.trim(),
                admin_phone: document.getElementById('admin_phone').value.trim(),
                password: document.getElementById('password').value,
                confirm_password: document.getElementById('confirm_password').value,
                terms: document.getElementById('terms').checked ? '1' : ''
            };
            
            const response = await fetch('api/register_school.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formDataToSend)
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.location.href = data.redirect_url || '../login.php?registered=1';
            } else {
                if (data.errors && typeof data.errors === 'object' && Object.keys(data.errors).length > 0) {
                    
                    let firstErrorField = null;
                    let firstErrorStep = 1;
                    
                    // LOOP THROUGH BACKEND ERRORS
                    for (const [field, message] of Object.entries(data.errors)) {
                        // STORE FIRST ERROR
                        if (!firstErrorField) {
                            firstErrorField = field;
                            firstErrorStep = fieldStepMap[field] || 1;
                        }
                        // SHOW FIELD ERROR
                        showFieldError(field, message);
                    }
                    
                    // SWITCH TO STEP WITH ERROR
                    if (firstErrorStep !== currentStep) {
                        currentStep = firstErrorStep;
                        updateSteps();
                    }
                    
                    // WAIT FOR STEP TO RENDER, THEN SCROLL
                    setTimeout(() => {
                        const fieldElement = document.getElementById(firstErrorField);
                        if (fieldElement) {
                            fieldElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            fieldElement.focus();
                        }
                    }, 200);
                    
                } else if (data.message) {
                    showError(data.message);
                } else {
                    showError('Registration failed. Please try again.');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Create account <i class="fas fa-check-circle"></i>';
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Network error. Please check your connection.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create account <i class="fas fa-check-circle"></i>';
        }
    });
    
    document.getElementById('school_email').addEventListener('blur', function() {
        const schoolEmail = this.value.trim();
        const adminEmailField = document.getElementById('admin_email');
        if (schoolEmail && !adminEmailField.value.trim()) adminEmailField.value = schoolEmail;
    });
    
    const reveals = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(entries => { entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); }); }, { threshold: 0.1 });
    reveals.forEach(el => revealObserver.observe(el));
</script>
</body>
</html>