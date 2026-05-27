<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Include config
require_once 'includes/config.php';

// Define base URL and canonical URL
$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/career-know-your-goal";

// Enhanced SEO metadata
$page_title = "Career Explorer | EduScore - Know Your Career Goal";
$page_description = "Explore over 350+ career options organized by subject areas. Find detailed information about careers in Arts, Business, and Technology.";
$page_keywords = "career explorer, career goals, career guidance Kenya, CBC curriculum, subject selection";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// Comprehensive Career Data organized by category and subject area
$career_categories = [
    'arts' => [
        'name' => 'Arts & Creative Arts',
        'icon' => 'fas fa-palette',
        'color' => '#10B981',
        'subjects' => [
            'Music & Dance' => [
                'careers' => [
                    ['title' => 'Music Teacher', 'description' => 'Teach music theory and practical skills in schools.', 'requirements' => 'Music degree + Education certification'],
                    ['title' => 'Professional Musician', 'description' => 'Perform in orchestras, bands, or as a solo artist.', 'requirements' => 'Advanced music training'],
                    ['title' => 'Music Producer', 'description' => 'Record, mix, and master music in studios.', 'requirements' => 'Music production certification'],
                    ['title' => 'Sound Engineer', 'description' => 'Manage audio equipment for live events and recordings.', 'requirements' => 'Audio engineering diploma'],
                    ['title' => 'Choreographer', 'description' => 'Create dance routines for performances.', 'requirements' => 'Dance training and experience'],
                    ['title' => 'Dance Instructor', 'description' => 'Teach various dance styles to students.', 'requirements' => 'Dance certification'],
                    ['title' => 'Cultural Officer', 'description' => 'Promote and preserve cultural heritage.', 'requirements' => 'Cultural studies degree'],
                    ['title' => 'Entertainment Manager', 'description' => 'Manage artists and entertainment events.', 'requirements' => 'Arts management degree']
                ]
            ],
            'Fine Arts' => [
                'careers' => [
                    ['title' => 'Fine Artist', 'description' => 'Create original artwork for exhibitions and sales.', 'requirements' => 'Fine arts degree'],
                    ['title' => 'Graphic Designer', 'description' => 'Design visual content for digital and print media.', 'requirements' => 'Graphic design diploma'],
                    ['title' => 'Art Teacher', 'description' => 'Teach art techniques and art history.', 'requirements' => 'Art education degree'],
                    ['title' => 'Illustrator', 'description' => 'Create drawings for books, magazines, and products.', 'requirements' => 'Illustration portfolio'],
                    ['title' => 'Curator', 'description' => 'Manage art collections in galleries and museums.', 'requirements' => 'Art history degree'],
                    ['title' => 'Art Therapist', 'description' => 'Use art for therapeutic purposes.', 'requirements' => 'Art therapy certification']
                ]
            ],
            'Theater & Film' => [
                'careers' => [
                    ['title' => 'Actor', 'description' => 'Perform in films, TV shows, and stage productions.', 'requirements' => 'Drama training'],
                    ['title' => 'Film Director', 'description' => 'Direct and oversee film production.', 'requirements' => 'Film degree or experience'],
                    ['title' => 'Screenwriter', 'description' => 'Write scripts for films and TV shows.', 'requirements' => 'Creative writing degree'],
                    ['title' => 'Cinematographer', 'description' => 'Handle camera and lighting for films.', 'requirements' => 'Film production training'],
                    ['title' => 'Video Editor', 'description' => 'Edit and assemble recorded footage.', 'requirements' => 'Video editing certification'],
                    ['title' => 'Production Designer', 'description' => 'Design sets for film and theater.', 'requirements' => 'Set design experience']
                ]
            ]
        ]
    ],
    'business' => [
        'name' => 'Business & Commerce',
        'icon' => 'fas fa-chart-line',
        'color' => '#8B5CF6',
        'subjects' => [
            'Business Studies' => [
                'careers' => [
                    ['title' => 'Accountant', 'description' => 'Manage financial records and prepare tax returns.', 'requirements' => 'CPA certification'],
                    ['title' => 'Financial Analyst', 'description' => 'Analyze investment opportunities.', 'requirements' => 'Finance degree'],
                    ['title' => 'Marketing Manager', 'description' => 'Develop marketing strategies.', 'requirements' => 'Marketing degree'],
                    ['title' => 'Human Resource Manager', 'description' => 'Manage employee relations and recruitment.', 'requirements' => 'HR management degree'],
                    ['title' => 'Entrepreneur', 'description' => 'Start and run your own business.', 'requirements' => 'Business knowledge + capital'],
                    ['title' => 'Banker', 'description' => 'Handle financial transactions and customer accounts.', 'requirements' => 'Banking certification']
                ]
            ],
            'Economics' => [
                'careers' => [
                    ['title' => 'Economist', 'description' => 'Study economic trends and advise on policy.', 'requirements' => 'Economics degree'],
                    ['title' => 'Policy Analyst', 'description' => 'Research and analyze public policies.', 'requirements' => 'Public policy degree'],
                    ['title' => 'Statistician', 'description' => 'Collect and analyze statistical data.', 'requirements' => 'Statistics degree']
                ]
            ]
        ]
    ],
    'technology' => [
        'name' => 'Technology & Computer Studies',
        'icon' => 'fas fa-laptop-code',
        'color' => '#00BFFF',
        'subjects' => [
            'Computer Studies' => [
                'careers' => [
                    ['title' => 'Software Engineer', 'description' => 'Develop and maintain software applications.', 'requirements' => 'Computer science degree'],
                    ['title' => 'Web Developer', 'description' => 'Build and maintain websites.', 'requirements' => 'Web development certification'],
                    ['title' => 'Data Scientist', 'description' => 'Analyze complex data to inform decisions.', 'requirements' => 'Data science degree'],
                    ['title' => 'Network Administrator', 'description' => 'Manage computer networks.', 'requirements' => 'Networking certification'],
                    ['title' => 'Cybersecurity Analyst', 'description' => 'Protect systems from cyber threats.', 'requirements' => 'Cybersecurity certification'],
                    ['title' => 'IT Project Manager', 'description' => 'Lead technology projects.', 'requirements' => 'Project management certification']
                ]
            ],
            'Media Technology' => [
                'careers' => [
                    ['title' => 'Multimedia Specialist', 'description' => 'Create multimedia content.', 'requirements' => 'Multimedia design degree'],
                    ['title' => 'Animation Designer', 'description' => 'Create animated content for media.', 'requirements' => 'Animation certification']
                ]
            ]
        ]
    ]
];

// Build complete careers array for search functionality
$all_careers = [];
foreach ($career_categories as $cat_key => $category) {
    foreach ($category['subjects'] as $subject => $data) {
        foreach ($data['careers'] as $career) {
            $all_careers[] = [
                'title' => $career['title'],
                'category' => $cat_key,
                'category_name' => $category['name'],
                'subject_area' => $subject,
                'description' => $career['description'],
                'requirements' => $career['requirements']
            ];
        }
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

h1, h2, h3, h4, .section-title, .hero-stat-number {
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

@media (max-width: 480px) {
    .header .container { padding: 10px 16px; }
    .logo img { height: 34px; }
    .menu-btn { width: 40px; height: 40px; font-size: 2rem; }
    .theme-toggle { width: 38px; height: 38px; font-size: 1.6rem; }
}

/*-----------------------------------*\
  #PAGE HEADER
\*-----------------------------------*/
.page-header {
    padding-top: 140px;
    padding-bottom: 70px;
    background: linear-gradient(135deg, #e8a84c, #c97e2a);
    text-align: center;
    position: relative;
    overflow: hidden;
}

body.dark-mode .page-header {
    background: linear-gradient(135deg, #c48a3c, #a66824);
}

.page-header h1 {
    font-size: clamp(2.5rem, 5vw, 3.5rem);
    color: white;
    margin-bottom: 15px;
}

.page-header h1 span {
    color: #2c2418;
}

.page-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.6rem;
    max-width: 700px;
    margin: 0 auto;
}

.stats-banner {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-top: 40px;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 15px 25px;
    border-radius: 20px;
    min-width: 120px;
}

.stat-number {
    font-size: 2.2rem;
    font-weight: 700;
    color: white;
    font-family: 'League Spartan', "Merriweather", serif;
}

.stat-label {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.95);
}

/*-----------------------------------*\
  #CATEGORY FILTERS
\*-----------------------------------*/
.category-filters {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin: 40px 0;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 12px 30px;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 1.4rem;
    background: rgba(255, 253, 248, 0.85);
    border: 2px solid rgba(230, 200, 140, 0.5);
    color: #5c4b34;
}

.filter-btn:hover {
    transform: translateY(-3px);
}

.filter-btn.active {
    background: #00BFFF;
    color: white;
    border-color: #00BFFF;
}

.filter-btn.arts.active { background: #10B981; border-color: #10B981; }
.filter-btn.business.active { background: #8B5CF6; border-color: #8B5CF6; }
.filter-btn.technology.active { background: #00BFFF; border-color: #00BFFF; }

body.dark-mode .filter-btn {
    background: rgba(50, 45, 38, 0.85);
    color: #cfc3a8;
    border-color: rgba(210, 170, 90, 0.3);
}

/*-----------------------------------*\
  #SEARCH SECTION
\*-----------------------------------*/
.search-section {
    margin-bottom: 40px;
}

.search-box {
    position: relative;
    max-width: 500px;
    margin: 0 auto;
}

.search-box input {
    width: 100%;
    padding: 15px 20px 15px 50px;
    border: 2px solid rgba(230, 200, 140, 0.5);
    border-radius: 50px;
    font-size: 1.6rem;
    background: rgba(255, 253, 248, 0.85);
    color: var(--eerie-black-1);
}

.search-box input:focus {
    outline: none;
    border-color: #00BFFF;
}

.search-box i {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #5c4b34;
}

body.dark-mode .search-box input {
    background: rgba(50, 45, 38, 0.85);
    color: #f7e5c2;
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .search-box i {
    color: #cfc3a8;
}

/*-----------------------------------*\
  #CAREER CARDS GRID
\*-----------------------------------*/
.career-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 20px;
}

.career-card {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    border-radius: 24px;
    padding: 25px;
    transition: all 0.35s ease;
    border: 1px solid rgba(230, 200, 140, 0.4);
    cursor: pointer;
    position: relative;
}

.career-card:hover {
    transform: translateY(-8px);
    background: rgba(255, 254, 252, 0.95);
    border-color: #e6b450;
}

.career-card .category-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 1rem;
    font-weight: 600;
}

.category-badge.arts { background: rgba(16, 185, 129, 0.15); color: #10B981; }
.category-badge.business { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }
.category-badge.technology { background: rgba(0, 191, 255, 0.15); color: #00BFFF; }

.career-icon {
    width: 60px;
    height: 60px;
    background: #fef0d4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    font-size: 2rem;
    color: #00BFFF;
    transition: all 0.3s ease;
}

.career-card:hover .career-icon {
    transform: scale(1.05);
}

.career-card h3 {
    font-size: 1.8rem;
    margin-bottom: 10px;
    color: #2c2418;
}

.career-card p {
    font-size: 1.3rem;
    color: #5c4b34;
    margin-bottom: 15px;
    line-height: 1.5;
}

.subject-tag {
    display: inline-block;
    background: rgba(0, 191, 255, 0.1);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 1rem;
    color: #00BFFF;
}

body.dark-mode .career-card {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .career-card:hover {
    background: rgba(65, 58, 48, 0.92);
}

body.dark-mode .career-card h3 {
    color: #f7e5c2;
}

body.dark-mode .career-card p {
    color: #cfc3a8;
}

body.dark-mode .career-icon {
    background: #6b5538;
    color: #f3cd81;
}

/*-----------------------------------*\
  #MODAL
\*-----------------------------------*/
.career-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}

.career-modal.active {
    display: flex;
}

.modal-content {
    background: rgba(255, 253, 248, 0.98);
    backdrop-filter: blur(10px);
    border-radius: 30px;
    max-width: 700px;
    width: 90%;
    max-height: 85vh;
    overflow-y: auto;
    padding: 30px;
    position: relative;
    border: 1px solid rgba(230, 200, 140, 0.5);
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 25px;
    font-size: 2rem;
    cursor: pointer;
    color: #5c4b34;
    transition: all 0.2s ease;
}

.modal-close:hover {
    color: #00BFFF;
    transform: scale(1.1);
}

.modal-category-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.modal-content h2 {
    color: #2c2418;
    margin-bottom: 10px;
}

.modal-content h3 {
    color: #2c2418;
    margin-bottom: 10px;
    margin-top: 15px;
}

.modal-content p, .modal-content li {
    color: #5c4b34;
}

body.dark-mode .modal-content {
    background: rgba(50, 45, 38, 0.98);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .modal-content h2,
body.dark-mode .modal-content h3 {
    color: #f7e5c2;
}

body.dark-mode .modal-content p,
body.dark-mode .modal-content li {
    color: #cfc3a8;
}

body.dark-mode .modal-close {
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
    .career-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .career-grid {
        grid-template-columns: 1fr;
    }
    .footer-top {
        grid-template-columns: 1fr;
    }
    .stats-banner {
        gap: 15px;
    }
    .stat-item {
        padding: 10px 15px;
        min-width: 100px;
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
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Explore <span>Career Options</span></h1>
            <p>Discover your ideal career path based on your interests and subject strengths</p>
            <div class="stats-banner">
                <div class="stat-item"><div class="stat-number">50+</div><div class="stat-label">Career Options</div></div>
                <div class="stat-item"><div class="stat-number">3</div><div class="stat-label">Major Pathways</div></div>
                <div class="stat-item"><div class="stat-number">100%</div><div class="stat-label">Free Guidance</div></div>
            </div>
        </div>
    </section>

    <!-- Career Explorer Section -->
    <section class="section">
        <div class="container">
            <!-- Category Filters -->
            <div class="category-filters">
                <button class="filter-btn active" data-category="all" onclick="filterByCategory('all')">All Careers</button>
                <button class="filter-btn arts" data-category="arts" onclick="filterByCategory('arts')">🎨 Arts & Creative</button>
                <button class="filter-btn business" data-category="business" onclick="filterByCategory('business')">💼 Business & Commerce</button>
                <button class="filter-btn technology" data-category="technology" onclick="filterByCategory('technology')">💻 Technology & ICT</button>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="careerSearch" placeholder="Search careers (e.g., Doctor, Engineer, Teacher, Designer...)" onkeyup="filterCareers()">
                </div>
            </div>

            <!-- Career Cards Grid -->
            <div class="career-grid" id="careerGrid">
                <?php foreach ($all_careers as $career): ?>
                <div class="career-card reveal" data-category="<?php echo $career['category']; ?>" data-title="<?php echo htmlspecialchars($career['title']); ?>" onclick="showCareerDetail('<?php echo htmlspecialchars($career['title']); ?>', '<?php echo $career['category']; ?>', '<?php echo htmlspecialchars($career['category_name']); ?>', '<?php echo htmlspecialchars($career['subject_area']); ?>', '<?php echo htmlspecialchars(addslashes($career['description'])); ?>', '<?php echo htmlspecialchars(addslashes($career['requirements'])); ?>')">
                    <div class="category-badge <?php echo $career['category']; ?>"><?php echo $career['category_name']; ?></div>
                    <div class="career-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($career['title']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($career['description'], 0, 80)) . '...'; ?></p>
                    <span class="subject-tag"><?php echo htmlspecialchars($career['subject_area']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<!-- Career Detail Modal -->
<div class="career-modal" id="careerModal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div id="modalContent"></div>
    </div>
</div>

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

// Category Filter
let currentCategory = 'all';

function filterByCategory(category) {
    currentCategory = category;
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    const cards = document.querySelectorAll('#careerGrid .career-card');
    const searchTerm = document.getElementById('careerSearch').value.toLowerCase();
    
    cards.forEach(card => {
        const cardCategory = card.dataset.category;
        const title = card.dataset.title.toLowerCase();
        
        const matchesCategory = (category === 'all' || cardCategory === category);
        const matchesSearch = title.includes(searchTerm);
        
        if (matchesCategory && matchesSearch) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Career Search
function filterCareers() {
    const searchTerm = document.getElementById('careerSearch').value.toLowerCase();
    const cards = document.querySelectorAll('#careerGrid .career-card');
    
    cards.forEach(card => {
        const cardCategory = card.dataset.category;
        const title = card.dataset.title.toLowerCase();
        
        const matchesCategory = (currentCategory === 'all' || cardCategory === currentCategory);
        const matchesSearch = title.includes(searchTerm);
        
        if (matchesCategory && matchesSearch) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Career Detail Modal
function showCareerDetail(title, category, categoryName, subjectArea, description, requirements) {
    const modal = document.getElementById('careerModal');
    const modalContent = document.getElementById('modalContent');
    
    let categoryClass = '';
    let categoryColor = '';
    if (category === 'arts') {
        categoryClass = 'arts';
        categoryColor = '#10B981';
    } else if (category === 'business') {
        categoryClass = 'business';
        categoryColor = '#8B5CF6';
    } else {
        categoryClass = 'technology';
        categoryColor = '#00BFFF';
    }
    
    modalContent.innerHTML = `
        <h2 style="margin-bottom: 10px;">${title}</h2>
        <div class="modal-category-badge ${categoryClass}" style="background: ${categoryColor}15; color: ${categoryColor};">${categoryName}</div>
        <p style="margin-bottom: 15px;"><strong>Subject Area:</strong> ${subjectArea}</p>
        <h3 style="margin-bottom: 10px; margin-top: 15px;">Career Description</h3>
        <p style="margin-bottom: 20px;">${description}</p>
        <h3 style="margin-bottom: 10px;">Requirements</h3>
        <p style="margin-bottom: 20px;">${requirements}</p>
        <h3 style="margin-bottom: 10px;">Next Steps</h3>
        <ul style="margin-left: 20px; margin-bottom: 20px;">
            <li>Research educational institutions offering programs in this field</li>
            <li>Talk to professionals working in this career</li>
            <li>Consider internships or volunteer opportunities</li>
            <li>Focus on relevant subjects in your current studies</li>
        </ul>
        <button onclick="closeModal()" style="background: ${categoryColor}; color: white; padding: 10px 25px; border-radius: 30px; font-weight: 600; cursor: pointer; margin-top: 10px; border: none;">Close</button>
    `;
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('careerModal').classList.remove('active');
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('careerModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>
</body>
</html>