<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Include config
require_once 'includes/config.php';

// Define base URL and canonical URL
$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/career-pathways";

// Enhanced SEO metadata
$page_title = "Career Pathways | EduScore - Discover Your Future Career Path";
$page_description = "Explore careers, choose the right subjects, and align your future with Kenya's CBC pathways. AI-powered career guidance for Kenyan students.";
$page_keywords = "career pathways, career guidance Kenya, CBC curriculum, subject selection, career quiz, Kenyan schools";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// CBC Pathways Data
$cbc_pathways = [
    'stem' => [
        'name' => 'STEM Pathway',
        'full_name' => 'Science, Technology, Engineering, and Mathematics',
        'icon' => 'fas fa-microscope',
        'color' => '#00BFFF',
        'description' => 'Focusing on scientific inquiry, innovation, and problem-solving. Ideal for analytically minded students aiming for technical careers.',
        'tracks' => [
            'Pure Sciences' => 'Emphasizes theoretical foundations in physics, chemistry, and biology.',
            'Applied Sciences' => 'Focuses on real-world applications in agriculture, food science, and biotechnology.',
            'Technical and Engineering' => 'Hands-on engineering and tech skills including robotics and programming.',
            'Careers and Technology Studies' => 'Vocational-oriented tech integration for practical careers.'
        ],
        'core_subjects' => ['English/Kiswahili', 'Mathematics', 'Community Service Learning', 'Physical Education'],
        'elective_subjects' => [
            'Physics' => 'Mechanics, energy, waves',
            'Chemistry' => 'Matter, reactions, lab work',
            'Biology' => 'Life sciences, ecology',
            'Computer Science' => 'Programming, AI, cybersecurity',
            'Aviation Technology' => 'Aerodynamics, flight systems',
            'Agriculture' => 'Sustainable farming, biotech'
        ],
        'career_links' => ['Doctors', 'Engineers', 'Data Scientists', 'Pilots', 'Architects', 'IT Specialists']
    ],
    'social_sciences' => [
        'name' => 'Social Sciences Pathway',
        'full_name' => 'Humanities, Business, and Social Studies',
        'icon' => 'fas fa-globe-africa',
        'color' => '#8B5CF6',
        'description' => 'Nurtures critical thinkers, communicators, and societal leaders, emphasizing human behavior, culture, and economics.',
        'tracks' => [
            'Languages and Literature' => 'Focuses on communication and cultural studies.',
            'Humanities' => 'History, geography, and social studies.',
            'Business Studies' => 'Entrepreneurship and economics.'
        ],
        'core_subjects' => ['English/Kiswahili', 'Mathematics', 'Community Service Learning', 'Physical Education'],
        'elective_subjects' => [
            'History and Citizenship' => 'Global/Kenyan history, governance',
            'Geography' => 'Physical/human environments, GIS',
            'Religious Education' => 'IRE/CRE/HRE, ethics',
            'Literature' => 'Creative writing, analysis',
            'Business Studies' => 'Entrepreneurship, finance',
            'Foreign Languages' => 'French, German for global communication'
        ],
        'career_links' => ['Lawyers', 'Journalists', 'Economists', 'Teachers', 'Psychologists', 'Entrepreneurs']
    ],
    'arts_sports' => [
        'name' => 'Arts and Sports Science Pathway',
        'full_name' => 'Creative Arts, Design, and Sports Science',
        'icon' => 'fas fa-palette',
        'color' => '#10B981',
        'description' => 'Develops talents in performance, design, and athletics, integrating science for professional viability.',
        'tracks' => [
            'Sports' => 'Physical training and sports management.',
            'Visual Arts' => 'Design, sculpture, digital media.',
            'Performing Arts' => 'Music, dance, theater.'
        ],
        'core_subjects' => ['English/Kiswahili', 'Mathematics', 'Community Service Learning', 'Physical Education'],
        'elective_subjects' => [
            'Physical Education and Sports' => 'Training, coaching, nutrition',
            'Visual Arts' => 'Drawing, painting, graphic design',
            'Performing Arts' => 'Music, dance, theater composition',
            'Film and Media Studies' => 'Production, editing'
        ],
        'career_links' => ['Athletes', 'Musicians', 'Filmmakers', 'Designers', 'Coaches', 'Event Managers']
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
  #HERO SECTION
\*-----------------------------------*/
.hero {
    padding-top: 140px;
    padding-bottom: 70px;
    background: linear-gradient(135deg, #e8a84c, #c97e2a);
    position: relative;
    overflow: hidden;
    text-align: center;
}

body.dark-mode .hero {
    background: linear-gradient(135deg, #c48a3c, #a66824);
}

.hero .section-title {
    color: white;
    margin-bottom: 20px;
}

.hero .section-title span {
    color: #2c2418;
}

.hero-text {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.8rem;
    max-width: 700px;
    margin: 0 auto 25px;
}

.hero-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-top: 40px;
    flex-wrap: wrap;
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 20px 30px;
    border-radius: 20px;
    min-width: 130px;
}

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: white;
    font-family: 'League Spartan', "Merriweather", serif;
}

.stat-label {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.95);
}

/*-----------------------------------*\
  #PATHWAYS SECTION
\*-----------------------------------*/
.pathways-section {
    padding: 70px 0;
}

.pathways-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 40px;
}

.pathway-card {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    border-radius: 24px;
    padding: 35px 25px;
    text-align: center;
    transition: all 0.35s ease;
    border: 1px solid rgba(230, 200, 140, 0.4);
    cursor: pointer;
}

.pathway-card:hover {
    transform: translateY(-10px);
    background: rgba(255, 254, 252, 0.95);
    border-color: #e6b450;
}

.pathway-icon {
    width: 80px;
    height: 80px;
    background: #fef0d4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.5rem;
    transition: all 0.3s ease;
}

.pathway-card:nth-child(1) .pathway-icon { color: #00BFFF; }
.pathway-card:nth-child(2) .pathway-icon { color: #8B5CF6; }
.pathway-card:nth-child(3) .pathway-icon { color: #10B981; }

.pathway-card:hover .pathway-icon {
    transform: scale(1.05);
}

.pathway-card h3 {
    font-size: 2rem;
    margin-bottom: 12px;
    color: #2c2418;
}

.pathway-card p {
    font-size: 1.4rem;
    line-height: 1.6;
    color: #5c4b34;
}

.pathway-btn {
    display: inline-block;
    margin-top: 20px;
    padding: 8px 20px;
    background: transparent;
    color: #00BFFF;
    border: 1px solid #00BFFF;
    border-radius: 30px;
    font-weight: 600;
    font-size: 1.3rem;
    transition: all 0.3s ease;
}

.pathway-card:hover .pathway-btn {
    background: #00BFFF;
    color: white;
    transform: translateX(5px);
}

body.dark-mode .pathway-card {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .pathway-card:hover {
    background: rgba(65, 58, 48, 0.92);
}

body.dark-mode .pathway-card h3 {
    color: #f7e5c2;
}

body.dark-mode .pathway-card p {
    color: #cfc3a8;
}

body.dark-mode .pathway-icon {
    background: #6b5538;
}

/*-----------------------------------*\
  #CAREER OPTIONS SECTION
\*-----------------------------------*/
.career-options {
    padding: 70px 0;
    background: rgba(255, 253, 245, 0.5);
    border-radius: 40px;
    margin: 20px 0;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 40px;
}

.option-card {
    background: rgba(255, 253, 248, 0.85);
    backdrop-filter: blur(2px);
    border-radius: 24px;
    padding: 40px 30px;
    text-align: center;
    transition: all 0.35s ease;
    border: 1px solid rgba(230, 200, 140, 0.4);
    text-decoration: none;
    display: block;
}

.option-card:hover {
    transform: translateY(-10px);
    background: rgba(255, 254, 252, 0.95);
    border-color: #e6b450;
}

.option-icon {
    width: 80px;
    height: 80px;
    background: #fef0d4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.5rem;
    color: #00BFFF;
    transition: all 0.3s ease;
}

.option-card:hover .option-icon {
    transform: scale(1.05);
}

.option-card h3 {
    font-size: 1.8rem;
    margin-bottom: 15px;
    color: #2c2418;
}

.option-card p {
    font-size: 1.4rem;
    color: #5c4b34;
    margin-bottom: 20px;
}

.option-btn {
    display: inline-block;
    padding: 10px 25px;
    background: #00BFFF;
    color: white;
    border-radius: 30px;
    font-weight: 600;
    font-size: 1.3rem;
    transition: all 0.3s ease;
}

.option-card:hover .option-btn {
    transform: translateX(5px);
    background: #009ac9;
}

body.dark-mode .career-options {
    background: rgba(30, 28, 22, 0.5);
}

body.dark-mode .option-card {
    background: rgba(50, 45, 38, 0.85);
    border-color: rgba(210, 170, 90, 0.3);
}

body.dark-mode .option-card:hover {
    background: rgba(65, 58, 48, 0.92);
}

body.dark-mode .option-card h3 {
    color: #f7e5c2;
}

body.dark-mode .option-card p {
    color: #cfc3a8;
}

body.dark-mode .option-icon {
    background: #6b5538;
}

/*-----------------------------------*\
  #MODAL
\*-----------------------------------*/
.pathway-modal {
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

.pathway-modal.active {
    display: flex;
}

.modal-content {
    background: rgba(255, 253, 248, 0.98);
    backdrop-filter: blur(10px);
    border-radius: 30px;
    max-width: 700px;
    width: 90%;
    max-height: 80vh;
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

.subject-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 15px 0;
}

.subject-chip {
    background: #fef0d4;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 1.2rem;
    color: #b86f2c;
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

body.dark-mode .subject-chip {
    background: #6b5538;
    color: #f3cd81;
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
    .pathways-grid, .options-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .hero .section-title { font-size: 3rem; }
}

@media (max-width: 768px) {
    .pathways-grid, .options-grid {
        grid-template-columns: 1fr;
    }
    .footer-top {
        grid-template-columns: 1fr;
    }
    .hero-stats {
        flex-direction: column;
        align-items: center;
    }
    .stat-card {
        width: 100%;
        max-width: 200px;
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
                <li class="navbar-item"><a href="career-pathways.php" class="navbar-link active" data-nav-link>Career Pathways</a></li>
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
    <section class="hero">
        <div class="container">
            <div class="hero-content reveal">
                <h1 class="section-title">
                    Discover Your Future <span class="span">Career Path</span>
                </h1>
                <p class="hero-text">
                    Explore careers, choose the right subjects, and align your future with Kenya's CBC pathways.
                </p>
                <div class="hero-stats">
                    <div class="stat-card">
                        <div class="stat-number">3</div>
                        <div class="stat-label">Major Pathways</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Career Options</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Free Guidance</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CBC Pathways Section -->
    <section class="pathways-section">
        <div class="container">
            <p class="section-subtitle">Kenya's CBC Pathways</p>
            <h2 class="section-title">Choose Your <span>Learning Path</span></h2>
            
            <div class="pathways-grid">
                <div class="pathway-card reveal" onclick="showPathwayDetail('stem')">
                    <div class="pathway-icon"><i class="fas fa-microscope"></i></div>
                    <h3>STEM Pathway</h3>
                    <p>Science, Technology, Engineering, and Mathematics</p>
                    <span class="pathway-btn">Explore →</span>
                </div>
                
                <div class="pathway-card reveal" onclick="showPathwayDetail('social_sciences')">
                    <div class="pathway-icon"><i class="fas fa-globe-africa"></i></div>
                    <h3>Social Sciences Pathway</h3>
                    <p>Humanities, Business, and Social Studies</p>
                    <span class="pathway-btn">Explore →</span>
                </div>
                
                <div class="pathway-card reveal" onclick="showPathwayDetail('arts_sports')">
                    <div class="pathway-icon"><i class="fas fa-palette"></i></div>
                    <h3>Arts & Sports Science</h3>
                    <p>Creative Arts, Design, and Sports Science</p>
                    <span class="pathway-btn">Explore →</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Career Options Section -->
    <section class="career-options">
        <div class="container">
            <p class="section-subtitle">How Would You Like to Proceed?</p>
            <h2 class="section-title">Choose Your <span>Discovery Method</span></h2>
            
            <div class="options-grid">
                <a href="career-know-your-goal.php" class="option-card reveal">
                    <div class="option-icon"><i class="fas fa-bullseye"></i></div>
                    <h3>I Know My Career Goal</h3>
                    <p>Search and explore detailed information about specific careers you're interested in.</p>
                    <span class="option-btn">Explore Careers →</span>
                </a>
                
                <a href="career-subject-quiz.php" class="option-card reveal">
                    <div class="option-icon"><i class="fas fa-question-circle"></i></div>
                    <h3>I'm Not Sure Which Subjects to Choose</h3>
                    <p>Take our interactive quiz to discover careers that match your interests and strengths.</p>
                    <span class="option-btn">Take the Quiz →</span>
                </a>
                
                <a href="career-subject-selection.php" class="option-card reveal">
                    <div class="option-icon"><i class="fas fa-book-open"></i></div>
                    <h3>I've Already Chosen My Subjects</h3>
                    <p>Select your subjects and discover careers that match your combination.</p>
                    <span class="option-btn">Find My Careers →</span>
                </a>
            </div>
        </div>
    </section>
</main>

<!-- Pathway Detail Modal -->
<div class="pathway-modal" id="pathwayModal">
    <div class="modal-content">
        <span class="modal-close" onclick="closePathwayModal()">&times;</span>
        <div id="pathwayModalContent"></div>
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

// Pathway Data
const pathwayData = <?php echo json_encode($cbc_pathways); ?>;

function showPathwayDetail(pathwayKey) {
    const pathway = pathwayData[pathwayKey];
    const modal = document.getElementById('pathwayModal');
    const modalContent = document.getElementById('pathwayModalContent');
    
    modalContent.innerHTML = `
        <h2 style="margin-bottom: 10px;">${pathway.name}</h2>
        <p style="color: ${pathway.color}; margin-bottom: 20px; font-weight: 600;">${pathway.full_name}</p>
        <p style="margin-bottom: 20px;">${pathway.description}</p>
        
        <h3 style="margin-bottom: 10px;">Tracks:</h3>
        <ul style="margin-bottom: 20px; margin-left: 20px;">
            ${Object.entries(pathway.tracks).map(([track, desc]) => `<li><strong>${track}:</strong> ${desc}</li>`).join('')}
        </ul>
        
        <h3 style="margin-bottom: 10px;">Core Subjects:</h3>
        <div class="subject-chips" style="margin-bottom: 20px;">
            ${pathway.core_subjects.map(s => `<span class="subject-chip">${s}</span>`).join('')}
        </div>
        
        <h3 style="margin-bottom: 10px;">Elective Subjects:</h3>
        <ul style="margin-bottom: 20px; margin-left: 20px;">
            ${Object.entries(pathway.elective_subjects).map(([subject, desc]) => `<li><strong>${subject}:</strong> ${desc}</li>`).join('')}
        </ul>
        
        <h3 style="margin-bottom: 10px;">Career Links:</h3>
        <div class="subject-chips" style="margin-bottom: 20px;">
            ${pathway.career_links.map(c => `<span class="subject-chip">${c}</span>`).join('')}
        </div>
        
        <button onclick="closePathwayModal()" style="background: #00BFFF; color: white; padding: 10px 25px; border-radius: 30px; font-weight: 600; cursor: pointer; margin-top: 20px;">Close</button>
    `;
    modal.classList.add('active');
}

function closePathwayModal() {
    document.getElementById('pathwayModal').classList.remove('active');
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('pathwayModal');
    if (event.target === modal) {
        closePathwayModal();
    }
}
</script>
</body>
</html>