<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once 'includes/config.php';

$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/career-pathways";

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
        'bg_gradient' => 'linear-gradient(135deg, #00BFFF 0%, #0099cc 100%)',
        'description' => 'Focusing on scientific inquiry, innovation, and problem-solving. Ideal for analytically minded students aiming for technical careers.',
        'tracks' => [
            'Pure Sciences' => 'Emphasizes theoretical foundations in physics, chemistry, and biology.',
            'Applied Sciences' => 'Focuses on real-world applications in agriculture, food science, and biotechnology.',
            'Technical and Engineering' => 'Hands-on engineering and tech skills including robotics and programming.',
            'Careers and Technology Studies' => 'Vocational-oriented tech integration for practical careers.'
        ],
        'core_subjects' => ['English/Kiswahili (Language)', 'Mathematics (Advanced)', 'Community Service Learning', 'Physical Education/Health Education'],
        'elective_subjects' => [
            'Physics' => 'Mechanics, energy, waves',
            'Chemistry' => 'Matter, reactions, lab work',
            'Biology' => 'Life sciences, ecology',
            'Computer Science' => 'Programming, AI, cybersecurity',
            'Aviation Technology' => 'Aerodynamics, flight systems',
            'Agriculture' => 'Sustainable farming, biotech',
            'Building Construction' => 'Practical engineering',
            'Electricity/Power Mechanics' => 'Technical skills',
            'Integrated Science' => 'Broad intro to sciences'
        ],
        'learning_outcomes' => ['Critical Thinking', 'Innovation', 'Ethical use of technology', 'Problem Solving'],
        'career_links' => ['Doctors', 'Engineers', 'Data Scientists', 'Pilots', 'Architects', 'IT Specialists', 'Researchers'],
        'university_pathways' => ['Engineering', 'Medicine', 'Computer Science', 'Aviation', 'Architecture']
    ],
    'social_sciences' => [
        'name' => 'Social Sciences Pathway',
        'full_name' => 'Humanities, Business, and Social Studies',
        'icon' => 'fas fa-globe-africa',
        'color' => '#8B5CF6',
        'bg_gradient' => 'linear-gradient(135deg, #8B5CF6 0%, #6d28d9 100%)',
        'description' => 'Nurtures critical thinkers, communicators, and societal leaders, emphasizing human behavior, culture, and economics.',
        'tracks' => [
            'Languages and Literature' => 'Focuses on communication and cultural studies.',
            'Humanities' => 'History, geography, and social studies.',
            'Business Studies' => 'Entrepreneurship and economics.'
        ],
        'core_subjects' => ['English/Kiswahili (Emphasized)', 'Mathematics (Basic)', 'Community Service Learning', 'Physical Education'],
        'elective_subjects' => [
            'History and Citizenship' => 'Global/Kenyan history, governance',
            'Geography' => 'Physical/human environments, GIS',
            'Religious Education' => 'IRE/CRE/HRE, ethics, philosophy',
            'Literature in English/Kiswahili' => 'Creative writing, analysis',
            'Business Studies' => 'Entrepreneurship, finance',
            'Legal Studies' => 'Law, human rights',
            'Foreign Languages' => 'French, German for global communication'
        ],
        'learning_outcomes' => ['Empathy', 'Ethical Decision Making', 'Global Awareness', 'Communication'],
        'career_links' => ['Lawyers', 'Journalists', 'Economists', 'Teachers', 'Psychologists', 'Entrepreneurs', 'Diplomats'],
        'university_pathways' => ['Law', 'Business Administration', 'Journalism', 'Social Work', 'Economics']
    ],
    'arts_sports' => [
        'name' => 'Arts and Sports Science Pathway',
        'full_name' => 'Creative Arts, Design, and Sports Science',
        'icon' => 'fas fa-palette',
        'color' => '#10B981',
        'bg_gradient' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)',
        'description' => 'Develops talents in performance, design, and athletics, integrating science for professional viability.',
        'tracks' => [
            'Sports' => 'Physical training and sports management.',
            'Visual Arts' => 'Design, sculpture, digital media.',
            'Performing Arts' => 'Music, dance, theater.'
        ],
        'core_subjects' => ['English/Kiswahili', 'Mathematics (Basic)', 'Community Service Learning', 'Physical Education (Expanded)'],
        'elective_subjects' => [
            'Physical Education and Sports' => 'Training, coaching, nutrition',
            'Visual Arts' => 'Drawing, painting, graphic design',
            'Performing Arts' => 'Music, dance, theater composition',
            'Home Science' => 'Textiles, nutrition (arts integration)',
            'Film and Media Studies' => 'Production, editing',
            'Leatherwork/Woodwork' => 'Craft-based arts'
        ],
        'learning_outcomes' => ['Creativity', 'Teamwork', 'Physical Resilience', 'Mental Resilience'],
        'career_links' => ['Athletes', 'Musicians', 'Filmmakers', 'Designers', 'Coaches', 'Event Managers'],
        'university_pathways' => ['Fine Arts', 'Sports Science', 'Media Studies', 'Music', 'Theater']
    ]
];

// Career data structure with pathway mapping
$careers = [
    'doctor' => [
        'title' => 'Doctor',
        'pathway' => 'stem',
        'pathway_name' => 'STEM',
        'subjects' => ['Biology', 'Chemistry', 'Physics/Mathematics'],
        'courses' => ['Bachelor of Medicine and Bachelor of Surgery (MBChB)'],
        'institutions' => ['University of Nairobi', 'Kenyatta University', 'Moi University', 'Egerton University'],
        'skills' => ['Critical Thinking', 'Empathy', 'Problem Solving', 'Attention to Detail'],
        'description' => 'Medical doctors diagnose and treat illnesses, injuries, and other health conditions.',
        'outlook' => 'High demand, especially in specialized fields',
        'related_careers' => ['Surgeon', 'Pediatrician', 'Psychiatrist', 'Dentist']
    ],
    'software_engineer' => [
        'title' => 'Software Engineer',
        'pathway' => 'stem',
        'pathway_name' => 'STEM',
        'subjects' => ['Mathematics', 'Physics', 'Computer Studies'],
        'courses' => ['BSc Computer Science', 'BSc Software Engineering', 'BSc Information Technology'],
        'institutions' => ['University of Nairobi', 'Strathmore University', 'JKUAT', 'KCA University'],
        'skills' => ['Programming', 'Problem Solving', 'Logical Thinking', 'Creativity'],
        'description' => 'Software engineers design, develop, and maintain software applications and systems.',
        'outlook' => 'Very high demand, rapidly growing field',
        'related_careers' => ['Data Scientist', 'DevOps Engineer', 'Mobile Developer', 'Cloud Architect']
    ],
    'civil_engineer' => [
        'title' => 'Civil Engineer',
        'pathway' => 'stem',
        'pathway_name' => 'STEM',
        'subjects' => ['Mathematics', 'Physics', 'Geography'],
        'courses' => ['Bachelor of Civil Engineering'],
        'institutions' => ['University of Nairobi', 'JKUAT', 'Technical University of Kenya'],
        'skills' => ['Design', 'Project Management', 'Mathematics', 'Problem Solving'],
        'description' => 'Civil engineers design, construct, and maintain infrastructure projects like roads, bridges, and buildings.',
        'outlook' => 'Steady demand, infrastructure development in Kenya',
        'related_careers' => ['Structural Engineer', 'Construction Manager', 'Urban Planner']
    ],
    'pilot' => [
        'title' => 'Pilot',
        'pathway' => 'stem',
        'pathway_name' => 'STEM',
        'subjects' => ['Mathematics', 'Physics', 'Geography'],
        'courses' => ['Bachelor of Science in Aviation', 'Diploma in Flight Training'],
        'institutions' => ['East African School of Aviation', 'Kenya Airways Flight Training'],
        'skills' => ['Decision Making', 'Leadership', 'Communication', 'Situational Awareness'],
        'description' => 'Pilots operate aircraft, ensuring safe and efficient transportation of passengers and cargo.',
        'outlook' => 'Moderate demand, requires extensive training',
        'related_careers' => ['Air Traffic Controller', 'Flight Instructor', 'Aerospace Engineer']
    ],
    'lawyer' => [
        'title' => 'Lawyer',
        'pathway' => 'social_sciences',
        'pathway_name' => 'Social Sciences',
        'subjects' => ['English', 'History', 'CRE/IRE', 'Business Studies'],
        'courses' => ['Bachelor of Laws (LLB)'],
        'institutions' => ['University of Nairobi', 'Moi University', 'Catholic University', 'Strathmore University'],
        'skills' => ['Argumentation', 'Research', 'Communication', 'Analytical Thinking'],
        'description' => 'Lawyers advise and represent clients in legal matters, including courts and negotiations.',
        'outlook' => 'Steady demand, competitive field',
        'related_careers' => ['Judge', 'Legal Consultant', 'Corporate Counsel', 'Prosecutor']
    ],
    'economist' => [
        'title' => 'Economist',
        'pathway' => 'social_sciences',
        'pathway_name' => 'Social Sciences',
        'subjects' => ['Mathematics', 'Economics', 'Business Studies', 'Geography'],
        'courses' => ['Bachelor of Economics', 'Bachelor of Business Administration'],
        'institutions' => ['University of Nairobi', 'Kenyatta University', 'Strathmore University'],
        'skills' => ['Analytical Thinking', 'Research', 'Data Analysis', 'Critical Thinking'],
        'description' => 'Economists study production and distribution of resources, goods, and services.',
        'outlook' => 'Growing demand in banking and government sectors',
        'related_careers' => ['Financial Analyst', 'Policy Advisor', 'Banker', 'Consultant']
    ],
    'architect' => [
        'title' => 'Architect',
        'pathway' => 'stem',
        'pathway_name' => 'STEM',
        'subjects' => ['Mathematics', 'Physics', 'Art and Design'],
        'courses' => ['Bachelor of Architecture'],
        'institutions' => ['University of Nairobi', 'Jomo Kenyatta University', 'Technical University of Kenya'],
        'skills' => ['Creativity', 'Technical Drawing', 'Spatial Awareness', 'Project Management'],
        'description' => 'Architects design buildings and structures, combining art and engineering.',
        'outlook' => 'Moderate demand, tied to construction industry',
        'related_careers' => ['Interior Designer', 'Urban Planner', 'Landscape Architect', 'Construction Manager']
    ],
    'teacher' => [
        'title' => 'Teacher',
        'pathway' => 'social_sciences',
        'pathway_name' => 'Social Sciences',
        'subjects' => ['English', 'Mathematics', 'Subject specialization', 'Kiswahili'],
        'courses' => ['Bachelor of Education', 'Diploma in Education'],
        'institutions' => ['Kenyatta University', 'Mount Kenya University', 'Maseno University', 'Laikipia University'],
        'skills' => ['Communication', 'Patience', 'Leadership', 'Organization'],
        'description' => 'Teachers educate and inspire students across various subjects and grade levels.',
        'outlook' => 'High demand, especially for STEM and special education',
        'related_careers' => ['School Administrator', 'Education Consultant', 'Curriculum Developer', 'Trainer']
    ],
    'data_analyst' => [
        'title' => 'Data Analyst',
        'pathway' => 'stem',
        'pathway_name' => 'STEM',
        'subjects' => ['Mathematics', 'Statistics', 'Computer Studies'],
        'courses' => ['BSc Statistics', 'BSc Data Science', 'BSc Computer Science'],
        'institutions' => ['University of Nairobi', 'Strathmore University', 'JKUAT', 'Zetech University'],
        'skills' => ['Analytical Thinking', 'SQL', 'Data Visualization', 'Statistical Analysis'],
        'description' => 'Data analysts collect, process, and perform statistical analyses on data.',
        'outlook' => 'Very high demand across all industries',
        'related_careers' => ['Data Scientist', 'Business Analyst', 'Data Engineer', 'BI Developer']
    ],
    'journalist' => [
        'title' => 'Journalist',
        'pathway' => 'arts_sports',
        'pathway_name' => 'Arts & Sports',
        'subjects' => ['English', 'Kiswahili', 'History', 'CRE/IRE'],
        'courses' => ['Bachelor of Journalism', 'Bachelor of Mass Communication'],
        'institutions' => ['University of Nairobi', 'Daystar University', 'USIU', 'Multimedia University'],
        'skills' => ['Writing', 'Research', 'Communication', 'Interviewing'],
        'description' => 'Journalists investigate and report news and current events.',
        'outlook' => 'Competitive, digital media growing',
        'related_careers' => ['News Anchor', 'Editor', 'Content Creator', 'Public Relations Specialist']
    ],
    'graphic_designer' => [
        'title' => 'Graphic Designer',
        'pathway' => 'arts_sports',
        'pathway_name' => 'Arts & Sports',
        'subjects' => ['Art and Design', 'Computer Studies', 'Mathematics'],
        'courses' => ['Bachelor of Fine Arts', 'Diploma in Graphic Design'],
        'institutions' => ['University of Nairobi', 'Kenya Institute of Design', 'Technical University of Kenya'],
        'skills' => ['Creativity', 'Typography', 'Color Theory', 'Software Proficiency'],
        'description' => 'Graphic designers create visual concepts to communicate ideas.',
        'outlook' => 'Growing demand in digital media',
        'related_careers' => ['UI/UX Designer', 'Art Director', 'Web Designer', 'Illustrator']
    ]
];

// Subject combinations mapping
$subject_combinations = [
    'Physics+Chemistry+Mathematics' => [
        'careers' => ['Engineer', 'Doctor', 'Software Engineer', 'Data Scientist', 'Architect', 'Pilot'],
        'pathways' => ['STEM'],
        'description' => 'Strong foundation for engineering, medicine, and technology careers'
    ],
    'Biology+Chemistry+Mathematics' => [
        'careers' => ['Doctor', 'Pharmacist', 'Dentist', 'Veterinarian', 'Biomedical Engineer'],
        'pathways' => ['STEM'],
        'description' => 'Ideal for medical and health science careers'
    ],
    'Business+Geography+CRE' => [
        'careers' => ['Lawyer', 'Entrepreneur', 'Economist', 'Public Administrator', 'Banker'],
        'pathways' => ['Social Sciences'],
        'description' => 'Great for law, business, and public service careers'
    ],
    'Agriculture+Biology+Chemistry' => [
        'careers' => ['Veterinarian', 'Agricultural Engineer', 'Food Scientist', 'Environmental Scientist'],
        'pathways' => ['STEM'],
        'description' => 'Perfect for agriculture and environmental science careers'
    ],
    'History+CRE+English' => [
        'careers' => ['Lawyer', 'Teacher', 'Journalist', 'Diplomat', 'Public Administrator'],
        'pathways' => ['Social Sciences', 'Arts & Sports Science'],
        'description' => 'Excellent for humanities and social science careers'
    ],
    'Art+Design+Computer Studies' => [
        'careers' => ['Graphic Designer', 'UX Designer', 'Animator', 'Game Designer', 'Multimedia Artist'],
        'pathways' => ['Arts & Sports Science'],
        'description' => 'Perfect for creative and digital arts careers'
    ]
];

// Quiz questions for career assessment
$quiz_questions = [
    [
        'id' => 1,
        'question' => 'What subjects do you enjoy most?',
        'options' => [
            'Mathematics & Sciences' => 'STEM',
            'Languages & Humanities' => 'Social Sciences',
            'Arts & Design' => 'Arts & Sports Science',
            'Business & Commerce' => 'Social Sciences'
        ]
    ],
    [
        'id' => 2,
        'question' => 'Do you enjoy problem solving?',
        'options' => [
            'Yes, I love challenges' => 'STEM',
            'Sometimes' => 'STEM',
            'Not really' => 'Social Sciences',
            'I prefer creative tasks' => 'Arts & Sports Science'
        ]
    ],
    [
        'id' => 3,
        'question' => 'Do you like creativity and design?',
        'options' => [
            'Yes, I am very creative' => 'Arts & Sports Science',
            'Somewhat' => 'Arts & Sports Science',
            'Not my strength' => 'STEM',
            'I prefer structure' => 'STEM'
        ]
    ],
    [
        'id' => 4,
        'question' => 'Do you enjoy helping people?',
        'options' => [
            'Yes, very much' => 'Social Sciences',
            'Sometimes' => 'Social Sciences',
            'Not really' => 'STEM',
            'I prefer working independently' => 'STEM'
        ]
    ],
    [
        'id' => 5,
        'question' => 'Do you enjoy technology?',
        'options' => [
            'Yes, I love tech' => 'STEM',
            'I use it but not passionate' => 'STEM',
            'Not really' => 'Arts & Sports Science',
            'I prefer hands-on work' => 'Arts & Sports Science'
        ]
    ],
    [
        'id' => 6,
        'question' => 'Do you prefer practical or theoretical work?',
        'options' => [
            'Practical, hands-on work' => 'STEM',
            'Theoretical, research-based' => 'STEM',
            'A mix of both' => 'Social Sciences',
            'Creative expression' => 'Arts & Sports Science'
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
          #HERO SECTION
        ============================================*/
        .hero {
            padding-top: 130px;
            padding-bottom: 80px;
            background: linear-gradient(135deg, #00BFFF 0%, #0099cc 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero .container {
            display: grid;
            gap: 50px;
        }
        
        .hero-content {
            text-align: center;
        }
        
        .hero .section-title {
            font-size: 5rem;
            color: white;
            margin-bottom: 20px;
        }
        
        .hero .section-title span {
            color: #FFD700;
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
            text-align: center;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: var(--radius-15);
            min-width: 130px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
        }
        
        .stat-label {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .hero-banner {
            display: grid;
            grid-template-columns: 1fr 0.8fr;
            align-items: flex-start;
            gap: 30px;
        }
        
        .hero-banner .img-holder.one {
            border-top-right-radius: 70px;
            border-bottom-left-radius: 110px;
        }
        
        .hero-banner .img-holder.two {
            border-top-left-radius: 50px;
            border-bottom-right-radius: 90px;
        }
        
        @media (min-width: 992px) {
            .hero .container {
                grid-template-columns: 1fr 1fr;
                align-items: center;
                gap: 60px;
            }
            
            .hero-content {
                text-align: left;
            }
            
            .hero .section-title,
            .hero-text {
                text-align: left;
                margin-left: 0;
                margin-right: 0;
            }
            
            .hero .btn {
                margin-inline: 0;
            }
            
            .hero-stats {
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 991px) {
            .hero-banner {
                display: none;
            }
        }
        

        
        /* Career Options Cards */
        .career-options {
            padding: 60px 0;
            background: var(--white);
        }
        
        .options-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
        }
        
        .option-card {
            background: var(--white);
            border-radius: var(--radius-15);
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--platinum);
            cursor: pointer;
            text-decoration: none;
            display: block;
        }
        
        .option-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-3);
        }
        
        .option-icon {
            width: 80px;
            height: 80px;
            background: rgba(0, 191, 255, 0.1);
            border-radius: var(--radius-circle);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: #00BFFF;
        }
        
        .option-card h3 {
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .option-card p {
            font-size: 1.4rem;
            color: var(--gray-web);
            margin-bottom: 20px;
        }
        
        .option-btn {
            display: inline-block;
            padding: 10px 25px;
            background: #00BFFF;
            color: white;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.4rem;
            transition: all 0.3s ease;
        }
        
        .option-card:hover .option-btn {
            transform: translateX(5px);
            background: #009ac9;
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
            .pathways-grid,
            .options-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hero .section-title {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 768px) {
            .pathways-grid,
            .options-grid {
                grid-template-columns: 1fr;
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
    <section class="hero has-bg-image" id="home" aria-label="home">
        <div class="container">
            <div class="hero-content reveal">
                <h1 class="h1 section-title">
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
            
            <div class="hero-banner">
                <div class="img-holder one" style="--width: 270; --height: 300;">
                    <img src="/images/school-bg.png" width="270" height="300" alt="hero banner" class="img-cover">
                </div>
                <div class="img-holder two" style="--width: 240; --height: 370;">
                    <img src="/images/logo.png" width="240" height="370" alt="hero banner" class="img-cover">
                </div>
            </div>
        </div>
    </section>


    <!-- Career Options Cards -->
    <section class="career-options">
        <div class="container">
            <p class="section-subtitle">How Would You Like to Proceed?</p>
            <h2 class="section-title">Choose Your <span>Career Discovery Method</span></h2>
            
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

// Pathway Data
const pathwayData = <?php echo json_encode($cbc_pathways); ?>;

function showPathwayDetail(pathwayKey) {
    const pathway = pathwayData[pathwayKey];
    const modal = document.getElementById('pathwayModal');
    const modalContent = document.getElementById('pathwayModalContent');
    
    modalContent.innerHTML = `
        <h2 style="color: var(--eerie-black-1); margin-bottom: 10px;">${pathway.name}</h2>
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
        
        <h3 style="margin-bottom: 10px;">Learning Outcomes:</h3>
        <div class="subject-chips" style="margin-bottom: 20px;">
            ${pathway.learning_outcomes.map(o => `<span class="subject-chip">${o}</span>`).join('')}
        </div>
        
        <h3 style="margin-bottom: 10px;">Career Links:</h3>
        <div class="subject-chips" style="margin-bottom: 20px;">
            ${pathway.career_links.map(c => `<span class="subject-chip">${c}</span>`).join('')}
        </div>
        
        <h3 style="margin-bottom: 10px;">University Pathways:</h3>
        <div class="subject-chips" style="margin-bottom: 20px;">
            ${pathway.university_pathways.map(u => `<span class="subject-chip">${u}</span>`).join('')}
        </div>
        
        <button class="quiz-btn next" onclick="closePathwayModal()" style="margin-top: 20px;">Close</button>
    `;
    modal.classList.add('active');
}

function closePathwayModal() {
    document.getElementById('pathwayModal').classList.remove('active');
}
</script>

</body>
</html>