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
$current_url = $base_url . $_SERVER['REQUEST_URI'];

// Enhanced SEO metadata with keyword-rich targeting
$page_title = "Exam Analysis & Report Cards | EduScore - Student Performance Analytics Kenya";
$page_description = "Comprehensive exam analysis, merit lists, report cards, and student performance tracking for Kenyan schools. CBC & 8-4-4 compatible. Trusted by 500+ schools. Free 14-day trial.";
$page_keywords = "exam analysis Kenya, report cards Kenya, student performance tracking, merit list generator, school analytics, CBC assessment tools, 8-4-4 exam analysis, grade reports, academic analytics, student ranking system";
$page_url = $current_url;
$page_image = $base_url . "/images/analytics-og.jpg";

// Structured data for rich snippets (WebApplication Schema)
$structured_data = [
    "@context" => "https://schema.org",
    "@type" => "WebApplication",
    "name" => "EduScore Exam Analysis",
    "applicationCategory" => "EducationApplication",
    "operatingSystem" => "Web",
    "description" => "Comprehensive exam analysis and student performance tracking for Kenyan schools. Generate merit lists, report cards, and detailed analytics.",
    "url" => $page_url,
    "image" => $page_image,
    "offers" => [
        "@type" => "Offer",
        "price" => "15.00",
        "priceCurrency" => "KES",
        "availability" => "https://schema.org/InStock"
    ],
    "browserRequirements" => "Requires JavaScript",
    "featureList" => "Exam Analysis, Merit Lists, Report Cards, Performance Tracking, Grade Analytics, Student Ranking"
];

// BreadcrumbList Schema for navigation
$breadcrumb_schema = [
    "@context" => "https://schema.org",
    "@type" => "BreadcrumbList",
    "itemListElement" => [
        [
            "@type" => "ListItem",
            "position" => 1,
            "name" => "Home",
            "item" => $base_url . "/"
        ],
        [
            "@type" => "ListItem",
            "position" => 2,
            "name" => "Exam Analysis",
            "item" => $page_url
        ]
    ]
];

// FAQ Schema specific to exam analysis
$faq_schema = [
    "@context" => "https://schema.org",
    "@type" => "FAQPage",
    "mainEntity" => [
        [
            "@type" => "Question",
            "name" => "How does EduScore exam analysis work for Kenyan schools?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "EduScore automates exam analysis by processing student scores to generate merit lists, grade distributions, subject performance reports, and individual student progress reports. It supports both CBC and 8-4-4 curricula."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "Can EduScore generate automated report cards?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Yes! EduScore can generate thousands of report cards in seconds with customizable templates, automatic grade calculations, teacher remarks, and parent-friendly formatting."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "Is EduScore exam analysis free to try?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Yes, we offer a 14-day free trial with full access to all exam analysis features including merit lists, report cards, performance charts, and detailed student analytics."
            ]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google Search Console Verification -->
    <meta name="google-site-verification" content="HZsZNr2Rfno72qnurFjgV4UEMnMM3H0qjWryXqIzxpI" />
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <!-- Primary SEO Meta Tags -->
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="en-ke" href="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="EduScore Kenya">
    <meta property="og:locale" content="en_KE">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Structured Data / Schema Markup -->
    <script type="application/ld+json">
    <?php echo json_encode($structured_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <script type="application/ld+json">
    <?php echo json_encode($breadcrumb_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <script type="application/ld+json">
    <?php echo json_encode($faq_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    
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
        body.dark-mode .stats-card,
        body.dark-mode .testimonial-card,
        body.dark-mode .how-it-works-step {
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
        
        .btn-yellow {
            background-color: var(--selective-yellow);
            color: var(--eerie-black-1);
        }
        
        .btn-yellow:hover {
            background-color: hsl(42, 94%, 48%);
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
        
        .section-text {
            text-align: center;
            font-size: var(--fs-5);
            margin-block: 15px 25px;
            color: var(--gray-web);
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
        /* Breadcrumb */
        .breadcrumb {
            padding: 15px 0;
            background: var(--isabelline);
            font-size: 1.4rem;
        }
        
        .breadcrumb .container {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--kappel);
            transition: var(--transition-1);
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb .separator {
            color: var(--gray-web);
        }
        
        .breadcrumb .current {
            color: var(--eerie-black-1);
            font-weight: 600;
        }
        
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
            font-family: var(--ff-league_spartan);
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
            flex-wrap: wrap;
        }
        
        .stat-item .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--kappel);
            font-family: var(--ff-league_spartan);
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
        
        /* CTA Buttons in Hero */
        .hero-cta-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        /* How It Works */
        .how-it-works {
            background: var(--white);
        }
        
        .how-it-works-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .how-it-works-step {
            text-align: center;
            padding: 30px 20px;
            background: var(--white);
            border-radius: var(--radius-10);
            border: 1px solid var(--platinum);
            transition: var(--transition-1);
            position: relative;
        }
        
        .how-it-works-step:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2);
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            background: var(--kappel);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto 20px;
            font-family: var(--ff-league_spartan);
        }
        
        .how-it-works-step h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: var(--eerie-black-1);
            font-family: var(--ff-league_spartan);
        }
        
        .how-it-works-step p {
            color: var(--gray-web);
            font-size: 1.4rem;
            line-height: 1.6;
        }
        
        /* Testimonials */
        .testimonials {
            background: var(--isabelline);
        }
        
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .testimonial-card {
            background: var(--white);
            padding: 30px;
            border-radius: var(--radius-10);
            border: 1px solid var(--platinum);
            transition: var(--transition-1);
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2);
        }
        
        .testimonial-stars {
            color: var(--selective-yellow);
            margin-bottom: 15px;
            font-size: 1.4rem;
        }
        
        .testimonial-text {
            color: var(--eerie-black-1);
            font-style: italic;
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 1.4rem;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .testimonial-avatar {
            width: 45px;
            height: 45px;
            background: var(--kappel_15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--kappel);
            font-size: 1.6rem;
        }
        
        .testimonial-author-info h4 {
            color: var(--eerie-black-1);
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .testimonial-author-info p {
            color: var(--gray-web);
            font-size: 1.2rem;
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
            font-family: var(--ff-league_spartan);
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
            font-family: var(--ff-league_spartan);
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
            font-family: var(--ff-league_spartan);
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
            .hero-cta-group { justify-content: center; }
            .how-it-works-grid { grid-template-columns: 1fr; }
            .testimonials-grid { grid-template-columns: 1fr; }
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
        .delay-1 { transition-delay: 0.2s; }
        .delay-2 { transition-delay: 0.4s; }
        .delay-3 { transition-delay: 0.6s; }
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
                <li class="navbar-item"><a href="index.php" class="navbar-link">Home</a></li>
                <li class="navbar-item"><a href="index.php#about" class="navbar-link">About</a></li>
                <li class="navbar-item"><a href="analytics.php" class="navbar-link active">Exam Analysis</a></li>
                <li class="navbar-item"><a href="index.php#pricing" class="navbar-link">Pricing</a></li>
                <li class="navbar-item"><a href="blog.php" class="navbar-link">Blog</a></li>
                <li class="navbar-item"><a href="index.php#faq" class="navbar-link">FAQ</a></li>
                <li class="navbar-item"><a href="index.php#contact" class="navbar-link">Contact</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                <i class="fas fa-moon"></i><i class="fas fa-sun"></i>
            </button>
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="btn">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn" style="background: transparent; border: 2px solid var(--kappel); color: var(--kappel);">Login</a>
                <a href="register.php" class="btn">Register</a>
            <?php endif; ?>
            <button class="menu-btn" aria-label="open menu" data-nav-toggler>
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="overlay" data-nav-toggler data-overlay></div>
    </div>
</header>

<main>
    <!-- Breadcrumb for SEO -->
    <div class="breadcrumb">
        <div class="container">
            <a href="index.php">Home</a>
            <span class="separator">›</span>
            <span class="current">Exam Analysis</span>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="page-hero">
        <div class="container page-hero-grid">
            <div class="reveal">
                <h1 class="page-hero-title">Exam <span class="highlight">Analysis</span> Made Simple for Kenyan Schools</h1>
                <p class="page-hero-desc">Track student performance, generate detailed reports, create merit lists, and gain actionable insights with EduScore's comprehensive exam analysis suite. Trusted by 500+ schools across Kenya for both CBC and 8-4-4 curricula.</p>
                <div class="hero-cta-group">
                    <a href="register.php" class="btn" style="padding: 12px 32px;">Start Free Trial <i class="fas fa-arrow-right"></i></a>
                    <a href="login.php" class="btn btn-yellow" style="padding: 12px 32px;">Login to Dashboard</a>
                </div>
                <div class="page-hero-stats">
                    <div class="stat-item"><div class="stat-number">500+</div><div class="stat-label">Schools Using EduScore</div></div>
                    <div class="stat-item"><div class="stat-number">98%</div><div class="stat-label">Report Accuracy</div></div>
                    <div class="stat-item"><div class="stat-number">1M+</div><div class="stat-label">Exams Processed</div></div>
                </div>
            </div>
            <div class="reveal delay-1">
                <img src="/images/analytics.PNG" alt="Exam Analysis Dashboard for Kenyan Schools" class="page-hero-image" onerror="this.src='/images/school-bg.png'">
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="section how-it-works">
        <div class="container">
            <div class="section-header">
                <p class="section-subtitle">How It Works</p>
                <h2>Get Started in 3 Simple Steps</h2>
                <div class="yellow-line"></div>
            </div>
            <div class="how-it-works-grid">
                <div class="how-it-works-step reveal">
                    <div class="step-number">1</div>
                    <h3>Upload Exam Scores</h3>
                    <p>Easily input student scores through our intuitive interface or bulk import from Excel spreadsheets.</p>
                </div>
                <div class="how-it-works-step reveal delay-1">
                    <div class="step-number">2</div>
                    <h3>Analyze Performance</h3>
                    <p>Our system automatically calculates grades, ranks students, and generates comprehensive analytics.</p>
                </div>
                <div class="how-it-works-step reveal delay-2">
                    <div class="step-number">3</div>
                    <h3>Generate Reports</h3>
                    <p>Download professional report cards, merit lists, and detailed performance reports in seconds.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section section">
        <div class="container">
            <div class="section-header">
                <p class="section-subtitle">Exam Analysis Features</p>
                <h2>Everything You Need for Student Assessment</h2>
                <div class="yellow-line"></div>
                <p style="color: var(--gray-web); margin-top: 15px;">Powerful tools designed for Kenyan schools' examination needs</p>
            </div>
            <div class="features-grid">
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Individual Student Reports</h3>
                    <p>Personalized progress reports with subject-wise breakdowns, historical comparisons, and growth tracking — all in one click.</p>
                    <div class="badge-list"><span class="badge">Subject mastery</span><span class="badge">Term comparison</span><span class="badge">Growth tracking</span></div>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-trophy"></i></div>
                    <h3>Merit List & Student Ranking</h3>
                    <p>Auto-generate merit lists, class ranks, grade summaries, and performance percentiles. Celebrate academic excellence transparently.</p>
                    <div class="badge-list"><span class="badge">Top performers</span><span class="badge">Percentile rank</span><span class="badge">Auto-sorting</span></div>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-chart-pie"></i></div>
                    <h3>Class & Subject Analytics</h3>
                    <p>Identify weak topics, track question-level difficulty, and compare class averages. Actionable insights for teachers and administrators.</p>
                    <div class="badge-list"><span class="badge">Gap analysis</span><span class="badge">Performance trends</span><span class="badge">Heat maps</span></div>
                </div>
                <div class="feature-card reveal">
                    <div class="feature-icon"><i class="fas fa-brain"></i></div>
                    <h3>AI Grade Predictor</h3>
                    <p>Predict final grades based on continuous assessment, mock exams, and historical data — data-driven forecasting for better outcomes.</p>
                    <div class="badge-list"><span class="badge">Smart insights</span><span class="badge">95% accuracy</span><span class="badge">Early intervention</span></div>
                </div>
                <div class="feature-card reveal delay-1">
                    <div class="feature-icon"><i class="fas fa-file-alt"></i></div>
                    <h3>Batch Report Cards</h3>
                    <p>Generate thousands of report cards in seconds with custom templates, automatic grade calculations, and teacher remarks.</p>
                    <div class="badge-list"><span class="badge">Bulk PDF</span><span class="badge">Email to parents</span><span class="badge">Custom templates</span></div>
                </div>
                <div class="feature-card reveal delay-2">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Performance Heatmaps</h3>
                    <p>Visualize student performance across subjects, identify outliers, and implement early intervention strategies for at-risk students.</p>
                    <div class="badge-list"><span class="badge">Risk zones</span><span class="badge">Improvement tracking</span><span class="badge">Visual analytics</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section testimonials">
        <div class="container">
            <div class="section-header">
                <p class="section-subtitle">What Schools Say</p>
                <h2>Trusted by Educators Across Kenya</h2>
                <div class="yellow-line"></div>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card reveal">
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"EduScore exam analysis has transformed how we process results. What used to take weeks now takes minutes. The merit lists are always accurate and the report cards look professional."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">M</div>
                        <div class="testimonial-author-info">
                            <h4>Mrs. Muthoni</h4>
                            <p>Principal, Nairobi Primary School</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card reveal delay-1">
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"The grade analytics feature helps us identify struggling students early. Our KCSE performance has improved significantly since we started using EduScore for exam analysis."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">O</div>
                        <div class="testimonial-author-info">
                            <h4>Mr. Ochieng</h4>
                            <p>Deputy Principal, Kisumu High School</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card reveal delay-2">
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Batch report card generation saves us countless hours every term. Parents love the detailed feedback, and our teachers can focus more on teaching than paperwork."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">W</div>
                        <div class="testimonial-author-info">
                            <h4>Madam Wanjiku</h4>
                            <p>Head Teacher, Mombasa Academy</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="cta-banner">
        <div class="container">
            <h2>Ready to Transform Exam Analysis at Your School?</h2>
            <p>Join 500+ Kenyan schools already using EduScore for faster, more accurate results processing</p>
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                <a href="register.php" class="btn btn-yellow" style="padding: 12px 32px;">Start Free Trial</a>
                <a href="login.php" class="btn" style="background: transparent; border: 2px solid white; color: white;">Login</a>
            </div>
        </div>
    </section>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="container footer-top">
        <div class="footer-brand">
            <a href="index.php"><img src="/images/logo.png" alt="EduScore logo" style="height: 40px;"></a>
            <p class="footer-brand-text">Modern school management system for Kenyan educational institutions. Exam analysis, fee management, and more.</p>
            <div class="wrapper"><span>📍 Add:</span><address>Ngara - Nairobi, Kenya</address></div>
            <div class="wrapper"><span>📞 Call:</span><a href="tel:+254799115282" class="footer-link">+254 799 115 282</a></div>
            <div class="wrapper"><span>✉️ Email:</span><a href="mailto:eduscoreke@gmail.com" class="footer-link">eduscoreke@gmail.com</a></div>
        </div>
        <ul class="footer-list">
            <li><p class="footer-list-title">Quick Links</p></li>
            <li><a href="index.php" class="footer-link">Home</a></li>
            <li><a href="index.php#features" class="footer-link">Features</a></li>
            <li><a href="index.php#pricing" class="footer-link">Pricing</a></li>
            <li><a href="analytics.php" class="footer-link">Exam Analysis</a></li>
        </ul>
        <ul class="footer-list">
            <li><p class="footer-list-title">Resources</p></li>
            <li><a href="blog.php" class="footer-link">Blog</a></li>
            <li><a href="index.php#faq" class="footer-link">FAQ</a></li>
            <li><a href="index.php#contact" class="footer-link">Contact Us</a></li>
            <li><a href="index.php#about" class="footer-link">About Us</a></li>
        </ul>
        <div class="footer-list">
            <p class="footer-list-title">Connect With Us</p>
            <p>Follow us on social media for updates</p>
            <div class="social-list">
                <a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                <a href="#" class="social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>Copyright <?php echo date('Y'); ?> All Rights Reserved by <a href="index.php" class="copyright-link">EduScore Kenya</a></p>
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