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
    
    <!-- Primary SEO Meta Tags -->
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow">
    
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
            --shadow-3: 0 10px 50px 0 rgba(0, 0, 0, 0.1);
            --shadow-2: 0 10px 30px rgba(0, 0, 0, 0.06);
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
            transition: background-color 0.3s ease, color 0.3s ease;
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
            font-size: 0.85rem;
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
        
        .theme-toggle .fa-sun { display: none; }
        body.dark-mode .theme-toggle .fa-moon { display: none; }
        body.dark-mode .theme-toggle .fa-sun { display: inline-block; }
        
        /* Container - Wider */
        .container { 
            max-width: 1000px; 
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
        }
        
        .form-card:hover {
            transform: translateY(-3px);
        }
        
        .logo-center {
            text-align: center;
            margin-bottom: 25px;
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
            max-width: 80%;
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
        
        /* Input Groups - Compact */
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
        
        /* Multi-Select Styles */
        .multi-select-container {
            position: relative;
            width: 100%;
        }
        
        .multi-select-trigger {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--platinum);
            background: var(--white);
            text-align: left;
            color: var(--text-dark);
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
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
        }
        
        .multi-select-trigger .placeholder {
            color: var(--gray-web);
        }
        
        .multi-select-trigger .selected-count {
            background: var(--kappel);
            color: #fff;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 20px;
            margin-left: 8px;
            flex-shrink: 0;
            min-width: 22px;
            text-align: center;
        }
        
        .multi-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--white);
            border: 1px solid var(--platinum);
            border-radius: 10px;
            padding: 8px;
            z-index: 1000;
            display: none;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            max-height: 200px;
            overflow-y: auto;
            margin-top: 5px;
        }
        
        .multi-select-dropdown.show {
            display: block;
        }
        
        .multi-select-option {
            padding: 8px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dark);
            font-size: 0.8rem;
        }
        
        .multi-select-option:hover {
            background: var(--kappel_15);
        }
        
        .multi-select-option.selected {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .checkbox-custom {
            width: 16px;
            height: 16px;
            border: 2px solid var(--platinum);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .multi-select-option.selected .checkbox-custom {
            background: var(--kappel);
            border-color: var(--kappel);
        }
        
        .multi-select-option.selected .checkbox-custom::after {
            content: "✓";
            color: #fff;
            font-size: 10px;
            font-weight: bold;
        }
        
        .error-message {
            color: #dc2626;
            font-size: 0.7rem;
            margin-top: 4px;
            display: none;
        }
        
        /* Form Row */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* Password Field */
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
        }
        
        .password-toggle:hover {
            color: var(--kappel);
        }
        
        /* Password Strength */
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
        
        /* Password Requirements */
        .password-requirements {
            margin-top: 10px;
            padding: 10px;
            background: var(--isabelline);
            border-radius: 8px;
            font-size: 0.65rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 15px;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            color: var(--gray-web);
        }
        
        .requirement i {
            width: 12px;
            font-size: 0.6rem;
            margin-right: 6px;
        }
        
        .requirement.met { color: #10b981; }
        .requirement.unmet { color: #dc2626; }
        
        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-nav {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            border: none;
        }
        
        .btn-prev {
            background: transparent;
            border: 1.5px solid var(--platinum);
            color: var(--text-dark);
        }
        
        .btn-prev:hover {
            border-color: var(--kappel);
            color: var(--kappel);
        }
        
        .btn-next {
            background: var(--kappel);
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
        
        /* Checkbox */
        .checkbox-group {
            margin: 20px 0;
        }
        
        .checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            cursor: pointer;
        }
        
        .checkbox-label input {
            width: 16px;
            height: 16px;
            margin-top: 2px;
            cursor: pointer;
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
        
        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
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
        
        /* Alert */
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
        
        .alert.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .form-card { padding: 25px 20px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .form-title { font-size: 1.4rem; }
            .step-label { font-size: 0.6rem; }
            .step-number { width: 32px; height: 32px; font-size: 0.8rem; }
            .form-steps { max-width: 100%; }
            .password-requirements { grid-template-columns: 1fr; }
            .theme-toggle { top: 15px; right: 15px; padding: 6px 12px; font-size: 0.75rem; }
        }
        
        @media (max-width: 480px) {
            .form-card { padding: 20px 15px; }
            .form-title { font-size: 1.2rem; }
            .input-group input,
            .input-group select,
            .multi-select-trigger { padding: 8px 12px; font-size: 0.8rem; }
            .btn-nav { padding: 8px; font-size: 0.8rem; }
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
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>School Email <span class="required">*</span></label>
                            <input type="email" id="school_email" name="school_email" required placeholder="email@example.com">
                        </div>
                        <div class="input-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" id="school_phone" name="school_phone" required placeholder="Phone Number">
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
                                <div class="error-message" id="institution_level_error"></div>
                            </div>
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
                                <div class="error-message" id="product_type_error"></div>
                            </div>
                        </div>
                        <div class="input-group">
                            <label>County <span class="required">*</span></label>
                            <select id="county" name="county" required>
                                <option value="">Select County</option>
                                <?php foreach ($kenyan_counties as $county): ?>
                                    <option value="<?php echo strtolower(str_replace("'", "", $county)); ?>"><?php echo htmlspecialchars($county); ?></option>
                                <?php endforeach; ?>
                            </select>
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
                        </div>
                        <div class="input-group">
                            <label>School Motto <span class="required">*</span></label>
                            <input type="text" id="school_motto" name="school_motto" required placeholder="School motto">
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>School Address</label>
                        <input type="text" id="school_address" name="school_address" placeholder="School address (Optional)">
                    </div>
                </div>
                
                <!-- SECTION 2: Admin Details -->
                <div class="form-section" id="section-2">
                    <div class="input-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" id="admin_name" name="admin_name" required placeholder="Enter full name">
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" id="admin_email" name="admin_email" required placeholder="admin@example.com">
                        </div>
                        <div class="input-group">
                            <label>Phone Number</label>
                            <input type="tel" id="admin_phone" name="admin_phone" placeholder="Optional">
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
                    </div>
                    
                    <div class="input-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="terms" name="terms" required>
                            <span>By submitting, you agree to EduScore's <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a></span>
                        </label>
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
        const errorElement = this.container.querySelector('.error-message');
        if (errorElement && this.selectedValues.length > 0) errorElement.style.display = 'none';
        
        // Also store as JSON for the form submission
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
            const errorElement = this.container.querySelector('.error-message');
            if (this.selectedValues.length === 0) {
                if (errorElement) {
                    errorElement.textContent = 'Please select at least one option';
                    errorElement.style.display = 'block';
                }
                return false;
            }
            if (errorElement) errorElement.style.display = 'none';
            return true;
        }
    }
    
    // Initialize multi-selects
    const institutionLevelSelect = new MultiSelect('institution_level_container', 'institution_level', 'institution_level_trigger', 'institution_level_dropdown', 'institution_level_count');
    const productTypeSelect = new MultiSelect('product_type_container', 'product_type', 'product_type_trigger', 'product_type_dropdown', 'product_type_count');
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.multi-select-container')) {
            document.querySelectorAll('.multi-select-dropdown.show').forEach(dropdown => dropdown.classList.remove('show'));
        }
    });
    
    function validateStep(step) {
        if (step === 1) {
            const schoolName = document.getElementById('school_name').value.trim();
            const schoolEmail = document.getElementById('school_email').value.trim();
            const schoolPhone = document.getElementById('school_phone').value.trim();
            const institutionType = document.getElementById('institution_type').value;
            const county = document.getElementById('county').value;
            const totalStudents = document.getElementById('total_students').value;
            const schoolMotto = document.getElementById('school_motto').value.trim();
            
            if (!institutionLevelSelect.validate()) { showError('Please select at least one institution level'); return false; }
            if (!productTypeSelect.validate()) { showError('Please select at least one product type'); return false; }
            if (!schoolName) { showError('School name is required'); return false; }
            if (!schoolEmail || !isValidEmail(schoolEmail)) { showError('Valid school email is required'); return false; }
            if (!schoolPhone) { showError('Phone number is required'); return false; }
            if (!institutionType) { showError('Please select institution type'); return false; }
            if (!county) { showError('Please select county'); return false; }
            if (!totalStudents) { showError('Please select students population'); return false; }
            if (!schoolMotto) { showError('School motto is required'); return false; }
        } else if (step === 2) {
            const adminName = document.getElementById('admin_name').value.trim();
            const adminEmail = document.getElementById('admin_email').value.trim();
            if (!adminName) { showError('Admin full name is required'); return false; }
            if (!adminEmail || !isValidEmail(adminEmail)) { showError('Valid admin email is required'); return false; }
        } else if (step === 3) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            const hasLength = password.length >= 8 && password.length <= 15;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            if (!password) { showError('Password is required'); return false; }
            if (!hasLength || !hasUppercase || !hasLowercase || !hasNumber || !hasSpecial) {
                showError('Password must meet all requirements');
                return false;
            }
            if (password !== confirmPassword) { showError('Passwords do not match'); return false; }
            if (!terms) { showError('You must agree to the Terms of Service and Privacy Policy'); return false; }
        }
        hideError();
        return true;
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
    
// In register.php, replace the form submit handler with this:
const form = document.getElementById('registerForm');
form.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!validateStep(3)) return;
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
    
    try {
        // Collect form data properly
        const formDataToSend = {
            school_name: document.getElementById('school_name').value.trim(),
            school_email: document.getElementById('school_email').value.trim(),
            school_phone: document.getElementById('school_phone').value.trim(),
            institution_type: document.getElementById('institution_type').value,
            institution_level: document.getElementById('institution_level').value ? 
                document.getElementById('institution_level').value.split(',') : [],
            county: document.getElementById('county').value,
            total_students: document.getElementById('total_students').value,
            product_type: document.getElementById('product_type').value ? 
                document.getElementById('product_type').value.split(',') : [],
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
            showError(data.message || 'Registration failed. Please try again.');
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