<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production

// Include config
require_once 'includes/config.php';

// Define base URL and canonical URL
$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/";

// Enhanced SEO metadata with primary keyword targeting
$page_title = "EduScore | #1 Affordable School Management System Kenya 2025";
$page_description = "Kenya's leading school management system with advanced CBE tools, comprehensive 8-4-4 support, student portfolios, and seamless parent engagement. Free 14-day trial. Trusted by 500+ schools.";
$page_keywords = "school management system Kenya, CBE tools Kenya, competency based education, 8-4-4 support, student portfolios, parent engagement, school ERP Kenya";
$page_url = $current_url;
$page_image = $base_url . "images/og-image.jpg";

// Structured data for rich snippets (SoftwareApplication Schema)
$structured_data = [
    "@context" => "https://schema.org",
    "@type" => "SoftwareApplication",
    "name" => "EduScore School Management System",
    "applicationCategory" => "EducationApplication",
    "operatingSystem" => "Web",
    "offers" => [
        "@type" => "Offer",
        "price" => "15.00",
        "priceCurrency" => "KES",
        "priceValidUntil" => "2025-12-31",
        "availability" => "https://schema.org/InStock"
    ],
    "aggregateRating" => [
        "@type" => "AggregateRating",
        "ratingValue" => "4.8",
        "ratingCount" => "500",
        "bestRating" => "5"
    ],
    "description" => "Complete school management solution for Kenyan schools with exam analysis, fee management, and AI-powered teaching tools.",
    "url" => $base_url,
    "image" => $page_image,
    "author" => [
        "@type" => "Organization",
        "name" => "EduScore Kenya",
        "url" => $base_url
    ],
    "featureList" => "Exam Analysis, Fee Management, Bulk SMS, Parents Portal, Exam Generator, Mwalimu AI"
];

// Organization schema for brand signals
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

// FAQ Schema for rich snippets (enhanced with more questions)
$faq_schema = [
    "@context" => "https://schema.org",
    "@type" => "FAQPage",
    "mainEntity" => [
        [
            "@type" => "Question",
            "name" => "Is there a free trial available?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Yes, we offer a 14-day free trial for all our plans. No credit card required."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "What is Mwalimu AI?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Mwalimu AI is our AI-powered teaching assistant for the CBC curriculum. It helps create lesson plans, generate assessments, and provides personalized learning support."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "Can parents access student information?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Yes! The Parents Portal gives parents real-time access to their children's academic progress, fee balances, and attendance records."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "What payment methods do you accept?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "We accept M-Pesa, bank transfers, and credit cards with flexible payment options for Kenyan schools."
            ]
        ]
    ]
];

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !isset($_GET['skip_redirect'])) {
    header("Location: dashboard.php");
    exit;
}

// =============================================
// PRELOADER FIX FOR SEO
// =============================================
// Only show preloader to LOGGED IN users (not to Google bots)
// Google bot detection via User-Agent
$is_logged_in = isset($_SESSION['user_id']);
$is_google_bot = false;

// Check if the visitor is a search engine bot
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $bot_patterns = ['Googlebot', 'Google-InspectionTool', 'Bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider', 'YandexBot', 'facebot', 'ia_archiver', 'AhrefsBot', 'SemrushBot'];
    foreach ($bot_patterns as $pattern) {
        if (stripos($user_agent, $pattern) !== false) {
            $is_google_bot = true;
            break;
        }
    }
}

// Show preloader ONLY to logged-in users (not search engines)
// And only once per session to prevent loops
$show_preloader = $is_logged_in && !$is_google_bot && !isset($_SESSION['preloader_shown']) && !isset($_GET['skip_preloader']);

if ($show_preloader) {
    $_SESSION['preloader_shown'] = time();
    include 'preloader.php';
    exit;
}

// Pricing data (preserved exactly as original)
$pricing = [
    'primary' => [
        'public' => [
            'single' => ['price' => 15, 'onboarding' => 2000],
            'both' => ['price' => 25, 'onboarding' => 3500]
        ],
        'private' => [
            'single' => ['price' => 30, 'onboarding' => 5000],
            'both' => ['price' => 50, 'onboarding' => 8000]
        ]
    ],
    'secondary' => [
        'public' => [
            'single' => ['price' => 20, 'onboarding' => 2500],
            'both' => ['price' => 35, 'onboarding' => 4500]
        ],
        'private' => [
            'single' => ['price' => 40, 'onboarding' => 6000],
            'both' => ['price' => 70, 'onboarding' => 10000]
        ]
    ]
];

// Function to get blog image URL (same as blog.php)
function getBlogImageUrl($imagePath) {
    if (empty($imagePath)) return null;

    $upload_base = "https://eduscore.gt.tc/uploads/blogs/";

    // If already full URL, return as is
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }

    // Extract filename and attach correct domain
    return $upload_base . basename($imagePath);
}

// Fetch latest 3 unique blog posts from database for homepage
$latest_blogs = [];
$blog_error = false;

try {
    // Check if db connection exists (using $db from config.php)
    if (isset($db) && $db instanceof PDO) {
        // Fetch latest 3 blogs, order by created_at DESC, limit 3
        $stmt = $db->prepare("SELECT id, title, description, image, category, created_at, author, views, likes 
                               FROM blogs 
                               ORDER BY created_at DESC 
                               LIMIT 3");
        $stmt->execute();
        $latest_blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process each blog to add the full image URL using the function
        foreach ($latest_blogs as &$blog) {
            $blog['image_url'] = getBlogImageUrl($blog['image']);
        }
        
    } else {
        $blog_error = true;
    }
} catch (PDOException $e) {
    error_log("Blog fetch error: " . $e->getMessage());
    $blog_error = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google Search Console Verification -->
    <meta name="google-site-verification" content="HZsZNr2Rfno72qnurFjgV4UEMnMM3H0qjWryXqIzxpI" />

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <!-- Primary SEO Meta Tags -->
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    
    <!-- Canonical URL to prevent duplicate content -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="en-ke" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph / Facebook Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="EduScore | Affordable School Management System Kenya">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="EduScore Kenya">
    <meta property="og:locale" content="en_KE">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="EduScore | Affordable School Management System Kenya">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Favicons & App Icons -->
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#00BFFF">
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Structured Data / Schema Markup -->
    <script type="application/ld+json">
    <?php echo json_encode($structured_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <script type="application/ld+json">
    <?php echo json_encode($org_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <script type="application/ld+json">
    <?php echo json_encode($faq_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    
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
        
        body.dark-mode .course-card,
        body.dark-mode .category-card,
        body.dark-mode .blog-card .card-content,
        body.dark-mode .stats-card,
        body.dark-mode .contact-card,
        body.dark-mode .contact-form-container {
            background-color: hsl(0, 0%, 20%);
        }
        
        body.dark-mode .blog-card .card-content {
            box-shadow: 0 10px 30px hsla(0, 0%, 0%, 0.3);
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
        
        body.dark-mode .header-action-btn {
            color: var(--eerie-black-1);
        }
        
        body.dark-mode .contact-form-input,
        body.dark-mode .contact-form-textarea {
            background-color: hsl(0, 0%, 25%);
            color: var(--eerie-black-1);
            border-color: var(--platinum);
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
        
        :focus-visible { outline-offset: 4px; }
        
        ::-webkit-scrollbar { width: 10px; }
        
        ::-webkit-scrollbar-track { background-color: hsl(0, 0%, 98%); }
        
        ::-webkit-scrollbar-thumb { background-color: hsl(0, 0%, 80%); }
        
        ::-webkit-scrollbar-thumb:hover { background-color: hsl(0, 0%, 70%); }
        
        /*-----------------------------------*\
          #REUSED STYLE
        \*-----------------------------------*/
        .container { padding-inline: 15px; }
        
        .section { padding-block: var(--section-padding); }
        
        .shape {
            position: absolute;
            display: none;
        }
        
        .has-bg-image {
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
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
        
        .h3 {
            font-size: var(--fs-3);
            font-weight: var(--fw-500);
        }
        
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
        }
        
        .has-before,
        .has-after {
            position: relative;
            z-index: 1;
        }
        
        .has-before::before,
        .has-after::after {
            position: absolute;
            content: "";
        }
        
        .btn::before {
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
        
        .category-card,
        .stats-card { background-color: hsla(var(--color), 0.1); }
        
        :is(.course, .blog) .section-title { margin-block-end: 40px; }
        
        /*-----------------------------------*\
          #HEADER - FIXED MOBILE NAVBAR
        \*-----------------------------------*/
        .header .btn { display: none; }
        
        .header {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            background-color: var(--white);
            padding-block: 12px;
            box-shadow: var(--shadow-1);
            z-index: 4;
            transition: var(--transition-1);
            overflow: visible;
        }
        
        .header.active { 
            position: fixed;
            transform: translateY(-100%);
            animation: slideIn 0.5s ease forwards;
        }
        
        @keyframes slideIn {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(0); }
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            max-width: 100%;
            overflow: visible;
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
        
        .portal-buttons-header {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        
        .portal-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.4rem;
            text-decoration: none;
            transition: all 0.3s ease;
            white-space: nowrap;
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
            background: transparent;
            color: #00BFFF;
            border: 1.5px solid #00BFFF;
        }
        
        .portal-btn-finance:hover {
            background: #00BFFF;
            color: #ffffff;
            transform: translateY(-2px);
        }
        
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
        
        /* Mobile Navbar - Fixed */
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
            -webkit-overflow-scrolling: touch;
            box-shadow: 2px 0 20px rgba(0,0,0,0.15);
        }
        
        .navbar.active {
            left: 0;
        }
        
        /* Prevent body scroll when navbar is open */
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
            border-block-end: 1px solid var(--platinum);
        }
        
        .nav-close-btn {
            background-color: var(--white);
            box-shadow: var(--shadow-2);
            padding: 8px;
            border-radius: var(--radius-circle);
            font-size: 2rem;
            cursor: pointer;
        }
        
        .nav-close-btn:is(:hover, :focus) {
            background-color: var(--kappel);
            color: var(--white);
        }
        
        .navbar-list { 
            padding: 15px 20px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .navbar-item:not(:last-child) { border-block-end: 1px solid var(--platinum); }
        
        .navbar-link {
            padding-block: 12px;
            font-weight: var(--fw-500);
            transition: var(--transition-1);
            display: block;
        }
        
        .navbar-link:is(:hover, :focus) { color: var(--kappel); }
        
        .mobile-portal-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
            padding: 15px 20px;
            border-top: 1px solid var(--platinum);
        }
        
        .mobile-portal-btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.4rem;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .mobile-portal-btn-analytics {
            background: #00BFFF;
            color: #ffffff;
        }
        
        .mobile-portal-btn-analytics:hover {
            background: #009ac9;
        }
        
        .mobile-portal-btn-finance {
            background: transparent;
            color: #00BFFF;
            border: 1.5px solid #00BFFF;
        }
        
        .mobile-portal-btn-finance:hover {
            background: #00BFFF;
            color: #ffffff;
        }
        
        /* Dropdown Menu for Solutions */
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: static;
            background-color: var(--white);
            width: 100%;
            box-shadow: none;
            border-radius: var(--radius-5);
            padding: 0;
            margin-top: 5px;
            margin-left: 15px;
            opacity: 1;
            visibility: visible;
            transform: none;
            display: none;
        }
        
        .dropdown.active .dropdown-menu {
            display: block;
        }
        
        .dropdown-menu li a {
            padding: 10px 20px;
            display: block;
            transition: var(--transition-1);
            color: var(--eerie-black-1);
            font-size: 1.4rem;
        }
        
        .dropdown-menu li a:hover {
            background-color: var(--kappel_15);
            color: var(--kappel);
            padding-left: 25px;
        }
        
        .dropdown-arrow {
            transition: transform 0.3s ease;
            display: inline-block;
            margin-left: 5px;
            font-size: 1rem;
        }
        
        .dropdown.active .dropdown-arrow {
            transform: rotate(180deg);
        }
        
        .overlay {
            position: fixed;
            inset: 0;
            background-color: var(--black_80);
            pointer-events: none;
            opacity: 0;
            z-index: 1000;
            transition: opacity 0.3s ease;
        }
        
        .overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        /* Desktop Navbar */
        @media (min-width: 992px) {
            .menu-btn {
                display: none;
            }
            
            .navbar {
                position: static;
                left: auto !important;
                width: auto;
                max-width: none;
                height: auto;
                background: none;
                transform: none !important;
                overflow: visible;
                display: flex;
                align-items: center;
                box-shadow: none;
            }
            
            body.navbar-open {
                overflow: auto;
                position: relative;
                width: auto;
            }
            
            .navbar .wrapper {
                display: none;
            }
            
            .navbar-list {
                flex-direction: row;
                padding: 0;
                gap: 30px;
                align-items: center;
            }
            
            .navbar-item:not(:last-child) {
                border-block-end: none;
            }
            
            .navbar-link {
                padding-block: 0;
            }
            
            .mobile-portal-buttons {
                display: none;
            }
            
            .dropdown {
                position: relative;
            }
            
            .dropdown-menu {
                position: absolute;
                top: 100%;
                left: 0;
                background-color: var(--white);
                min-width: 220px;
                box-shadow: var(--shadow-2);
                border-radius: var(--radius-5);
                padding: 10px 0;
                margin: 0;
                opacity: 0;
                visibility: hidden;
                transform: translateY(10px);
                transition: all 0.3s ease;
                z-index: 10;
                display: block;
            }
            
            .dropdown:hover .dropdown-menu {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }
            
            .dropdown.active .dropdown-menu {
                display: block;
                opacity: 1;
                visibility: visible;
            }
            
            .dropdown-arrow {
                display: inline-block;
            }
            
            .overlay {
                display: none;
            }
        }
        
        @media (max-width: 991px) {
            .portal-buttons-header {
                display: none;
            }
            
            .header .btn {
                display: none;
            }
        }
        
        /*-----------------------------------*\
          #HERO
        \*-----------------------------------*/
        .hero {
            padding-block-start: calc(var(--section-padding) + 80px);
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23f0f9ff" fill-opacity="0.5" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
        }
        
        .hero .container {
            display: grid;
            gap: 40px;
        }
        
        .hero-text {
            color: var(--eerie-black-1);
            font-size: var(--fs-4);
            text-align: center;
            margin-block: 18px 20px;
        }
        
        .hero .btn { margin-inline: auto; }
        
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
        
        /*-----------------------------------*\
          #CATEGORY
        \*-----------------------------------*/
        .category .section-subtitle { color: var(--radical-red); }
        
        .category .section-title { --color: var(--kappel); }
        
        .category .section-text { margin-block-end: 40px; }
        
        .category-card {
            padding: 50px 30px;
            text-align: center;
            border-radius: var(--radius-5);
            transition: var(--transition-1);
        }
        
        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-2);
        }
        
        .category-card .card-icon {
            background-color: hsla(var(--color), 0.1);
            width: 80px;
            height: 80px;
            display: grid;
            place-items: center;
            border-radius: var(--radius-circle);
            margin-inline: auto;
            margin-block-end: 30px;
        }
        
        .category-card .card-text {
            color: var(--eerie-black-1);
            font-size: var(--fs-5);
            margin-block: 15px 25px;
        }
        
        .category-card .card-badge {
            background-color: hsla(var(--color), 0.1);
            color: hsl(var(--color));
            font-size: var(--fs-5);
            font-weight: var(--fw-500);
            padding: 2px 18px;
            max-width: max-content;
            margin-inline: auto;
            border-radius: var(--radius-5);
        }
        
        /*-----------------------------------*\
          #ABOUT
        \*-----------------------------------*/
        .about {
            padding-block-start: 0;
            overflow: hidden;
        }
        
        .about .container {
            display: grid;
            gap: 30px;
        }
        
        .about-banner {
            position: relative;
            z-index: 1;
        }
        
        .about-banner .img-holder { border-radius: var(--radius-10); }
        
        .about-shape-2 {
            display: block;
            bottom: -100px;
            left: -60px;
            animation: bounce 2.5s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-30px); }
            60% { transform: translateY(-15px); }
        }
        
        .about :is(.section-subtitle, .section-title, .section-text) {
            text-align: left;
        }
        
        .about-item {
            margin-block: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .about-item ion-icon {
            color: var(--selective-yellow);
            font-size: 20px;
        }
        
        .about-item .span {
            color: var(--eerie-black-1);
            font-family: var(--ff-league_spartan);
        }
        
        /*-----------------------------------*\
          #COURSE
        \*-----------------------------------*/
        .course { background-color: var(--isabelline); }
        
        .course-card {
            position: relative;
            background-color: var(--white);
            border-radius: var(--radius-5);
            overflow: hidden;
            transition: var(--transition-1);
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2);
        }
        
        .course-card .img-cover { transition: var(--transition-2); }
        
        .course-card:is(:hover, :focus-within) .img-cover { transform: scale(1.1); }
        
        .course-card :is(.abs-badge, .badge) {
            font-family: var(--ff-league_spartan);
            border-radius: var(--radius-3);
        }
        
        .course-card .abs-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--selective-yellow);
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 5px;
            line-height: 1;
            padding: 6px 8px;
            padding-block-end: 3px;
        }
        
        .course-card .abs-badge ion-icon {
            font-size: 18px;
            margin-block-end: 5px;
        }
        
        .course-card .card-content { padding: 25px; }
        
        .course-card .badge {
            background-color: var(--kappel_15);
            max-width: max-content;
            color: var(--kappel);
            line-height: 25px;
            padding-inline: 10px;
        }
        
        .course-card .card-title {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            margin-block: 15px 8px;
            transition: var(--transition-1);
        }
        
        .course-card .card-title:is(:hover, :focus) { color: var(--kappel); }
        
        .course-card :is(.wrapper, .rating-wrapper, .card-meta-list, .card-meta-item) {
            display: flex;
            align-items: center;
        }
        
        .course-card .wrapper { gap: 10px; }
        
        .course-card .rating-wrapper { gap: 3px; }
        
        .course-card .rating-wrapper ion-icon { color: var(--selective-yellow); }
        
        .course-card .rating-text {
            color: var(--eerie-black-1);
            font-size: var(--fs-6);
            font-weight: var(--fw-500);
        }
        
        .course-card .price {
            color: var(--radical-red);
            font-family: var(--ff-league_spartan);
            font-size: var(--fs-4);
            font-weight: var(--fw-600);
            margin-block: 8px 15px;
        }
        
        .course-card .card-meta-list { flex-wrap: wrap; }
        
        .course-card .card-meta-item {
            position: relative;
            gap: 5px;
        }
        
        .course-card .card-meta-item:not(:last-child)::after {
            content: "|";
            display: inline-block;
            color: var(--platinum);
            padding-inline: 10px;
        }
        
        .course-card .card-meta-item ion-icon {
            color: var(--quick-silver);
        }
        
        .course-card .card-meta-item .span {
            color: var(--eerie-black-1);
            font-size: var(--fs-7);
        }
        
        .course .btn {
            margin-inline: auto;
            margin-block-start: 60px;
        }
        
        /*-----------------------------------*\
          #STATS
        \*-----------------------------------*/
        .stats-card {
            text-align: center;
            padding: 25px;
            border-radius: var(--radius-10);
            transition: var(--transition-1);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2);
        }
        
        .stats-card :is(.card-title, .card-text) { font-family: var(--ff-league_spartan); }
        
        .stats-card .card-title {
            color: hsl(var(--color));
            font-size: var(--fs-2);
            line-height: 1.1;
        }
        
        .stats-card .card-text {
            color: var(--eerie-black-1);
            text-transform: uppercase;
        }
        
        /*-----------------------------------*\
          #BLOG - Dynamically fetching 3 latest posts from database
        \*-----------------------------------*/
        .blog-card .card-banner { border-radius: var(--radius-10); }
        
        .blog-card .card-banner .img-cover { transition: var(--transition-2); }
        
        .blog-card .card-banner::after {
            inset: 0;
            background-color: var(--black_50);
            opacity: 0;
            transition: var(--transition-1);
        }
        
        .blog-card:is(:hover, :focus-within) .card-banner .img-cover { transform: scale(1.1); }
        
        .blog-card:is(:hover, :focus-within) .card-banner::after { opacity: 1; }
        
        .blog-card .card-content {
            position: relative;
            margin-inline: 15px;
            background-color: var(--white);
            padding: 20px;
            border-radius: var(--radius-10);
            box-shadow: var(--shadow-3);
            margin-block-start: -100px;
            z-index: 1;
            transition: var(--transition-1);
        }
        
        .blog-card:hover .card-content {
            transform: translateY(-5px);
        }
        
        .blog-card .card-btn {
            position: absolute;
            top: -40px;
            right: 30px;
            background-color: var(--kappel);
            color: var(--white);
            font-size: 20px;
            padding: 20px;
            border-radius: var(--radius-circle);
            transition: var(--transition-1);
            opacity: 0;
        }
        
        .blog-card .card-btn:is(:hover, :focus) { background-color: var(--radical-red); }
        
        .blog-card:is(:hover, :focus-within) .card-btn {
            opacity: 1;
            transform: translateY(10px);
        }
        
        .blog-card :is(.card-meta-item, .card-text, .card-subtitle) {
            font-size: var(--fs-5);
        }
        
        .blog-card .card-subtitle { text-transform: uppercase; }
        
        .blog-card .card-title {
            margin-block: 10px 15px;
            transition: var(--transition-1);
        }
        
        .blog-card .card-title:is(:hover, :focus) { color: var(--kappel); }
        
        .blog-card :is(.card-meta-list, .card-meta-item) { display: flex; }
        
        .blog-card .card-meta-list {
            flex-wrap: wrap;
            gap: 10px 20px;
            margin-block-end: 20px;
        }
        
        .blog-card .card-meta-item {
            gap: 10px;
            align-items: center;
            color: var(--eerie-black-1);
        }
        
        .blog-card .card-meta-item ion-icon {
            color: var(--kappel);
            font-size: 18px;
        }
        
        /* View All Blog Button */
        .view-all-blog {
            text-align: center;
            margin-top: 40px;
        }
        
        .view-all-blog .btn {
            display: inline-flex;
            margin: 0 auto;
        }
        
        /*-----------------------------------*\
          #CONTACT SECTION
        \*-----------------------------------*/
        .contact-section {
            background-color: var(--isabelline);
        }
        
        .contact-grid {
            display: grid;
            gap: 40px;
        }
        
        .contact-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .contact-card {
            background: var(--white);
            padding: 30px 20px;
            border-radius: var(--radius-10);
            text-align: center;
            transition: var(--transition-1);
            box-shadow: var(--shadow-1);
            border: 1px solid var(--platinum);
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2);
        }
        
        .contact-card-icon {
            width: 60px;
            height: 60px;
            background: var(--kappel_15);
            border-radius: var(--radius-circle);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--kappel);
            font-size: 2.5rem;
        }
        
        .contact-card h3 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: var(--eerie-black-1);
        }
        
        .contact-card p {
            color: var(--gray-web);
            font-size: 1.4rem;
            line-height: 1.6;
        }
        
        .contact-card a {
            color: var(--gray-web);
            transition: var(--transition-1);
        }
        
        .contact-card a:hover {
            color: var(--kappel);
        }
        
        .contact-form-container {
            background: var(--white);
            padding: 40px;
            border-radius: var(--radius-10);
            box-shadow: var(--shadow-1);
            border: 1px solid var(--platinum);
        }
        
        .contact-form-title {
            font-size: 2rem;
            color: var(--eerie-black-1);
            margin-bottom: 10px;
            font-family: var(--ff-league_spartan);
        }
        
        .contact-form-subtitle {
            color: var(--gray-web);
            margin-bottom: 30px;
            font-size: 1.4rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--eerie-black-1);
            font-size: 1.4rem;
        }
        
        .contact-form-input,
        .contact-form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--platinum);
            border-radius: var(--radius-5);
            font-size: 1.4rem;
            transition: var(--transition-1);
            background: var(--white);
            color: var(--eerie-black-1);
        }
        
        .contact-form-input:focus,
        .contact-form-textarea:focus {
            outline: none;
            border-color: var(--kappel);
            box-shadow: 0 0 0 3px var(--kappel_15);
        }
        
        .contact-form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .submit-btn {
            background: var(--kappel);
            color: var(--white);
            padding: 12px 30px;
            border-radius: var(--radius-5);
            font-weight: 600;
            font-size: 1.4rem;
            cursor: pointer;
            transition: var(--transition-1);
            border: none;
            width: auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .submit-btn:hover {
            background: hsl(170, 75%, 35%);
            transform: translateY(-2px);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .contact-form-container {
                padding: 25px;
            }
            
            .contact-info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: var(--radius-5);
    margin-bottom: 25px;
    font-size: 1.4rem;
    animation: slideDown 0.3s ease;
    display: none;
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

.alert i {
    margin-right: 8px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
        /*-----------------------------------*\
          #FOOTER
        \*-----------------------------------*/
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
        
        .footer-brand .wrapper .span { font-weight: var(--fw-500); }
        
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
        
        .newsletter-form { margin-block: 20px 35px; }
        
        .newsletter-form .input-field {
            background-color: var(--white);
            padding: 12px;
            border-radius: var(--radius-5);
            margin-block-end: 20px;
            color: var(--eerie-black-1);
        }
        
        .newsletter-form .btn {
            min-width: 100%;
            justify-content: center;
        }
        
        .social-list {
            display: flex;
            gap: 25px;
        }
        
        .social-link { font-size: 20px; }
        
        .footer-bottom {
            border-block-start: 1px solid var(--eerie-black-1);
            padding-block: 30px;
        }
        
        .copyright { text-align: center; }
        
        .copyright-link {
            color: var(--kappel);
            display: inline-block;
        }
        
        /*-----------------------------------*\
          #BACK TO TOP
        \*-----------------------------------*/
        .back-top-btn {
            position: fixed;
            bottom: 40px;
            right: 30px;
            background-color: var(--kappel);
            color: var(--white);
            font-size: 20px;
            padding: 15px;
            border-radius: var(--radius-circle);
            z-index: 3;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition-1);
        }
        
        .back-top-btn.active {
            transform: translateY(10px);
            opacity: 1;
            pointer-events: all;
        }
        
        /* Chat Widget */
        .chat-widget {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        .chat-toggle {
            width: 60px;
            height: 60px;
            background: #00BFFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,191,255,0.3);
            transition: all 0.3s ease;
            border: none;
            color: white;
            font-size: 1.8rem;
        }
        
        .chat-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0,191,255,0.4);
        }
        
        .chat-menu {
            position: absolute;
            bottom: 80px;
            right: 0;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            width: 280px;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s ease;
            border: 1px solid var(--platinum);
        }
        
        .chat-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .chat-header {
            background: #00BFFF;
            padding: 1rem;
            color: white;
            text-align: center;
        }
        
        .chat-header h4 {
            font-size: 1rem;
            margin: 0;
            font-weight: 600;
        }
        
        .chat-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--platinum);
            cursor: pointer;
        }
        
        .chat-option:last-child { border-bottom: none; }
        
        .chat-option:hover { background: var(--isabelline); }
        
        .chat-option-icon {
            width: 45px;
            height: 45px;
            background: rgba(0,191,255,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #00BFFF;
        }
        
        .chat-option-info h5 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--eerie-black-1);
            margin: 0 0 0.2rem;
        }
        
        .chat-option-info p {
            font-size: 0.7rem;
            color: var(--gray-web);
            margin: 0;
        }
        
        /* AI Chat Modal */
        .ai-chat-modal {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 350px;
            height: 500px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            z-index: 1001;
            display: none;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--platinum);
        }
        
        .ai-chat-modal.active { display: flex; }
        
        .ai-chat-header {
            background: #00BFFF;
            padding: 1rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .close-ai-chat {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        .ai-chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .message {
            max-width: 85%;
            padding: 0.6rem 1rem;
            border-radius: 18px;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .message.bot {
            background: var(--isabelline);
            color: var(--eerie-black-1);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        
        .message.user {
            background: #00BFFF;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        
        .ai-chat-input-area {
            display: flex;
            padding: 0.75rem;
            border-top: 1px solid var(--platinum);
            gap: 0.5rem;
        }
        
        .ai-chat-input {
            flex: 1;
            padding: 0.6rem 1rem;
            border: 1px solid var(--platinum);
            border-radius: 25px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            background: var(--white);
            color: var(--eerie-black-1);
        }
        
        .ai-chat-input:focus {
            outline: none;
            border-color: #00BFFF;
        }
        
        .ai-chat-send {
            background: #00BFFF;
            border: none;
            color: white;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .typing-indicator {
            display: flex;
            gap: 0.3rem;
            padding: 0.6rem 1rem;
            background: var(--isabelline);
            border-radius: 18px;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #94a3b8;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-6px); opacity: 1; }
        }
        
        /* Pricing Section Styles */
        .pricing { background-color: var(--isabelline); }
        
        .pricing-header { text-align: center; margin-bottom: 3rem; }
        
        .pricing-title { font-size: 2.2rem; font-weight: 700; color: var(--eerie-black-1); margin-bottom: 0.5rem; }
        
        .pricing-underline { width: 80px; height: 3px; background: #facc15; margin: 1rem auto 0; border-radius: 2px; }
        
        .pricing-group-title { font-size: 2rem; font-weight: 700; color: var(--kappel); text-align: center; margin: 3rem 0 2rem; }
        
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; max-width: 1200px; margin: 0 auto 3rem; padding: 0 1.5rem; }
        
        .pricing-card { border-radius: 24px; padding: 2rem; text-align: center; position: relative; transition: all 0.4s ease; background: var(--white); border: 1px solid var(--platinum); box-shadow: var(--shadow-1); }
        
        .pricing-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-3); }
        
        .pricing-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); padding: 0.5rem 1.5rem; border-radius: 50px; font-size: 0.85rem; font-weight: 700; color: #fff; background: var(--kappel); white-space: nowrap; }
        
        .pricing-card h4 { font-size: 1.1rem; font-weight: 600; margin: 1.5rem 0 0.5rem; color: var(--eerie-black-1); }
        
        .pricing-price { font-size: 2.2rem; font-weight: 800; color: var(--kappel); margin: 0.5rem 0; }
        
        .currency { font-size: 1rem; font-weight: 600; }
        .amount { font-size: 2.2rem; }
        
        .pricing-period { color: var(--gray-web); font-size: 0.85rem; margin-bottom: 1rem; }
        
        .onboarding-fee { background: rgba(30,64,175,0.08); padding: 0.5rem; border-radius: 12px; margin: 1rem 0; font-size: 0.9rem; }
        
        .onboarding-fee strong { color: var(--kappel); font-size: 1.1rem; }
        
        .price-divider { margin: 1rem 0; border-top: 1px dashed var(--platinum); }
        
        .pricing-actions { display: flex; gap: 0.75rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
        
        .pricing-button { padding: 0.7rem 1.2rem; border-radius: 10px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-block; font-size: 0.9rem; white-space: nowrap; }
        
        .pricing-button.primary { background: var(--kappel); color: #fff; }
        .pricing-button.primary:hover { background: hsl(170, 75%, 35%); transform: translateY(-2px); }
        .pricing-button.outline { border: 2px solid var(--kappel); color: var(--kappel); background: transparent; }
        .pricing-button.outline:hover { background: var(--kappel); color: #fff; transform: translateY(-2px); }
        
        /* FAQ Section */
        .faq { background: var(--white); padding: 90px 0; }
        
        .faq-header { text-align: center; margin-bottom: 3rem; }
        
        .faq-title { font-size: 2.2rem; font-weight: 700; color: var(--eerie-black-1); margin-bottom: 0.5rem; }
        
        .faq-underline { width: 80px; height: 3px; background: #facc15; margin: 1rem auto 0; border-radius: 2px; }
        
        .faq-container { max-width: 800px; margin: 0 auto; padding: 0 1.5rem; }
        
        .faq-item { background: var(--white); margin-bottom: 1rem; border-radius: 12px; box-shadow: var(--shadow-1); overflow: hidden; border: 1px solid var(--platinum); }
        
        .faq-question { padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 600; color: var(--eerie-black-1); }
        
        .faq-question i { transition: transform 0.3s ease; color: var(--kappel); }
        
        .faq-item.active .faq-question i { transform: rotate(180deg); }
        
        .faq-answer { padding: 0 1.5rem 1.5rem; color: var(--gray-web); line-height: 1.6; display: none; }
        
        .faq-item.active .faq-answer { display: block; }
        
        /* CTA Section */
        .cta { background: linear-gradient(135deg, var(--kappel), hsl(170, 75%, 35%)); padding: 90px 0; text-align: center; }
        
        body.dark-mode .cta { background: linear-gradient(135deg, hsl(170, 75%, 35%), hsl(170, 75%, 25%)); }
        
        .cta-container { max-width: 800px; margin: 0 auto; padding: 0 1.5rem; }
        
        .cta-title { font-size: 2.2rem; font-weight: 800; margin-bottom: 1rem; color: #ffffff; }
        
        .cta-subtitle { font-size: 1.1rem; opacity: 0.9; margin-bottom: 2rem; color: rgba(255,255,255,0.9); }
        
        .cta .btn { display: inline-block; width: auto; white-space: nowrap; background: #ffffff; color: var(--kappel); padding: 1rem 2rem; border-radius: 12px; font-weight: 600; text-decoration: none; transition: all 0.3s ease; }
        
        .cta .btn:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(0,0,0,0.2); background: #f8f9fa; }
        
        /* Responsive */
        @media (min-width: 575px) {
            .container { max-width: 520px; width: 100%; margin-inline: auto; }
            .grid-list { grid-template-columns: 1fr 1fr; }
            :is(.course, .blog) .grid-list { grid-template-columns: 1fr; }
            .stats-card { padding: 40px 30px; }
            .footer-brand, .footer-list:last-child { grid-column: 1 / 3; }
            .newsletter-form { display: flex; align-items: center; gap: 10px; }
            .newsletter-form .input-field { margin-block-end: 0; }
            .newsletter-form .btn { min-width: max-content; }
            .contact-info-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (min-width: 768px) {
            :root {
                --fs-1: 4.6rem;
                --fs-2: 3.8rem;
            }
            .container { max-width: 720px; }
            .btn { padding: 15px 30px; }
            :is(.course, .blog) .grid-list { grid-template-columns: 1fr 1fr; }
            .hero { padding-block-start: calc(var(--section-padding) + 90px); }
            .hero .container { gap: 50px; }
            .hero-banner { position: relative; z-index: 1; }
            .hero-banner .img-holder.one { justify-self: flex-end; }
            .hero-banner .img-holder.two { margin-block-start: 100px; }
            .hero-shape-1 { display: block; position: absolute; bottom: -40px; left: -10px; }
            .about { padding-block-start: 50px; }
            .about-banner { padding: 60px; padding-inline-end: 0; }
            .about-banner .img-holder { max-width: max-content; margin-inline: auto; }
            .about-shape-1 { display: block; top: -40px; right: -70px; }
            .footer-brand, .footer-list:last-child { grid-column: auto; }
            .newsletter-form .btn { padding-block: 10px; }
            .contact-grid { grid-template-columns: 1fr 1fr; gap: 50px; }
            .contact-info-grid { grid-template-columns: 1fr; margin-bottom: 0; }
        }
        
        @media (min-width: 992px) {
            :root {
                --fs-1: 5.5rem;
                --fs-2: 4.5rem;
            }
            .container { max-width: 960px; }
            .grid-list { grid-template-columns: repeat(4, 1fr); }
            :is(.course, .blog) .grid-list { grid-template-columns: repeat(3, 1fr); }
            .hero .container { grid-template-columns: 1fr 1fr; align-items: center; }
            .hero .section-title, .hero-text { text-align: left; }
            .hero .btn { margin-inline: 0; }
            .about .container { grid-template-columns: 1fr 0.6fr; align-items: center; gap: 60px; }
            .footer .grid-list { grid-template-columns: 1fr 0.6fr 0.6fr 1.2fr; }
            .contact-info-grid { grid-template-columns: 1fr; }
            .contact-grid { grid-template-columns: 1fr 1fr; gap: 60px; }
        }
        
        @media (min-width: 1200px) {
            :root {
                --fs-1: 6.5rem;
                --section-padding: 120px;
            }
            .container { max-width: 1185px; }
            .shape { display: block; }
            .about-content, .blog { position: relative; }
            .hero { padding-block-start: calc(var(--section-padding) + 120px); }
            .hero .container { gap: 80px; }
            .hero-shape-2 { top: -80px; z-index: -1; }
            .about .container { gap: 110px; }
            .about-banner .img-holder { margin-inline: 0; }
            .about-shape-3 { top: -20px; left: -100px; z-index: -1; }
            .about-content { z-index: 1; }
            .about-shape-4 { top: 30px; right: -60px; z-index: -1; }
            .blog-shape { top: 0; left: 0; }
        }
        
        @media (max-width: 991px) {
            .hero-banner { display: none; }
            .pricing-grid { grid-template-columns: 1fr; }
            .faq-container { padding: 0 1rem; }
            .cta-title { font-size: 1.8rem; }
            .stats-card { padding: 20px; }
        }
        
        @media (max-width: 767px) {
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .contact-info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .logo img { height: 32px; }
            .header-actions { gap: 8px; }
        }
        
        /* Reveal Animation */
        .reveal { opacity: 0; transform: translateY(40px); transition: all 0.8s ease; }
        .reveal.active { opacity: 1; transform: translateY(0); }
        .delay-1 { transition-delay: 0.2s; }
        .delay-2 { transition-delay: 0.4s; }
        .delay-3 { transition-delay: 0.6s; }
        
        /* Preloader */
        #eduscore-preloader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #ffffff; z-index: 9999;
            display: flex; justify-content: center; align-items: center; flex-direction: column; transition: opacity 0.5s ease;
        }
        .hero-buttons .btn {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hero-buttons .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.btn-primary-custom:hover,
.btn-secondary-custom:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    filter: brightness(0.95);
}
        /* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: var(--radius-5);
    margin-bottom: 25px;
    font-size: 1.4rem;
    animation: slideDown 0.3s ease;
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

.alert i {
    margin-right: 8px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
        body.dark-mode #eduscore-preloader { background: #0f172a; }
        .dotted-spinner { width: 80px; height: 80px; position: relative; margin-bottom: 30px; }
        .dotted-spinner::before { content: ''; position: absolute; width: 80px; height: 80px; border-radius: 50%; border: 4px dotted #facc15; border-top-color: transparent; animation: spin 1.5s linear infinite; }
        .dotted-spinner::after { content: ''; position: absolute; width: 60px; height: 60px; top: 10px; left: 10px; border-radius: 50%; border: 3px dotted rgba(250, 204, 21, 0.5); border-bottom-color: transparent; animation: spinReverse 1s linear infinite; }
        .loading-text { font-size: 1.5rem; font-weight: 600; color: #1e40af; margin-bottom: 20px; }
        .progress-container { width: 200px; height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #facc15, #fbbf24); width: 0%; animation: progress 2s ease-in-out infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes spinReverse { 0% { transform: rotate(0deg); } 100% { transform: rotate(-360deg); } }
        @keyframes progress { 0% { width: 0%; transform: translateX(-100%); } 50% { width: 100%; transform: translateX(0%); } 100% { width: 0%; transform: translateX(100%); } }
    </style>
</head>
<body>
    <?php if ($show_preloader): ?>
    <div id="eduscore-preloader">
        <div class="dotted-spinner"></div>
        <div class="loading-text">Loading EduScore</div>
        <div class="progress-container"><div class="progress-bar"></div></div>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="header" data-header>
        <div class="container">
            <a href="/" class="logo">
                <img src="/images/logo.png" alt="EduScore logo">
            </a>

            <nav class="navbar" data-navbar>
                <div class="wrapper">
                    <a href="/" class="logo">
                        <img src="/images/logo.png" alt="EduScore logo">
                    </a>
                    <button class="nav-close-btn" aria-label="close menu" data-nav-toggler>
                        <ion-icon name="close-outline" aria-hidden="true"></ion-icon>
                    </button>
                </div>
                <ul class="navbar-list">
                    <li class="navbar-item"><a href="#home" class="navbar-link" data-nav-link>Home</a></li>
                    <li class="navbar-item"><a href="#about" class="navbar-link" data-nav-link>About</a></li>
                    <li class="navbar-item dropdown" id="solutionsDropdown">
                        <a href="#courses" class="navbar-link" data-nav-link>
                            Solutions <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="analytics.php"><i class="fas fa-chart-line" style="margin-right: 8px;"></i> Exam Management</a></li>
                            <li><a href="feesystem.php"><i class="fas fa-wallet" style="margin-right: 8px;"></i> Fee Management</a></li>
                            <li><a href="parents-portal/register.php"><i class="fas fa-users" style="margin-right: 8px;"></i> Parents Portal</a></li>
                            <li><a href="#"><i class="fas fa-file-alt" style="margin-right: 8px;"></i> Reports</a></li>
                        </ul>
                    </li>
                    <li class="navbar-item"><a href="#pricing" class="navbar-link" data-nav-link>Pricing</a></li>
                    <li class="navbar-item"><a href="blog.php" class="navbar-link" data-nav-link>Blog</a></li>
                    <li class="navbar-item"><a href="#contact" class="navbar-link" data-nav-link>Contact</a></li>
                </ul>
                
                <!-- Mobile Portal Buttons inside navbar -->
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
        <article>
            <!-- Hero Section -->
<section class="section hero has-bg-image" id="home" aria-label="home">
    <div class="container">
        <div class="hero-content reveal">
            <h1 class="h1 section-title">
                The Best School <span class="span">Management System</span> for Kenyan Schools
            </h1>
            <p class="hero-text">
                Streamline administration, enhance learning outcomes, and engage parents seamlessly with Kenya's #1 school management platform.
            </p>
            
            <!-- Button Group with better styling -->
            <div class="hero-button-group" style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; margin-top: 2rem;">
                <a href="register.php" class="btn-primary-custom" style="background: var(--kappel); color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <span>Start Free Trial</span>
                    <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
                </a>
                <a href="parents-portal.php" class="btn-secondary-custom" style="background: var(--selective-yellow); color: var(--eerie-black-1); padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-users"></i>
                    <span>Parents Portal</span>
                </a>
                <a href="bulksms.php" class="btn-secondary-custom" style="background: var(--radical-red); color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-sms"></i>
                    <span>Bulk SMS</span>
                </a>
            </div>
        </div>
        <figure class="hero-banner reveal delay-1">
            <div class="img-holder one" style="--width: 270; --height: 300;">
                <img src="/images/school-bg.png" width="270" height="300" alt="hero banner" class="img-cover">
            </div>
            <div class="img-holder two" style="--width: 240; --height: 370;">
                <img src="/images/logo.png" width="240" height="370" alt="hero banner" class="img-cover">
            </div>
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 380 190'%3E%3Cpath fill='%2300BFFF' fill-opacity='0.1' d='M0,0 L380,0 L380,190 L0,190 Z'/%3E%3C/svg%3E" width="380" height="190" alt="" class="shape hero-shape-1">
        </figure>
    </div>
</section>

            <!-- Stats Counter Section -->
            <section class="stats-counter section">
                <div class="container">
                    <ul class="grid-list">
                        <li><div class="stats-card" style="--color: 170, 75%, 41%"><h3 class="card-title" id="schoolsCount">0</h3><p class="card-text">Schools</p></div></li>
                        <li><div class="stats-card" style="--color: 351, 83%, 61%"><h3 class="card-title" id="studentsCount">0</h3><p class="card-text">Students</p></div></li>
                        <li><div class="stats-card" style="--color: 260, 100%, 67%"><h3 class="card-title" id="teachersCount">0</h3><p class="card-text">Teachers</p></div></li>
                        <li><div class="stats-card" style="--color: 42, 94%, 55%"><h3 class="card-title" id="reportsCount">0</h3><p class="card-text">Reports Generated</p></div></li>
                    </ul>
                </div>
            </section>

            <!-- Category / Features Section -->
            <section class="section category" aria-label="category">
                <div class="container">
                    <p class="section-subtitle">Features</p>
                    <h2 class="h2 section-title">Everything You Need to <span class="span">Run Your School</span></h2>
                    <p class="section-text">Powerful tools designed to streamline every aspect of school administration</p>
                    <ul class="grid-list">
                        <li><div class="category-card" style="--color: 170, 75%, 41%"><div class="card-icon"><i class="fas fa-coins" style="font-size: 40px; color: hsl(170, 75%, 41%);"></i></div><h3 class="h3"><a href="#" class="card-title">Smart Fee Management</a></h3><p class="card-text">Automated fee tracking, online payments, and real-time balance updates.</p><span class="card-badge">Popular</span></div></li>
                        <li><div class="category-card" style="--color: 351, 83%, 61%"><div class="card-icon"><i class="fas fa-bullhorn" style="font-size: 40px; color: hsl(351, 83%, 61%);"></i></div><h3 class="h3"><a href="#" class="card-title">Bulk SMS Notifications</a></h3><p class="card-text">Send instant alerts, reminders, and announcements to parents and staff.</p><span class="card-badge">Instant</span></div></li>
                        <li><div class="category-card" style="--color: 260, 100%, 67%"><div class="card-icon"><i class="fas fa-chart-pie" style="font-size: 40px; color: hsl(260, 100%, 67%);"></i></div><h3 class="h3"><a href="#" class="card-title">Real-time Analytics</a></h3><p class="card-text">Visualize school performance and student progress with interactive charts.</p><span class="card-badge">Live</span></div></li>
                        <li><div class="category-card" style="--color: 42, 94%, 55%"><div class="card-icon"><i class="fas fa-file-alt" style="font-size: 40px; color: hsl(42, 94%, 55%);"></i></div><h3 class="h3"><a href="#" class="card-title">Automated Report Cards</a></h3><p class="card-text">Generate professional report cards instantly with customizable templates.</p><span class="card-badge">Auto</span></div></li>
                    </ul>
                </div>
            </section>

            <!-- About Section -->
            <section class="section about" id="about" aria-label="about">
                <div class="container">
                    <figure class="about-banner reveal">
                        <div class="img-holder" style="--width: 520; --height: 370;"><img src="/images/school-bg.png" width="520" height="370" loading="lazy" alt="about banner" class="img-cover"></div>
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 360 420'%3E%3Cpath fill='%2300BFFF' fill-opacity='0.05' d='M0,0 L360,0 L360,420 L0,420 Z'/%3E%3C/svg%3E" width="360" height="420" loading="lazy" alt="" class="shape about-shape-1">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 371 220'%3E%3Cpath fill='%23facc15' fill-opacity='0.1' d='M0,0 L371,0 L371,220 L0,220 Z'/%3E%3C/svg%3E" width="371" height="220" loading="lazy" alt="" class="shape about-shape-2">
                    </figure>
                    <div class="about-content reveal delay-1">
                        <p class="section-subtitle">About Us</p>
                        <h2 class="h2 section-title">Over 10 Years in <span class="span">School Management</span> for Kenyan Education</h2>
                        <p class="section-text">EduScore is Kenya's leading school management system, trusted by 500+ schools across the country. We provide comprehensive solutions for CBC and 8-4-4 curricula.</p>
                        <ul class="about-list">
                            <li class="about-item"><ion-icon name="checkmark-done-outline" aria-hidden="true"></ion-icon><span class="span">Expert Trainers & Support</span></li>
                            <li class="about-item"><ion-icon name="checkmark-done-outline" aria-hidden="true"></ion-icon><span class="span">Cloud-Based Remote Access</span></li>
                            <li class="about-item"><ion-icon name="checkmark-done-outline" aria-hidden="true"></ion-icon><span class="span">Lifetime Data Security</span></li>
                        </ul>
                    </div>
                </div>
            </section>

<!-- Courses / Solutions Section -->
<section class="section course" id="courses" aria-label="course">
    <div class="container">
        <p class="section-subtitle">Our Solutions</p>
        <h2 class="h2 section-title">Pick A Solution To Get Started</h2>
        <ul class="grid-list">
            <li>
                <div class="course-card">
                    <figure class="card-banner img-holder" style="--width: 370; --height: 220;">
                        <img src="/images/analytics.PNG" width="370" height="220" loading="lazy" alt="Exam Analysis" class="img-cover">
                    </figure>
                    <div class="abs-badge"><ion-icon name="time-outline" aria-hidden="true"></ion-icon><span class="span">Real-time</span></div>
                    <div class="card-content">
                        <span class="badge">Analytics</span>
                        <h3 class="h3"><a href="analytics.php" class="card-title">Exam Analysis & Performance Tracking</a></h3>
                        <div class="wrapper">
                            <div class="rating-wrapper">
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                            </div>
                            <p class="rating-text">(4.9/5 Rating)</p>
                        </div>
                        <data class="price" value="0">Free Trial</data>
                        <ul class="card-meta-list">
                            <li class="card-meta-item"><ion-icon name="library-outline" aria-hidden="true"></ion-icon><span class="span">Auto Reports</span></li>
                            <li class="card-meta-item"><ion-icon name="people-outline" aria-hidden="true"></ion-icon><span class="span">All Students</span></li>
                        </ul>
                        <div class="pricing-actions" style="margin-top: 1.5rem;">
                            <a href="analytics.php" class="pricing-button primary" style="background: var(--kappel); color: #fff; padding: 0.7rem 1.2rem; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-block;">Get Started</a>
                            <a href="analytics.php" class="pricing-button outline" style="border: 2px solid var(--kappel); color: var(--kappel); background: transparent; padding: 0.7rem 1.2rem; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-block;">Learn More</a>
                        </div>
                    </div>
                </div>
            </li>
            <li>
                <div class="course-card">
                    <figure class="card-banner img-holder" style="--width: 370; --height: 220;">
                        <img src="/images/feesystem.PNG" width="370" height="220" loading="lazy" alt="Fee Management" class="img-cover">
                    </figure>
                    <div class="abs-badge"><ion-icon name="time-outline" aria-hidden="true"></ion-icon><span class="span">Automated</span></div>
                    <div class="card-content">
                        <span class="badge">Finance</span>
                        <h3 class="h3"><a href="feesystem.php" class="card-title">Fee Management & Payment Tracking</a></h3>
                        <div class="wrapper">
                            <div class="rating-wrapper">
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                            </div>
                            <p class="rating-text">(4.8/5 Rating)</p>
                        </div>
                        <data class="price" value="0">Free Trial</data>
                        <ul class="card-meta-list">
                            <li class="card-meta-item"><ion-icon name="library-outline" aria-hidden="true"></ion-icon><span class="span">M-Pesa</span></li>
                            <li class="card-meta-item"><ion-icon name="people-outline" aria-hidden="true"></ion-icon><span class="span">Auto Reminders</span></li>
                        </ul>
                        <div class="pricing-actions" style="margin-top: 1.5rem;">
                            <a href="feesystem.php" class="pricing-button primary" style="background: var(--kappel); color: #fff; padding: 0.7rem 1.2rem; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-block;">Get Started</a>
                            <a href="feesystem.php" class="pricing-button outline" style="border: 2px solid var(--kappel); color: var(--kappel); background: transparent; padding: 0.7rem 1.2rem; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-block;">Learn More</a>
                        </div>
                    </div>
                </div>
            </li>
            <li>
                <div class="course-card">
                    <figure class="card-banner img-holder" style="--width: 370; --height: 220;">
                        <img src="/images/bulksms.PNG" width="370" height="220" loading="lazy" alt="Bulk SMS" class="img-cover">
                    </figure>
                    <div class="abs-badge"><ion-icon name="time-outline" aria-hidden="true"></ion-icon><span class="span">Instant</span></div>
                    <div class="card-content">
                        <span class="badge">Communication</span>
                        <h3 class="h3"><a href="bulksms.php" class="card-title">Bulk SMS & Notifications</a></h3>
                        <div class="wrapper">
                            <div class="rating-wrapper">
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                                <ion-icon name="star"></ion-icon>
                            </div>
                            <p class="rating-text">(4.9/5 Rating)</p>
                        </div>
                        <data class="price" value="0">Free Trial</data>
                        <ul class="card-meta-list">
                            <li class="card-meta-item"><ion-icon name="library-outline" aria-hidden="true"></ion-icon><span class="span">Bulk Messaging</span></li>
                            <li class="card-meta-item"><ion-icon name="people-outline" aria-hidden="true"></ion-icon><span class="span">Instant Delivery</span></li>
                        </ul>
                        <div class="pricing-actions" style="margin-top: 1.5rem;">
                            <a href="bulksms.php" class="pricing-button primary" style="background: var(--kappel); color: #fff; padding: 0.7rem 1.2rem; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-block;">Get Started</a>
                            <a href="bulksms.php" class="pricing-button outline" style="border: 2px solid var(--kappel); color: var(--kappel); background: transparent; padding: 0.7rem 1.2rem; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-block;">Learn More</a>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
        <a href="register.php" class="btn has-before"><span class="span">Browse more solutions</span><ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a>
    </div>
</section>

            <!-- Pricing Section -->
            <section class="section pricing" id="pricing">
                <div class="container">
                    <div class="pricing-header"><h2 class="pricing-title">Pricing</h2><div class="pricing-underline"></div><p class="section-text">Affordable, transparent pricing for Kenyan schools</p></div>
                    <h3 class="pricing-group-title">🏫 Primary Schools</h3>
                    <div class="pricing-grid">
                        <div class="pricing-card reveal"><div class="pricing-badge">Public School</div><h4>Single System (Finance or Exam)</h4><div class="pricing-price"><span class="currency">KES</span><span class="amount"><?php echo number_format($pricing['primary']['public']['single']['price']); ?></span></div><div class="pricing-period">per student per term</div><div class="onboarding-fee"><i class="fas fa-handshake"></i> Onboarding Fee: <strong>KES <?php echo number_format($pricing['primary']['public']['single']['onboarding']); ?></strong></div><div class="price-divider"></div><h4>Both Systems (Finance + Exam)</h4><div class="pricing-price"><span class="currency">KES</span><span class="amount"><?php echo number_format($pricing['primary']['public']['both']['price']); ?></span></div><div class="pricing-period">per student per term</div><div class="onboarding-fee"><i class="fas fa-handshake"></i> Onboarding Fee: <strong>KES <?php echo number_format($pricing['primary']['public']['both']['onboarding']); ?></strong></div><div class="pricing-actions"><a href="register.php" class="pricing-button primary">Get Started</a><a href="login.php" class="pricing-button outline">Login</a></div></div>
                        <div class="pricing-card reveal delay-1"><div class="pricing-badge">Private School</div><h4>Single System (Finance or Exam)</h4><div class="pricing-price"><span class="currency">KES</span><span class="amount"><?php echo number_format($pricing['primary']['private']['single']['price']); ?></span></div><div class="pricing-period">per student per term</div><div class="onboarding-fee"><i class="fas fa-handshake"></i> Onboarding Fee: <strong>KES <?php echo number_format($pricing['primary']['private']['single']['onboarding']); ?></strong></div><div class="price-divider"></div><h4>Both Systems (Finance + Exam)</h4><div class="pricing-price"><span class="currency">KES</span><span class="amount"><?php echo number_format($pricing['primary']['private']['both']['price']); ?></span></div><div class="pricing-period">per student per term</div><div class="onboarding-fee"><i class="fas fa-handshake"></i> Onboarding Fee: <strong>KES <?php echo number_format($pricing['primary']['private']['both']['onboarding']); ?></strong></div><div class="pricing-actions"><a href="register.php" class="pricing-button primary">Get Started</a><a href="login.php" class="pricing-button outline">Login</a></div></div>
                    </div>
                    <h3 class="pricing-group-title">🎓 Secondary Schools</h3>
                    <div class="pricing-grid">
                        <div class="pricing-card reveal"><div class="pricing-badge">Public School</div><h4>Single System (Finance or Exam)</h4><div class="pricing-price"><span class="currency">KES</span><span class="amount"><?php echo number_format($pricing['secondary']['public']['single']['price']); ?></span></div><div class="pricing-period">per student per term</div><div class="onboarding-fee"><i class="fas fa-handshake"></i> Onboarding Fee: <strong>KES <?php echo number_format($pricing['secondary']['public']['single']['onboarding']); ?></strong></div><div class="price-divider"></div><h4>Both Systems (Finance + Exam)</h4><div class="pricing-price"><span class="currency">KES</span><span class="amount"><?php echo number_format($pricing['secondary']['public']['both']['price']); ?></span></div><div class="pricing-period">per student per term</div><div class="onboarding-fee"><i class="fas fa-handshake"></i> Onboarding Fee: <strong>KES <?php echo number_format($pricing['secondary']['public']['both']['onboarding']); ?></strong></div><div class="pricing-actions"><a href="register.php" class="pricing-button primary">Get Started</a><a href="login.php" class="pricing-button outline">Login</a></div></div>
                        <div class="pricing-card reveal delay-1"><div class="pricing-badge">Private School</div><h4>Single System (Finance or Exam)</h4><div class="pricing-price"><span class="currency">KES</span><span class="amount"><?php echo number_format($pricing['secondary']['private']['single']['price']); ?></span></div><div class="pricing-period">per student per term</div><div class="onboarding-fee"><i class="fas fa-handshake"></i> Onboarding Fee: <strong>KES <?php echo number_format($pricing['secondary']['private']['single']['onboarding']); ?></strong></div><div class="price-divider"></div><h4>Both Systems (Finance + Exam)</h4><div class="pricing-price"><span class="currency">KES</span><span class="amount"><?php echo number_format($pricing['secondary']['private']['both']['price']); ?></span></div><div class="pricing-period">per student per term</div><div class="onboarding-fee"><i class="fas fa-handshake"></i> Onboarding Fee: <strong>KES <?php echo number_format($pricing['secondary']['private']['both']['onboarding']); ?></strong></div><div class="pricing-actions"><a href="register.php" class="pricing-button primary">Get Started</a><a href="login.php" class="pricing-button outline">Login</a></div></div>
                    </div>
                    <p class="section-text" style="margin-top: 1rem;"><i class="fas fa-info-circle"></i> * Parents Portal, Exam Generator, and Mwalimu AI included in all plans.</p>
                </div>
            </section>

            <!-- FAQ Section -->
            <section class="section faq" id="faq">
                <div class="container">
                    <div class="faq-header"><h2 class="faq-title">Frequently Asked Questions</h2><div class="faq-underline"></div></div>
                    <div class="faq-container">
                        <div class="faq-item"><div class="faq-question">Is there a free trial available? <i class="fas fa-chevron-down"></i></div><div class="faq-answer">Yes, we offer a 14-day free trial for all our plans. No credit card required.</div></div>
                        <div class="faq-item"><div class="faq-question">What is Mwalimu AI? <i class="fas fa-chevron-down"></i></div><div class="faq-answer">Mwalimu AI is our AI-powered teaching assistant for the CBC curriculum. It helps create lesson plans, generate assessments, and provides personalized learning support.</div></div>
                        <div class="faq-item"><div class="faq-question">Can parents access student information? <i class="fas fa-chevron-down"></i></div><div class="faq-answer">Yes! The Parents Portal gives parents real-time access to their children's academic progress, fee balances, and attendance records.</div></div>
                        <div class="faq-item"><div class="faq-question">What payment methods do you accept? <i class="fas fa-chevron-down"></i></div><div class="faq-answer">We accept M-Pesa, bank transfers, and credit cards with flexible payment options for Kenyan schools.</div></div>
                    </div>
                </div>
            </section>

            <!-- Blog Section - Dynamically fetching 3 latest posts from database -->
            <section class="section blog has-bg-image" id="blog" aria-label="blog">
                <div class="container">
                    <p class="section-subtitle">Latest Articles</p>
                    <h2 class="h2 section-title">Get News With EduScore</h2>
                    
                    <?php if (!empty($latest_blogs)): ?>
                        <ul class="grid-list">
                            <?php foreach ($latest_blogs as $blog): ?>
                                <li>
                                    <div class="blog-card">
<figure class="card-banner img-holder has-after" style="--width: 370; --height: 370;">
    <?php if ($blog['image_url']): ?>
        <img src="<?php echo htmlspecialchars($blog['image_url']); ?>" width="370" height="370" loading="lazy" alt="<?php echo htmlspecialchars($blog['title']); ?>" class="img-cover">
    <?php else: ?>
        <div class="no-image" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--kappel) 0%, var(--kappel_15) 100%);">
            <i class="fas fa-newspaper" style="font-size: 4rem; color: white; opacity: 0.5;"></i>
        </div>
    <?php endif; ?>
</figure>
                                        <div class="card-content">
                                            <a href="blog.php?id=<?php echo $blog['id']; ?>" class="card-btn" aria-label="read more">
                                                <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
                                            </a>
                                            <a href="blog.php?id=<?php echo $blog['id']; ?>" class="card-subtitle"><?php echo htmlspecialchars($blog['category'] ?? 'Education'); ?></a>
                                            <h3 class="h3">
                                                <a href="blog.php?id=<?php echo $blog['id']; ?>" class="card-title"><?php echo htmlspecialchars($blog['title']); ?></a>
                                            </h3>
                                            <ul class="card-meta-list">
                                                <li class="card-meta-item">
                                                    <ion-icon name="calendar-outline" aria-hidden="true"></ion-icon>
                                                    <span class="span"><?php echo date('M d, Y', strtotime($blog['created_at'])); ?></span>
                                                </li>
                                                <li class="card-meta-item">
                                                    <ion-icon name="person-outline" aria-hidden="true"></ion-icon>
                                                    <span class="span"><?php echo htmlspecialchars($blog['author'] ?? 'EduScore Team'); ?></span>
                                                </li>
                                            </ul>
                                            <p class="card-text"><?php echo htmlspecialchars(substr(strip_tags($blog['description'] ?? ''), 0, 100)) . '...'; ?></p>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <!-- Fallback message when no blogs in database -->
                        <div style="text-align: center; padding: 3rem;">
                            <p>No blog posts available yet. Check back soon for updates!</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="view-all-blog">
                        <a href="blog.php" class="btn has-before">
                            <span class="span">View All Articles</span>
                            <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
                        </a>
                    </div>
                </div>
            </section>

<!-- Contact Us Section -->
<section class="section contact-section" id="contact">
    <div class="container">
        <p class="section-subtitle">Contact Us</p>
        <h2 class="h2 section-title">Get in <span class="span">Touch</span> With Us</h2>
        <p class="section-text">Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
        
        <div id="contactAlert" style="display: none;"></div>
        
        <div class="contact-grid">
            <!-- Contact Info Cards -->
            <div class="contact-info reveal">
                <div class="contact-info-grid">
                    <div class="contact-card">
                        <div class="contact-card-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3>Our Location</h3>
                        <p>Ngara - Nairobi, Kenya</p>
                    </div>
                    <div class="contact-card">
                        <div class="contact-card-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <h3>Phone Number</h3>
                        <p><a href="tel:+254799115282">+254 799 115 282</a></p>
                    </div>
                    <div class="contact-card">
                        <div class="contact-card-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3>Email Address</h3>
                        <p><a href="mailto:eduscoreke@gmail.com">eduscoreke@gmail.com</a></p>
                    </div>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="contact-form-container reveal delay-1">
                <h3 class="contact-form-title">Send Us a Message</h3>
                <p class="contact-form-subtitle">Fill in the form below, and our team will respond as soon as possible.</p>
                
                <form id="contactForm" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Your Name <span style="color: red;">*</span></label>
                            <input type="text" id="name" name="name" class="contact-form-input" placeholder="Enter your full name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Your Email</label>
                            <input type="email" id="email" name="email" class="contact-form-input" placeholder="Enter your email address">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number <span style="color: red;">*</span></label>
                            <input type="tel" id="phone" name="phone" class="contact-form-input" placeholder="Enter your phone number" required>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject <span style="color: red;">*</span></label>
                            <input type="text" id="subject" name="subject" class="contact-form-input" placeholder="What is this regarding?" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="message">Message <span style="color: red;">*</span></label>
                        <textarea id="message" name="message" class="contact-form-textarea" placeholder="Write your message here..." required></textarea>
                    </div>
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
            <!-- CTA Section -->
            <section class="cta" id="cta">
                <div class="cta-container"><h2 class="cta-title">Ready to Transform Your School?</h2><p class="cta-subtitle">Join thousands of Kenyan schools using EduScore to streamline operations and enhance educational outcomes.</p><a href="register.php" class="btn">Start Free Trial</a></div>
            </section>
        </article>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-top section">
            <div class="container grid-list">
                <div class="footer-brand"><a href="#" class="logo"><img src="/images/logo.png" alt="EduScore logo"></a><p class="footer-brand-text">Modern school management system for Kenyan educational institutions.</p><div class="wrapper"><span class="span">Add:</span><address class="address">Ngara - Nairobi, Kenya</address></div><div class="wrapper"><span class="span">Call:</span><a href="tel:+254799115282" class="footer-link">+254 799 115 282</a></div><div class="wrapper"><span class="span">Email:</span><a href="mailto:eduscoreke@gmail.com" class="footer-link">eduscoreke@gmail.com</a></div></div>
                <ul class="footer-list"><li><p class="footer-list-title">Online Platform</p></li><li><a href="#about" class="footer-link">About</a></li><li><a href="#courses" class="footer-link">Courses</a></li><li><a href="#" class="footer-link">Instructor</a></li><li><a href="#" class="footer-link">Events</a></li><li><a href="#" class="footer-link">Purchase Guide</a></li></ul>
                <ul class="footer-list"><li><p class="footer-list-title">Links</p></li><li><a href="#contact" class="footer-link">Contact Us</a></li><li><a href="#" class="footer-link">Gallery</a></li><li><a href="blog.php" class="footer-link">News & Articles</a></li><li><a href="#faq" class="footer-link">FAQ's</a></li><li><a href="login.php" class="footer-link">Sign In/Registration</a></li></ul>
                <div class="footer-list"><p class="footer-list-title">Newsletter</p><p class="footer-list-text">Enter your email address to register to our newsletter subscription</p><form action="" class="newsletter-form"><input type="email" name="email_address" placeholder="Your email" required class="input-field"><button type="submit" class="btn has-before"><span class="span">Subscribe</span><ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></button></form><ul class="social-list"><li><a href="#" class="social-link"><ion-icon name="logo-facebook"></ion-icon></a></li><li><a href="#" class="social-link"><ion-icon name="logo-linkedin"></ion-icon></a></li><li><a href="#" class="social-link"><ion-icon name="logo-instagram"></ion-icon></a></li><li><a href="#" class="social-link"><ion-icon name="logo-twitter"></ion-icon></a></li><li><a href="#" class="social-link"><ion-icon name="logo-youtube"></ion-icon></a></li></ul></div>
            </div>
        </div>
        <div class="footer-bottom"><div class="container"><p class="copyright">Copyright <?php echo date('Y'); ?> All Rights Reserved by <a href="#" class="copyright-link">EduScore Kenya</a></p></div></div>
    </footer>

    <!-- Back to Top Button -->
    <a href="#top" class="back-top-btn" aria-label="back top top" data-back-top-btn><ion-icon name="chevron-up" aria-hidden="true"></ion-icon></a>

    <!-- Chat Widget -->
    <div class="chat-widget"><div class="chat-toggle" id="chatToggle"><i class="fas fa-comment-dots"></i></div><div class="chat-menu" id="chatMenu"><div class="chat-header"><h4>Chat with us</h4><p>We're here to help!</p></div><a href="https://wa.me/254799115282?text=Hello%21%20I%27m%20interested%20in%20EduScore" target="_blank" class="chat-option"><div class="chat-option-icon"><i class="fab fa-whatsapp"></i></div><div class="chat-option-info"><h5>WhatsApp Chat</h5><p>Talk to our support team</p></div></a><div class="chat-option" id="aiChatBtn"><div class="chat-option-icon"><i class="fas fa-robot"></i></div><div class="chat-option-info"><h5>AI Assistant</h5><p>Get instant answers 24/7</p></div></div></div></div>

    <!-- AI Chat Modal -->
    <div class="ai-chat-modal" id="aiChatModal"><div class="ai-chat-header"><h4><i class="fas fa-robot"></i> EduScore AI Assistant</h4><button class="close-ai-chat" id="closeAiChat">&times;</button></div><div class="ai-chat-messages" id="aiChatMessages"><div class="message bot">👋 Hi there! I'm EduScore AI Assistant. How can I help you today?<br>You can ask me about:<br>• Features and pricing<br>• School management solutions<br>• Getting started guide<br>• Technical support</div></div><div class="ai-chat-input-area"><input type="text" class="ai-chat-input" id="aiChatInput" placeholder="Type your message..."><button class="ai-chat-send" id="aiChatSend"><i class="fas fa-paper-plane"></i></button></div></div>

    <!-- Ionicon -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

    <script>
        <?php if ($show_preloader): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const preloader = document.getElementById('eduscore-preloader');
                if (preloader) { preloader.style.opacity = '0'; setTimeout(() => preloader.remove(), 500); }
            }, 2000);
        });
        <?php endif; ?>

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

        // Mobile Dropdown Toggle
        const solutionsDropdown = document.getElementById('solutionsDropdown');
        if (solutionsDropdown && window.innerWidth < 992) {
            solutionsDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.toggle('active');
            });
        }

        // Header active on scroll
        const header = document.querySelector("[data-header]");
        const backTopBtn = document.querySelector("[data-back-top-btn]");
        const activeElem = function () {
            if (window.scrollY > 100) {
                header.classList.add("active");
                backTopBtn.classList.add("active");
            } else {
                header.classList.remove("active");
                backTopBtn.classList.remove("active");
            }
        }
        window.addEventListener("scroll", activeElem);

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => { question.parentElement.classList.toggle('active'); });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]:not(.dropdown-menu a)').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                const target = document.querySelector(targetId);
                if (target) { 
                    e.preventDefault(); 
                    target.scrollIntoView({ behavior: 'smooth' }); 
                    if (window.innerWidth < 992 && navbar.classList.contains('active')) {
                        closeNavbar();
                    }
                }
            });
        });

        // Scroll reveal
        const reveals = document.querySelectorAll('.reveal');
        const revealObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); });
        }, { threshold: 0.15 });
        reveals.forEach(el => revealObserver.observe(el));

        // Stats counter animation
        function animateNumber(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                element.textContent = current.toLocaleString();
                if (progress < 1) window.requestAnimationFrame(step);
            };
            window.requestAnimationFrame(step);
        }
        function fetchStats() {
            fetch('ajax/get_stats.php').then(response => response.json()).then(data => {
                if (data.success) {
                    const schoolsEl = document.getElementById('schoolsCount');
                    const studentsEl = document.getElementById('studentsCount');
                    const teachersEl = document.getElementById('teachersCount');
                    const reportsEl = document.getElementById('reportsCount');
                    if (schoolsEl) animateNumber(schoolsEl, 0, data.data.schools, 1500);
                    if (studentsEl) animateNumber(studentsEl, 0, data.data.students, 1500);
                    if (teachersEl) animateNumber(teachersEl, 0, data.data.teachers, 1500);
                    if (reportsEl) animateNumber(reportsEl, 0, data.data.reports, 1500);
                }
            }).catch(error => console.error('Error fetching stats:', error));
        }
        const statsSection = document.querySelector('.stats-counter');
        if (statsSection) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => { if (entry.isIntersecting) { fetchStats(); observer.unobserve(entry.target); } });
            }, { threshold: 0.3 });
            observer.observe(statsSection);
        }

        // Chat Widget
        const chatToggle = document.getElementById('chatToggle');
        const chatMenu = document.getElementById('chatMenu');
        const aiChatBtn = document.getElementById('aiChatBtn');
        const aiChatModal = document.getElementById('aiChatModal');
        const closeAiChat = document.getElementById('closeAiChat');
        const aiChatSend = document.getElementById('aiChatSend');
        const aiChatInput = document.getElementById('aiChatInput');
        const aiChatMessages = document.getElementById('aiChatMessages');
        if (chatToggle && chatMenu) {
            chatToggle.addEventListener('click', (e) => { e.stopPropagation(); chatMenu.classList.toggle('active'); });
            document.addEventListener('click', (e) => { if (!chatToggle.contains(e.target) && !chatMenu.contains(e.target)) chatMenu.classList.remove('active'); });
        }
        if (aiChatBtn && aiChatModal) { aiChatBtn.addEventListener('click', () => { if (chatMenu) chatMenu.classList.remove('active'); aiChatModal.classList.add('active'); }); }
        if (closeAiChat && aiChatModal) { closeAiChat.addEventListener('click', () => { aiChatModal.classList.remove('active'); }); }
        function getAIResponse(userMessage) {
            const msg = userMessage.toLowerCase();
            if (msg.includes('price') || msg.includes('pricing')) return "💰 EduScore offers affordable pricing starting from KES 15 per student per term for public primary schools.";
            if (msg.includes('feature')) return "✨ EduScore includes: Exam Analysis, Fee Management, Bulk SMS, Parents Portal, Exam Generator, Mwalimu AI assistant, and more!";
            if (msg.includes('trial')) return "🎉 Yes! We offer a 14-day free trial with no credit card required.";
            if (msg.includes('support')) return "🛟 You can reach our support team via WhatsApp at +254 799 115 282 or email at eduscoreke@gmail.com";
            return "🤖 Thanks for your message! For immediate assistance, please reach out via WhatsApp at +254 799 115 282.";
        }
        function addTypingIndicator() { if (!aiChatMessages) return; const typingDiv = document.createElement('div'); typingDiv.className = 'typing-indicator'; typingDiv.id = 'typingIndicator'; typingDiv.innerHTML = '<span></span><span></span><span></span>'; aiChatMessages.appendChild(typingDiv); aiChatMessages.scrollTop = aiChatMessages.scrollHeight; }
        function removeTypingIndicator() { const indicator = document.getElementById('typingIndicator'); if (indicator) indicator.remove(); }
        function addMessage(text, isUser) { if (!aiChatMessages) return; const messageDiv = document.createElement('div'); messageDiv.className = `message ${isUser ? 'user' : 'bot'}`; messageDiv.innerHTML = text; aiChatMessages.appendChild(messageDiv); aiChatMessages.scrollTop = aiChatMessages.scrollHeight; }
        async function sendMessage() { if (!aiChatInput || !aiChatMessages) return; const message = aiChatInput.value.trim(); if (!message) return; addMessage(message, true); aiChatInput.value = ''; addTypingIndicator(); setTimeout(() => { removeTypingIndicator(); const response = getAIResponse(message); addMessage(response, false); }, 800); }
        if (aiChatSend && aiChatInput) { aiChatSend.addEventListener('click', sendMessage); aiChatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); }); }
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && aiChatModal && aiChatModal.classList.contains('active')) aiChatModal.classList.remove('active'); });
        
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                if (solutionsDropdown) solutionsDropdown.classList.remove('active');
                body.classList.remove('navbar-open');
                body.style.top = '';
            }
        });
        
// Contact Form with AJAX - No page reload
const contactForm = document.getElementById('contactForm');
const submitBtn = document.getElementById('submitBtn');
const contactAlert = document.getElementById('contactAlert');

if (contactForm) {
    contactForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Get form values
        const name = document.getElementById('name')?.value.trim();
        const email = document.getElementById('email')?.value.trim();
        const phone = document.getElementById('phone')?.value.trim();
        const subject = document.getElementById('subject')?.value.trim();
        const message = document.getElementById('message')?.value.trim();
        
        // Client-side validation
        if (!name) {
            showAlert('Please enter your name', 'error');
            return;
        }
        if (!phone) {
            showAlert('Please enter your phone number', 'error');
            return;
        }
        if (!subject) {
            showAlert('Please enter a subject', 'error');
            return;
        }
        if (!message) {
            showAlert('Please enter your message', 'error');
            return;
        }
        if (email && !isValidEmail(email)) {
            showAlert('Please enter a valid email address', 'error');
            return;
        }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('subject', subject);
        formData.append('message', message);
        
        // Show loading state
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('ajax/save_contact.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                showAlert(result.message, 'success');
                contactForm.reset();
            } else {
                showAlert('Error: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('An error occurred. Please try again later.', 'error');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

function showAlert(message, type) {
    const alertDiv = document.getElementById('contactAlert');
    if (alertDiv) {
        alertDiv.style.display = 'block';
        alertDiv.className = 'alert alert-' + type;
        alertDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-triangle') + '"></i> ' + message;
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            setTimeout(() => {
                alertDiv.style.display = 'none';
                alertDiv.style.opacity = '1';
            }, 500);
        }, 5000);
    }
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
    </script>
</body>
</html>