<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once 'includes/config.php';

$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/career-know-your-goal";

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

// Additional career options from the Ministry of Education data
$additional_careers = [
    'Agriculture' => ['Farm Manager', 'Agricultural Officer', 'Agronomist', 'Horticulturist', 'Veterinarian', 'Food Scientist', 'Agricultural Engineer', 'Fisheries Officer', 'Livestock Officer', 'Crop Protection Specialist'],
    'Sports & Recreation' => ['Sports Coach', 'Physical Education Teacher', 'Athlete', 'Sports Administrator', 'Fitness Trainer', 'Sports Psychologist', 'Recreation Manager', 'Sports Journalist', 'Event Coordinator', 'Referee/Umpire'],
    'Pure Sciences' => ['Chemist', 'Biologist', 'Physicist', 'Lab Technician', 'Environmental Scientist', 'Pharmacist', 'Medical Researcher', 'Forensic Scientist', 'Geologist', 'Meteorologist'],
    'Engineering & Technical' => ['Civil Engineer', 'Mechanical Engineer', 'Electrical Engineer', 'Architect', 'Surveyor', 'Quantity Surveyor', 'Aviation Engineer', 'Automotive Engineer', 'Building Contractor', 'Power Systems Engineer'],
    'Languages' => ['English Teacher', 'Kiswahili Teacher', 'Translator', 'Interpreter', 'Editor', 'Content Writer', 'Linguist', 'Language Specialist', 'Copywriter', 'Journalist']
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
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        .shape {
            position: absolute;
            display: none;
        }
        
        .h1,
        .h2,
        .h3 {
            color: var(--eerie-black-1);
            font-family: var(--ff-league_spartan);
            line-height: 1;
        }
        
        .h1,
        .h2 { font-weight: var(--fw-600); }
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
        
        .btn {
            background-color: var(--kappel);
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: var(--fs-4);
            display: flex;
            align-items: center;
            gap: 7px;
            max-width: max-content;
            padding: 10px 20px;
            border-radius: var(--radius-5);
            overflow: hidden;
            position: relative;
            z-index: 1;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: var(--gradient);
            z-index: -1;
            border-radius: inherit;
            transform: translateX(-100%);
            transition: var(--transition-2);
        }
        
        .btn:is(:hover, :focus)::before { transform: translateX(0); }
        
        .img-holder {
            aspect-ratio: var(--width) / var(--height);
            background-color: var(--light-gray);
            overflow: hidden;
        }
        
        .img-cover {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .section-subtitle {
            font-size: var(--fs-5);
            text-transform: uppercase;
            font-weight: var(--fw-500);
            letter-spacing: 1px;
            text-align: center;
            margin-block-end: 15px;
        }
        
        .section-text {
            font-size: var(--fs-5);
            text-align: center;
            margin-block: 15px 25px;
        }
        
        .grid-list {
            display: grid;
            gap: 30px;
        }
        
        /*-----------------------------------*\
          #HEADER
        \*-----------------------------------*/
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: var(--shadow-1);
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .header.active {
            transform: translateY(-100%);
            animation: slideIn 0.5s ease forwards;
        }
        
        @keyframes slideIn {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(0); }
        }
        
        body.dark-mode .header {
            background: rgba(15, 23, 42, 0.95);
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            padding: 14px 32px;
            transition: padding 0.25s ease;
        }
        
        .logo {
            flex-shrink: 0;
            line-height: 0;
        }
        
        .logo img {
            height: 44px;
            transition: height 0.25s ease;
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
            list-style: none;
        }
        
        .navbar-link {
            font-weight: 500;
            color: var(--eerie-black-1);
            transition: color 0.2s ease;
            position: relative;
            padding: 8px 0;
            font-size: 1.55rem;
            text-decoration: none;
        }
        
        body.dark-mode .navbar-link {
            color: #e2e8f0;
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
        
        .navbar-link:hover::after,
        .navbar-link.active::after {
            width: 100%;
        }
        
        .navbar-link.active {
            color: #00BFFF;
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
            padding: 10px;
            border-radius: 50%;
            width: 42px;
            height: 42px;
            color: var(--eerie-black-1);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Both icons inside toggle - hidden/shown via CSS based on body class */
        .theme-toggle i {
            position: absolute;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        
        /* Default light mode: show moon, hide sun */
        .theme-toggle .fa-moon {
            opacity: 1;
            transform: scale(1);
        }
        
        .theme-toggle .fa-sun {
            opacity: 0;
            transform: scale(0.5);
        }
        
        /* Dark mode: hide moon, show sun */
        body.dark-mode .theme-toggle .fa-moon {
            opacity: 0;
            transform: scale(0.5);
        }
        
        body.dark-mode .theme-toggle .fa-sun {
            opacity: 1;
            transform: scale(1);
        }
        
        .portal-btn {
            padding: 9px 22px;
            border-radius: 40px;
            font-weight: 600;
            text-decoration: none;
            font-size: 1.35rem;
        }
        
        .portal-btn-analytics {
            background: #00BFFF;
            color: #ffffff;
        }
        
        .portal-btn-finance {
            background: rgba(0, 191, 255, 0.08);
            color: #00BFFF;
            border: 1px solid rgba(0, 191, 255, 0.4);
        }
        
        .menu-btn {
            display: none;
            background: rgba(0, 0, 0, 0.04);
            border: none;
            font-size: 2.2rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 12px;
            width: 44px;
            height: 44px;
        }
        
        @media (max-width: 991px) {
            .navbar {
                position: fixed;
                top: 0;
                right: -100%;
                width: 85%;
                height: 100vh;
                background: rgba(255, 255, 255, 0.96);
                backdrop-filter: blur(24px);
                z-index: 1001;
                transition: right 0.35s ease;
                padding: 24px 20px;
                flex-direction: column;
            }
            
            .navbar.active {
                right: 0;
            }
            
            .navbar-list {
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }
            
            .navbar-link {
                display: block;
                padding: 14px 16px;
            }
            
            .portal-buttons-header {
                display: none;
            }
            
            .menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
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
                text-align: center;
                text-decoration: none;
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
                padding: 0;
                overflow: visible;
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
        
        /*============================================
          #PAGE HEADER
        ============================================*/
        .page-header {
            padding-top: 140px;
            padding-bottom: 60px;
            background: linear-gradient(135deg, #00BFFF 0%, #0099cc 100%);
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 3.5rem;
            color: white;
            margin-bottom: 15px;
        }
        
        .page-header h1 span {
            color: #FFD700;
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
            gap: 40px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: var(--radius-15);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: white;
        }
        
        .stat-label {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        /*============================================
          #CATEGORY FILTERS
        ============================================*/
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
            background: var(--white);
            border: 2px solid var(--platinum);
            color: var(--gray-web);
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
        
        /*============================================
          #SEARCH SECTION
        ============================================*/
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
            border: 2px solid var(--platinum);
            border-radius: 50px;
            font-size: 1.6rem;
            background: var(--white);
            color: var(--eerie-black-1);
        }
        
        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-web);
        }
        
        /*============================================
          #CAREER CARDS GRID
        ============================================*/
        .career-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
        }
        
        .career-card {
            background: var(--white);
            border-radius: var(--radius-15);
            padding: 25px;
            transition: all 0.3s ease;
            border: 1px solid var(--platinum);
            cursor: pointer;
            position: relative;
        }
        
        .career-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2);
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
        
        .category-badge.arts { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .category-badge.business { background: rgba(139, 92, 246, 0.1); color: #8B5CF6; }
        .category-badge.technology { background: rgba(0, 191, 255, 0.1); color: #00BFFF; }
        
        .career-icon {
            width: 60px;
            height: 60px;
            background: rgba(0, 191, 255, 0.1);
            border-radius: var(--radius-circle);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 2rem;
            color: #00BFFF;
        }
        
        .career-card h3 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .career-card p {
            font-size: 1.3rem;
            color: var(--gray-web);
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
            margin-top: 10px;
        }
        
        /*============================================
          #CAREER MODAL
        ============================================*/
        .career-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 1002;
            align-items: center;
            justify-content: center;
        }
        
        .career-modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--white);
            max-width: 800px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            border-radius: var(--radius-15);
            padding: 30px;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2rem;
            cursor: pointer;
            color: var(--gray-web);
        }
        
        .modal-category-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
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
        
        @media (max-width: 991px) {
            .career-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .page-header h1 {
                font-size: 2.8rem;
            }
        }
        
        @media (max-width: 768px) {
            .career-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
                font-size: 2.2rem;
            }
            
            .footer-top {
                grid-template-columns: 1fr;
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
                <div class="stat-item"><div class="stat-number">350+</div><div class="stat-label">Career Options</div></div>
                <div class="stat-item"><div class="stat-number">25+</div><div class="stat-label">Subject Areas</div></div>
                <div class="stat-item"><div class="stat-number">3</div><div class="stat-label">Major Pathways</div></div>
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
                <div class="career-card" data-category="<?php echo $career['category']; ?>" data-title="<?php echo htmlspecialchars($career['title']); ?>" onclick="showCareerDetail('<?php echo htmlspecialchars($career['title']); ?>', '<?php echo $career['category']; ?>', '<?php echo htmlspecialchars($career['category_name']); ?>', '<?php echo htmlspecialchars($career['subject_area']); ?>', '<?php echo htmlspecialchars(addslashes($career['description'])); ?>', '<?php echo htmlspecialchars(addslashes($career['requirements'])); ?>')">
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
    if (window.scrollY > 100) {
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
        <h2 style="color: var(--eerie-black-1); margin-bottom: 10px;">${title}</h2>
        <div class="modal-category-badge ${categoryClass}" style="background: ${categoryColor}10; color: ${categoryColor};">${categoryName}</div>
        <p style="margin-bottom: 15px;"><strong>Subject Area:</strong> ${subjectArea}</p>
        <h3 style="margin-bottom: 10px;">Career Description</h3>
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
        <button class="quiz-btn next" onclick="closeModal()" style="margin-top: 20px; padding: 10px 25px; background: ${categoryColor}; color: white; border: none; border-radius: 25px; cursor: pointer;">Close</button>
    `;
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('careerModal').classList.remove('active');
}
</script>

</body>
</html>