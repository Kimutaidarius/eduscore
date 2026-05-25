<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once 'includes/config.php';

$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/career-subject-quiz";

$page_title = "Career Compass Quiz | EduScore - Discover Your Ideal Career Path";
$page_description = "Take our smart Career Compass assessment. Evaluate your interests, talents, and aptitude to get personalized career recommendations.";
$page_keywords = "career quiz, career compass, aptitude test, career assessment, career guidance Kenya";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// Comprehensive quiz questions organized by category
$quiz_sections = [
    'interests' => [
        'title' => 'Your Interests',
        'icon' => 'fas fa-heart',
        'color' => '#FF6B6B',
        'description' => 'What activities and subjects naturally draw your attention?',
        'questions' => [
            ['text' => 'I enjoy reading books, writing stories, or expressing myself through words.', 'trait' => 'linguistic'],
            ['text' => 'I love solving puzzles, working with numbers, and logical problems.', 'trait' => 'logical'],
            ['text' => 'I am fascinated by how things work, science experiments, and technology.', 'trait' => 'scientific'],
            ['text' => 'I enjoy drawing, painting, designing, or creating visual art.', 'trait' => 'artistic'],
            ['text' => 'I like playing musical instruments, singing, or composing music.', 'trait' => 'musical'],
            ['text' => 'I enjoy sports, physical activities, and staying active.', 'trait' => 'kinesthetic'],
            ['text' => 'I like helping people, volunteering, and understanding others\' feelings.', 'trait' => 'social'],
            ['text' => 'I enjoy leading teams, organizing events, and taking charge.', 'trait' => 'enterprising'],
            ['text' => 'I prefer working with plants, animals, or being outdoors.', 'trait' => 'naturalist'],
            ['text' => 'I enjoy learning new languages and exploring different cultures.', 'trait' => 'cultural']
        ]
    ],
    'talents' => [
        'title' => 'Your Talents',
        'icon' => 'fas fa-star',
        'color' => '#4ECDC4',
        'description' => 'What comes naturally to you? What skills do you excel at?',
        'questions' => [
            ['text' => 'I am good at communicating ideas clearly and persuading others.', 'trait' => 'communication'],
            ['text' => 'I excel at mathematics, calculations, and analytical thinking.', 'trait' => 'analytical'],
            ['text' => 'I have strong problem-solving skills and enjoy challenges.', 'trait' => 'problem_solving'],
            ['text' => 'I am creative and can come up with innovative solutions.', 'trait' => 'creative'],
            ['text' => 'I pay attention to details and notice things others miss.', 'trait' => 'detail_oriented'],
            ['text' => 'I am good at working with my hands and building things.', 'trait' => 'hands_on'],
            ['text' => 'I learn new software and technology quickly.', 'trait' => 'tech_savvy'],
            ['text' => 'I am empathetic and understand people\'s emotions well.', 'trait' => 'empathetic'],
            ['text' => 'I am organized and good at planning and scheduling.', 'trait' => 'organized'],
            ['text' => 'I am adaptable and can handle unexpected situations well.', 'trait' => 'adaptable']
        ]
    ],
    'aptitude' => [
        'title' => 'Your Aptitude',
        'icon' => 'fas fa-brain',
        'color' => '#45B7D1',
        'description' => 'What are you naturally good at? Where do you have potential to excel?',
        'questions' => [
            ['text' => 'I understand complex concepts quickly and easily.', 'trait' => 'quick_learner'],
            ['text' => 'I can visualize spatial relationships and think in 3D.', 'trait' => 'spatial'],
            ['text' => 'I have good memory and can recall information effectively.', 'trait' => 'memory'],
            ['text' => 'I can analyze data and draw meaningful conclusions.', 'trait' => 'analytical'],
            ['text' => 'I can think critically and evaluate arguments logically.', 'trait' => 'critical_thinking'],
            ['text' => 'I can manage multiple tasks and prioritize effectively.', 'trait' => 'multitasking'],
            ['text' => 'I can work independently and self-motivate.', 'trait' => 'independent'],
            ['text' => 'I can collaborate well in team settings.', 'trait' => 'collaborative'],
            ['text' => 'I can lead and inspire others.', 'trait' => 'leadership'],
            ['text' => 'I can handle pressure and meet deadlines.', 'trait' => 'resilient']
        ]
    ]
];

// Career mapping based on traits
$career_mappings = [
    'linguistic' => ['Journalist', 'Author', 'Lawyer', 'Teacher', 'Editor', 'Content Creator', 'Public Relations Specialist', 'Translator'],
    'logical' => ['Mathematician', 'Accountant', 'Data Analyst', 'Economist', 'Statistician', 'Financial Analyst', 'Actuary', 'Auditor'],
    'scientific' => ['Doctor', 'Engineer', 'Scientist', 'Pharmacist', 'Researcher', 'Lab Technician', 'Environmental Scientist', 'Biotechnologist'],
    'artistic' => ['Graphic Designer', 'Artist', 'Architect', 'Photographer', 'Animator', 'Fashion Designer', 'Interior Designer', 'Art Director'],
    'musical' => ['Musician', 'Music Teacher', 'Sound Engineer', 'Composer', 'Conductor', 'Music Producer', 'Therapist', 'Entertainer'],
    'kinesthetic' => ['Athlete', 'Physical Therapist', 'Dancer', 'Coach', 'Personal Trainer', 'Sports Manager', 'Recreation Director', 'Fitness Instructor'],
    'social' => ['Psychologist', 'Social Worker', 'Counselor', 'Nurse', 'Teacher', 'Human Resources', 'Community Manager', 'Therapist'],
    'enterprising' => ['Entrepreneur', 'Manager', 'Sales Director', 'Marketing Manager', 'Business Consultant', 'Real Estate Agent', 'Event Planner', 'CEO'],
    'naturalist' => ['Farmer', 'Veterinarian', 'Forester', 'Environmental Scientist', 'Gardener', 'Conservationist', 'Zoologist', 'Marine Biologist'],
    'cultural' => ['Diplomat', 'Translator', 'Tour Guide', 'Cultural Officer', 'International Relations Specialist', 'Foreign Language Teacher', 'Travel Consultant'],
    'communication' => ['Journalist', 'Public Speaker', 'Teacher', 'Lawyer', 'Marketing Specialist', 'Sales Representative', 'Broadcaster', 'Writer'],
    'analytical' => ['Data Scientist', 'Engineer', 'Financial Analyst', 'Researcher', 'Statistician', 'Investment Banker', 'Consultant', 'Auditor'],
    'problem_solving' => ['Engineer', 'IT Specialist', 'Consultant', 'Project Manager', 'Detective', 'Troubleshooter', 'Systems Analyst', 'Operations Manager'],
    'creative' => ['Designer', 'Artist', 'Innovator', 'Content Creator', 'Architect', 'Product Developer', 'Advertising Creative', 'Filmmaker'],
    'detail_oriented' => ['Accountant', 'Editor', 'Quality Assurance', 'Auditor', 'Legal Assistant', 'Copy Editor', 'Data Entry Specialist', 'Proofreader'],
    'hands_on' => ['Mechanic', 'Carpenter', 'Electrician', 'Plumber', 'Technician', 'Chef', 'Builder', 'Craftsman'],
    'tech_savvy' => ['Software Developer', 'IT Manager', 'Web Developer', 'Cybersecurity Analyst', 'Network Administrator', 'Tech Support', 'Systems Engineer'],
    'empathetic' => ['Counselor', 'Psychologist', 'Social Worker', 'Nurse', 'Teacher', 'Human Resources', 'Therapist', 'Customer Service'],
    'organized' => ['Project Manager', 'Administrator', 'Event Planner', 'Executive Assistant', 'Operations Manager', 'Logistics Coordinator', 'Office Manager'],
    'adaptable' => ['Project Manager', 'Consultant', 'Entrepreneur', 'Event Coordinator', 'Crisis Manager', 'Generalist', 'Change Manager'],
    'quick_learner' => ['Consultant', 'Researcher', 'Analyst', 'Entrepreneur', 'Innovator', 'Strategist', 'Problem Solver'],
    'spatial' => ['Architect', 'Pilot', 'Surveyor', 'Civil Engineer', 'Urban Planner', 'Graphic Designer', 'Interior Designer', 'Cartographer'],
    'memory' => ['Lawyer', 'Historian', 'Librarian', 'Teacher', 'Researcher', 'Archivist', 'Translator', 'Medical Professional'],
    'critical_thinking' => ['Lawyer', 'Judge', 'Analyst', 'Consultant', 'Researcher', 'Strategist', 'Policy Advisor', 'Journalist'],
    'multitasking' => ['Project Manager', 'Executive Assistant', 'Event Planner', 'Air Traffic Controller', 'Emergency Dispatcher', 'Operations Manager'],
    'independent' => ['Writer', 'Artist', 'Freelancer', 'Consultant', 'Researcher', 'Entrepreneur', 'Remote Worker', 'Designer'],
    'collaborative' => ['Teacher', 'Social Worker', 'Project Manager', 'Team Lead', 'HR Manager', 'Community Organizer', 'Coach'],
    'leadership' => ['Manager', 'CEO', 'Team Lead', 'Director', 'Supervisor', 'Executive', 'Entrepreneur', 'Administrator'],
    'resilient' => ['Doctor', 'Nurse', 'Emergency Responder', 'Military', 'Police Officer', 'Firefighter', 'Crisis Counselor', 'Social Worker']
];

// Pathway recommendations based on dominant traits
$pathway_recommendations = [
    'STEM' => [
        'description' => 'Science, Technology, Engineering & Mathematics',
        'careers' => ['Engineer', 'Doctor', 'Data Scientist', 'Software Developer', 'Researcher', 'Architect', 'Pilot', 'Scientist'],
        'color' => '#00BFFF',
        'subjects' => ['Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Studies']
    ],
    'Social Sciences' => [
        'description' => 'Humanities, Business & Social Studies',
        'careers' => ['Lawyer', 'Teacher', 'Psychologist', 'Economist', 'Journalist', 'Social Worker', 'Business Manager', 'Diplomat'],
        'color' => '#8B5CF6',
        'subjects' => ['History', 'Geography', 'CRE/IRE', 'Business Studies', 'English']
    ],
    'Arts & Sports' => [
        'description' => 'Creative Arts, Design & Sports Science',
        'careers' => ['Artist', 'Musician', 'Athlete', 'Designer', 'Coach', 'Actor', 'Photographer', 'Dancer'],
        'color' => '#10B981',
        'subjects' => ['Art and Design', 'Music', 'Physical Education', 'Theatre', 'Home Science']
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
        
        .h1,
        .h2,
        .h3 {
            color: var(--eerie-black-1);
            font-family: var(--ff-league_spartan);
            line-height: 1.2;
        }
        
        .h1 { font-size: var(--fs-1); }
        .h2 { font-size: var(--fs-2); }
        
        .section-subtitle {
            font-size: var(--fs-5);
            text-transform: uppercase;
            font-weight: var(--fw-500);
            letter-spacing: 1px;
            text-align: center;
            margin-block-end: 15px;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 15px;
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
        
        /*============================================
          #QUIZ CONTAINER
        ============================================*/
        .quiz-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        /* Section Cards */
        .quiz-section {
            background: var(--white);
            border-radius: var(--radius-15);
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: var(--shadow-1);
            border: 1px solid var(--platinum);
        }
        
        .section-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, var(--section-color) 0%, var(--section-color-dark) 100%);
            color: white;
        }
        
        .section-header i {
            font-size: 2rem;
            margin-right: 15px;
        }
        
        .section-header h2 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            color: white;
        }
        
        .section-header p {
            font-size: 1.3rem;
            opacity: 0.9;
        }
        
        .questions-wrapper {
            padding: 20px 30px;
        }
        
        .question-item {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--platinum);
        }
        
        .question-text {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--eerie-black-1);
            font-weight: 500;
        }
        
        .rating-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .rating-btn {
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--isabelline);
            color: var(--gray-web);
            font-weight: 500;
            font-size: 1.3rem;
            border: none;
        }
        
        .rating-btn:hover {
            transform: translateY(-2px);
        }
        
        .rating-btn.selected {
            background: var(--section-color);
            color: white;
        }
        
        /* Progress Bar */
        .quiz-progress {
            margin-bottom: 30px;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            padding: 12px;
            background: var(--isabelline);
            border-radius: 10px;
            margin: 0 5px;
            font-weight: 600;
            color: var(--gray-web);
            transition: all 0.3s ease;
        }
        
        .progress-step.active {
            background: #00BFFF;
            color: white;
        }
        
        .progress-step.completed {
            background: #10B981;
            color: white;
        }
        
        .progress-bar-container {
            height: 8px;
            background: var(--platinum);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .progress-fill {
            height: 100%;
            background: #00BFFF;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        /* Results Dashboard */
        .results-dashboard {
            display: none;
            background: var(--white);
            border-radius: var(--radius-15);
            padding: 40px;
            box-shadow: var(--shadow-2);
        }
        
        .results-dashboard.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .results-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .confidence-score {
            font-size: 4rem;
            font-weight: 700;
            color: #00BFFF;
        }
        
        .pathway-card {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(0, 191, 255, 0.02));
            border-radius: var(--radius-15);
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid #00BFFF;
        }
        
        .career-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }
        
        .career-tag {
            background: rgba(0, 191, 255, 0.1);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 1.3rem;
            color: #00BFFF;
        }
        
        .subject-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }
        
        .subject-tag {
            background: var(--isabelline);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 1.2rem;
            color: var(--gray-web);
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        
        .action-btn {
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .action-btn.primary {
            background: #00BFFF;
            color: white;
        }
        
        .action-btn.secondary {
            background: var(--isabelline);
            color: var(--eerie-black-1);
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
        }
        
        /* Navigation Buttons */
        .quiz-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .nav-btn {
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .nav-btn.prev {
            background: var(--isabelline);
            color: var(--gray-web);
        }
        
        .nav-btn.next {
            background: #00BFFF;
            color: white;
        }
        
        .nav-btn.submit {
            background: #10B981;
            color: white;
        }
        
        .nav-btn:hover {
            transform: translateY(-2px);
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
            .rating-options {
                gap: 8px;
            }
            
            .rating-btn {
                padding: 6px 12px;
                font-size: 1.1rem;
            }
            
            .question-text {
                font-size: 1.3rem;
            }
            
            .progress-step {
                font-size: 1rem;
                padding: 8px;
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
            <h1>Career <span>Compass</span></h1>
            <p>Discover your ideal career path by evaluating your Interests, Talents, and Aptitude</p>
        </div>
    </section>

    <!-- Quiz Container -->
    <div class="quiz-container">
        <!-- Progress Indicator -->
        <div class="quiz-progress">
            <div class="progress-steps" id="progressSteps">
                <div class="progress-step active" data-step="0">📍 Interests</div>
                <div class="progress-step" data-step="1">⭐ Talents</div>
                <div class="progress-step" data-step="2">🧠 Aptitude</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-fill" id="quizProgress"></div>
            </div>
        </div>

        <!-- Quiz Sections -->
        <div id="quizSectionsContainer"></div>

        <!-- Results Dashboard -->
        <div class="results-dashboard" id="resultsDashboard">
            <div class="results-header">
                <h2>Your Career Compass Results</h2>
                <div class="confidence-score" id="confidenceScore"></div>
                <p>Based on your responses, here's your personalized career guidance</p>
            </div>
            <div id="resultsContent"></div>
            <div class="action-buttons">
                <button class="action-btn secondary" onclick="resetQuiz()">Take Quiz Again</button>
                <a href="career-know-your-goal.php" class="action-btn primary">Explore Careers →</a>
            </div>
        </div>
    </div>
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
    if (window.scrollY > 100) {
        header.classList.add("active");
        backTopBtn.classList.add("active");
    } else {
        header.classList.remove("active");
        backTopBtn.classList.remove("active");
    }
});

// Set active nav link
const currentPage = window.location.pathname.split('/').pop();
document.querySelectorAll('.navbar-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPage || (currentPage === '' && href === 'index.php')) {
        link.classList.add('active');
    }
});

// Quiz Data
const quizSections = <?php echo json_encode($quiz_sections); ?>;
const careerMappings = <?php echo json_encode($career_mappings); ?>;
const pathwayRecommendations = <?php echo json_encode($pathway_recommendations); ?>;

let currentSection = 0;
let userResponses = {
    interests: [],
    talents: [],
    aptitude: []
};

// Initialize Quiz
function initQuiz() {
    renderQuizSection();
    updateProgress();
}

function renderQuizSection() {
    const container = document.getElementById('quizSectionsContainer');
    const sections = ['interests', 'talents', 'aptitude'];
    const sectionKey = sections[currentSection];
    const section = quizSections[sectionKey];
    
    let html = `
        <div class="quiz-section" style="--section-color: ${section.color}; --section-color-dark: ${section.color}cc;">
            <div class="section-header" style="background: linear-gradient(135deg, ${section.color}, ${section.color}cc);">
                <div>
                    <i class="${section.icon}"></i>
                    <h2>${section.title}</h2>
                    <p>${section.description}</p>
                </div>
            </div>
            <div class="questions-wrapper">
    `;
    
    section.questions.forEach((question, idx) => {
        const savedValue = userResponses[sectionKey][idx] || 0;
        html += `
            <div class="question-item">
                <div class="question-text">${question.text}</div>
                <div class="rating-options">
                    <button class="rating-btn ${savedValue === 1 ? 'selected' : ''}" onclick="setRating(${currentSection}, ${idx}, 1, this)">Strongly Disagree</button>
                    <button class="rating-btn ${savedValue === 2 ? 'selected' : ''}" onclick="setRating(${currentSection}, ${idx}, 2, this)">Disagree</button>
                    <button class="rating-btn ${savedValue === 3 ? 'selected' : ''}" onclick="setRating(${currentSection}, ${idx}, 3, this)">Neutral</button>
                    <button class="rating-btn ${savedValue === 4 ? 'selected' : ''}" onclick="setRating(${currentSection}, ${idx}, 4, this)">Agree</button>
                    <button class="rating-btn ${savedValue === 5 ? 'selected' : ''}" onclick="setRating(${currentSection}, ${idx}, 5, this)">Strongly Agree</button>
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
        </div>
        <div class="quiz-navigation">
            <button class="nav-btn prev" onclick="previousSection()" ${currentSection === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : ''}>Previous</button>
            <button class="nav-btn next" onclick="nextSection()">Next</button>
        </div>
    `;
    
    container.innerHTML = html;
}

function setRating(sectionIdx, questionIdx, value, btnElement) {
    const sections = ['interests', 'talents', 'aptitude'];
    const sectionKey = sections[sectionIdx];
    
    if (!userResponses[sectionKey]) {
        userResponses[sectionKey] = [];
    }
    userResponses[sectionKey][questionIdx] = value;
    
    // Update UI
    const container = btnElement.parentElement;
    container.querySelectorAll('.rating-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    btnElement.classList.add('selected');
}

function previousSection() {
    if (currentSection > 0) {
        currentSection--;
        renderQuizSection();
        updateProgress();
    }
}

function nextSection() {
    const sections = ['interests', 'talents', 'aptitude'];
    const sectionKey = sections[currentSection];
    const sectionResponses = userResponses[sectionKey] || [];
    const totalQuestions = quizSections[sectionKey].questions.length;
    const answeredCount = sectionResponses.filter(v => v > 0).length;
    
    if (answeredCount < totalQuestions) {
        alert(`Please answer all questions in this section before continuing. (${answeredCount}/${totalQuestions} answered)`);
        return;
    }
    
    if (currentSection < 2) {
        currentSection++;
        renderQuizSection();
        updateProgress();
    } else {
        // Calculate and show results
        calculateResults();
    }
}

function updateProgress() {
    // Update progress steps
    document.querySelectorAll('.progress-step').forEach((step, idx) => {
        if (idx < currentSection) {
            step.classList.add('completed');
            step.classList.remove('active');
        } else if (idx === currentSection) {
            step.classList.add('active');
            step.classList.remove('completed');
        } else {
            step.classList.remove('active', 'completed');
        }
    });
    
    // Update progress bar
    const progressPercent = ((currentSection + 1) / 3) * 100;
    document.getElementById('quizProgress').style.width = `${progressPercent}%`;
}

function calculateResults() {
    // Calculate trait scores
    const traitScores = {};
    const sections = ['interests', 'talents', 'aptitude'];
    
    sections.forEach(sectionKey => {
        const section = quizSections[sectionKey];
        const responses = userResponses[sectionKey] || [];
        
        section.questions.forEach((question, idx) => {
            const rating = responses[idx] || 0;
            const trait = question.trait;
            if (!traitScores[trait]) traitScores[trait] = 0;
            traitScores[trait] += rating;
        });
    });
    
    // Get top traits
    const sortedTraits = Object.entries(traitScores).sort((a, b) => b[1] - a[1]);
    const topTraits = sortedTraits.slice(0, 5);
    
    // Collect career recommendations
    let recommendedCareers = [];
    topTraits.forEach(([trait, score]) => {
        if (careerMappings[trait]) {
            recommendedCareers.push(...careerMappings[trait]);
        }
    });
    
    // Remove duplicates and limit
    recommendedCareers = [...new Set(recommendedCareers)].slice(0, 12);
    
    // Determine recommended pathway
    let pathwayScores = { 'STEM': 0, 'Social Sciences': 0, 'Arts & Sports': 0 };
    
    topTraits.forEach(([trait, score]) => {
        if (['scientific', 'logical', 'analytical', 'problem_solving', 'tech_savvy', 'spatial'].includes(trait)) {
            pathwayScores['STEM'] += score;
        }
        if (['linguistic', 'social', 'enterprising', 'empathetic', 'leadership', 'cultural'].includes(trait)) {
            pathwayScores['Social Sciences'] += score;
        }
        if (['artistic', 'musical', 'kinesthetic', 'creative', 'naturalist', 'hands_on'].includes(trait)) {
            pathwayScores['Arts & Sports'] += score;
        }
    });
    
    const topPathway = Object.entries(pathwayScores).sort((a, b) => b[1] - a[1])[0][0];
    const confidence = Math.round((pathwayScores[topPathway] / 100) * 100);
    const pathway = pathwayRecommendations[topPathway];
    
    // Display results
    displayResults(topPathway, pathway, recommendedCareers, confidence);
}

function displayResults(pathwayKey, pathway, careers, confidence) {
    // Hide quiz sections, show results
    document.getElementById('quizSectionsContainer').style.display = 'none';
    document.getElementById('resultsDashboard').classList.add('active');
    document.querySelector('.quiz-progress').style.display = 'none';
    
    document.getElementById('confidenceScore').textContent = `${confidence}% Match`;
    
    const careersHtml = careers.slice(0, 8).map(career => `<span class="career-tag">${career}</span>`).join('');
    
    const resultsContent = `
        <div class="pathway-card" style="border-left-color: ${pathway.color};">
            <h3>Your Recommended Pathway</h3>
            <p style="font-size: 1.8rem; font-weight: 700; margin: 10px 0; color: ${pathway.color};">${pathwayKey}</p>
            <p>${pathway.description}</p>
            <div class="career-tags">
                ${pathway.careers.map(c => `<span class="career-tag" style="background: ${pathway.color}15; color: ${pathway.color};">${c}</span>`).join('')}
            </div>
        </div>
        
        <div class="pathway-card" style="border-left-color: #10B981;">
            <h3>Recommended Subjects for ${pathwayKey}</h3>
            <div class="subject-tags">
                ${pathway.subjects.map(s => `<span class="subject-tag">${s}</span>`).join('')}
            </div>
        </div>
        
        <div class="pathway-card" style="border-left-color: #8B5CF6;">
            <h3>Top Career Recommendations for You</h3>
            <div class="career-tags">
                ${careersHtml}
            </div>
            <p style="margin-top: 15px; font-size: 1.3rem; color: var(--gray-web);">Based on your interests, talents, and aptitude assessment</p>
        </div>
    `;
    
    document.getElementById('resultsContent').innerHTML = resultsContent;
}

function resetQuiz() {
    // Reset all data
    currentSection = 0;
    userResponses = {
        interests: [],
        talents: [],
        aptitude: []
    };
    
    // Show quiz sections again
    document.getElementById('quizSectionsContainer').style.display = 'block';
    document.querySelector('.quiz-progress').style.display = 'block';
    document.getElementById('resultsDashboard').classList.remove('active');
    
    // Re-render quiz
    renderQuizSection();
    updateProgress();
}

// Initialize quiz on page load
document.addEventListener('DOMContentLoaded', () => {
    initQuiz();
});
</script>

</body>
</html>