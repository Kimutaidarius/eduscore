<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Include config
require_once 'includes/config.php';

// Define base URL and canonical URL
$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/about";

// Enhanced SEO metadata
$page_title = "About Us | EduScore - Kenya's #1 School Management System";
$page_description = "Learn about EduScore, Kenya's most trusted school management system. Discover our mission to transform education with innovative CBE tools, analytics, and parent engagement solutions.";
$page_keywords = "about EduScore, school management system Kenya, education technology, CBE tools, student performance tracking, school ERP Kenya";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Organization schema
$org_schema = [
    "@context" => "https://schema.org",
    "@type" => "Organization",
    "name" => "EduScore Kenya",
    "url" => $base_url,
    "logo" => $base_url . "/images/logo.png",
    "sameAs" => [
        "https://facebook.com/eduscorekenya",
        "https://twitter.com/eduscorekenya",
        "https://linkedin.com/company/eduscore-kenya"
    ],
    "contactPoint" => [
        "@type" => "ContactPoint",
        "telephone" => "+254-799-115-282",
        "contactType" => "sales",
        "areaServed" => "KE",
        "availableLanguage" => ["English", "Swahili"]
    ],
    "address" => [
        "@type" => "PostalAddress",
        "addressCountry" => "KE",
        "addressRegion" => "Nairobi"
    ]
];

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// Handle contact form submission
$contact_message = '';
$contact_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    $errors = [];
    
    if (empty($name)) $errors[] = 'Please enter your name';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address';
    if (empty($subject)) $errors[] = 'Please enter a subject';
    if (empty($message)) $errors[] = 'Please enter your message';
    
    if (empty($errors)) {
        // Here you would typically send an email or save to database
        $contact_message = 'Thank you for contacting us! We will get back to you shortly.';
    } else {
        $contact_error = implode('<br>', $errors);
    }
}
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
    
    <script type="application/ld+json">
    <?php echo json_encode($org_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    
    <style>
/*-----------------------------------*\
  #UNIFORM SYSTEM STYLES (MATCHES INDEX.PHP)
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

body, button, input, textarea, select, p, li, a, span, div:not(.special-font) {
    font-family: "Merriweather", serif;
}

h1, h2, h3, h4, .h1, .h2, .h3, .section-title, .hero-stat-number, .portal-card-title {
    font-family: 'League Spartan', "Merriweather", serif;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    text-transform: capitalize;
}

.background-wrapper {
    background: linear-gradient(170deg, #fef9c6 35%, #fffef8 100%);
    width: 100%;
    min-height: 100vh;
}

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

/* Dark Mode Overrides */
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

body.dark-mode .background-wrapper {
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
  #HEADER - GLASSMORPHISM
\*-----------------------------------*/
.header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: rgba(255, 253, 245, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.2, 0, 0, 1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.5);
}

.header.active {
    background: rgba(255, 253, 245, 0.92);
    backdrop-filter: blur(20px);
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.06);
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
        background: rgba(255, 253, 245, 0.96);
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
        background: rgba(25, 30, 35, 0.96);
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
        text-decoration: none;
        transition: all 0.2s ease;
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
    .header.active .container { padding: 8px 20px; }
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
    
    .navbar .wrapper, .nav-close-btn, .mobile-portal-buttons { display: none; }
    .navbar-list { flex-direction: row; gap: 36px; }
    .navbar-link { padding: 8px 0; font-size: 1.55rem; }
    .menu-btn { display: none; }
    .overlay { display: none; }
    .portal-buttons-header { display: flex; }
}

@media (max-width: 480px) {
    .header .container { padding: 10px 16px; }
    .logo img { height: 34px; }
    .menu-btn { width: 40px; height: 40px; font-size: 2rem; }
    .theme-toggle { width: 38px; height: 38px; font-size: 1.6rem; }
}

/*-----------------------------------*\
  #ABOUT HERO SECTION
\*-----------------------------------*/
.about-hero {
    position: relative;
    padding-top: 140px;
    padding-bottom: 80px;
    overflow: hidden;
}

.about-hero-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.about-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 253, 245, 0.7);
    backdrop-filter: blur(4px);
    color: #b86f2c;
    padding: 8px 20px;
    border-radius: 60px;
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 24px;
    border: 2px solid #e6b050;
}

.about-hero-content h1 {
    font-size: clamp(3rem, 5vw, 4.5rem);
    line-height: 1.2;
    color: #2c2418;
    margin-bottom: 15px;
}

.about-hero-content h1 span {
    color: #00BFFF;
}

.about-hero-tagline {
    font-size: 1.8rem;
    color: #5b4a33;
    margin-bottom: 25px;
    font-weight: 500;
}

.about-hero-text {
    font-size: 1.6rem;
    line-height: 1.7;
    margin-bottom: 30px;
    color: #5c4b34;
}

.about-hero-features {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 30px 0;
}

.hero-feature {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(4px);
    padding: 8px 18px;
    border-radius: 50px;
    border: 1px solid rgba(230, 200, 140, 0.4);
    transition: all 0.3s ease;
}

.hero-feature:hover {
    transform: translateY(-3px);
    border-color: #00BFFF;
}

.hero-feature i {
    color: #00BFFF;
    font-size: 1.4rem;
}

.hero-feature span {
    color: #2c2418;
    font-size: 1.3rem;
    font-weight: 500;
}

.about-hero-image img {
    width: 100%;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

body.dark-mode .about-hero-badge {
    background: rgba(50, 45, 38, 0.85);
    border-color: #c48a3c;
    color: #f5bc70;
}

body.dark-mode .about-hero-content h1 {
    color: #f9f2df;
}

body.dark-mode .about-hero-tagline {
    color: #e2cfae;
}

body.dark-mode .about-hero-text {
    color: #cfc3a8;
}

body.dark-mode .hero-feature {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .hero-feature span {
    color: #f7e5c2;
}

/*-----------------------------------*\
  #FEATURES SECTION
\*-----------------------------------*/
.features-section {
    padding: 70px 0;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.feature-card {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    padding: 35px 25px;
    border-radius: 24px;
    text-align: center;
    transition: all 0.35s ease;
    border: 1px solid rgba(230, 200, 140, 0.4);
}

.feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.15);
    background: rgba(255, 254, 252, 0.95);
    border-color: #e6b450;
}

.feature-icon {
    width: 70px;
    height: 70px;
    background: #fef0d4;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.2rem;
    color: #b86f2c;
    transition: all 0.3s ease;
}

.feature-card:hover .feature-icon {
    background: #fae6bc;
    color: #9b5e2c;
}

.feature-card h3 {
    font-size: 1.8rem;
    margin-bottom: 12px;
    color: #2c2418;
}

.feature-card p {
    font-size: 1.4rem;
    line-height: 1.6;
    color: #5c4b34;
}

body.dark-mode .feature-card {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .feature-card:hover {
    background: rgba(65, 58, 48, 0.92);
}

body.dark-mode .feature-card h3 {
    color: #f7e5c2;
}

body.dark-mode .feature-card p {
    color: #cfc3a8;
}

body.dark-mode .feature-icon {
    background: #6b5538;
    color: #f3cd81;
}

/*-----------------------------------*\
  #STATS SECTION
\*-----------------------------------*/
.stats-section {
    background: rgba(255, 253, 245, 0.7);
    backdrop-filter: blur(8px);
    padding: 60px 0;
    border-radius: 40px;
    margin: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    text-align: center;
}

.stat-item h2 {
    font-size: clamp(2.5rem, 5vw, 3.5rem);
    font-weight: 800;
    color: #c1792c;
    margin-bottom: 8px;
}

.stat-item p {
    font-size: 1.3rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    color: #5b4a33;
}

body.dark-mode .stats-section {
    background: rgba(30, 28, 22, 0.7);
}

body.dark-mode .stat-item h2 {
    color: #f3bc6c;
}

body.dark-mode .stat-item p {
    color: #e2cfae;
}

/*-----------------------------------*\
  #MISSION VISION SECTION
\*-----------------------------------*/
.mission-vision-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin: 60px 0;
}

.mission-card, .vision-card {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    padding: 40px;
    border-radius: 24px;
    text-align: center;
    border: 1px solid rgba(230, 200, 140, 0.4);
    transition: all 0.35s ease;
}

.mission-card:hover, .vision-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 254, 252, 0.95);
    border-color: #e6b450;
}

.mission-card h3, .vision-card h3 {
    font-size: 2rem;
    color: #c1792c;
    margin-bottom: 15px;
}

.mission-card p, .vision-card p {
    font-size: 1.5rem;
    line-height: 1.7;
    color: #5c4b34;
}

body.dark-mode .mission-card, body.dark-mode .vision-card {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .mission-card h3, body.dark-mode .vision-card h3 {
    color: #f3bc6c;
}

body.dark-mode .mission-card p, body.dark-mode .vision-card p {
    color: #cfc3a8;
}

/*-----------------------------------*\
  #CONTACT SECTION
\*-----------------------------------*/
.contact-section {
    padding: 70px 0;
}

.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 50px;
    margin-top: 40px;
}

.contact-info {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.contact-card {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    padding: 28px;
    border-radius: 20px;
    text-align: center;
    border: 1px solid rgba(230, 200, 140, 0.4);
    transition: all 0.35s ease;
}

.contact-card:hover {
    transform: translateY(-4px);
    background: rgba(255, 254, 252, 0.95);
    border-color: #e6b450;
}

.contact-card-icon {
    width: 50px;
    height: 50px;
    background: #fef0d4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: #b86f2c;
    font-size: 1.8rem;
}

.contact-card h3 {
    font-size: 1.6rem;
    margin-bottom: 8px;
    color: #2c2418;
}

.contact-card p, .contact-card a {
    color: #5c4b34;
    font-size: 1.4rem;
    text-decoration: none;
}

.contact-card a:hover {
    color: #00BFFF;
}

.contact-form-container {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    padding: 35px;
    border-radius: 24px;
    border: 1px solid rgba(230, 200, 140, 0.4);
}

.contact-form-title {
    font-size: 1.8rem;
    color: #2c2418;
    margin-bottom: 8px;
}

.contact-form-subtitle {
    color: #5c4b34;
    font-size: 1.4rem;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #2c2418;
    font-size: 1.4rem;
}

.contact-form-input,
.contact-form-textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid rgba(230, 200, 140, 0.5);
    border-radius: 12px;
    font-size: 1.4rem;
    background: rgba(255, 255, 255, 0.5);
    color: #2c2418;
    transition: all 0.3s ease;
}

.contact-form-input:focus,
.contact-form-textarea:focus {
    outline: none;
    border-color: #00BFFF;
    box-shadow: 0 0 0 3px rgba(0, 191, 255, 0.1);
}

.contact-form-textarea {
    min-height: 100px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.submit-btn {
    background: #e9b35f;
    color: #2a241c;
    padding: 12px 28px;
    border-radius: 60px;
    font-weight: 700;
    font-size: 1.45rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.25s ease;
    border: none;
}

.submit-btn:hover {
    background: #d4943c;
    transform: translateX(5px);
}

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 1.4rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

body.dark-mode .contact-card,
body.dark-mode .contact-form-container {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .contact-card h3,
body.dark-mode .contact-form-title,
body.dark-mode .form-group label {
    color: #f7e5c2;
}

body.dark-mode .contact-card p,
body.dark-mode .contact-card a,
body.dark-mode .contact-form-subtitle {
    color: #cfc3a8;
}

body.dark-mode .contact-form-input,
body.dark-mode .contact-form-textarea {
    background: rgba(30, 28, 22, 0.8);
    color: #f7e5c2;
    border-color: rgba(210, 170, 90, 0.3);
}

/*-----------------------------------*\
  #DEVELOPER SECTION
\*-----------------------------------*/
.developer-section {
    background: linear-gradient(135deg, #e8a84c, #c97e2a);
    padding: 60px 0;
    border-radius: 30px;
    margin: 20px 0;
    text-align: center;
}

.developer-content h2 {
    font-size: 2rem;
    color: #ffffff;
    margin-bottom: 15px;
}

.developer-content h2 span {
    color: #2c2418;
}

.developer-content p {
    font-size: 1.4rem;
    margin-bottom: 25px;
    color: rgba(255, 255, 255, 0.95);
}

.developer-contact {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.dev-contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(0, 0, 0, 0.15);
    padding: 10px 20px;
    border-radius: 50px;
    backdrop-filter: blur(4px);
}

.dev-contact-item i {
    font-size: 1.4rem;
    color: #2c2418;
}

.dev-contact-item a, .dev-contact-item span {
    color: white;
    text-decoration: none;
    font-size: 1.4rem;
}

.dev-contact-item a:hover {
    color: #2c2418;
}

body.dark-mode .developer-section {
    background: linear-gradient(135deg, #c48a3c, #a66824);
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
    font-family: var(--ff-league_spartan);
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
    text-decoration: none;
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

/*-----------------------------------*\
  #REVEAL & BACK TO TOP
\*-----------------------------------*/
.reveal {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.7s ease, transform 0.7s ease;
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

/* Responsive */
@media (max-width: 991px) {
    .about-hero-grid,
    .contact-grid,
    .mission-vision-grid {
        grid-template-columns: 1fr;
        gap: 40px;
    }
    
    .about-hero {
        padding-top: 120px;
        padding-bottom: 60px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .footer-top {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}

@media (max-width: 768px) {
    .about-hero-features {
        justify-content: center;
    }
    
    .hero-feature {
        font-size: 1.2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

main {
    min-height: 400px;
}
    </style>
</head>
<body>
<div class="background-wrapper">
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
                    <li class="navbar-item"><a href="about.php" class="navbar-link active" data-nav-link>About</a></li>
                    <li class="navbar-item"><a href="pricing.php" class="navbar-link" data-nav-link>Pricing</a></li>
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
        <!-- About Hero Section -->
        <section class="about-hero">
            <div class="container">
                <div class="about-hero-grid">
                    <div class="about-hero-content reveal">
                        <div class="about-hero-badge">
                            <i class="fas fa-graduation-cap"></i> About EduScore
                        </div>
                        <h1>Empowering Schools.<br><span>Elevating Education.</span></h1>
                        <p class="about-hero-tagline">Smart Dashboard | Real-time Insights</p>
                        <p class="about-hero-text">
                            EduScore is a comprehensive school management system designed to simplify academic, administrative, and communication processes for modern schools across Kenya.
                        </p>
                        <div class="about-hero-features">
                            <div class="hero-feature">
                                <i class="fas fa-chart-line"></i>
                                <span>Smart Dashboard</span>
                            </div>
                            <div class="hero-feature">
                                <i class="fas fa-boxes"></i>
                                <span>All-in-One Management</span>
                            </div>
                            <div class="hero-feature">
                                <i class="fas fa-shield-alt"></i>
                                <span>Secure & Reliable</span>
                            </div>
                            <div class="hero-feature">
                                <i class="fas fa-rocket"></i>
                                <span>Built for Efficiency</span>
                            </div>
                        </div>
                        <a href="register.php" class="btn">Get Started <ion-icon name="arrow-forward-outline"></ion-icon></a>
                    </div>
                    <div class="about-hero-image reveal">
                        <img src="/images/about.png" alt="EduScore Dashboard Preview">
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section">
            <div class="container">
                <p class="section-subtitle">Why Choose Us</p>
                <h2 class="section-title">Powerful Features for <span class="span">Modern Schools</span></h2>
                
                <div class="features-grid">
                    <div class="feature-card reveal">
                        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                        <h3>Automated Student Performance Tracking</h3>
                        <p>Track student progress automatically with real-time analytics and comprehensive reports.</p>
                    </div>
                    
                    <div class="feature-card reveal">
                        <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
                        <h3>Performance Analytics</h3>
                        <p>Advanced analytics for both students and teachers. Monitor metrics and make data-driven decisions.</p>
                    </div>
                    
                    <div class="feature-card reveal">
                        <div class="feature-icon"><i class="fas fa-comments"></i></div>
                        <h3>Parent-Teacher Communication</h3>
                        <p>Seamless communication between parents and teachers through instant messaging and updates.</p>
                    </div>
                    
                    <div class="feature-card reveal">
                        <div class="feature-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <h3>Secure Cloud-Based Storage</h3>
                        <p>All data securely stored in the cloud with enterprise-grade encryption.</p>
                    </div>
                    
                    <div class="feature-card reveal">
                        <div class="feature-icon"><i class="fas fa-plug"></i></div>
                        <h3>Easy Integration</h3>
                        <p>Seamlessly integrate with payment gateways, SMS platforms, and other educational tools.</p>
                    </div>
                    
                    <div class="feature-card reveal">
                        <div class="feature-icon"><i class="fas fa-headset"></i></div>
                        <h3>24/7 Support & Training</h3>
                        <p>Round-the-clock technical support and comprehensive training resources.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item reveal">
                        <h2>500+</h2>
                        <p>Schools Trust Us</p>
                    </div>
                    <div class="stat-item reveal">
                        <h2>100k+</h2>
                        <p>Active Students</p>
                    </div>
                    <div class="stat-item reveal">
                        <h2>50k+</h2>
                        <p>Happy Parents</p>
                    </div>
                    <div class="stat-item reveal">
                        <h2>24/7</h2>
                        <p>Support Available</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission & Vision Section -->
        <section class="mission-vision-section">
            <div class="container">
                <div class="mission-vision-grid">
                    <div class="mission-card reveal">
                        <h3>Our Mission</h3>
                        <p>To empower educational institutions in Kenya with innovative, affordable, and easy-to-use technology solutions that enhance learning outcomes, streamline administration, and foster meaningful parent engagement.</p>
                    </div>
                    <div class="vision-card reveal">
                        <h3>Our Vision</h3>
                        <p>To be Kenya's most trusted education technology partner, transforming every school into a smart, efficient, and future-ready learning environment.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="contact-section">
            <div class="container">
                <p class="section-subtitle">Get In Touch</p>
                <h2 class="section-title">Contact <span class="span">Us</span></h2>
                
                <div class="contact-grid">
                    <div class="contact-info">
                        <div class="contact-card reveal">
                            <div class="contact-card-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <h3>Visit Us</h3>
                            <p>Ngara - Nairobi, Kenya</p>
                        </div>
                        
                        <div class="contact-card reveal">
                            <div class="contact-card-icon"><i class="fas fa-phone-alt"></i></div>
                            <h3>Call Us</h3>
                            <p><a href="tel:+254799115282">+254 799 115 282</a></p>
                        </div>
                        
                        <div class="contact-card reveal">
                            <div class="contact-card-icon"><i class="fas fa-envelope"></i></div>
                            <h3>Email Us</h3>
                            <p><a href="mailto:eduscoreke@gmail.com">eduscoreke@gmail.com</a></p>
                        </div>
                        
                        <div class="contact-card reveal">
                            <div class="contact-card-icon"><i class="fas fa-clock"></i></div>
                            <h3>Working Hours</h3>
                            <p>Monday - Friday: 8:00 AM - 6:00 PM<br>Saturday: 9:00 AM - 1:00 PM</p>
                        </div>
                    </div>
                    
                    <div class="contact-form-container reveal">
                        <h3 class="contact-form-title">Send Us a Message</h3>
                        <p class="contact-form-subtitle">Have questions? We'd love to hear from you!</p>
                        
                        <?php if ($contact_message): ?>
                            <div class="alert alert-success"><?php echo $contact_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($contact_error): ?>
                            <div class="alert alert-error"><?php echo $contact_error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Your Name *</label>
                                    <input type="text" id="name" name="name" class="contact-form-input" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" class="contact-form-input" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" class="contact-form-input">
                                </div>
                                <div class="form-group">
                                    <label for="subject">Subject *</label>
                                    <input type="text" id="subject" name="subject" class="contact-form-input" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="message">Message *</label>
                                <textarea id="message" name="message" class="contact-form-textarea" required></textarea>
                            </div>
                            <button type="submit" name="contact_submit" class="submit-btn">
                                Send Message <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Developer Section -->
        <section class="developer-section">
            <div class="container">
                <div class="developer-content reveal">
                    <h2>Developed By <span>KYM Technologies LTD</span></h2>
                    <p>Innovative education technology solutions tailored for Kenyan schools.</p>
                    <div class="developer-contact">
                        <div class="dev-contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <a href="tel:+254799115282">0799 115 282</a>
                        </div>
                        <div class="dev-contact-item">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:kymtechnologiesltd@gmail.com">kymtechnologiesltd@gmail.com</a>
                        </div>
                        <div class="dev-contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Nairobi, Kenya</span>
                        </div>
                    </div>
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

    <!-- Back to Top Button -->
    <a href="#top" class="back-top-btn" aria-label="back to top" data-back-top-btn>
        <ion-icon name="chevron-up" aria-hidden="true"></ion-icon>
    </a>
</div>

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
const navLinks = document.querySelectorAll("[data-nav-link]");
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
navLinks.forEach(link => link.addEventListener("click", closeNavbar));

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
</script>
</body>
</html>