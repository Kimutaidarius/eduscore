<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include config
require_once 'includes/config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Define base URL
$base_url = "https://eduscore.co.ke";

// SEO Meta Data
$page_title = "Parents Portal | EduScore - Stay Connected with Your Child's Education";
$page_description = "Access real-time student progress, exam results, fee balances, attendance records, and school announcements. Stay connected with your child's education journey.";
$page_keywords = "parents portal Kenya, student progress tracking, exam results online, school fees payment, attendance monitoring, parent engagement";
$page_url = $base_url . "/parents-portal.php";
$page_image = $base_url . "/images/parents-portal-og.jpg";
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
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="EduScore Kenya">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#00BFFF">
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
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
            --black_80: hsla(0, 0%, 0%, 0.8);
            --white_50: hsla(0, 0%, 100%, 0.5);
            --black_50: hsla(0, 0%, 0%, 0.5);
            --black_30: hsla(0, 0%, 0%, 0.3);
            --white: hsl(0, 0%, 100%);
            
            --gradient: linear-gradient(-90deg, hsl(151, 58%, 46%) 0%, hsl(170, 75%, 41%) 100%);
            
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
            --black_80: hsla(0, 0%, 0%, 0.9);
        }
        
        body.dark-mode .feature-card,
        body.dark-mode .stats-card {
            background-color: hsl(0, 0%, 20%);
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
        
        a,
        img,
        span,
        data,
        input,
        button,
        textarea,
        ion-icon { display: block; }
        
        a {
            color: inherit;
            text-decoration: none;
        }
        
        img { height: auto; }
        
        input,
        button,
        textarea {
            background: none;
            border: none;
            font: inherit;
        }
        
        input,
        textarea { width: 100%; }
        
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
        
        /*-----------------------------------*\
          #REUSED STYLE
        \*-----------------------------------*/
        .container { padding-inline: 15px; max-width: 1200px; margin: 0 auto; }
        
        .section { padding-block: var(--section-padding); }
        
        .btn {
            background-color: var(--kappel);
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: var(--fs-4);
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: var(--radius-5);
            transition: var(--transition-1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,191,255,0.2);
        }
        
        .section-subtitle {
            font-size: var(--fs-5);
            text-transform: uppercase;
            font-weight: var(--fw-500);
            letter-spacing: 1px;
            text-align: center;
            margin-block-end: 15px;
        }
        
        .section-title {
            --color: var(--radical-red);
            text-align: center;
        }
        
        .section-title .span {
            display: inline-block;
            color: var(--color);
        }
        
        /*-----------------------------------*\
          #HEADER
        \*-----------------------------------*/
        .header {
            position: sticky;
            top: 0;
            background-color: var(--white);
            padding-block: 12px;
            box-shadow: var(--shadow-1);
            z-index: 100;
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            flex-shrink: 0;
        }
        
        .logo img { 
            width: auto; 
            height: 40px;
            max-width: 120px;
            object-fit: contain;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
        }
        
        .theme-toggle {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: var(--eerie-black-1);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
        }
        
        .theme-toggle .fa-sun { display: none; }
        
        body.dark-mode .theme-toggle .fa-moon { display: none; }
        body.dark-mode .theme-toggle .fa-sun { display: block; }
        
        .menu-btn {
            background: none;
            border: none;
            font-size: 2.4rem;
            cursor: pointer;
            color: var(--eerie-black-1);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            flex-shrink: 0;
        }
        
        /* Mobile Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: -100%;
            background-color: var(--white);
            width: 85%;
            max-width: 320px;
            height: 100vh;
            z-index: 1001;
            transition: left 0.3s ease-out;
            overflow-y: auto;
            box-shadow: 2px 0 20px rgba(0,0,0,0.15);
        }
        
        .navbar.active { left: 0; }
        
        body.navbar-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
        
        .navbar .wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--platinum);
        }
        
        .nav-close-btn {
            font-size: 2rem;
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .navbar-list { padding: 15px 20px; }
        
        .navbar-item { margin-bottom: 10px; }
        
        .navbar-link {
            display: block;
            padding: 10px 0;
            font-weight: 500;
            transition: var(--transition-1);
        }
        
        .navbar-link:hover,
        .navbar-link.active { color: var(--kappel); }
        
        .overlay {
            position: fixed;
            inset: 0;
            background-color: var(--black_80);
            opacity: 0;
            visibility: hidden;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Desktop Navbar */
        @media (min-width: 992px) {
            .menu-btn { display: none; }
            
            .navbar {
                position: static;
                left: auto;
                width: auto;
                max-width: none;
                height: auto;
                background: none;
                transform: none;
                box-shadow: none;
            }
            
            body.navbar-open {
                overflow: auto;
                position: relative;
                width: auto;
            }
            
            .navbar .wrapper { display: none; }
            
            .navbar-list {
                display: flex;
                gap: 30px;
                padding: 0;
            }
            
            .navbar-item { margin-bottom: 0; }
            
            .navbar-link { padding: 0; }
            
            .overlay { display: none; }
        }
        
        /*-----------------------------------*\
          #PAGE SPECIFIC STYLES
        \*-----------------------------------*/
        .page-hero {
            background: linear-gradient(135deg, var(--kappel_15) 0%, var(--white) 100%);
            padding: 60px 0;
        }
        
        .page-hero-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }
        
        .page-hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
            color: var(--eerie-black-1);
        }
        
        .page-hero-title .highlight {
            color: var(--kappel);
            border-bottom: 4px solid var(--selective-yellow);
            display: inline-block;
        }
        
        .page-hero-desc {
            color: var(--gray-web);
            margin-bottom: 30px;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .page-hero-stats {
            display: flex;
            gap: 40px;
            margin-top: 40px;
        }
        
        .stat-item .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--kappel);
        }
        
        .stat-item .stat-label {
            color: var(--gray-web);
            font-size: 0.9rem;
        }
        
        .page-hero-image {
            width: 100%;
            border-radius: 20px;
            box-shadow: var(--shadow-3);
        }
        
        /* Features Grid */
        .features-section {
            background: var(--white);
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--eerie-black-1);
        }
        
        .yellow-line {
            width: 80px;
            height: 4px;
            background: var(--selective-yellow);
            margin: 15px auto;
            border-radius: 4px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid var(--platinum);
            transition: var(--transition-1);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2);
            border-color: var(--kappel);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--kappel_15);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--kappel);
            font-size: 1.8rem;
        }
        
        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 12px;
            color: var(--eerie-black-1);
        }
        
        .feature-card p {
            color: var(--gray-web);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .badge-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            background: var(--selective-yellow);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--eerie-black-1);
        }
        
        /* CTA Banner */
        .cta-banner {
            background: var(--kappel);
            padding: 60px 0;
            text-align: center;
        }
        
        .cta-banner h2 {
            color: white;
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .cta-banner p {
            color: rgba(255,255,255,0.8);
            margin-bottom: 30px;
        }
        
        /* Footer */
        .footer {
            background-color: var(--eerie-black-2);
            color: var(--gray-x-11);
            font-size: var(--fs-5);
            padding-block-start: 60px;
        }
        
        .footer-top {
            display: grid;
            gap: 30px;
            padding-block-end: 40px;
        }
        
        .footer-brand-text { margin-block: 20px; }
        
        .footer-brand .wrapper {
            display: flex;
            gap: 5px;
            margin-block: 10px;
        }
        
        .footer-link { transition: var(--transition-1); }
        
        .footer-link:is(:hover, :focus) { color: var(--kappel); }
        
        .footer-list-title {
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: var(--fs-3);
            font-weight: var(--fw-600);
            margin-block-end: 10px;
        }
        
        .footer-list .footer-link { padding-block: 5px; }
        
        .social-list {
            display: flex;
            gap: 25px;
            margin-top: 1.5rem;
        }
        
        .social-link {
            font-size: 2rem;
            transition: var(--transition-1);
        }
        
        .social-link:hover {
            color: var(--kappel);
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            text-align: center;
            padding-block: 30px;
            border-top: 1px solid var(--eerie-black-1);
        }
        
        .copyright-link { color: var(--kappel); display: inline-block; }
        
        /* Responsive */
        @media (min-width: 575px) {
            .footer-top { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (min-width: 768px) {
            .footer-top { grid-template-columns: repeat(4, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-hero-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .page-hero-title { font-size: 2.5rem; }
            .page-hero-stats { justify-content: center; }
            .features-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .logo img { height: 32px; }
            .header-actions { gap: 8px; }
            .page-hero-title { font-size: 2rem; }
        }
        
        /* Reveal Animation */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s ease;
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
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
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="navbar-list">
                <li><a href="index.php" class="navbar-link">Home</a></li>
                <li><a href="index.php#about" class="navbar-link">About</a></li>
                <li><a href="index.php#pricing" class="navbar-link">Pricing</a></li>
                <li><a href="blog.php" class="navbar-link">Blog</a></li>
                <li><a href="index.php#faq" class="navbar-link">FAQ</a></li>
                <li><a href="index.php#contact" class="navbar-link">Contact</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i><i class="fas fa-sun"></i>
            </button>
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="btn">Dashboard</a>
            <?php else: ?>
<a href="/parents-portal/login.php" class="btn btn-outline" style="background: transparent; border: 2px solid var(--kappel); color: var(--kappel);">Login</a>
<a href="/parents-portal/register.php" class="btn">Register</a>
            <?php endif; ?>
            <button class="menu-btn" aria-label="open menu" data-nav-toggler>
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="overlay" data-nav-toggler data-overlay></div>
    </div>
</header>

<main>
    <!-- Hero Section -->
    <section class="page-hero">
        <div class="container page-hero-grid">
            <div class="reveal">
                <h1 class="page-hero-title">Parents <span class="highlight">Portal</span></h1>
                <p class="page-hero-desc">Stay connected with your child's education journey. Access real-time student progress, exam results, fee balances, attendance records, and school announcements—all in one convenient platform.</p>
                <div>
                    <a href="/parents-portal/register.php" class="btn" style="padding: 12px 32px;">Get Started Free <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="page-hero-stats">
                    <div class="stat-item"><div class="stat-number">50K+</div><div class="stat-label">Parents</div></div>
                    <div class="stat-item"><div class="stat-number">98%</div><div class="stat-label">Satisfaction</div></div>
                    <div class="stat-item"><div class="stat-number">24/7</div><div class="stat-label">Access</div></div>
                </div>
            </div>
            <div class="reveal delay-1">
                <img src="/images/parents-portal.PNG" alt="Parents Portal Dashboard" class="page-hero-image" onerror="this.src='/images/school-bg.png'">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section section">
        <div class="container">
            <div class="section-header">
                <h2>What Parents Can Access</h2>
                <div class="yellow-line"></div>
                <p style="color: var(--gray-web);">Everything you need to monitor and support your child's education</p>
            </div>
            <div class="features-grid">
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Academic Progress</h3>
                    <p>View real-time student performance, exam results, subject-wise scores, and overall grade trends.</p>
                    <div class="badge-list"><span class="badge">Live Updates</span><span class="badge">Performance Trends</span></div>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-coins"></i></div>
                    <h3>Fee Management</h3>
                    <p>Check fee balances, view payment history, download receipts, and receive payment reminders.</p>
                    <div class="badge-list"><span class="badge">Balance Check</span><span class="badge">Payment History</span></div>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                    <h3>Attendance Tracking</h3>
                    <p>Monitor your child's attendance record, view absence reports, and receive absence notifications.</p>
                    <div class="badge-list"><span class="badge">Daily Attendance</span><span class="badge">Reports</span></div>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-file-alt"></i></div>
                    <h3>Report Cards</h3>
                    <p>Download and view digital report cards, termly results, and teacher comments online.</p>
                    <div class="badge-list"><span class="badge">Digital Reports</span><span class="badge">Teacher Comments</span></div>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-bell"></i></div>
                    <h3>School Announcements</h3>
                    <p>Receive instant notifications about school events, holidays, meetings, and important updates.</p>
                    <div class="badge-list"><span class="badge">Push Notifications</span><span class="badge">Email Alerts</span></div>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-comments"></i></div>
                    <h3>Teacher Communication</h3>
                    <p>Send messages to teachers, schedule parent-teacher conferences, and receive feedback.</p>
                    <div class="badge-list"><span class="badge">Direct Messaging</span><span class="badge">Conference Booking</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="features-section section" style="background: var(--isabelline);">
        <div class="container">
            <div class="section-header">
                <h2>How It Works</h2>
                <div class="yellow-line"></div>
                <p>Simple steps to get started with Parents Portal</p>
            </div>
            <div class="features-grid">
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-user-plus"></i></div>
                    <h3>1. Register Account</h3>
                    <p>Create your parent account using the registration code provided by your child's school.</p>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-child"></i></div>
                    <h3>2. Link Your Child</h3>
                    <p>Add your children using their admission numbers or student IDs provided by the school.</p>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-chart-simple"></i></div>
                    <h3>3. Monitor Progress</h3>
                    <p>Access real-time updates on academic performance, attendance, and fee status.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="features-section section">
        <div class="container">
            <div class="section-header">
                <h2>Why Choose Parents Portal</h2>
                <div class="yellow-line"></div>
                <p>Benefits for parents and guardians</p>
            </div>
            <div class="features-grid">
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-clock"></i></div>
                    <h3>Real-time Updates</h3>
                    <p>Get instant access to your child's academic progress as soon as results are released.</p>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                    <h3>Mobile Friendly</h3>
                    <p>Access the portal from any device—smartphone, tablet, or computer—anytime, anywhere.</p>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-lock"></i></div>
                    <h3>Secure & Private</h3>
                    <p>Your child's information is protected with industry-standard security and encryption.</p>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-bell"></i></div>
                    <h3>Instant Notifications</h3>
                    <p>Receive email and SMS alerts for important updates, fee deadlines, and events.</p>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-history"></i></div>
                    <h3>Historical Data</h3>
                    <p>Access past academic records, previous exam results, and historical performance data.</p>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-headset"></i></div>
                    <h3>24/7 Support</h3>
                    <p>Our support team is always available to assist you with any questions or concerns.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="cta-banner">
        <div class="container">
            <h2>Ready to stay connected with your child's education?</h2>
            <p>Join thousands of parents already using EduScore Parents Portal</p>
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
<a href="/parents-portal/register.php" class="btn" style="background: var(--selective-yellow); color: var(--eerie-black-1); padding: 12px 32px;">Register Now</a>
<a href="/parents-portal/login.php" class="btn" style="background: transparent; border: 2px solid white; color: white;">Login</a>
            </div>
        </div>
    </section>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="container footer-top">
        <div class="footer-brand">
            <a href="index.php"><img src="/images/logo.png" alt="EduScore logo" style="height: 40px;"></a>
            <p class="footer-brand-text">Modern school management system for Kenyan educational institutions.</p>
            <div class="wrapper"><span>Add:</span><address>Ngara - Nairobi, Kenya</address></div>
            <div class="wrapper"><span>Call:</span><a href="tel:+254799115282" class="footer-link">+254 799 115 282</a></div>
            <div class="wrapper"><span>Email:</span><a href="mailto:eduscoreke@gmail.com" class="footer-link">eduscoreke@gmail.com</a></div>
        </div>
        <ul class="footer-list">
            <li><p class="footer-list-title">Online Platform</p></li>
            <li><a href="index.php#features" class="footer-link">Features</a></li>
            <li><a href="index.php#pricing" class="footer-link">Pricing</a></li>
            <li><a href="index.php#faq" class="footer-link">FAQ</a></li>
        </ul>
        <ul class="footer-list">
            <li><p class="footer-list-title">Links</p></li>
            <li><a href="index.php#contact" class="footer-link">Contact Us</a></li>
            <li><a href="blog.php" class="footer-link">Blog</a></li>
            <li><a href="index.php#about" class="footer-link">About</a></li>
        </ul>
        <div class="footer-list">
            <p class="footer-list-title">Newsletter</p>
            <p>Enter your email to subscribe</p>
            <form style="display: flex; gap: 10px; margin-top: 1rem;">
                <input type="email" name="email" placeholder="Your email" style="padding: 10px; border-radius: 5px; border: none; flex: 1;">
                <button type="submit" class="btn" style="padding: 10px 20px;">Subscribe</button>
            </form>
            <div class="social-list">
                <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>Copyright <?php echo date('Y'); ?> All Rights Reserved by <a href="#" class="copyright-link">EduScore Kenya</a></p>
    </div>
</footer>

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

    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); });
    }, { threshold: 0.15 });
    reveals.forEach(el => revealObserver.observe(el));
</script>
</body>
</html>