<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Include config
require_once 'includes/config.php';

// Define base URL and canonical URL
$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/pricing";

// Enhanced SEO metadata
$page_title = "Pricing | EduScore - Affordable School Management System Kenya";
$page_description = "Transparent and affordable pricing for Kenyan schools. Choose from flexible plans for primary, junior secondary, and senior secondary schools.";
$page_keywords = "pricing, school management system price, EduScore pricing, school ERP cost Kenya, affordable school software";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// Pricing Data Structure
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
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    
    <style>
/*-----------------------------------*\
  #UNIFORM SYSTEM STYLES
\*-----------------------------------*/
body {
    background: linear-gradient(135deg, #fffdf5 0%, #fffaf0 50%, #fff5eb 100%);
    /* Fallback - soft cream */
    background-color: #fffdf5;
    font-family: "Merriweather", serif;
    color: #000;
    margin: 0;
    padding: 0;
    min-height: 100vh;
}

body, button, input, textarea, select, p, li, a, span {
    font-family: "Merriweather", serif;
}

h1, h2, h3, h4, .section-title {
    font-family: 'League Spartan', "Merriweather", serif;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    text-transform: capitalize;
}

/*-----------------------------------*\
  #CUSTOM PROPERTY
\*-----------------------------------*/
:root {
    --kappel: hsl(170, 75%, 41%);
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
    
    --shadow-1: 0 6px 15px 0 hsla(0, 0%, 0%, 0.05);
    --shadow-2: 0 10px 30px hsla(0, 0%, 0%, 0.06);
    --shadow-3: 0 10px 50px 0 hsla(220, 53%, 22%, 0.1);
    
    --radius-pill: 500px;
    --radius-circle: 50%;
    --radius-3: 3px;
    --radius-5: 5px;
    --radius-10: 10px;
    --radius-15: 15px;
    
    --transition-1: 0.25s ease;
    --transition-2: 0.5s ease;
}

/* Dark Mode */
body.dark-mode {
    --eerie-black-1: hsl(0, 0%, 90%);
    --eerie-black-2: hsl(0, 0%, 95%);
    --gray-web: hsl(0, 0%, 70%);
    --light-gray: hsl(0, 0%, 30%);
    --isabelline: hsl(36, 20%, 15%);
    --platinum: hsl(0, 0%, 25%);
    --white: hsl(0, 0%, 15%);
    background: linear-gradient(170deg, #3a3a2a 35%, #2a2a28 100%);
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
    text-align: center;
    margin-bottom: 20px;
}

.section-title .span {
    display: inline-block;
    color: #00BFFF;
}

.section-subtitle {
    font-size: var(--fs-5);
    text-transform: uppercase;
    font-weight: var(--fw-500);
    letter-spacing: 1px;
    text-align: center;
    margin-block-end: 15px;
    color: #00BFFF;
}

.btn {
    background-color: #00BFFF;
    color: white;
    font-family: var(--ff-league_spartan);
    font-size: var(--fs-4);
    display: inline-flex;
    align-items: center;
    gap: 7px;
    max-width: max-content;
    padding: 12px 28px;
    border-radius: 60px;
    text-decoration: none;
    transition: var(--transition-1);
    font-weight: 600;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 191, 255, 0.3);
    background-color: #009ac9;
}

/*-----------------------------------*\
  #HEADER
\*-----------------------------------*/
.header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: rgba(255, 253, 245, 0.85);
    backdrop-filter: blur(16px);
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.2, 0, 0, 1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.5);
}

.header.active {
    background: rgba(255, 253, 245, 0.92);
    backdrop-filter: blur(20px);
    padding-block: 0;
}

body.dark-mode .header {
    background: rgba(25, 30, 35, 0.85);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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

.navbar-link:hover, .navbar-link.active {
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

.navbar-link.active::after, .navbar-link:hover::after {
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
    backdrop-filter: blur(4px);
}

.portal-btn-analytics {
    background: #00BFFF;
    color: #ffffff;
}

.portal-btn-analytics:hover {
    background: #009ac9;
    transform: translateY(-2px);
}

.portal-btn-finance {
    background: rgba(0, 191, 255, 0.08);
    color: #00BFFF;
    border: 1px solid rgba(0, 191, 255, 0.4);
}

.portal-btn-finance:hover {
    background: #00BFFF;
    color: #ffffff;
}

.menu-btn {
    display: none;
    background: rgba(0, 0, 0, 0.04);
    border: none;
    font-size: 2.2rem;
    cursor: pointer;
    color: var(--eerie-black-1);
    padding: 8px;
    border-radius: 12px;
    width: 44px;
    height: 44px;
}

/* Mobile Drawer */
@media (max-width: 991px) {
    .navbar {
        position: fixed;
        top: 0;
        right: -100%;
        width: min(85%, 360px);
        height: 100vh;
        background: rgba(255, 253, 245, 0.96);
        backdrop-filter: blur(24px);
        z-index: 1001;
        transition: right 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        overflow-y: auto;
        padding: 24px 20px;
        display: flex;
        flex-direction: column;
    }
    
    .navbar.active { right: 0; }
    
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
        font-size: 2rem;
        cursor: pointer;
        padding: 10px;
        border-radius: 50%;
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
        border-radius: 14px;
    }
    
    .navbar-link:hover, .navbar-link.active {
        background: rgba(0, 191, 255, 0.08);
        color: #00BFFF;
        transform: translateX(3px);
    }
    
    .navbar-link::after { display: none; }
    
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
        text-align: center;
    }
    
    .mobile-portal-btn-analytics {
        background: #00BFFF;
        color: #ffffff;
    }
    
    .mobile-portal-btn-finance {
        background: transparent;
        color: #00BFFF;
        border: 1.5px solid #00BFFF;
    }
    
    .portal-buttons-header { display: none; }
    .menu-btn { display: flex; }
    .header .container { padding: 12px 20px; }
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

@media (min-width: 992px) {
    .navbar {
        position: static;
        right: auto;
        width: auto;
        height: auto;
        background: none;
        backdrop-filter: none;
        padding: 0;
        overflow: visible;
    }
    .navbar .wrapper, .nav-close-btn, .mobile-portal-buttons { display: none; }
    .navbar-list { flex-direction: row; gap: 36px; }
    .menu-btn { display: none; }
    .overlay { display: none; }
    .portal-buttons-header { display: flex; }
}

/*-----------------------------------*\
  #PRICING HERO
\*-----------------------------------*/
.pricing-hero {
    padding-top: 140px;
    padding-bottom: 60px;
    background: linear-gradient(135deg, #e8a84c, #c97e2a);
    position: relative;
    overflow: hidden;
    text-align: center;
}

body.dark-mode .pricing-hero {
    background: linear-gradient(135deg, #c48a3c, #a66824);
}

.bubble {
    position: absolute;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    pointer-events: none;
    animation: bubbleMove 8s ease-in-out infinite;
}

@keyframes bubbleMove {
    0% { transform: translateY(100vh) scale(0); opacity: 0; }
    20% { opacity: 0.4; }
    80% { opacity: 0.2; }
    100% { transform: translateY(-20vh) scale(1); opacity: 0; }
}

.bubble:nth-child(1) { width: 30px; height: 30px; left: 5%; animation-duration: 6s; }
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

.pricing-hero h1 {
    font-size: clamp(2rem, 6vw, 4rem);
    color: #ffffff;
    margin-bottom: 15px;
}

.pricing-hero h1 span {
    color: #2c2418;
}

.pricing-hero p {
    font-size: 1.6rem;
    color: rgba(255, 255, 255, 0.9);
    max-width: 700px;
    margin: 0 auto;
}

/*-----------------------------------*\
  #PRICING CARDS
\*-----------------------------------*/
.pricing-cards-section {
    padding: 70px 0;
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
    border-bottom: 2px solid rgba(230, 200, 140, 0.5);
}

.school-icon {
    width: 50px;
    height: 50px;
    background: rgba(0, 191, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: #00BFFF;
}

.school-title {
    font-size: 2rem;
    color: var(--eerie-black-1);
}

.school-type-tabs {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    padding-bottom: 10px;
}

.school-tab {
    padding: 10px 25px;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 1.4rem;
    background: rgba(255, 253, 248, 0.85);
    border: 1px solid rgba(230, 200, 140, 0.5);
    color: #5c4b34;
}

.school-tab.active {
    background: #00BFFF;
    color: white;
    border-color: #00BFFF;
}

body.dark-mode .school-tab {
    background: rgba(50, 45, 38, 0.85);
    color: #cfc3a8;
}

.pricing-card-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.pricing-card {
    border-radius: 24px;
    overflow: hidden;
    transition: all 0.35s ease;
    border: 1px solid rgba(230, 200, 140, 0.4);
    position: relative;
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
}

.pricing-card:hover {
    transform: translateY(-10px);
    background: rgba(255, 254, 252, 0.95);
    border-color: #e6b450;
}

.fee-package { border-top: 3px solid #00BFFF; }
.analytics-package { border-top: 3px solid #10B981; }
.complete-package { border-top: 3px solid #8B5CF6; }

.fee-package .main-price { color: #00BFFF; }
.analytics-package .main-price { color: #10B981; }
.complete-package .main-price { color: #8B5CF6; }

.fee-package .price-btn { background: #00BFFF; }
.analytics-package .price-btn { background: #10B981; }
.complete-package .price-btn { background: #8B5CF6; }

.fee-package .ribbon span { background: #00BFFF; }
.analytics-package .ribbon span { background: #10B981; }
.complete-package .ribbon span { background: #8B5CF6; }

.ribbon {
    width: 150px;
    height: 150px;
    position: absolute;
    top: -10px;
    left: -10px;
    overflow: hidden;
    z-index: 1;
}

.ribbon span {
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
}

.card-price {
    text-align: center;
    padding: 25px 20px 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
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
    color: #5c4b34;
}

.onboarding-fee {
    text-align: center;
    font-size: 1.2rem;
    color: #5c4b34;
    padding: 10px 20px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.package-name {
    text-align: center;
    padding: 15px 20px 0;
}

.package-name h3 {
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 5px;
    color: #2c2418;
}

.package-name p {
    font-size: 1.2rem;
    color: #5c4b34;
}

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

.price-btn {
    width: 80%;
    margin: 20px auto 25px;
    height: 45px;
    color: #fff;
    font-size: 1.5rem;
    font-weight: 600;
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

.note {
    text-align: center;
    margin-top: 50px;
    padding: 20px;
    background: rgba(255, 253, 248, 0.7);
    border-radius: 24px;
}

.note p {
    font-size: 1.3rem;
    color: #5c4b34;
}

body.dark-mode .pricing-card {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .pricing-card:hover {
    background: rgba(65, 58, 48, 0.92);
}

body.dark-mode .package-name h3 {
    color: #f7e5c2;
}

body.dark-mode .per-student-price,
body.dark-mode .onboarding-fee,
body.dark-mode .package-name p {
    color: #cfc3a8;
}

body.dark-mode .note {
    background: rgba(30, 28, 22, 0.7);
}

body.dark-mode .note p {
    color: #cfc3a8;
}

/*-----------------------------------*\
  #FOOTER
\*-----------------------------------*/
.footer {
    background-color: #2c2418;
    color: #cfc3a8;
    padding-block-start: 60px;
    margin-top: 40px;
    border-radius: 30px 30px 0 0;
}

.footer-top {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    padding-block-end: 40px;
}

.footer-list-title {
    color: #f7e5c2;
    font-size: 1.6rem;
    font-weight: 600;
    margin-block-end: 12px;
}

.footer-link {
    transition: var(--transition-1);
    display: block;
    padding-block: 4px;
    font-size: 1.3rem;
    color: #cfc3a8;
}

.footer-link:hover {
    color: #e9b35f;
}

.copyright {
    text-align: center;
    padding-block: 25px;
    border-block-start: 1px solid rgba(207, 195, 168, 0.2);
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
    background-color: #e9b35f;
    color: #2c2418;
    font-size: 1.6rem;
    padding: 12px;
    border-radius: 50%;
    z-index: 3;
    opacity: 0;
    pointer-events: none;
    transition: var(--transition-1);
    cursor: pointer;
}

.back-top-btn.active {
    opacity: 1;
    pointer-events: all;
}

.back-top-btn:hover {
    background-color: #d4943c;
    transform: translateY(-3px);
}

@media (max-width: 991px) {
    .pricing-card-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    .school-type-tabs {
        justify-content: center;
    }
    .footer-top {
        grid-template-columns: 1fr;
        gap: 25px;
    }
}

@media (max-width: 768px) {
    .pricing-hero {
        padding-top: 120px;
    }
    .school-header {
        justify-content: center;
    }
}

main {
    min-height: 400px;
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
        <?php for($i = 1; $i <= 12; $i++) echo '<div class="bubble"></div>'; ?>
        <div class="container">
            <div class="pricing-badge">
                <i class="fas fa-tag"></i> Customized pricing according to total student enrollment
            </div>
            <h1>Simple, flexible pricing <span>built for schools</span></h1>
            <p>Find a package that matches your school's size and operational needs. Each plan comes with smart digital tools to help manage academics, reports, communication, and administration more efficiently.</p>
        </div>
    </section>

    <!-- Pricing Cards Section -->
    <section class="pricing-cards-section">
        <div class="container">
            
            <!-- Primary School -->
            <div class="school-type reveal">
                <div class="school-header">
                    <div class="school-icon"><i class="fas fa-school"></i></div>
                    <h2 class="school-title">Primary School</h2>
                </div>
                
                <div class="school-type-tabs">
                    <button class="school-tab public active" data-school="primary" data-type="public">Public School</button>
                    <button class="school-tab private" data-school="primary" data-type="private">Private School</button>
                </div>
                
                <div class="pricing-card-grid public-grid" id="primary-public">
                    <div class="pricing-card fee-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card analytics-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card complete-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card-grid private-grid" id="primary-private" style="display: none;">
                    <div class="pricing-card fee-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card analytics-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card complete-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- Junior Secondary School -->
            <div class="school-type reveal">
                <div class="school-header">
                    <div class="school-icon"><i class="fas fa-chalkboard-user"></i></div>
                    <h2 class="school-title">Junior Secondary School</h2>
                </div>
                
                <div class="school-type-tabs">
                    <button class="school-tab public active" data-school="junior" data-type="public">Public School</button>
                    <button class="school-tab private" data-school="junior" data-type="private">Private School</button>
                </div>
                
                <div class="pricing-card-grid public-grid" id="junior-public">
                    <div class="pricing-card fee-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card analytics-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card complete-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card-grid private-grid" id="junior-private" style="display: none;">
                    <div class="pricing-card fee-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card analytics-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card complete-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- Senior Secondary School -->
            <div class="school-type reveal">
                <div class="school-header">
                    <div class="school-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h2 class="school-title">Senior Secondary School</h2>
                </div>
                
                <div class="school-type-tabs">
                    <button class="school-tab public active" data-school="senior" data-type="public">Public School</button>
                    <button class="school-tab private" data-school="senior" data-type="private">Private School</button>
                </div>
                
                <div class="pricing-card-grid public-grid" id="senior-public">
                    <div class="pricing-card fee-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card analytics-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card complete-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="pricing-card-grid private-grid" id="senior-private" style="display: none;">
                    <div class="pricing-card fee-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card analytics-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="pricing-card complete-package">
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
                        <a href="register.php" class="price-btn">Get Started <i class="fas fa-arrow-right"></i></a>
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
                <p>Modern school management system for Kenyan educational institutions.</p>
                <div><span>Email:</span> <a href="mailto:eduscoreke@gmail.com" class="footer-link">eduscoreke@gmail.com</a></div>
                <div><span>Phone:</span> <a href="tel:+254799115282" class="footer-link">+254 799 115 282</a></div>
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

<a href="#top" class="back-top-btn" aria-label="back to top" data-back-top-btn>
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
if (overlay) overlay.addEventListener("click", closeNavbar);

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
    entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); });
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
        
        const parentSection = this.closest('.school-type');
        parentSection.querySelectorAll('.school-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
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