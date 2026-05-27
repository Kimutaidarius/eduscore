<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Include config
require_once 'includes/config.php';

// Define base URL and canonical URL
$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/career-subject-selection";

// Enhanced SEO metadata
$page_title = "Subject Combination Explorer | EduScore - Find Your Career Path";
$page_description = "Browse subject combinations, filter by pathway and track, and discover careers based on Kenya's CBC curriculum.";
$page_keywords = "subject combination, career pathways, CBC curriculum, subject selection, Kenyan schools";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// Career goals data for dropdown
$career_goals = [
    'Doctor' => ['subjects' => ['Biology', 'Chemistry', 'Physics/Mathematics'], 'pathway' => 'STEM', 'track' => 'Pure Sciences'],
    'Engineer' => ['subjects' => ['Mathematics', 'Physics', 'Chemistry'], 'pathway' => 'STEM', 'track' => 'Technical Studies'],
    'Teacher' => ['subjects' => ['Subject specialization', 'English', 'Kiswahili'], 'pathway' => 'Social Sciences', 'track' => 'Humanities & Business'],
    'Lawyer' => ['subjects' => ['English', 'History', 'CRE/IRE'], 'pathway' => 'Social Sciences', 'track' => 'Humanities & Business'],
    'Artist' => ['subjects' => ['Fine Arts', 'Theatre & Film', 'Music & Dance'], 'pathway' => 'Arts & Sports', 'track' => 'Performing Arts'],
    'Athlete' => ['subjects' => ['Sports & Recreation', 'Biology', 'Physical Education'], 'pathway' => 'Arts & Sports', 'track' => 'Sports Science']
];

// Comprehensive subject combination data grouped by track
$core_tracks = [
    'ARTS & SPORTS' => [
        'Performing Arts' => [
            'core_subjects' => ['Fine Arts', 'Theatre & Film'],
            'optional_subjects' => ['Arabic', 'Biology', 'Business Studies', 'Computer Studies', 'CRE', 'IRE', 'HRE', 'Fasihi ya Kiswahili', 'French', 'General Science', 'Geography', 'German', 'History & Citizenship', 'Literature in English', 'Mandarin Chinese', 'Core Mathematics', 'Sports & Recreation'],
            'careers' => ['Actor', 'Director', 'Set Designer', 'Playwright', 'Art Therapist', 'Film Producer'],
            'university_courses' => ['Bachelor of Fine Arts', 'Film Production', 'Theatre Arts', 'Acting']
        ],
        'Music & Dance' => [
            'core_subjects' => ['Fine Arts', 'Music & Dance'],
            'optional_subjects' => ['Arabic', 'Biology', 'Business Studies', 'CRE', 'French', 'German', 'History', 'Literature'],
            'careers' => ['Musician', 'Dancer', 'Music Therapist', 'Choreographer', 'Music Teacher'],
            'university_courses' => ['Bachelor of Music', 'Dance Studies', 'Music Education']
        ]
    ],
    'STEM' => [
        'Pure Sciences' => [
            'core_subjects' => ['Biology', 'Chemistry', 'Physics', 'Advanced Mathematics'],
            'optional_subjects' => ['Computer Studies', 'Agriculture', 'General Science'],
            'careers' => ['Medical Doctor', 'Pharmacist', 'Engineer', 'Scientist', 'Researcher', 'Dentist', 'Veterinarian'],
            'university_courses' => ['Medicine', 'Engineering', 'Pharmacy', 'Biochemistry', 'Computer Science']
        ],
        'Technical Studies' => [
            'core_subjects' => ['Building Construction', 'Business Studies', 'Geography'],
            'optional_subjects' => ['Physics', 'Mathematics', 'Computer Studies'],
            'careers' => ['Construction Manager', 'Quantity Surveyor', 'Architect', 'Civil Engineer'],
            'university_courses' => ['Civil Engineering', 'Architecture', 'Quantity Surveying']
        ]
    ],
    'SOCIAL SCIENCES' => [
        'Humanities & Business' => [
            'core_subjects' => ['Business Studies', 'History & Citizenship', 'Geography'],
            'optional_subjects' => ['CRE/IRE/HRE', 'English', 'Literature', 'Economics'],
            'careers' => ['Economist', 'Business Consultant', 'Policy Analyst', 'Lawyer', 'Journalist'],
            'university_courses' => ['Economics', 'Law', 'Business Administration', 'Public Policy']
        ],
        'Languages & Literature' => [
            'core_subjects' => ['Literature in English', 'Fasihi ya Kiswahili', 'History & Citizenship'],
            'optional_subjects' => ['French', 'German', 'Mandarin', 'Arabic'],
            'careers' => ['Editor', 'Publisher', 'Journalist', 'Author', 'Diplomat', 'Translator'],
            'university_courses' => ['Literature', 'Journalism', 'Translation', 'International Relations']
        ]
    ]
];

// Flatten combinations for display
$all_combinations = [];
foreach ($core_tracks as $pathway => $tracks) {
    foreach ($tracks as $track_name => $track_data) {
        $all_combinations[] = [
            'pathway' => $pathway,
            'track' => $track_name,
            'core_subjects' => $track_data['core_subjects'],
            'optional_subjects' => $track_data['optional_subjects'],
            'careers' => $track_data['careers'],
            'university_courses' => $track_data['university_courses'],
            'variations' => count($track_data['optional_subjects'])
        ];
    }
}

$total_combinations = array_sum(array_column($all_combinations, 'variations'));
$total_careers = count(array_unique(array_merge(...array_column($all_combinations, 'careers'))));

// Career mapping for recommendation engine
$career_to_track = [
    'Doctor' => ['pathway' => 'STEM', 'track' => 'Pure Sciences'],
    'Engineer' => ['pathway' => 'STEM', 'track' => 'Technical Studies'],
    'Architect' => ['pathway' => 'STEM', 'track' => 'Technical Studies'],
    'Lawyer' => ['pathway' => 'SOCIAL SCIENCES', 'track' => 'Humanities & Business'],
    'Economist' => ['pathway' => 'SOCIAL SCIENCES', 'track' => 'Humanities & Business'],
    'Journalist' => ['pathway' => 'SOCIAL SCIENCES', 'track' => 'Languages & Literature'],
    'Actor' => ['pathway' => 'ARTS & SPORTS', 'track' => 'Performing Arts'],
    'Musician' => ['pathway' => 'ARTS & SPORTS', 'track' => 'Music & Dance']
];

// Track options by pathway
$track_options = [
    'STEM' => ['Pure Sciences', 'Technical Studies'],
    'ARTS & SPORTS' => ['Performing Arts', 'Music & Dance'],
    'SOCIAL SCIENCES' => ['Humanities & Business', 'Languages & Literature']
];

// All subjects list for search
$all_subjects_list = [];
foreach ($core_tracks as $pathway => $tracks) {
    foreach ($tracks as $track_data) {
        foreach ($track_data['core_subjects'] as $subject) {
            if (!in_array($subject, $all_subjects_list)) $all_subjects_list[] = $subject;
        }
        foreach ($track_data['optional_subjects'] as $subject) {
            if (!in_array($subject, $all_subjects_list)) $all_subjects_list[] = $subject;
        }
    }
}
sort($all_subjects_list);
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
  #UNIFORM SYSTEM STYLES (PRESERVED)
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
  #HEADER (PRESERVED)
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
/* Add this to your style section - it removes underlines from nav links and footer links */

/* Remove underline from all navbar links */
.navbar-link {
    text-decoration: none !important;
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

/* Ensure no text decoration on hover or active states */
.navbar-link:hover,
.navbar-link.active {
    text-decoration: none !important;
}

/* Remove underline from footer links */
.footer-link {
    text-decoration: none !important;
}

.footer-link:hover {
    text-decoration: none !important;
}

/* Remove underline from portal buttons */
.portal-btn,
.mobile-portal-btn {
    text-decoration: none !important;
}

/* Remove underline from all footer anchor tags */
.footer a {
    text-decoration: none !important;
}

.footer a:hover {
    text-decoration: none !important;
}

/* Remove underline from back button */
.back-btn {
    text-decoration: none !important;
}

/* Remove underline from hero buttons */
.hero-btn {
    text-decoration: none !important;
}

/* Remove underline from action buttons */
.action-btn {
    text-decoration: none !important;
}

/* Remove underline from card buttons */
.card-btn {
    text-decoration: none !important;
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
  #NEW HERO SECTION
\*-----------------------------------*/
.hero-section {
    padding-top: 140px;
    padding-bottom: 60px;
    background: linear-gradient(135deg, #e8a84c, #c97e2a);
    text-align: center;
    position: relative;
    overflow: hidden;
}

body.dark-mode .hero-section {
    background: linear-gradient(135deg, #c48a3c, #a66824);
}

.hero-section h1 {
    font-size: clamp(2.5rem, 5vw, 3.5rem);
    color: white;
    margin-bottom: 15px;
}

.hero-section h1 span {
    color: #2c2418;
}

.hero-section .hero-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.6rem;
    max-width: 700px;
    margin: 0 auto 30px;
}

.hero-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.hero-btn {
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.hero-btn.primary {
    background: white;
    color: #c97e2a;
}

.hero-btn.primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.hero-btn.secondary {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.5);
}

.hero-btn.secondary:hover {
    background: white;
    color: #c97e2a;
    transform: translateY(-3px);
}

/*-----------------------------------*\
  #FILTER SECTION (ENHANCED)
\*-----------------------------------*/
.filter-section {
    padding: 30px 0;
}

.filter-container {
    background: rgba(255, 253, 248, 0.7);
    backdrop-filter: blur(8px);
    border-radius: 24px;
    padding: 25px;
    border: 1px solid rgba(230, 200, 140, 0.4);
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c2418;
    font-size: 1.3rem;
}

.filter-select, .search-input {
    width: 100%;
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid rgba(230, 200, 140, 0.5);
    background: rgba(255, 253, 248, 0.85);
    color: #2c2418;
    font-size: 1.4rem;
    cursor: pointer;
}

.filter-select:focus, .search-input:focus {
    outline: none;
    border-color: #00BFFF;
}

.search-input {
    cursor: text;
}

.career-goal-select {
    background: rgba(0, 191, 255, 0.1);
    border-color: #00BFFF;
}

body.dark-mode .filter-container {
    background: rgba(50, 45, 38, 0.7);
}

body.dark-mode .filter-group label {
    color: #f7e5c2;
}

body.dark-mode .filter-select, body.dark-mode .search-input {
    background: rgba(50, 45, 38, 0.85);
    color: #f7e5c2;
    border-color: rgba(210, 170, 90, 0.3);
}

.results-count {
    text-align: center;
    font-size: 1.4rem;
    color: #5c4b34;
    margin-top: 20px;
}

body.dark-mode .results-count {
    color: #cfc3a8;
}

/* Stats Cards */
.stats-cards {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin: 40px 0;
    flex-wrap: wrap;
}

.stat-card {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    border-radius: 20px;
    padding: 25px 35px;
    text-align: center;
    border: 1px solid rgba(230, 200, 140, 0.4);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 254, 252, 0.95);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #00BFFF;
    font-family: 'League Spartan', "Merriweather", serif;
}

.stat-label {
    font-size: 1.3rem;
    color: #5c4b34;
    margin-top: 5px;
}

body.dark-mode .stat-card {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .stat-label {
    color: #cfc3a8;
}

/*-----------------------------------*\
  #COMBINATIONS GRID (GROUPED CARDS)
\*-----------------------------------*/
.combinations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 30px;
    padding: 20px 0;
}

.combination-card {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    border-radius: 24px;
    padding: 25px;
    transition: all 0.35s ease;
    border: 1px solid rgba(230, 200, 140, 0.4);
}

.combination-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 254, 252, 0.95);
    border-color: #e6b450;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.pathway-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 1.1rem;
    font-weight: 600;
}

.pathway-badge.stem { background: rgba(0, 191, 255, 0.1); color: #00BFFF; }
.pathway-badge.arts { background: rgba(16, 185, 129, 0.1); color: #10B981; }
.pathway-badge.social { background: rgba(139, 92, 246, 0.1); color: #8B5CF6; }

.track-name {
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c2418;
    margin-bottom: 15px;
}

.core-subjects, .optional-subjects {
    margin: 15px 0;
}

.core-subjects h4, .optional-subjects h4 {
    font-size: 1.2rem;
    color: #5c4b34;
    margin-bottom: 8px;
}

.subject-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.subject-tag {
    background: #fef0d4;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 1.1rem;
    color: #b86f2c;
}

.variations-info {
    margin: 15px 0;
    padding: 10px;
    background: rgba(0, 191, 255, 0.05);
    border-radius: 12px;
    text-align: center;
    font-size: 1.2rem;
    color: #00BFFF;
}

.card-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.card-btn {
    flex: 1;
    padding: 10px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    border: none;
}

.card-btn.variations {
    background: rgba(0, 191, 255, 0.1);
    color: #00BFFF;
}

.card-btn.careers {
    background: #00BFFF;
    color: white;
}

.card-btn:hover {
    transform: translateY(-2px);
}

body.dark-mode .combination-card {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .combination-card:hover {
    background: rgba(65, 58, 48, 0.92);
}

body.dark-mode .track-name {
    color: #f7e5c2;
}

body.dark-mode .core-subjects h4, body.dark-mode .optional-subjects h4 {
    color: #cfc3a8;
}

body.dark-mode .subject-tag {
    background: #6b5538;
    color: #f3cd81;
}

/* Modal Styles */
.modal {
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

.modal.active {
    display: flex;
}

.modal-content {
    background: rgba(255, 253, 248, 0.98);
    border-radius: 24px;
    max-width: 700px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    padding: 30px;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 2rem;
    cursor: pointer;
    color: #5c4b34;
}

.variations-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.variations-table th, .variations-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid rgba(230, 200, 140, 0.3);
}

.careers-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 20px 0;
}

.university-list {
    list-style: none;
    padding-left: 0;
}

.university-list li {
    padding: 8px 0;
    border-bottom: 1px solid rgba(230, 200, 140, 0.3);
}

.recommendation-card {
    background: rgba(0, 191, 255, 0.05);
    border-radius: 16px;
    padding: 20px;
    margin-top: 20px;
}

body.dark-mode .modal-content {
    background: rgba(50, 45, 38, 0.98);
}

body.dark-mode .modal-close {
    color: #cfc3a8;
}

/* Load More */
.load-more-container {
    text-align: center;
    padding: 30px 0;
}

.load-more-btn {
    background: rgba(255, 253, 248, 0.85);
    border: 1px solid rgba(230, 200, 140, 0.5);
    padding: 12px 30px;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #2c2418;
}

.load-more-btn:hover {
    background: #00BFFF;
    color: white;
}

body.dark-mode .load-more-btn {
    background: rgba(50, 45, 38, 0.85);
    color: #f7e5c2;
}

body.dark-mode .load-more-btn:hover {
    background: #00BFFF;
    color: white;
}

/* Mobile Sticky Filter */
.sticky-filter-btn {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #00BFFF;
    color: white;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    z-index: 999;
    box-shadow: 0 4px 15px rgba(0, 191, 255, 0.3);
}

.bottom-sheet {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(255, 253, 248, 0.98);
    backdrop-filter: blur(16px);
    border-radius: 24px 24px 0 0;
    padding: 25px;
    z-index: 1002;
    transform: translateY(100%);
    transition: transform 0.3s ease;
    max-height: 80vh;
    overflow-y: auto;
}

.bottom-sheet.active {
    transform: translateY(0);
}

.bottom-sheet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(230, 200, 140, 0.3);
}

.bottom-sheet-close {
    font-size: 1.5rem;
    cursor: pointer;
}

@media (max-width: 768px) {
    .sticky-filter-btn {
        display: flex;
    }
    .filter-section .filter-container {
        display: none;
    }
    .combinations-grid {
        grid-template-columns: 1fr;
    }
    .hero-buttons {
        flex-direction: column;
        align-items: center;
    }
    .hero-btn {
        width: 80%;
        justify-content: center;
    }
    .stats-cards {
        flex-direction: column;
        align-items: center;
    }
    .stat-card {
        width: 80%;
    }
}

/*-----------------------------------*\
  #FOOTER (PRESERVED)
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
    left: 30px;
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
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>Find Your <span>Subject Combination</span></h1>
            <p class="hero-subtitle">Explore CBC Senior School subject combinations based on pathways, tracks, and career goals</p>
            <div class="hero-buttons">
                <a href="#combinations" class="hero-btn primary" onclick="document.getElementById('combinations').scrollIntoView({behavior: 'smooth'})">
                    <i class="fas fa-search"></i> Explore Combinations
                </a>
                <a href="#filters" class="hero-btn secondary" onclick="document.getElementById('filters').scrollIntoView({behavior: 'smooth'})">
                    <i class="fas fa-bullseye"></i> Match Careers
                </a>
                <a href="#combinations" class="hero-btn secondary" onclick="document.getElementById('combinations').scrollIntoView({behavior: 'smooth'})">
                    <i class="fas fa-book"></i> Choose Subjects
                </a>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <div class="container">
        <div class="stats-cards reveal">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_combinations; ?>+</div>
                <div class="stat-label">Subject Combinations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($core_tracks); ?></div>
                <div class="stat-label">Career Pathways</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_careers; ?>+</div>
                <div class="stat-label">Career Matches</div>
            </div>
        </div>
    </div>

    <!-- Smart Search & Filter Section -->
    <div class="container" id="filters">
        <div class="filter-section">
            <div class="filter-container reveal">
                <div class="filter-row">
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Filter by Pathway</label>
                        <select id="pathwayFilter" class="filter-select">
                            <option value="all">All Pathways</option>
                            <option value="STEM">STEM (Science, Technology, Engineering)</option>
                            <option value="ARTS & SPORTS">Arts & Sports Science</option>
                            <option value="SOCIAL SCIENCES">Social Sciences</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-tag"></i> Filter by Track</label>
                        <select id="trackFilter" class="filter-select">
                            <option value="all">All Tracks</option>
                        </select>
                    </div>
                </div>
                <div class="filter-row">
                    <div class="filter-group">
                        <label><i class="fas fa-bullseye"></i> I want to become a...</label>
                        <select id="careerGoalFilter" class="filter-select career-goal-select">
                            <option value="">Select a career goal (Optional)</option>
                            <?php foreach ($career_goals as $goal => $data): ?>
                                <option value="<?php echo htmlspecialchars($goal); ?>"><?php echo htmlspecialchars($goal); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search by Subject</label>
                        <input type="text" id="subjectSearch" class="search-input" placeholder="Type subject name...">
                    </div>
                </div>
                <div class="results-count" id="resultsCount"></div>
            </div>
        </div>
    </div>

    <!-- Mobile Sticky Filter Button -->
    <div class="sticky-filter-btn" id="stickyFilterBtn">
        <i class="fas fa-filter"></i>
    </div>

    <!-- Bottom Sheet for Mobile Filters -->
    <div class="bottom-sheet" id="bottomSheet">
        <div class="bottom-sheet-header">
            <h3>Filter Combinations</h3>
            <i class="fas fa-times bottom-sheet-close"></i>
        </div>
        <div class="filter-group">
            <label>Pathway</label>
            <select id="mobilePathwayFilter" class="filter-select">
                <option value="all">All Pathways</option>
                <option value="STEM">STEM</option>
                <option value="ARTS & SPORTS">Arts & Sports</option>
                <option value="SOCIAL SCIENCES">Social Sciences</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Track</label>
            <select id="mobileTrackFilter" class="filter-select">
                <option value="all">All Tracks</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Career Goal</label>
            <select id="mobileCareerGoalFilter" class="filter-select">
                <option value="">Select a career goal</option>
                <?php foreach ($career_goals as $goal => $data): ?>
                    <option value="<?php echo htmlspecialchars($goal); ?>"><?php echo htmlspecialchars($goal); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="load-more-btn" style="width:100%; margin-top:20px;" onclick="applyMobileFilters()">Apply Filters</button>
    </div>

    <!-- Combinations Grid -->
    <div class="container" id="combinations">
        <div class="combinations-grid" id="combinationsGrid"></div>
        <div class="load-more-container" id="loadMoreContainer">
            <button class="load-more-btn" onclick="loadMore()">Load More Combinations</button>
        </div>
    </div>
</main>

<!-- Modals -->
<div class="modal" id="variationsModal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('variationsModal')">&times;</span>
        <div id="variationsModalContent"></div>
    </div>
</div>

<div class="modal" id="careersModal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('careersModal')">&times;</span>
        <div id="careersModalContent"></div>
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

// Data
const coreTracks = <?php echo json_encode($core_tracks); ?>;
const trackOptions = <?php echo json_encode($track_options); ?>;
const careerToTrack = <?php echo json_encode($career_to_track); ?>;
const careerGoals = <?php echo json_encode($career_goals); ?>;

let currentLimit = 6;
let filteredCombinations = [];

function getAllCombinations() {
    const combos = [];
    for (const [pathway, tracks] of Object.entries(coreTracks)) {
        for (const [trackName, trackData] of Object.entries(tracks)) {
            combos.push({
                pathway: pathway,
                track: trackName,
                core_subjects: trackData.core_subjects,
                optional_subjects: trackData.optional_subjects,
                careers: trackData.careers,
                university_courses: trackData.university_courses,
                variations: trackData.optional_subjects.length
            });
        }
    }
    return combos;
}

function getPathwayClass(pathway) {
    if (pathway === 'STEM') return 'stem';
    if (pathway === 'ARTS & SPORTS') return 'arts';
    return 'social';
}

function filterCombinations() {
    const pathwayFilter = document.getElementById('pathwayFilter').value;
    const trackFilter = document.getElementById('trackFilter').value;
    const careerGoal = document.getElementById('careerGoalFilter').value;
    const subjectSearch = document.getElementById('subjectSearch').value.toLowerCase();
    
    let allCombos = getAllCombinations();
    
    filteredCombinations = allCombos.filter(combo => {
        if (pathwayFilter !== 'all' && combo.pathway !== pathwayFilter) return false;
        if (trackFilter !== 'all' && combo.track !== trackFilter) return false;
        if (careerGoal && careerGoals[careerGoal]) {
            const goalData = careerGoals[careerGoal];
            if (combo.pathway !== goalData.pathway) return false;
            if (combo.track !== goalData.track) return false;
        }
        if (subjectSearch) {
            const allSubjects = [...combo.core_subjects, ...combo.optional_subjects];
            const matchesSubject = allSubjects.some(s => s.toLowerCase().includes(subjectSearch));
            if (!matchesSubject) return false;
        }
        return true;
    });
    
    document.getElementById('resultsCount').innerHTML = `Showing ${filteredCombinations.length} combinations`;
    currentLimit = 6;
    renderCombinations();
}

function updateTrackOptions() {
    const pathway = document.getElementById('pathwayFilter').value;
    const trackSelect = document.getElementById('trackFilter');
    const mobileTrackSelect = document.getElementById('mobileTrackFilter');
    
    let options = ['<option value="all">All Tracks</option>'];
    if (pathway !== 'all' && trackOptions[pathway]) {
        trackOptions[pathway].forEach(track => {
            options.push(`<option value="${track}">${track}</option>`);
        });
    } else if (pathway === 'all') {
        const allTracks = new Set();
        for (const [p, tracks] of Object.entries(trackOptions)) {
            tracks.forEach(t => allTracks.add(t));
        }
        allTracks.forEach(track => {
            options.push(`<option value="${track}">${track}</option>`);
        });
    }
    
    trackSelect.innerHTML = options.join('');
    if (mobileTrackSelect) mobileTrackSelect.innerHTML = options.join('');
}

function renderCombinations() {
    const displayCombos = filteredCombinations.slice(0, currentLimit);
    const grid = document.getElementById('combinationsGrid');
    
    if (displayCombos.length === 0) {
        grid.innerHTML = `<div style="text-align: center; padding: 50px; background: rgba(255,253,248,0.5); border-radius: 20px;">No combinations found. Try adjusting your filters.</div>`;
        document.getElementById('loadMoreContainer').style.display = 'none';
        return;
    }
    
    grid.innerHTML = displayCombos.map(combo => `
        <div class="combination-card reveal">
            <div class="card-header">
                <span class="pathway-badge ${getPathwayClass(combo.pathway)}">${combo.pathway}</span>
            </div>
            <div class="track-name"><i class="fas fa-tag"></i> ${combo.track}</div>
            <div class="core-subjects">
                <h4>Core Subjects</h4>
                <div class="subject-tags">
                    ${combo.core_subjects.map(s => `<span class="subject-tag">${s}</span>`).join('')}
                </div>
            </div>
            <div class="optional-subjects">
                <h4>Choose ONE Optional Subject</h4>
                <div class="subject-tags">
                    ${combo.optional_subjects.slice(0, 6).map(s => `<span class="subject-tag">${s}</span>`).join('')}
                    ${combo.optional_subjects.length > 6 ? `<span class="subject-tag">+${combo.optional_subjects.length - 6} more</span>` : ''}
                </div>
            </div>
            <div class="variations-info">
                <i class="fas fa-layer-group"></i> ${combo.variations} Possible Variations
            </div>
            <div class="card-actions">
                <button class="card-btn variations" onclick="showVariations('${combo.pathway}', '${combo.track}')">
                    <i class="fas fa-list"></i> View Variations
                </button>
                <button class="card-btn careers" onclick="showCareers('${combo.pathway}', '${combo.track}')">
                    <i class="fas fa-briefcase"></i> Related Careers
                </button>
            </div>
        </div>
    `).join('');
    
    document.getElementById('loadMoreContainer').style.display = currentLimit >= filteredCombinations.length ? 'none' : 'block';
    
    // Re-initialize reveal for new cards
    const newReveals = document.querySelectorAll('.reveal');
    const newObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); });
    }, { threshold: 0.15 });
    newReveals.forEach(el => newObserver.observe(el));
}

function loadMore() {
    currentLimit += 6;
    renderCombinations();
}

function showVariations(pathway, track) {
    const trackData = coreTracks[pathway][track];
    const modal = document.getElementById('variationsModal');
    const content = document.getElementById('variationsModalContent');
    
    let variationsHtml = `
        <h2>${track} Variations</h2>
        <p>Core Subjects: <strong>${trackData.core_subjects.join(', ')}</strong></p>
        <table class="variations-table">
            <thead>
                <tr><th>Optional Subject</th><th>Related Careers</th></tr>
            </thead>
            <tbody>
    `;
    
    trackData.optional_subjects.slice(0, 15).forEach(opt => {
        variationsHtml += `<tr><td>${opt}</td><td>${trackData.careers.slice(0, 3).join(', ')}...</td></tr>`;
    });
    
    variationsHtml += `
            </tbody>
         </table>
        <p><strong>Total Variations:</strong> ${trackData.optional_subjects.length}</p>
        <button onclick="closeModal('variationsModal')" style="margin-top:20px; background:#00BFFF; color:white; padding:10px 25px; border-radius:30px; border:none; cursor:pointer;">Close</button>
    `;
    
    content.innerHTML = variationsHtml;
    modal.classList.add('active');
}

function showCareers(pathway, track) {
    const trackData = coreTracks[pathway][track];
    const modal = document.getElementById('careersModal');
    const content = document.getElementById('careersModalContent');
    
    content.innerHTML = `
        <h2>${track} - Career Opportunities</h2>
        <div class="careers-list">
            ${trackData.careers.map(c => `<span class="subject-tag" style="background:#00BFFF; color:white; padding:8px 16px;">${c}</span>`).join('')}
        </div>
        <h3>Recommended University Courses</h3>
        <ul class="university-list">
            ${trackData.university_courses.map(c => `<li>🎓 ${c}</li>`).join('')}
        </ul>
        <div class="recommendation-card">
            <h3><i class="fas fa-lightbulb"></i> You may also like</h3>
            <p>Based on your interest in ${track}, consider exploring related combinations within ${pathway}.</p>
        </div>
        <button onclick="closeModal('careersModal')" style="margin-top:20px; background:#00BFFF; color:white; padding:10px 25px; border-radius:30px; border:none; cursor:pointer;">Close</button>
    `;
    modal.classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Mobile filter functions
const stickyBtn = document.getElementById('stickyFilterBtn');
const bottomSheet = document.getElementById('bottomSheet');
const bottomSheetClose = document.querySelector('.bottom-sheet-close');

if (stickyBtn) {
    stickyBtn.addEventListener('click', () => {
        bottomSheet.classList.add('active');
    });
}

if (bottomSheetClose) {
    bottomSheetClose.addEventListener('click', () => {
        bottomSheet.classList.remove('active');
    });
}

function applyMobileFilters() {
    const mobilePathway = document.getElementById('mobilePathwayFilter').value;
    const mobileTrack = document.getElementById('mobileTrackFilter').value;
    const mobileCareer = document.getElementById('mobileCareerGoalFilter').value;
    
    document.getElementById('pathwayFilter').value = mobilePathway;
    if (mobileTrack !== 'all') document.getElementById('trackFilter').value = mobileTrack;
    document.getElementById('careerGoalFilter').value = mobileCareer;
    
    updateTrackOptions();
    filterCombinations();
    bottomSheet.classList.remove('active');
}

// Event listeners
document.getElementById('pathwayFilter').addEventListener('change', () => {
    updateTrackOptions();
    filterCombinations();
});
document.getElementById('trackFilter').addEventListener('change', () => {
    filterCombinations();
});
document.getElementById('careerGoalFilter').addEventListener('change', () => {
    filterCombinations();
});
document.getElementById('subjectSearch').addEventListener('input', () => {
    filterCombinations();
});

// Initialize
updateTrackOptions();
filterCombinations();
</script>
</body>
</html>