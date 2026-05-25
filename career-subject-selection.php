<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once 'includes/config.php';

$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/career-subject-selection";

$page_title = "Subject Combination Explorer | EduScore - Find Your Career Path";
$page_description = "Select your subject combination to discover eligible careers, university programs, and pathways based on Kenya's CBC curriculum.";
$page_keywords = "subject combination, career pathways, CBC curriculum, subject selection, Kenyan schools";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// Comprehensive subject combination data from Ministry of Education
$subject_combinations = [
    // ARTS & SPORTS - ARTS Track
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Arabic'], 'careers' => ['Fine Artist', 'Actor', 'Translator', 'Art Director']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Biology'], 'careers' => ['Medical Illustrator', 'Art Therapist', 'Biomedical Artist']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Business Studies'], 'careers' => ['Art Gallery Manager', 'Creative Entrepreneur', 'Arts Administrator']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Computer Studies'], 'careers' => ['Digital Artist', 'Game Designer', 'VFX Artist', 'Animator']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'CRE/IRE/HRE'], 'careers' => ['Religious Art Curator', 'Church Musician', 'Faith-based Artist']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Fasihi ya Kiswahili'], 'careers' => ['Swahili Playwright', 'Cultural Storyteller', 'Theatre Director']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'French'], 'careers' => ['International Art Dealer', 'French Theatre Director', 'Cultural Attaché']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'General Science'], 'careers' => ['Science Illustrator', 'Museum Curator', 'Scientific Animator']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Geography'], 'careers' => ['Location Scout', 'Environmental Artist', 'Travel Photographer']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'German'], 'careers' => ['European Art Specialist', 'German Theatre Consultant', 'Cultural Exchange Coordinator']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'History & Citizenship'], 'careers' => ['Historical Costume Designer', 'Period Film Consultant', 'Museum Historian']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Literature in English'], 'careers' => ['Literary Critic', 'Stage Playwright', 'Book Illustrator']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Mandarin'], 'careers' => ['Asian Art Specialist', 'International Film Producer', 'Cultural Liaison']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Advanced Mathematics'], 'careers' => ['Architectural Designer', 'Set Designer', 'Digital Modeling Artist']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Fine Arts', 'Theatre & Film', 'Sports & Recreation'], 'careers' => ['Sports Photographer', 'Event Designer', 'Stadium Artist']],

    // ARTS & SPORTS - Music & Dance Track
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Arabic'], 'careers' => ['Middle Eastern Music Specialist', 'Cultural Performer', 'Music Ethnologist']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Biology'], 'careers' => ['Music Therapist', 'Dance Movement Therapist', 'Biomechanics Specialist']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Business Studies'], 'careers' => ['Arts Manager', 'Talent Agent', 'Event Promoter', 'Record Label Executive']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Computer Studies'], 'careers' => ['Music Producer', 'Digital Composer', 'Sound Engineer', 'MIDI Specialist']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'CRE/IRE/HRE'], 'careers' => ['Church Choir Director', 'Worship Leader', 'Sacred Music Composer']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Fasihi ya Kiswahili'], 'careers' => ['Taarab Music Specialist', 'Swahili Dance Choreographer', 'Cultural Storyteller']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'French'], 'careers' => ['French Music Specialist', 'Ballet Choreographer', 'International Arts Liaison']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'General Science'], 'careers' => ['Acoustics Specialist', 'Music Technology Expert', 'Sound Scientist']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Geography'], 'careers' => ['World Music Specialist', 'Cultural Geographer', 'Tourism Performer']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'German'], 'careers' => ['German Opera Specialist', 'Classical Music Consultant', 'Cultural Exchange Artist']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'History & Citizenship'], 'careers' => ['Historical Dance Specialist', 'Period Music Performer', 'Cultural Heritage Officer']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Literature in English'], 'careers' => ['Librettist', 'Musical Theatre Director', 'Poetry Performer']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Mandarin'], 'careers' => ['Chinese Music Specialist', 'International Cultural Exchange', 'Asian Performing Arts']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Advanced Mathematics'], 'careers' => ['Music Theory Specialist', 'Digital Sound Engineer', 'Acoustic Designer']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Sports & Recreation'], 'careers' => ['Sports Entertainment Choreographer', 'Half-time Show Director']],
    ['pathway' => 'ARTS & SPORTS', 'track' => 'ARTS', 'subjects' => ['Music & Dance', 'Fine Arts', 'Theatre & Film'], 'careers' => ['Musical Theatre Performer', 'Film Score Composer', 'Stage Choreographer']],

    // STEM - Applied Sciences
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Aviation'], 'careers' => ['Agricultural Aviation Specialist', 'Agribusiness Pilot', 'Crop Duster Pilot']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Biology'], 'careers' => ['Agricultural Economist', 'Agribusiness Manager', 'Farm Manager']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Building Construction'], 'careers' => ['Agricultural Infrastructure Developer', 'Farm Construction Manager']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Chemistry'], 'careers' => ['Agricultural Chemist', 'Fertilizer Production Manager', 'Agrochemical Specialist']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Computer Studies'], 'careers' => ['AgriTech Entrepreneur', 'Precision Agriculture Specialist', 'Farm Management Software Developer']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Electricity'], 'careers' => ['Agricultural Electrification Specialist', 'Farm Energy Consultant']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'General Science'], 'careers' => ['Agricultural Scientist', 'Farm Operations Manager', 'Agricultural Consultant']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Geography'], 'careers' => ['Agricultural Land Use Planner', 'Farm Location Consultant']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Marine and fisheries'], 'careers' => ['Aquaculture Business Manager', 'Fisheries Entrepreneur']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Advanced Mathematics'], 'careers' => ['Agricultural Statistician', 'Farm Data Analyst']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Metal work'], 'careers' => ['Agricultural Equipment Dealer', 'Farm Machinery Business Owner']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Physics'], 'careers' => ['Agricultural Physics Specialist', 'Farm Technology Consultant']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Power Mechanics'], 'careers' => ['Agricultural Machinery Business', 'Tractor Dealership Manager']],
    ['pathway' => 'STEM', 'track' => 'APPLIED SCIENCES', 'subjects' => ['Agriculture', 'Business studies', 'Woodwork'], 'careers' => ['Farm Structure Business Owner', 'Agricultural Building Contractor']],

    // STEM - Pure Sciences
    ['pathway' => 'STEM', 'track' => 'PURE SCIENCES', 'subjects' => ['Advanced Mathematics', 'Biology', 'Chemistry'], 'careers' => ['Medical Doctor', 'Pharmacist', 'Biochemist', 'Researcher']],
    ['pathway' => 'STEM', 'track' => 'PURE SCIENCES', 'subjects' => ['Advanced Mathematics', 'Biology', 'Physics'], 'careers' => ['Biophysicist', 'Medical Physicist', 'Radiologist', 'Biomedical Engineer']],
    ['pathway' => 'STEM', 'track' => 'PURE SCIENCES', 'subjects' => ['Advanced Mathematics', 'Chemistry', 'Physics'], 'careers' => ['Chemical Engineer', 'Materials Scientist', 'Petroleum Engineer', 'Analytical Chemist']],
    ['pathway' => 'STEM', 'track' => 'PURE SCIENCES', 'subjects' => ['Biology', 'Chemistry', 'Physics'], 'careers' => ['Doctor', 'Dentist', 'Veterinarian', 'Forensic Scientist', 'Environmental Scientist']],
    ['pathway' => 'STEM', 'track' => 'PURE SCIENCES', 'subjects' => ['Advanced Mathematics', 'Chemistry', 'Computer Studies'], 'careers' => ['Computational Chemist', 'Cheminformatics Specialist', 'Data Scientist']],
    ['pathway' => 'STEM', 'track' => 'PURE SCIENCES', 'subjects' => ['Advanced Mathematics', 'Physics', 'Computer Studies'], 'careers' => ['Software Engineer', 'Computer Hardware Engineer', 'Robotics Engineer', 'AI Specialist']],

    // STEM - Technical Studies
    ['pathway' => 'STEM', 'track' => 'TECHNICAL STUDIES', 'subjects' => ['Aviation', 'Business Studies', 'Physics'], 'careers' => ['Aviation Manager', 'Airline Operations Manager', 'Aerospace Business Consultant']],
    ['pathway' => 'STEM', 'track' => 'TECHNICAL STUDIES', 'subjects' => ['Building Construction', 'Business Studies', 'Geography'], 'careers' => ['Construction Project Manager', 'Real Estate Developer', 'Quantity Surveyor']],
    ['pathway' => 'STEM', 'track' => 'TECHNICAL STUDIES', 'subjects' => ['Electricity', 'Business Studies', 'Physics'], 'careers' => ['Electrical Contractor', 'Energy Consultant', 'Power Systems Manager']],
    ['pathway' => 'STEM', 'track' => 'TECHNICAL STUDIES', 'subjects' => ['Marine & Fisheries', 'Business Studies', 'Biology'], 'careers' => ['Fisheries Manager', 'Marine Conservation Entrepreneur', 'Aquaculture Business Owner']],
    ['pathway' => 'STEM', 'track' => 'TECHNICAL STUDIES', 'subjects' => ['Media Technology', 'Business Studies', 'Computer Studies'], 'careers' => ['Media Entrepreneur', 'Digital Content Creator', 'Video Production Business Owner']],
    ['pathway' => 'STEM', 'track' => 'TECHNICAL STUDIES', 'subjects' => ['Metal Work', 'Business Studies', 'Physics'], 'careers' => ['Metal Fabrication Business Owner', 'Welding Contractor', 'Manufacturing Entrepreneur']],
    ['pathway' => 'STEM', 'track' => 'TECHNICAL STUDIES', 'subjects' => ['Power Mechanics', 'Business Studies', 'Physics'], 'careers' => ['Auto Repair Shop Owner', 'Power Systems Contractor', 'Mechanical Services Entrepreneur']],
    ['pathway' => 'STEM', 'track' => 'TECHNICAL STUDIES', 'subjects' => ['Wood Work', 'Business Studies', 'Geography'], 'careers' => ['Furniture Business Owner', 'Carpentry Contractor', 'Timber Merchant']],

    // Social Sciences - Humanities & Business Studies
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'HUMANITIES & BUSINESS', 'subjects' => ['Business Studies', 'History & Citizenship', 'Geography'], 'careers' => ['Economist', 'Business Consultant', 'Policy Analyst', 'Urban Planner']],
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'HUMANITIES & BUSINESS', 'subjects' => ['Business Studies', 'History & Citizenship', 'CRE/IRE/HRE'], 'careers' => ['Ethics Consultant', 'Religious Affairs Manager', 'Corporate Social Responsibility Officer']],
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'HUMANITIES & BUSINESS', 'subjects' => ['Business Studies', 'Geography', 'CRE/IRE/HRE'], 'careers' => ['Tourism Business Owner', 'Hotel Manager', 'Travel Agency Owner']],
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'HUMANITIES & BUSINESS', 'subjects' => ['History & Citizenship', 'Geography', 'CRE/IRE/HRE'], 'careers' => ['Heritage Manager', 'Museum Curator', 'Religious Site Manager', 'Tour Guide']],

    // Social Sciences - Languages & Literature
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'LANGUAGES & LITERATURE', 'subjects' => ['Literature in English', 'Fasihi ya Kiswahili', 'History & Citizenship'], 'careers' => ['Editor', 'Publisher', 'Content Creator', 'Journalist', 'Author']],
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'LANGUAGES & LITERATURE', 'subjects' => ['French', 'German', 'History & Citizenship'], 'careers' => ['Diplomat', 'Translator', 'International Relations Officer', 'Foreign Language Teacher']],
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'LANGUAGES & LITERATURE', 'subjects' => ['Arabic', 'French', 'Business Studies'], 'careers' => ['International Business Consultant', 'Trade Negotiator', 'Import/Export Manager']],
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'LANGUAGES & LITERATURE', 'subjects' => ['Sign Language', 'Arabic', 'CRE/IRE/HRE'], 'careers' => ['Sign Language Interpreter', 'Disability Inclusion Specialist', 'Community Outreach Coordinator']],
    ['pathway' => 'SOCIAL SCIENCES', 'track' => 'LANGUAGES & LITERATURE', 'subjects' => ['Mandarin', 'French', 'Business Studies'], 'careers' => ['China-Africa Trade Specialist', 'International Marketing Manager', 'Global Supply Chain Manager']]
];

// Group subjects for selection
$all_subjects = [];
foreach ($subject_combinations as $combo) {
    foreach ($combo['subjects'] as $subject) {
        if (!in_array($subject, $all_subjects)) {
            $all_subjects[] = $subject;
        }
    }
}
sort($all_subjects);
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
          #PAGE HEADER - DARK MODE COMPATIBLE
        ============================================*/
        .page-header {
            padding-top: 140px;
            padding-bottom: 60px;
            background: linear-gradient(135deg, #00BFFF 0%, #0099cc 100%);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Dark mode gradient - deeper, richer colors */
        body.dark-mode .page-header {
            background: linear-gradient(135deg, #0a2a3a 0%, #0a1a2a 100%);
        }
        
        /* Animated background for hero in dark mode */
        body.dark-mode .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(0, 191, 255, 0.15), transparent 50%);
            pointer-events: none;
        }
        
        body.dark-mode .page-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 80% 80%, rgba(0, 191, 255, 0.1), transparent 50%);
            pointer-events: none;
        }
        
        .page-header h1 {
            font-size: 3.5rem;
            color: white;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .page-header h1 span {
            color: #FFD700;
        }
        
        /* Dark mode heading adjustments */
        body.dark-mode .page-header h1 {
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.6rem;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        /* Dark mode paragraph */
        body.dark-mode .page-header p {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Floating particles animation for dark mode */
        body.dark-mode .page-header .particle {
            position: absolute;
            background: rgba(0, 191, 255, 0.3);
            border-radius: 50%;
            pointer-events: none;
            animation: floatParticle 15s infinite ease-in-out;
        }
        
        @keyframes floatParticle {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0; }
            10% { opacity: 0.5; }
            90% { opacity: 0.3; }
            100% { transform: translateY(-100px) translateX(50px); opacity: 0; }
        }
        
        /*============================================
          #SUBJECT SELECTION SECTION
        ============================================*/
        .selection-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        /* Subject Grid */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 40px 0;
        }
        
        .subject-card {
            background: var(--white);
            border-radius: var(--radius-15);
            padding: 18px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid var(--platinum);
            font-weight: 500;
            font-size: 1.4rem;
            color: var(--eerie-black-1);
            position: relative;
            overflow: hidden;
        }
        
        .subject-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 191, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .subject-card:hover::before {
            left: 100%;
        }
        
        .subject-card:hover {
            transform: translateY(-5px);
            border-color: #00BFFF;
            box-shadow: var(--shadow-2);
        }
        
        .subject-card.selected {
            border-color: #00BFFF;
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.1), rgba(0, 191, 255, 0.05));
            color: #00BFFF;
            transform: translateY(-3px);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 40px 0;
        }
        
        .action-btn {
            padding: 14px 35px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-btn.primary {
            background: #00BFFF;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 191, 255, 0.3);
        }
        
        .action-btn.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 191, 255, 0.4);
            background: #009ac9;
        }
        
        .action-btn.secondary {
            background: var(--isabelline);
            color: var(--eerie-black-1);
            border: 1px solid var(--platinum);
        }
        
        .action-btn.secondary:hover {
            transform: translateY(-3px);
            background: #00BFFF;
            color: white;
            border-color: #00BFFF;
        }
        
        /* Dark mode button adjustments */
        body.dark-mode .action-btn.secondary {
            background: #1e293b;
            color: #e2e8f0;
            border-color: #334155;
        }
        
        body.dark-mode .action-btn.secondary:hover {
            background: #00BFFF;
            color: white;
        }
        
        /* Results Container */
        .results-container {
            margin-top: 50px;
            display: none;
        }
        
        .results-container.active {
            display: block;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .results-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .results-header h3 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .results-header p {
            color: var(--gray-web);
        }
        
        .result-card {
            background: var(--white);
            border-radius: var(--radius-15);
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid var(--platinum);
            box-shadow: var(--shadow-1);
            transition: all 0.3s ease;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2);
        }
        
        /* Dark mode result card */
        body.dark-mode .result-card {
            background: #1e293b;
            border-color: #334155;
        }
        
        .pathway-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .pathway-badge.stem {
            background: rgba(0, 191, 255, 0.1);
            color: #00BFFF;
        }
        
        .pathway-badge.arts {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }
        
        .pathway-badge.social {
            background: rgba(139, 92, 246, 0.1);
            color: #8B5CF6;
        }
        
        .career-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0;
        }
        
        .career-tag {
            background: var(--isabelline);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 1.3rem;
            color: var(--eerie-black-1);
            transition: all 0.2s ease;
        }
        
        /* Dark mode career tag */
        body.dark-mode .career-tag {
            background: #334155;
            color: #e2e8f0;
        }
        
        .career-tag:hover {
            background: #00BFFF;
            color: white;
            transform: translateX(3px);
        }
        
        .compatibility-score {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: #10B981;
            color: white;
            border-radius: 30px;
            font-size: 1.2rem;
            margin-top: 15px;
        }
        
        .next-steps-card {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(0, 191, 255, 0.02));
            border-left: 4px solid #00BFFF;
        }
        
        /* Dark mode next steps card */
        body.dark-mode .next-steps-card {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.1), rgba(0, 191, 255, 0.05));
        }
        
        .next-steps-card ul {
            margin-left: 20px;
            margin-top: 15px;
        }
        
        .next-steps-card li {
            margin-bottom: 10px;
            font-size: 1.4rem;
        }
        
        /* Stats Cards */
        .stats-cards {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 40px 0;
            flex-wrap: wrap;
        }
        
        .stat-card-mini {
            background: linear-gradient(135deg, var(--white), var(--isabelline));
            border-radius: var(--radius-15);
            padding: 20px 35px;
            text-align: center;
            border: 1px solid var(--platinum);
        }
        
        /* Dark mode stat card */
        body.dark-mode .stat-card-mini {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-color: #334155;
        }
        
        .stat-number-mini {
            font-size: 2rem;
            font-weight: 700;
            color: #00BFFF;
        }
        
        .stat-label-mini {
            font-size: 1.2rem;
            color: var(--gray-web);
        }
        
        /* Dark mode stat label */
        body.dark-mode .stat-label-mini {
            color: #94a3b8;
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
            .subjects-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .subjects-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .footer-top {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
                font-size: 2.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-btn {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .subjects-grid {
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
            <h1>Find Your <span>Subject Combination</span></h1>
            <p>Select your subjects to discover eligible careers, university programs, and pathways based on Kenya's CBC curriculum</p>
        </div>
    </section>

    <!-- Subject Selection Section -->
    <div class="selection-container">
        <div class="section-subtitle">Choose Your Subjects</div>
        <h2 class="section-title">Select Your <span>Subject Combination</span></h2>
        
        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card-mini reveal">
                <div class="stat-number-mini"><?php echo count($subject_combinations); ?>+</div>
                <div class="stat-label-mini">Possible Combinations</div>
            </div>
            <div class="stat-card-mini reveal">
                <div class="stat-number-mini">3</div>
                <div class="stat-label-mini">Major Pathways</div>
            </div>
            <div class="stat-card-mini reveal">
                <div class="stat-number-mini">100%</div>
                <div class="stat-label-mini">Free Guidance</div>
            </div>
        </div>
        
        <!-- Subject Grid -->
        <div class="subjects-grid" id="subjectsGrid">
            <?php foreach ($all_subjects as $subject): ?>
            <div class="subject-card reveal" data-subject="<?php echo htmlspecialchars($subject); ?>" onclick="toggleSubject(this)">
                <?php echo htmlspecialchars($subject); ?>
                <i class="fas fa-check-circle" style="position: absolute; bottom: 10px; right: 10px; font-size: 1rem; opacity: 0; transition: opacity 0.2s;"></i>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons reveal">
            <button class="action-btn primary" onclick="findCareers()">
                <i class="fas fa-search"></i> Find My Careers
            </button>
            <button class="action-btn secondary" onclick="resetSelection()">
                <i class="fas fa-undo-alt"></i> Reset Selection
            </button>
        </div>
        
        <!-- Results Container -->
        <div class="results-container" id="resultsContainer">
            <div class="results-header">
                <h3>Your Career Recommendations</h3>
                <p>Based on your selected subject combination</p>
            </div>
            <div id="resultsContent"></div>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="index.php" class="logo"><img src="/images/logo.png" alt="EduScore logo"></a>
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
if (overlay) { overlay.addEventListener("click", closeNavbar); }

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

// Subject Combination Data
const subjectCombinations = <?php echo json_encode($subject_combinations); ?>;

let selectedSubjects = [];

function toggleSubject(element) {
    const subject = element.dataset.subject;
    const checkIcon = element.querySelector('.fa-check-circle');
    
    if (selectedSubjects.includes(subject)) {
        selectedSubjects = selectedSubjects.filter(s => s !== subject);
        element.classList.remove('selected');
        if (checkIcon) checkIcon.style.opacity = '0';
    } else {
        selectedSubjects.push(subject);
        element.classList.add('selected');
        if (checkIcon) checkIcon.style.opacity = '1';
    }
}

function resetSelection() {
    selectedSubjects = [];
    document.querySelectorAll('.subject-card').forEach(card => {
        card.classList.remove('selected');
        const checkIcon = card.querySelector('.fa-check-circle');
        if (checkIcon) checkIcon.style.opacity = '0';
    });
    document.getElementById('resultsContainer').classList.remove('active');
}

function findCareers() {
    const resultsContainer = document.getElementById('resultsContainer');
    const resultsContent = document.getElementById('resultsContent');
    
    if (selectedSubjects.length < 3) {
        resultsContent.innerHTML = `
            <div class="result-card" style="text-align: center; border-left-color: #cd3241;">
                <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: #cd3241; margin-bottom: 15px;"></i>
                <h3>Not Enough Subjects Selected</h3>
                <p>Please select at least 3 subjects to find career matches. You have selected ${selectedSubjects.length} subject(s).</p>
                <div class="career-tags" style="justify-content: center; margin-top: 20px;">
                    <span class="career-tag">Tip: Choose subjects you enjoy</span>
                    <span class="career-tag">Tip: Select related subjects</span>
                </div>
            </div>
        `;
        resultsContainer.classList.add('active');
        resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
    }
    
    // Find matching combinations
    let matchedCombinations = [];
    
    subjectCombinations.forEach(combo => {
        const matchCount = combo.subjects.filter(s => selectedSubjects.includes(s)).length;
        if (matchCount >= 2) {
            matchedCombinations.push({
                ...combo,
                matchScore: matchCount,
                totalSubjects: combo.subjects.length
            });
        }
    });
    
    // Sort by match score (highest first)
    matchedCombinations.sort((a, b) => b.matchScore - a.matchScore);
    
    // Remove duplicates (same pathway and track)
    const uniqueCombos = [];
    const seen = new Set();
    matchedCombinations.forEach(combo => {
        const key = `${combo.pathway}|${combo.track}`;
        if (!seen.has(key)) {
            seen.add(key);
            uniqueCombos.push(combo);
        }
    });
    
    if (uniqueCombos.length === 0) {
        resultsContent.innerHTML = `
            <div class="result-card" style="text-align: center;">
                <i class="fas fa-search" style="font-size: 3rem; color: #00BFFF; margin-bottom: 15px;"></i>
                <h3>No Exact Matches Found</h3>
                <p>Try selecting different subjects or consult with a career counselor for personalized guidance.</p>
                <div class="career-tags" style="justify-content: center; margin-top: 20px;">
                    <span class="career-tag">Teacher</span>
                    <span class="career-tag">Entrepreneur</span>
                    <span class="career-tag">Public Administrator</span>
                    <span class="career-tag">Social Worker</span>
                </div>
            </div>
        `;
        resultsContainer.classList.add('active');
        resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
    }
    
    // Build results HTML
    let html = '';
    
    uniqueCombos.slice(0, 5).forEach((combo, index) => {
        let pathwayClass = '';
        let pathwayIcon = '';
        if (combo.pathway === 'STEM') {
            pathwayClass = 'stem';
            pathwayIcon = 'fas fa-microscope';
        } else if (combo.pathway === 'ARTS & SPORTS') {
            pathwayClass = 'arts';
            pathwayIcon = 'fas fa-palette';
        } else {
            pathwayClass = 'social';
            pathwayIcon = 'fas fa-globe-africa';
        }
        
        const matchPercentage = Math.round((combo.matchScore / combo.totalSubjects) * 100);
        
        html += `
            <div class="result-card reveal">
                <div class="pathway-badge ${pathwayClass}">
                    <i class="${pathwayIcon}"></i> ${combo.pathway} - ${combo.track}
                </div>
                <h3 style="margin-bottom: 10px;">Subject Combination</h3>
                <div class="career-tags">
                    ${combo.subjects.map(s => `<span class="career-tag">${s}</span>`).join('')}
                </div>
                <h3 style="margin-top: 20px; margin-bottom: 10px;">Recommended Careers</h3>
                <div class="career-tags">
                    ${combo.careers.map(c => `<span class="career-tag">${c}</span>`).join('')}
                </div>
                <div class="compatibility-score">
                    <i class="fas fa-chart-line"></i> ${matchPercentage}% Match
                </div>
            </div>
        `;
    });
    
    html += `
        <div class="result-card next-steps-card">
            <h3><i class="fas fa-lightbulb"></i> Next Steps</h3>
            <ul>
                <li>📚 Research the educational requirements for careers that interest you</li>
                <li>💬 Talk to professionals working in these fields</li>
                <li>🎯 Consider internships or volunteer opportunities</li>
                <li>📖 Focus on your selected subjects to build a strong foundation</li>
                <li>👨‍🏫 Consult with your school's career guidance counselor</li>
            </ul>
        </div>
    `;
    
    resultsContent.innerHTML = html;
    resultsContainer.classList.add('active');
    
    // Re-initialize scroll reveal for new content
    const newReveals = document.querySelectorAll('.reveal');
    const newObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('active');
        });
    }, { threshold: 0.15 });
    newReveals.forEach(el => newObserver.observe(el));
    
    // Scroll to results
    resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

</body>
</html>