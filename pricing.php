<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once 'includes/config.php';

$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/pricing";

$page_title = "Pricing | EduScore - Affordable School Management System Kenya";
$page_description = "Transparent pricing for Kenyan schools. Choose from our flexible plans for primary, junior secondary, and senior secondary schools.";
$page_keywords = "pricing, school management system price, EduScore pricing, school ERP cost Kenya";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// Pricing Data Structure with Public and Private Schools
$pricing_data = [
    'primary' => [
        'name' => 'Primary School',
        'icon' => 'fas fa-school',
        'public' => [
            'fee_system' => ['onboarding' => 3500, 'per_student' => 15],
            'analytics' => ['onboarding' => 2500, 'per_student' => 15],
            'both_modules' => ['onboarding' => 6000, 'per_student' => 25]
        ],
        'private' => [
            'fee_system' => ['onboarding' => 5000, 'per_student' => 25],
            'analytics' => ['onboarding' => 4000, 'per_student' => 25],
            'both_modules' => ['onboarding' => 9000, 'per_student' => 45]
        ]
    ],
    'junior' => [
        'name' => 'Junior Secondary School',
        'icon' => 'fas fa-chalkboard-user',
        'public' => [
            'fee_system' => ['onboarding' => 4500, 'per_student' => 35],
            'analytics' => ['onboarding' => 3500, 'per_student' => 25],
            'both_modules' => ['onboarding' => 8000, 'per_student' => 55]
        ],
        'private' => [
            'fee_system' => ['onboarding' => 6500, 'per_student' => 55],
            'analytics' => ['onboarding' => 5500, 'per_student' => 45],
            'both_modules' => ['onboarding' => 12000, 'per_student' => 95]
        ]
    ],
    'senior' => [
        'name' => 'Senior Secondary School',
        'icon' => 'fas fa-graduation-cap',
        'public' => [
            'fee_system' => ['onboarding' => 7000, 'per_student' => 50],
            'analytics' => ['onboarding' => 5500, 'per_student' => 45],
            'both_modules' => ['onboarding' => 12500, 'per_student' => 90]
        ],
        'private' => [
            'fee_system' => ['onboarding' => 10000, 'per_student' => 80],
            'analytics' => ['onboarding' => 8500, 'per_student' => 70],
            'both_modules' => ['onboarding' => 18500, 'per_student' => 145]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow">
    
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /*-----------------------------------*\
          #CUSTOM PROPERTY
        \*-----------------------------------*/
        :root {
            --kappel: hsl(170, 75%, 41%);
            --kappel_15: hsla(170, 75%, 41%, 0.15);
            --selective-yellow: hsl(42, 94%, 55%);
            --eerie-black-1: hsl(0, 0%, 9%);
            --eerie-black-2: hsl(180, 3%, 7%);
            --quick-silver: hsl(0, 0%, 65%);
            --radical-red: hsl(351, 83%, 61%);
            --light-gray: hsl(0, 0%, 80%);
            --isabelline: hsl(36, 33%, 94%);
            --gray-x-11: hsl(0, 0%, 73%);
            --platinum: hsl(0, 0%, 90%);
            --gray-web: hsl(0, 0%, 50%);
            --white: hsl(0, 0%, 100%);
            
            --ff-league_spartan: 'League Spartan', sans-serif;
            --ff-poppins: 'Poppins', sans-serif;
            
            --fs-1: 4.2rem;
            --fs-2: 3.2rem;
            --fs-3: 2.3rem;
            --fs-4: 1.8rem;
            --fs-5: 1.5rem;
            --fs-6: 1.4rem;
            --fs-7: 1.3rem;
            
            --fw-500: 500;
            --fw-600: 600;
            
            --section-padding: 75px;
            
            --shadow-1: 0 5px 10px rgba(0,0,0,0.1);
            --shadow-2: 0 10px 30px hsla(0, 0%, 0%, 0.06);
            --shadow-3: 0 10px 50px 0 hsla(220, 53%, 22%, 0.1);
            
            --radius-pill: 500px;
            --radius-circle: 50%;
            --radius-3: 3px;
            --radius-5: 5px;
            --radius-10: 10px;
            --radius-15: 15px;
            --radius-25: 25px;
            
            --transition-1: 0.25s ease;
            --transition-2: 0.5s ease;
            --cubic-in: cubic-bezier(0.51, 0.03, 0.64, 0.28);
            --cubic-out: cubic-bezier(0.33, 0.85, 0.4, 0.96);
        }
        
        /* Dark Mode Overrides */
        body.dark-mode {
            --eerie-black-1: hsl(0, 0%, 90%);
            --eerie-black-2: hsl(0, 0%, 95%);
            --gray-web: hsl(0, 0%, 70%);
            --light-gray: hsl(0, 0%, 30%);
            --isabelline: hsl(36, 20%, 15%);
            --platinum: hsl(0, 0%, 25%);
            --white: hsl(0, 0%, 15%);
        }
        
        body.dark-mode .footer {
            background-color: #0a0e17;
        }
        
        body.dark-mode .header {
            background-color: var(--white);
        }
        
        body.dark-mode .navbar-link {
            color: var(--eerie-black-1);
        }
        
        /*-----------------------------------*\
          #RESET
        \*-----------------------------------*/
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        li { list-style: none; }
        
        a, img, span, data, input, button, textarea, ion-icon { display: block; }
        
        a {
            color: inherit;
            text-decoration: none;
        }
        
        img { height: auto; }
        
        input, button, textarea {
            background: none;
            border: none;
            font: inherit;
        }
        
        input, textarea { width: 100%; }
        
        button { cursor: pointer; }
        
        ion-icon { pointer-events: none; }
        
        address { font-style: normal; }
        
        html {
            font-family: var(--ff-poppins);
            font-size: 10px;
            scroll-behavior: smooth;
        }
        
        body {
            background-color: var(--white);
            color: var(--gray-web);
            font-size: 1.6rem;
            line-height: 1.75;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background-color: hsl(0, 0%, 98%); }
        ::-webkit-scrollbar-thumb { background-color: hsl(0, 0%, 80%); }
        
        /*-----------------------------------*\
          #REUSED STYLE
        \*-----------------------------------*/
        .container { 
            max-width: 1400px;
            margin: 0 auto;
            padding-inline: 15px;
        }
        
        .section { 
            padding-block: var(--section-padding); 
        }
        
        .h1, .h2, .h3 {
            color: var(--eerie-black-1);
            font-family: var(--ff-league_spartan);
            line-height: 1.2;
        }
        
        .h1, .h2 { font-weight: var(--fw-600); }
        .h1 { font-size: var(--fs-1); }
        .h2 { font-size: var(--fs-2); }
        
        .section-title {
            --color: var(--radical-red);
            text-align: center;
        }
        
        .section-title .span {
            display: inline-block;
            color: var(--color);
        }
        
        /*-----------------------------------*\
          #HEADER - GLASSMORPHISM
        \*-----------------------------------*/
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.2, 0, 0, 1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .header.active {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.06);
            padding-block: 0;
        }
        
        body.dark-mode .header {
            background: rgba(15, 23, 42, 0.85);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        body.dark-mode .header.active {
            background: rgba(15, 23, 42, 0.92);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            padding: 14px 32px;
            transition: padding 0.25s ease;
        }
        
        .header.active .container {
            padding: 8px 32px;
        }
        
        .logo {
            flex-shrink: 0;
            line-height: 0;
        }
        
        .logo img {
            width: auto;
            height: 44px;
            max-width: 150px;
            object-fit: contain;
            transition: height 0.25s ease;
        }
        
        .header.active .logo img {
            height: 38px;
        }
        
        .navbar {
            display: flex;
            align-items: center;
            flex: 1;
            justify-content: center;
        }
        
        .navbar-list {
            display: flex;
            align-items: center;
            gap: 36px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .navbar-link {
            font-weight: 500;
            color: var(--eerie-black-1);
            transition: color 0.2s ease;
            position: relative;
            padding: 8px 0;
            font-size: 1.55rem;
            letter-spacing: -0.2px;
            white-space: nowrap;
        }
        
        body.dark-mode .navbar-link {
            color: #f1f5f9;
        }
        
        .navbar-link:hover {
            color: #00BFFF;
        }
        
        .navbar-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2.5px;
            background: #00BFFF;
            transition: width 0.25s ease;
            border-radius: 2px;
        }
        
        .navbar-link:hover::after {
            width: 100%;
        }
        
        .navbar-link.active {
            color: #00BFFF;
        }
        
        .navbar-link.active::after {
            width: 100%;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-shrink: 0;
        }
        
        .theme-toggle {
            background: rgba(0, 0, 0, 0.04);
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--eerie-black-1);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            transition: all 0.2s ease;
            border-radius: 50%;
            width: 42px;
            height: 42px;
        }
        
        body.dark-mode .theme-toggle {
            background: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
        }
        
        .theme-toggle:hover {
            transform: scale(1.05);
            background: rgba(0, 191, 255, 0.12);
        }
        
        .theme-toggle .fa-sun { display: none; }
        body.dark-mode .theme-toggle .fa-moon { display: none; }
        body.dark-mode .theme-toggle .fa-sun { display: block; }
        
        .portal-buttons-header {
            display: flex;
            gap: 12px;
        }
        
        .portal-btn {
            padding: 9px 22px;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.35rem;
            letter-spacing: -0.2px;
            backdrop-filter: blur(4px);
        }
        
        .portal-btn-analytics {
            background: #00BFFF;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 191, 255, 0.2);
            border: none;
        }
        
        .portal-btn-analytics:hover {
            background: #009ac9;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0, 191, 255, 0.25);
        }
        
        .portal-btn-finance {
            background: rgba(0, 191, 255, 0.08);
            color: #00BFFF;
            border: 1px solid rgba(0, 191, 255, 0.4);
        }
        
        .portal-btn-finance:hover {
            background: #00BFFF;
            color: #ffffff;
            transform: translateY(-2px);
            border-color: transparent;
        }
        
        .menu-btn {
            display: none;
            background: rgba(0, 0, 0, 0.04);
            border: none;
            font-size: 2.2rem;
            cursor: pointer;
            color: var(--eerie-black-1);
            align-items: center;
            justify-content: center;
            padding: 8px;
            transition: all 0.2s ease;
            border-radius: 12px;
            width: 44px;
            height: 44px;
        }
        
        body.dark-mode .menu-btn {
            background: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
        }
        
        .menu-btn:hover {
            background: rgba(0, 191, 255, 0.12);
        }
        
        /* Mobile Drawer */
        @media (max-width: 991px) {
            .navbar {
                position: fixed;
                top: 0;
                right: -100%;
                width: min(85%, 360px);
                height: 100vh;
                background: rgba(255, 255, 255, 0.96);
                backdrop-filter: blur(24px);
                box-shadow: -8px 0 32px rgba(0, 0, 0, 0.12);
                z-index: 1001;
                transition: right 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
                overflow-y: auto;
                padding: 24px 20px;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
            }
            
            body.dark-mode .navbar {
                background: rgba(15, 23, 42, 0.96);
            }
            
            .navbar.active {
                right: 0;
            }
            
            .navbar .wrapper {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-bottom: 20px;
                margin-bottom: 24px;
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            }
            
            .nav-close-btn {
                background: rgba(0, 0, 0, 0.05);
                border: none;
                font-size: 2rem;
                cursor: pointer;
                padding: 10px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--eerie-black-1);
                width: 44px;
                height: 44px;
            }
            
            .navbar-list {
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }
            
            .navbar-link {
                display: block;
                padding: 14px 16px;
                font-size: 1.6rem;
                font-weight: 500;
                border-radius: 14px;
                transition: all 0.25s ease;
                color: var(--eerie-black-1);
            }
            
            .navbar-link:hover,
            .navbar-link.active {
                background: rgba(0, 191, 255, 0.08);
                color: #00BFFF;
                transform: translateX(3px);
            }
            
            .navbar-link::after {
                display: none;
            }
            
            .mobile-portal-buttons {
                display: flex;
                flex-direction: column;
                gap: 14px;
                margin-top: 32px;
                padding-top: 24px;
                border-top: 1px solid rgba(0, 0, 0, 0.08);
            }
            
            .mobile-portal-btn {
                padding: 14px 20px;
                border-radius: 44px;
                font-weight: 600;
                font-size: 1.45rem;
                text-decoration: none;
                transition: all 0.2s ease;
                text-align: center;
            }
            
            .mobile-portal-btn-analytics {
                background: #00BFFF;
                color: #ffffff;
                box-shadow: 0 2px 8px rgba(0, 191, 255, 0.2);
            }
            
            .mobile-portal-btn-finance {
                background: transparent;
                color: #00BFFF;
                border: 1.5px solid #00BFFF;
            }
            
            .portal-buttons-header {
                display: none;
            }
            
            .menu-btn {
                display: flex;
            }
            
            .header .container {
                padding: 12px 20px;
            }
            
            .header.active .container {
                padding: 8px 20px;
            }
        }
        
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
            z-index: 1000;
        }
        
        .overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        body.navbar-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
        }
        
        @media (min-width: 992px) {
            .navbar {
                position: static;
                right: auto;
                width: auto;
                height: auto;
                background: none;
                backdrop-filter: none;
                box-shadow: none;
                padding: 0;
                overflow: visible;
                display: flex;
                flex: 1;
                justify-content: center;
            }
            
            .navbar .wrapper,
            .nav-close-btn,
            .mobile-portal-buttons {
                display: none;
            }
            
            .navbar-list {
                flex-direction: row;
                gap: 36px;
            }
            
            .navbar-link {
                padding: 8px 0;
                font-size: 1.55rem;
            }
            
            .menu-btn {
                display: none;
            }
            
            .overlay {
                display: none;
            }
            
            .portal-buttons-header {
                display: flex;
            }
        }
        
        @media (max-width: 480px) {
            .header .container {
                padding: 10px 16px;
            }
            
            .logo img {
                height: 34px;
            }
            
            .menu-btn {
                width: 40px;
                height: 40px;
                font-size: 2rem;
            }
            
            .theme-toggle {
                width: 38px;
                height: 38px;
                font-size: 1.6rem;
            }
        }
        
        /*============================================
          #HERO SECTION - PRICING HERO
        ============================================*/
        .pricing-hero {
            padding-top: 140px;
            padding-bottom: 60px;
            background: #00BFFF;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        body.dark-mode .pricing-hero {
            background: #0099cc;
        }
        
        /* Bubble Animation */
        .bubble {
            position: absolute;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            pointer-events: none;
            animation: bubbleMove 8s ease-in-out infinite;
        }
        
        @keyframes bubbleMove {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }
            20% {
                opacity: 0.4;
            }
            80% {
                opacity: 0.2;
            }
            100% {
                transform: translateY(-20vh) scale(1);
                opacity: 0;
            }
        }
        
        .bubble:nth-child(1) { width: 30px; height: 30px; left: 5%; animation-duration: 6s; animation-delay: 0s; }
        .bubble:nth-child(2) { width: 50px; height: 50px; left: 15%; animation-duration: 8s; animation-delay: 1s; }
        .bubble:nth-child(3) { width: 20px; height: 20px; left: 25%; animation-duration: 5s; animation-delay: 2s; }
        .bubble:nth-child(4) { width: 70px; height: 70px; left: 35%; animation-duration: 10s; animation-delay: 0.5s; }
        .bubble:nth-child(5) { width: 40px; height: 40px; left: 45%; animation-duration: 7s; animation-delay: 3s; }
        .bubble:nth-child(6) { width: 25px; height: 25px; left: 55%; animation-duration: 5.5s; animation-delay: 1.5s; }
        .bubble:nth-child(7) { width: 60px; height: 60px; left: 65%; animation-duration: 9s; animation-delay: 2.5s; }
        .bubble:nth-child(8) { width: 35px; height: 35px; left: 75%; animation-duration: 6.5s; animation-delay: 4s; }
        .bubble:nth-child(9) { width: 45px; height: 45px; left: 85%; animation-duration: 7.5s; animation-delay: 1s; }
        .bubble:nth-child(10) { width: 55px; height: 55px; left: 95%; animation-duration: 8.5s; animation-delay: 3.5s; }
        .bubble:nth-child(11) { width: 15px; height: 15px; left: 8%; animation-duration: 4s; animation-delay: 5s; }
        .bubble:nth-child(12) { width: 80px; height: 80px; left: 42%; animation-duration: 12s; animation-delay: 1s; }
        
        /* Pricing Hero Badge */
        .pricing-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 1.3rem;
            font-weight: 500;
            color: #ffffff;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }
        
        .pricing-badge i {
            font-size: 1.2rem;
        }
        
        body.dark-mode .pricing-badge {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .pricing-hero h1 {
            font-size: 4rem;
            color: #ffffff;
            margin-bottom: 15px;
            white-space: nowrap;
        }
        
        .pricing-hero h1 span {
            color: #FFD700;
        }
        
        .pricing-hero p {
            font-size: 1.6rem;
            color: rgba(255, 255, 255, 0.9);
            max-width: 700px;
            margin: 0 auto;
        }
        
        body.dark-mode .pricing-hero p {
            color: rgba(255, 255, 255, 0.85);
        }
        
        @media (max-width: 992px) {
            .pricing-hero h1 { font-size: 3.2rem; white-space: nowrap; }
        }
        
        @media (max-width: 768px) {
            .pricing-hero { padding-top: 120px; }
            .pricing-hero h1 { font-size: 2.5rem; white-space: nowrap; }
        }
        
        @media (max-width: 576px) {
            .pricing-hero h1 { font-size: 2rem; white-space: normal; line-height: 1.3; }
        }
        
/*============================================
  #PRICING CARDS SECTION - MODERN STYLED
============================================*/
.pricing-cards-section {
    background-color: var(--white);
    padding-bottom: 80px;
}

.school-type {
    margin-bottom: 60px;
}

.school-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--platinum);
}

.school-icon {
    width: 50px;
    height: 50px;
    background: rgba(0, 191, 255, 0.1);
    border-radius: var(--radius-circle);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: #00BFFF;
}

.school-title {
    font-size: 2rem;
    color: var(--eerie-black-1);
    font-family: var(--ff-league_spartan);
}

/* School Type Tabs */
.school-type-tabs {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--platinum);
    padding-bottom: 10px;
}

.school-tab {
    padding: 10px 25px;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 1.4rem;
    background: var(--white);
    border: 1px solid var(--platinum);
    color: var(--gray-web);
}

.school-tab.active {
    background: #00BFFF;
    color: white;
    border-color: #00BFFF;
}

.school-tab.public.active {
    background: #00BFFF;
}

.school-tab.private.active {
    background: #8B5CF6;
}

/* Pricing Cards Grid */
.pricing-card-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

/* Modern Pricing Card */
.pricing-card {
    border-radius: var(--radius-15);
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid var(--platinum);
    position: relative;
    box-shadow: var(--shadow-1);
}

/* Card Background Colors - Faded versions */
.fee-package {
    background: linear-gradient(135deg, rgba(0, 191, 255, 0.08) 0%, rgba(0, 191, 255, 0.02) 100%);
    border-top: 3px solid #00BFFF;
}

.fee-package:hover {
    background: linear-gradient(135deg, rgba(0, 191, 255, 0.12) 0%, rgba(0, 191, 255, 0.04) 100%);
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.analytics-package {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(16, 185, 129, 0.02) 100%);
    border-top: 3px solid #10B981;
}

.analytics-package:hover {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(16, 185, 129, 0.04) 100%);
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.complete-package {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.08) 0%, rgba(139, 92, 246, 0.02) 100%);
    border-top: 3px solid #8B5CF6;
}

.complete-package:hover {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.12) 0%, rgba(139, 92, 246, 0.04) 100%);
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

/* Dark mode backgrounds */
body.dark-mode .fee-package {
    background: linear-gradient(135deg, rgba(0, 191, 255, 0.15) 0%, rgba(0, 191, 255, 0.05) 100%);
}

body.dark-mode .analytics-package {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%);
}

body.dark-mode .complete-package {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(139, 92, 246, 0.05) 100%);
}

/* Ribbon/Recommended Badge */
.pricing-card .ribbon {
    width: 150px;
    height: 150px;
    position: absolute;
    top: -10px;
    left: -10px;
    overflow: hidden;
    z-index: 1;
}

.pricing-card .ribbon::before,
.pricing-card .ribbon::after {
    position: absolute;
    content: "";
    z-index: -1;
    display: block;
    border: 7px solid #0056b3;
    border-top-color: transparent;
    border-left-color: transparent;
}

.pricing-card .ribbon::before {
    top: 0px;
    right: 15px;
}

.pricing-card .ribbon::after {
    bottom: 15px;
    left: 0px;
}

.pricing-card .ribbon span {
    position: absolute;
    top: 30px;
    right: 0;
    transform: rotate(-45deg);
    width: 200px;
    background: #00BFFF;
    padding: 8px 0;
    color: #fff;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    box-shadow: 0 5px 10px rgba(0,0,0,0.12);
}

/* Price Display - Text Style */
.card-price {
    text-align: center;
    padding: 25px 20px 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

body.dark-mode .card-price {
    border-bottom-color: rgba(255, 255, 255, 0.05);
}

.main-price {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
}

.main-price small {
    font-size: 1.4rem;
    font-weight: 500;
}

.per-student-price {
    font-size: 1.4rem;
    margin-top: 8px;
    color: var(--gray-web);
}

.onboarding-fee {
    text-align: center;
    font-size: 1.2rem;
    color: var(--gray-web);
    padding: 10px 20px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

body.dark-mode .onboarding-fee {
    border-bottom-color: rgba(255, 255, 255, 0.05);
}

/* Package Name */
.package-name {
    text-align: center;
    padding: 15px 20px 0;
}

.package-name h3 {
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.package-name p {
    font-size: 1.2rem;
    color: var(--gray-web);
}

/* School Type Badge */
.school-type-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 8px;
}

.school-type-badge.public {
    background: rgba(0, 191, 255, 0.15);
    color: #00BFFF;
}

.school-type-badge.private {
    background: rgba(139, 92, 246, 0.15);
    color: #8B5CF6;
}

/* Button */
.btn {
    width: 100%;
    display: flex;
    margin: 20px 0 25px;
    justify-content: center;
}

.price-btn {
    width: 80%;
    height: 45px;
    color: #fff;
    font-size: 1.5rem;
    font-weight: 600;
    border: none;
    outline: none;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.price-btn:hover {
    border-radius: 8px;
    transform: translateY(-2px);
    gap: 12px;
}

/* Card specific colors */
.fee-package .main-price { color: #00BFFF; }
.analytics-package .main-price { color: #10B981; }
.complete-package .main-price { color: #8B5CF6; }

.fee-package .price-btn { background: #00BFFF; }
.analytics-package .price-btn { background: #10B981; }
.complete-package .price-btn { background: #8B5CF6; }

.fee-package .price-btn:hover { background: #009ac9; }
.analytics-package .price-btn:hover { background: #0d9668; }
.complete-package .price-btn:hover { background: #7c3aed; }

.fee-package .ribbon span { background: #00BFFF; }
.analytics-package .ribbon span { background: #10B981; }
.complete-package .ribbon span { background: #8B5CF6; }

/* Note section */
.note {
    text-align: center;
    margin-top: 50px;
    padding: 20px;
    background: var(--isabelline);
    border-radius: var(--radius-10);
}

.note p {
    font-size: 1.3rem;
    color: var(--gray-web);
}

@media (max-width: 991px) {
    .pricing-card-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    
    .school-type-tabs {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .pricing-card {
        max-width: 100%;
    }
}
        
        /* Footer */
        .footer {
            background-color: var(--eerie-black-2);
            color: var(--gray-x-11);
            padding-block-start: 50px;
        }
        
        .footer-top {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            padding-block-end: 35px;
        }
        
        .footer-list-title {
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: 1.6rem;
            font-weight: 600;
            margin-block-end: 12px;
        }
        
        .footer-link {
            text-decoration: none;
            color: inherit;
            display: block;
            padding-block: 4px;
            font-size: 1.3rem;
        }
        
        .footer-link:hover {
            color: var(--kappel);
        }
        
        .copyright {
            text-align: center;
            padding-block: 25px;
            border-block-start: 1px solid var(--eerie-black-1);
            font-size: 1.25rem;
        }
        
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.7s ease;
        }
        
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        .back-top-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: var(--kappel);
            color: var(--white);
            font-size: 1.6rem;
            padding: 12px;
            border-radius: var(--radius-circle);
            z-index: 3;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition-1);
        }
        
        .back-top-btn.active {
            opacity: 1;
            pointer-events: all;
        }
        
        main {
            min-height: 400px;
        }
        
        @media (max-width: 768px) {
            .footer-top {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header" data-header>
    <div class="container">
        <a href="index.php" class="logo">
            <img src="/images/logo.png" alt="EduScore logo">
        </a>

        <nav class="navbar" data-navbar>
            <div class="wrapper">
                <a href="index.php" class="logo">
                    <img src="/images/logo.png" alt="EduScore logo">
                </a>
                <button class="nav-close-btn" aria-label="close menu" data-nav-toggler>
                    <ion-icon name="close-outline" aria-hidden="true"></ion-icon>
                </button>
            </div>
            <ul class="navbar-list">
                <li class="navbar-item"><a href="index.php" class="navbar-link" data-nav-link>Home</a></li>
                <li class="navbar-item"><a href="about.php" class="navbar-link" data-nav-link>About</a></li>
                <li class="navbar-item"><a href="pricing.php" class="navbar-link active" data-nav-link>Pricing</a></li>
                <li class="navbar-item"><a href="career-pathways.php" class="navbar-link" data-nav-link>Career Pathways</a></li>
                <li class="navbar-item"><a href="blog.php" class="navbar-link" data-nav-link>Blog</a></li>
            </ul>
            
            <div class="mobile-portal-buttons">
                <a href="analytics.php" class="mobile-portal-btn mobile-portal-btn-analytics">Analytics Portal</a>
                <a href="feesystem.php" class="mobile-portal-btn mobile-portal-btn-finance">Finance Portal</a>
            </div>
        </nav>
        
        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                <i class="fas fa-moon"></i><i class="fas fa-sun"></i>
            </button>
            <div class="portal-buttons-header">
                <a href="analytics.php" class="portal-btn portal-btn-analytics">Analytics Portal</a>
                <a href="feesystem.php" class="portal-btn portal-btn-finance">Finance Portal</a>
            </div>
            <button class="menu-btn" aria-label="open menu" data-nav-toggler>
                <ion-icon name="menu-outline" aria-hidden="true"></ion-icon>
            </button>
        </div>

        <div class="overlay" data-nav-toggler data-overlay></div>
    </div>
</header>

<main>
    <!-- Pricing Hero Section -->
    <section class="pricing-hero">
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        
        <div class="container">
            <div class="pricing-badge">
                <i class="fas fa-leaf"></i> Customized pricing according to total student enrollment
            </div>
            <h1>Simple, flexible pricing <span>built for schools</span></h1>
            <p>Find a package that matches your school's size and operational needs. Each plan comes with smart digital tools to help manage academics, reports, communication, and administration more efficiently.</p>
        </div>
    </section>

    <!-- Pricing Cards Section -->
<section class="pricing-cards-section section">
    <div class="container">
        
        <!-- Primary School -->
        <div class="school-type reveal">
            <div class="school-header">
                <div class="school-icon">
                    <i class="fas fa-school"></i>
                </div>
                <h2 class="school-title">Primary School</h2>
            </div>
            
            <!-- School Type Tabs -->
            <div class="school-type-tabs">
                <button class="school-tab public active" data-school="primary" data-type="public">Public School</button>
                <button class="school-tab private" data-school="primary" data-type="private">Private School</button>
            </div>
            
            <!-- Public School Pricing -->
            <div class="pricing-card-grid public-grid" id="primary-public">
                <!-- Fee Management System -->
                <div class="pricing-card fee-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['primary']['public']['fee_system']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['primary']['public']['fee_system']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Fee Management System</h3>
                        <p>Complete financial management</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <!-- Analytics System -->
                <div class="pricing-card analytics-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['primary']['public']['analytics']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['primary']['public']['analytics']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Analytics System</h3>
                        <p>Data-driven insights</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <!-- Complete Package -->
                <div class="pricing-card complete-package reveal">
                    <div class="ribbon"><span>Recommended</span></div>
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['primary']['public']['both_modules']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['primary']['public']['both_modules']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Complete Package</h3>
                        <p>Everything you need</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- Private School Pricing (Hidden by default) -->
            <div class="pricing-card-grid private-grid" id="primary-private" style="display: none;">
                <!-- Fee Management System -->
                <div class="pricing-card fee-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['primary']['private']['fee_system']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['primary']['private']['fee_system']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Fee Management System</h3>
                        <p>Complete financial management</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <!-- Analytics System -->
                <div class="pricing-card analytics-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['primary']['private']['analytics']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['primary']['private']['analytics']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Analytics System</h3>
                        <p>Data-driven insights</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <!-- Complete Package -->
                <div class="pricing-card complete-package reveal">
                    <div class="ribbon"><span>Recommended</span></div>
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['primary']['private']['both_modules']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['primary']['private']['both_modules']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Complete Package</h3>
                        <p>Everything you need</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Junior Secondary School -->
        <div class="school-type reveal">
            <div class="school-header">
                <div class="school-icon">
                    <i class="fas fa-chalkboard-user"></i>
                </div>
                <h2 class="school-title">Junior Secondary School</h2>
            </div>
            
            <div class="school-type-tabs">
                <button class="school-tab public active" data-school="junior" data-type="public">Public School</button>
                <button class="school-tab private" data-school="junior" data-type="private">Private School</button>
            </div>
            
            <div class="pricing-card-grid public-grid" id="junior-public">
                <div class="pricing-card fee-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['junior']['public']['fee_system']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['junior']['public']['fee_system']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Fee Management System</h3>
                        <p>Complete financial management</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card analytics-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['junior']['public']['analytics']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['junior']['public']['analytics']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Analytics System</h3>
                        <p>Data-driven insights</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card complete-package reveal">
                    <div class="ribbon"><span>Recommended</span></div>
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['junior']['public']['both_modules']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['junior']['public']['both_modules']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Complete Package</h3>
                        <p>Everything you need</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="pricing-card-grid private-grid" id="junior-private" style="display: none;">
                <div class="pricing-card fee-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['junior']['private']['fee_system']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['junior']['private']['fee_system']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Fee Management System</h3>
                        <p>Complete financial management</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card analytics-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['junior']['private']['analytics']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['junior']['private']['analytics']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Analytics System</h3>
                        <p>Data-driven insights</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card complete-package reveal">
                    <div class="ribbon"><span>Recommended</span></div>
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['junior']['private']['both_modules']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['junior']['private']['both_modules']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Complete Package</h3>
                        <p>Everything you need</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Senior Secondary School -->
        <div class="school-type reveal">
            <div class="school-header">
                <div class="school-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2 class="school-title">Senior Secondary School</h2>
            </div>
            
            <div class="school-type-tabs">
                <button class="school-tab public active" data-school="senior" data-type="public">Public School</button>
                <button class="school-tab private" data-school="senior" data-type="private">Private School</button>
            </div>
            
            <div class="pricing-card-grid public-grid" id="senior-public">
                <div class="pricing-card fee-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['senior']['public']['fee_system']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['senior']['public']['fee_system']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Fee Management System</h3>
                        <p>Complete financial management</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card analytics-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['senior']['public']['analytics']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['senior']['public']['analytics']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Analytics System</h3>
                        <p>Data-driven insights</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card complete-package reveal">
                    <div class="ribbon"><span>Recommended</span></div>
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['senior']['public']['both_modules']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['senior']['public']['both_modules']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Complete Package</h3>
                        <p>Everything you need</p>
                        <span class="school-type-badge public">Public School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="pricing-card-grid private-grid" id="senior-private" style="display: none;">
                <div class="pricing-card fee-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['senior']['private']['fee_system']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['senior']['private']['fee_system']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Fee Management System</h3>
                        <p>Complete financial management</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card analytics-package reveal">
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['senior']['private']['analytics']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['senior']['private']['analytics']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Analytics System</h3>
                        <p>Data-driven insights</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card complete-package reveal">
                    <div class="ribbon"><span>Recommended</span></div>
                    <div class="card-price">
                        <div class="main-price">KES <?php echo number_format($pricing_data['senior']['private']['both_modules']['onboarding']); ?><small> one-time</small></div>
                        <div class="per-student-price">+ KES <?php echo $pricing_data['senior']['private']['both_modules']['per_student']; ?>/student/term</div>
                    </div>
                    <div class="onboarding-fee">One-time onboarding fee included</div>
                    <div class="package-name">
                        <h3>Complete Package</h3>
                        <p>Everything you need</p>
                        <span class="school-type-badge private">Private School</span>
                    </div>
                    <div class="btn">
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="note reveal">
            <p><i class="fas fa-info-circle"></i> All prices are in Kenyan Shillings (KES). Onboarding fee is a one-time payment. Per-student fee is charged termly based on the number of active students in the system. Private school pricing includes premium features and priority support.</p>
        </div>
    </div>
</section>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="index.php" class="logo">
                    <img src="/images/logo.png" alt="EduScore logo">
                </a>
                <p class="footer-brand-text">Modern school management system for Kenyan educational institutions.</p>
                <div>
                    <span>Email:</span>
                    <a href="mailto:eduscoreke@gmail.com" class="footer-link">eduscoreke@gmail.com</a>
                </div>
                <div>
                    <span>Phone:</span>
                    <a href="tel:+254799115282" class="footer-link">+254 799 115 282</a>
                </div>
            </div>
            <ul class="footer-list">
                <li><p class="footer-list-title">Links</p></li>
                <li><a href="about.php" class="footer-link">About</a></li>
                <li><a href="pricing.php" class="footer-link">Pricing</a></li>
                <li><a href="blog.php" class="footer-link">Blog</a></li>
            </ul>
            <ul class="footer-list">
                <li><p class="footer-list-title">Portals</p></li>
                <li><a href="analytics.php" class="footer-link">Analytics Portal</a></li>
                <li><a href="feesystem.php" class="footer-link">Finance Portal</a></li>
                <li><a href="parents-portal.php" class="footer-link">Parents Portal</a></li>
            </ul>
            <div class="footer-list">
                <p class="footer-list-title">Newsletter</p>
                <p>Subscribe for updates and news</p>
            </div>
        </div>
        <div class="copyright">
            <p>Copyright <?php echo date('Y'); ?> All Rights Reserved by EduScore Kenya</p>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<a href="#top" class="back-top-btn" aria-label="back top top" data-back-top-btn>
    <ion-icon name="chevron-up" aria-hidden="true"></ion-icon>
</a>

<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<script>
// Theme Toggle
const themeToggle = document.getElementById('themeToggle');
const body = document.body;
const savedTheme = localStorage.getItem('theme');
if (savedTheme === 'dark') { body.classList.add('dark-mode'); }
if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        localStorage.setItem('theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
    });
}

// Navbar Toggle
const navbar = document.querySelector("[data-navbar]");
const navTogglers = document.querySelectorAll("[data-nav-toggler]");
const overlay = document.querySelector("[data-overlay]");

const toggleNavbar = function () { 
    navbar.classList.toggle("active"); 
    overlay.classList.toggle("active");
    if (navbar.classList.contains("active")) {
        body.classList.add("navbar-open");
        body.style.top = `-${window.scrollY}px`;
    } else {
        const scrollY = body.style.top;
        body.classList.remove("navbar-open");
        body.style.top = '';
        window.scrollTo(0, parseInt(scrollY || '0') * -1);
    }
}

const closeNavbar = function () { 
    navbar.classList.remove("active"); 
    overlay.classList.remove("active");
    body.classList.remove("navbar-open");
    body.style.top = '';
}

navTogglers.forEach(toggler => toggler.addEventListener("click", toggleNavbar));

if (overlay) {
    overlay.addEventListener("click", closeNavbar);
}

// Header active on scroll
const header = document.querySelector("[data-header]");
const backTopBtn = document.querySelector("[data-back-top-btn]");

window.addEventListener("scroll", function() {
    if (window.scrollY > 50) {
        header.classList.add("active");
        backTopBtn.classList.add("active");
    } else {
        header.classList.remove("active");
        backTopBtn.classList.remove("active");
    }
});

// Scroll reveal
const reveals = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) entry.target.classList.add('active');
    });
}, { threshold: 0.15 });
reveals.forEach(el => revealObserver.observe(el));

// Set active nav link
const currentPage = window.location.pathname.split('/').pop();
document.querySelectorAll('.navbar-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPage || (currentPage === '' && href === 'index.php')) {
        link.classList.add('active');
    }
});
// School Type Tabs Functionality
document.querySelectorAll('.school-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const school = this.dataset.school;
        const type = this.dataset.type;
        
        // Update active state for tabs in this school section
        const parentSection = this.closest('.school-type');
        parentSection.querySelectorAll('.school-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        // Show/hide appropriate grids
        const publicGrid = parentSection.querySelector('.public-grid');
        const privateGrid = parentSection.querySelector('.private-grid');
        
        if (type === 'public') {
            publicGrid.style.display = 'grid';
            privateGrid.style.display = 'none';
        } else {
            publicGrid.style.display = 'none';
            privateGrid.style.display = 'grid';
        }
    });
});
</script>

</body>
</html>