<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

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

// Structured data for rich snippets
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

// FAQ Schema
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

// Preloader logic
$is_logged_in = isset($_SESSION['user_id']);
$is_google_bot = false;

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

$show_preloader = $is_logged_in && !$is_google_bot && !isset($_SESSION['preloader_shown']) && !isset($_GET['skip_preloader']);

if ($show_preloader) {
    $_SESSION['preloader_shown'] = time();
    include 'preloader.php';
    exit;
}

// Pricing data
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

// Function to get blog image URL
function getBlogImageUrl($imagePath) {
    if (empty($imagePath)) return null;
    $upload_base = "https://eduscore.gt.tc/uploads/blogs/";
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }
    return $upload_base . basename($imagePath);
}

// Fetch latest blog posts
$latest_blogs = [];
$blog_error = false;

try {
    if (isset($db) && $db instanceof PDO) {
        $stmt = $db->prepare("SELECT id, title, description, image, category, created_at, author, views, likes 
                               FROM blogs 
                               ORDER BY created_at DESC 
                               LIMIT 3");
        $stmt->execute();
        $latest_blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
    <meta name="google-site-verification" content="HZsZNr2Rfno72qnurFjgV4UEMnMM3H0qjWryXqIzxpI" />
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="en-ke" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="EduScore | Affordable School Management System Kenya">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="EduScore Kenya">
    <meta property="og:locale" content="en_KE">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="EduScore | Affordable School Management System Kenya">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#00BFFF">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- 
      UNIFORM FONT: Using your requested Merriweather (serif) as primary.
      Keeping League Spartan and Poppins as secondary/headers for design integrity.
    -->
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&family=League+Spartan:wght@400;500;600;700;800&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    
    <script type="application/ld+json">
    <?php echo json_encode($structured_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <script type="application/ld+json">
    <?php echo json_encode($org_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <script type="application/ld+json">
    <?php echo json_encode($faq_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    
    <style>
/*-----------------------------------*\
  #UNIFORM SYSTEM STYLES (BACKGROUND & FONT)
\*-----------------------------------*/

/* 
  UNIFORM BACKGROUND: 
  Faded, softer version of the original gradient for a more elegant look
  Original: #f5f57b → #fff6fb
  Faded:   #fef9c6 → #fffef8 (much lighter, airy)
*/
body {
    /* Elegant airy gradient - soft vanilla to whisper pink/peach */
    background: linear-gradient(135deg, #fffdf5 0%, #fffaf0 50%, #fff5eb 100%);
    /* Fallback - soft cream */
    background-color: #fffdf5;
    font-family: "Merriweather", serif;
    color: #2a2418;
    margin: 0;
    padding: 0;
    min-height: 100vh;
}
/* 
  Ensure all text elements inherit the uniform font 
  while allowing specific headings to use their specialized fonts for design.
*/
body, button, input, textarea, select, p, li, a, span, div:not(.special-font) {
    font-family: "Merriweather", serif;
}

/* Headers can keep League Spartan for typographic hierarchy, but body text remains Merriweather */
h1, h2, h3, h4, .h1, .h2, .h3, .section-title, .hero-stat-number, .portal-card-title {
    font-family: var(--ff-league_spartan), "Merriweather", serif;
}

/* Reset box-sizing and margins as you originally had */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    text-transform: capitalize;
}

/* The .background class is now applied to main wrapper to ensure gradient consistency */
.background-wrapper {
    background: linear-gradient(170deg, #fef9c6 35%, #fffef8 100%);
    width: 100%;
    min-height: 100vh;
}

/* Preserve all your existing custom properties and styles below */
/*-----------------------------------*\
  #CUSTOM PROPERTY (YOUR EXISTING)
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

/* Dark Mode Overrides - faded dark version matching the new faded aesthetic */
body.dark-mode {
    --eerie-black-1: hsl(0, 0%, 90%);
    --eerie-black-2: hsl(0, 0%, 95%);
    --gray-web: hsl(0, 0%, 70%);
    --light-gray: hsl(0, 0%, 30%);
    --isabelline: hsl(36, 20%, 15%);
    --platinum: hsl(0, 0%, 25%);
    --white: hsl(0, 0%, 15%);
    --black_80: hsla(0, 0%, 0%, 0.9);
    /* Faded dark mode background - softer contrast */
    background: linear-gradient(170deg, #3a3a2a 35%, #2a2a28 100%);
}

/* Ensure background wrapper also adapts for dark mode */
body.dark-mode .background-wrapper {
    background: linear-gradient(170deg, #3a3a2a 35%, #2a2a28 100%);
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

/*-----------------------------------*\
  #RESET (your existing)
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

/* Body background is now set above, but we keep other body styles */
body {
    font-size: 1.6rem;
    line-height: 1.75;
    transition: background-color 0.3s ease, color 0.3s ease;
}

::-webkit-scrollbar { width: 10px; }
::-webkit-scrollbar-track { background-color: hsl(0, 0%, 98%); }
::-webkit-scrollbar-thumb { background-color: hsl(0, 0%, 80%); }

/*-----------------------------------*\
  #REUSED STYLE (your existing)
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
    line-height: 1;
}

.h1, .h2 { font-weight: var(--fw-600); }
.h1 { font-size: var(--fs-1); }
.h2 { font-size: var(--fs-2); }

/*-----------------------------------*\
  #TYPING ANIMATION FOR SECTION TITLE
\*-----------------------------------*/
/*-----------------------------------*\
  #TYPING ANIMATION FOR SECTION TITLE
\*-----------------------------------*/
.typing-title {
    text-align: center;
    margin-bottom: 20px;
    color: #2c2418;
    transition: color 0.2s;
    min-height: 80px;
    font-size: clamp(2.8rem, 6vw, 5.2rem);
    line-height: 1.2;
    font-family: var(--ff-league_spartan), "Merriweather", serif;
}

.typing-title .span {
    display: inline-block;
    background: linear-gradient(135deg, #d68b3c, #b35f1a);
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
}

body.dark-mode .typing-title .span {
    background: linear-gradient(135deg, #f5bc70, #e7a047);
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
}

/* Blinking cursor effect */
.typing-title::after {
    content: '|';
    display: inline-block;
    animation: blink 0.8s step-end infinite;
    margin-left: 4px;
    color: #d68b3c;
    font-weight: 300;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}

/* Hide cursor when typing is complete */
.typing-title[data-typed="true"]::after {
    display: none;
}

/* Hide cursor on mobile for cleaner look */
@media (max-width: 768px) {
    .typing-title::after {
        display: none;
    }
}

.typing-title .word:nth-child(1) { animation-delay: 0.1s; }
.typing-title .word:nth-child(2) { animation-delay: 0.2s; }
.typing-title .word:nth-child(3) { animation-delay: 0.3s; }
.typing-title .word:nth-child(4) { animation-delay: 0.4s; }
.typing-title .word:nth-child(5) { animation-delay: 0.5s; }
.typing-title .word:nth-child(6) { animation-delay: 0.6s; }
.typing-title .word:nth-child(7) { animation-delay: 0.7s; }
.typing-title .word:nth-child(8) { animation-delay: 0.8s; }

@keyframes wordFadeIn {
    0% {
        opacity: 0;
        transform: translateY(15px);
        filter: blur(4px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
        filter: blur(0);
    }
}

/* Cursor blink effect for typing feel */
.typing-title::after {
    content: '|';
    display: inline-block;
    animation: blink 0.8s step-end infinite;
    margin-left: 4px;
    color: var(--radical-red);
    font-weight: 300;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}

/* Hide cursor on hover (optional) */
.typing-title:hover::after {
    opacity: 0;
}

/* Alternative: True typewriter effect using JavaScript (more advanced) */
.typewriter-text {
    overflow: hidden;
    border-right: 3px solid var(--radical-red);
    white-space: nowrap;
    margin: 0 auto;
    animation: 
        typing 3.5s steps(40, end),
        blink-caret 0.75s step-end infinite;
}

@keyframes typing {
    from { width: 0; }
    to { width: 100%; }
}

@keyframes blink-caret {
    from, to { border-color: transparent; }
    50% { border-color: var(--radical-red); }
}

/* Responsive adjustments for mobile */
@media (max-width: 768px) {
    .typing-title::after {
        display: none; /* Hide cursor on mobile for cleaner look */
    }
    
    @keyframes wordFadeIn {
        0% {
            opacity: 0;
            transform: translateY(10px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
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

.grid-list {
    display: grid;
    gap: 30px;
}

/*-----------------------------------*\
  #HEADER - GLASSMORPHISM (updated for faded background)
\*-----------------------------------*/
.header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: rgba(255, 253, 245, 0.85);  /* lighter, more transparent to match faded bg */
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
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

body.dark-mode .header.active {
    background: rgba(25, 30, 35, 0.92);
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
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

.navbar-link:hover::after {
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
    box-shadow: 0 6px 14px rgba(0, 191, 255, 0.25);
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
    border-color: transparent;
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

body.dark-mode .menu-btn {
    background: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
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
    
    .navbar.active {
        right: 0;
    }
    
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
    
    .navbar-link:hover,
    .navbar-link.active {
        background: rgba(0, 191, 255, 0.08);
        color: #00BFFF;
        transform: translateX(3px);
    }
    
    .navbar-link::after {
        display: none;
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
        font-size: 1.45rem;
        text-decoration: none;
        transition: all 0.2s ease;
        text-align: center;
    }
    
    .mobile-portal-btn-analytics {
        background: #00BFFF;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 191, 255, 0.2);
    }
    
    .mobile-portal-btn-finance {
        background: transparent;
        color: #00BFFF;
        border: 1.5px solid #00BFFF;
    }
    
    .portal-buttons-header {
        display: none;
    }
    
    .menu-btn {
        display: flex;
    }
    
    .header .container {
        padding: 12px 20px;
    }
    
    .header.active .container {
        padding: 8px 20px;
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
        box-shadow: none;
        padding: 0;
        overflow: visible;
        display: flex;
        flex: 1;
        justify-content: center;
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
    
    .navbar-link {
        padding: 8px 0;
        font-size: 1.55rem;
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

@media (max-width: 480px) {
    .header .container {
        padding: 10px 16px;
    }
    
    .logo img {
        height: 34px;
    }
    
    .menu-btn {
        width: 40px;
        height: 40px;
        font-size: 2rem;
    }
    
    .theme-toggle {
        width: 38px;
        height: 38px;
        font-size: 1.6rem;
    }
}

/* All heading/text colors adapt for dark mode (updated for faded aesthetic) */
body.dark-mode .section-title,
body.dark-mode .hero-text,
body.dark-mode .hero-stat-label,
body.dark-mode .cbe-badge span,
body.dark-mode .hero-stat-number {
  color: #f9f2df;
}

body.dark-mode .cbe-badge {
  border-color: #f5bc70;
  color: #f5bc70;
  box-shadow: 0 4px 15px rgba(245, 188, 112, 0.3);
}

body.dark-mode .hero-stats {
  background: rgba(30, 28, 22, 0.7);
  backdrop-filter: blur(10px);
  border-color: rgba(245, 200, 130, 0.25);
}

body.dark-mode .btn-primary-custom {
  background: #d4973b !important;
  color: #1e1b14 !important;
}

body.dark-mode .btn-secondary-custom:first-of-type {
  background: #b87c2e !important;
  color: #fff0db !important;
}

body.dark-mode .btn-secondary-custom:last-of-type {
  background: #bf6f3a !important;
  color: #fff4e6 !important;
}

/* ---------- HERO SECTION (fully responsive, dark mode ready) ---------- */
.hero {
  position: relative;
  padding-top: 110px;
  padding-bottom: 70px;
  overflow: hidden;
}

.hero .container {
  position: relative;
  z-index: 1;
  display: grid;
  gap: 50px;
}

.hero-content {
  text-align: center;
}

/* CBE badge - updated for faded background */
.cbe-badge {
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
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.03);
  animation: slideInLeft 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94), softPulse 2.5s infinite ease-in-out;
  opacity: 0;
  transform: translateX(-30px);
  animation-fill-mode: forwards;
}

@keyframes slideInLeft {
  0% { opacity: 0; transform: translateX(-50px); }
  100% { opacity: 1; transform: translateX(0); }
}

@keyframes softPulse {
  0%, 100% { box-shadow: 0 4px 12px rgba(230, 176, 80, 0.15); border-color: #e6b050; }
  50% { box-shadow: 0 8px 22px rgba(230, 176, 80, 0.25); border-color: #f5bc6e; }
}

/* Title styles — responsive font size */
.hero .section-title {
  font-size: clamp(2.8rem, 6vw, 5.2rem);
  line-height: 1.2;
  margin-bottom: 20px;
  color: #2c2418;
  transition: color 0.2s;
}

.hero .section-title .span {
  display: inline-block;
  background: linear-gradient(135deg, #d68b3c, #b35f1a);
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}

body.dark-mode .hero .section-title .span {
  background: linear-gradient(135deg, #f5bc70, #e7a047);
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}

/* Hero text — comfortable readability */
.hero-text {
  color: #3b2f20;
  font-size: clamp(1.5rem, 2.5vw, 1.9rem);
  text-align: center;
  margin-block: 0 28px;
  max-width: 700px;
  margin-left: auto;
  margin-right: auto;
  line-height: 1.5;
  font-weight: 500;
}

body.dark-mode .hero-text {
  color: #f0e5d2;
}

/* Button group — responsive, dark mode text contrast */
.hero-button-group {
  display: flex;
  gap: 1.2rem;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 1.8rem;
}

.btn-primary-custom,
.btn-secondary-custom {
  transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.2s;
  text-decoration: none;
  font-weight: 700;
  border-radius: 60px;
  font-size: 1.45rem;
  padding: 12px 28px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  letter-spacing: -0.2px;
  backdrop-filter: blur(2px);
}

.btn-primary-custom:hover,
.btn-secondary-custom:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 22px -10px rgba(0, 0, 0, 0.25);
}

/* Stats grid — updated for faded background */
.hero-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-top: 50px;
  padding: 28px 22px;
  background: rgba(255, 253, 245, 0.7);
  backdrop-filter: blur(12px);
  border-radius: 36px;
  border: 1px solid rgba(245, 200, 130, 0.5);
  box-shadow: 0 12px 28px -8px rgba(0, 0, 0, 0.04);
  transition: background 0.2s;
}

.hero-stat-item {
  text-align: center;
  padding: 10px 5px;
}

.hero-stat-number {
  font-size: clamp(2.5rem, 5vw, 3.8rem);
  font-weight: 800;
  color: #c1792c;
  margin-bottom: 8px;
  line-height: 1;
  transition: color 0.2s;
}

body.dark-mode .hero-stat-number {
  color: #f3bc6c;
}

.hero-stat-label {
  font-size: clamp(1rem, 3vw, 1.35rem);
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
  color: #5b4a33;
}

body.dark-mode .hero-stat-label {
  color: #e2cfae;
}

/* Hero banner images (right side) */
.hero-banner {
  display: grid;
  grid-template-columns: 1fr 0.8fr;
  align-items: flex-start;
  gap: 30px;
  position: relative;
  z-index: 1;
}

.img-holder {
  background-color: rgba(245, 230, 200, 0.3);
  border-radius: 32px;
  overflow: hidden;
  aspect-ratio: var(--width) / var(--height);
  transition: transform 0.3s ease;
}

.img-holder img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.hero-banner .img-holder.one {
  border-top-right-radius: 70px;
  border-bottom-left-radius: 110px;
}

.hero-banner .img-holder.two {
  border-top-left-radius: 50px;
  border-bottom-right-radius: 90px;
}

/* ---------- RESPONSIVE BREAKPOINTS (MOBILE-FIRST + TABLET/DESKTOP) ---------- */
@media (min-width: 992px) {
  .hero {
    padding-top: 130px;
    padding-bottom: 90px;
  }
  .hero .container {
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 60px;
  }
  .hero-content {
    text-align: left;
  }
  .cbe-badge {
    margin-left: 0;
    margin-right: auto;
  }
  .hero .section-title {
    text-align: left;
  }
  .hero-text {
    text-align: left;
    margin-left: 0;
    margin-right: 0;
  }
  .hero-button-group {
    justify-content: flex-start;
  }
  .hero-stats {
    justify-content: flex-start;
    margin-top: 48px;
  }
}

/* Tablet & mobile adjustments */
@media (max-width: 991px) {
  .hero-banner {
    display: none;
  }
  .hero {
    padding-top: 100px;
    padding-bottom: 50px;
  }
  .hero-stats {
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    padding: 20px 18px;
    margin-top: 35px;
  }
  .hero-stat-item {
    padding: 8px 0;
  }
  .hero-button-group {
    gap: 14px;
  }
  .btn-primary-custom,
  .btn-secondary-custom {
    padding: 10px 22px;
    font-size: 1.3rem;
  }
}

@media (max-width: 580px) {
  .hero-stats {
    grid-template-columns: 1fr;
    gap: 10px;
    text-align: center;
  }
  .hero .section-title {
    margin-bottom: 12px;
  }
  .hero-button-group {
    flex-direction: column;
    align-items: stretch;
  }
  .hero-button-group a {
    justify-content: center;
  }
  .cbe-badge {
    font-size: 1.2rem;
    padding: 6px 16px;
  }
}

/* reveal animation for content */
.reveal {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1), transform 0.7s ease;
}
.reveal.active {
  opacity: 1;
  transform: translateY(0);
}

/*-----------------------------------*\
  #PORTAL CARDS SECTION (updated for faded background)
\*-----------------------------------*/
.portal-cards-section {
  padding: 70px 0 90px;
  position: relative;
}

/* Grid layout – responsive */
.portal-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 32px;
}

/* 
  CARD STYLES – Updated for faded background
  Softer, airier card backgrounds that complement the new faded gradient
*/
.portal-card {
  background: rgba(255, 253, 248, 0.85);
  backdrop-filter: blur(2px);
  border-radius: 32px;
  padding: 32px 24px 36px;
  text-align: center;
  transition: all 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
  border: 1px solid rgba(230, 200, 140, 0.4);
  box-shadow: 0 12px 28px -8px rgba(0, 0, 0, 0.04), 0 2px 4px rgba(0, 0, 0, 0.01);
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  background-image: none !important;
}

/* Hover effect: lift + soft shadow expansion */
.portal-card:hover {
  transform: translateY(-10px);
  box-shadow: 0 28px 40px -12px rgba(0, 0, 0, 0.12);
  background: rgba(255, 254, 252, 0.95);
  border-color: #e6b450;
}

/* Icon container – warm, solid color */
.portal-card-icon {
  width: 84px;
  height: 84px;
  background: #fef0d4;
  border-radius: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
  font-size: 2.8rem;
  color: #b86f2c;
  transition: all 0.3s ease;
  box-shadow: 0 6px 12px -6px rgba(0, 0, 0, 0.03);
}

.portal-card:hover .portal-card-icon {
  transform: scale(1.02) translateY(-4px);
  background: #fae6bc;
  color: #9b5e2c;
  box-shadow: 0 12px 18px -8px rgba(184, 111, 44, 0.15);
}

/* Card title – warm dark tone */
.portal-card-title {
  font-size: 1.9rem;
  font-weight: 700;
  margin-bottom: 14px;
  letter-spacing: -0.3px;
  background: none;
  -webkit-background-clip: unset;
  background-clip: unset;
  color: #2c2418;
}

/* Description text */
.portal-card-desc {
  font-size: 1.5rem;
  line-height: 1.5;
  color: #5c4b34;
  margin-bottom: 28px;
  opacity: 0.85;
  font-weight: 400;
  max-width: 260px;
  margin-left: auto;
  margin-right: auto;
}

/* Button – warm, solid color */
.portal-card-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 12px 28px;
  background: #e9b35f;
  border: none;
  border-radius: 60px;
  font-weight: 700;
  font-size: 1.45rem;
  text-decoration: none;
  color: #2a241c;
  transition: all 0.25s ease;
  width: fit-content;
  margin: 0 auto;
  box-shadow: 0 2px 6px rgba(0,0,0,0.03);
  letter-spacing: 0.2px;
}

.portal-card-btn i {
  transition: transform 0.2s ease;
  font-size: 1.2rem;
}

.portal-card-btn:hover {
  background: #d4943c;
  color: #1e1a14;
  transform: translateX(5px);
  box-shadow: 0 8px 18px rgba(217, 148, 60, 0.2);
}

.portal-card-btn:hover i {
  transform: translateX(4px);
}

/* Beta badge */
.beta-badge {
  position: absolute;
  top: 20px;
  right: 20px;
  background: #e7a541;
  color: #2d2418;
  font-size: 1rem;
  font-weight: 800;
  padding: 5px 12px;
  border-radius: 40px;
  letter-spacing: 0.4px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
  backdrop-filter: blur(2px);
  border: 1px solid rgba(255,225,160,0.6);
}

/* Smooth entrance animation for cards */
@keyframes cardReveal {
  0% {
    opacity: 0;
    transform: translateY(32px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

.portal-card {
  opacity: 0;
  transform: translateY(32px);
  animation: cardReveal 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
}

/* Stagger children animations */
.portal-card:nth-child(1) { animation-delay: 0.05s; }
.portal-card:nth-child(2) { animation-delay: 0.1s; }
.portal-card:nth-child(3) { animation-delay: 0.15s; }
.portal-card:nth-child(4) { animation-delay: 0.2s; }
.portal-card:nth-child(5) { animation-delay: 0.25s; }
.portal-card:nth-child(6) { animation-delay: 0.3s; }

/* Dark mode adaptation for cards - softer to match faded aesthetic */
body.dark-mode .portal-card {
  background: rgba(50, 45, 38, 0.85);
  border-color: rgba(210, 170, 90, 0.3);
  box-shadow: 0 12px 28px -8px rgba(0, 0, 0, 0.3);
}
body.dark-mode .portal-card:hover {
  background: rgba(65, 58, 48, 0.92);
  border-color: #dba551;
}
body.dark-mode .portal-card-title {
  color: #f7e5c2;
}
body.dark-mode .portal-card-desc {
  color: #cfc3a8;
}
body.dark-mode .portal-card-icon {
  background: #6b5538;
  color: #f3cd81;
}
body.dark-mode .portal-card-btn {
  background: #cd9548;
  color: #1f1b12;
}
body.dark-mode .portal-card-btn:hover {
  background: #e2a655;
  color: #0d0b07;
}
body.dark-mode .beta-badge {
  background: #c48a3c;
  color: #f7f0dd;
}

/* Responsive */
@media (max-width: 768px) {
  .portal-grid {
    grid-template-columns: 1fr;
    gap: 28px;
  }
  .portal-card {
    padding: 28px 20px 32px;
  }
  .portal-card-icon {
    width: 70px;
    height: 70px;
    font-size: 2.2rem;
  }
  .portal-card-title {
    font-size: 1.7rem;
  }
}

/*-----------------------------------*\
  #FOOTER
\*-----------------------------------*/
.footer {
    background-color: var(--eerie-black-2);
    color: var(--gray-x-11);
    padding-block-start: 60px;
}

.footer-top {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    padding-block-end: 40px;
}

.footer-list-title {
    color: var(--white);
    font-family: var(--ff-league_spartan);
    font-size: var(--fs-3);
    font-weight: var(--fw-600);
    margin-block-end: 10px;
}

.footer-link {
    transition: var(--transition-1);
    display: block;
    padding-block: 5px;
}

.footer-link:hover {
    color: var(--kappel);
}

.copyright {
    text-align: center;
    padding-block: 30px;
    border-block-start: 1px solid var(--eerie-black-1);
}

/* Back to Top */
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

/* Responsive */
@media (max-width: 991px) {
    .footer-top {
        grid-template-columns: 1fr;
    }
}

main {
    min-height: 400px;
}
/*-----------------------------------*\
  #MOBILE APP BANNER
\*-----------------------------------*/
.mobile-app-banner {
    background: linear-gradient(135deg, #e8a84c, #c97e2a);
    border-radius: 60px;
    padding: 12px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 8px 20px rgba(201, 126, 42, 0.25);
    animation: bannerGlow 2s ease-in-out infinite, slideInDown 0.6s ease-out;
    position: relative;
    overflow: hidden;
}

@keyframes bannerGlow {
    0%, 100% { box-shadow: 0 8px 20px rgba(201, 126, 42, 0.25); }
    50% { box-shadow: 0 12px 28px rgba(201, 126, 42, 0.45); }
}

@keyframes slideInDown {
    0% { opacity: 0; transform: translateY(-30px); }
    100% { opacity: 1; transform: translateY(0); }
}

.mobile-app-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    20% { left: 100%; }
    100% { left: 100%; }
}

.mobile-app-banner-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    font-weight: 700;
    color: #ffffff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.mobile-app-banner-content i:first-child {
    font-size: 1.8rem;
    animation: phoneShake 1s ease-in-out infinite;
}

@keyframes phoneShake {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(5deg); }
    75% { transform: rotate(-5deg); }
}

.mobile-app-banner-content span:first-of-type {
    font-size: 1.4rem;
    letter-spacing: 0.5px;
}

.mobile-app-banner-content .fa-arrow-right {
    font-size: 1.2rem;
    opacity: 0.9;
}

.banner-subtext {
    font-size: 1.1rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 40px;
    letter-spacing: 0.3px;
}

.banner-close {
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 50%;
    transition: all 0.2s ease;
    background: rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.banner-close:hover {
    background: rgba(0, 0, 0, 0.25);
    transform: scale(1.1);
}

.banner-close i {
    font-size: 1.2rem;
    color: white;
}

/* Dark mode adjustments for banner */
body.dark-mode .mobile-app-banner {
    background: linear-gradient(135deg, #c48a3c, #a66824);
    box-shadow: 0 8px 20px rgba(164, 104, 36, 0.35);
}

body.dark-mode .banner-subtext {
    background: rgba(0, 0, 0, 0.25);
}

/* Responsive adjustments for banner */
@media (max-width: 768px) {
    .mobile-app-banner {
        padding: 10px 16px;
        flex-direction: column;
        text-align: center;
    }
    
    .mobile-app-banner-content {
        justify-content: center;
    }
    
    .banner-subtext {
        font-size: 1rem;
    }
    
    .banner-close {
        position: absolute;
        top: 8px;
        right: 8px;
    }
}

@media (max-width: 580px) {
    .mobile-app-banner-content span:first-of-type {
        font-size: 1.2rem;
    }
    
    .banner-subtext {
        font-size: 0.9rem;
    }
}
    </style>
</head>
<body>
    <!-- UNIFORM BACKGROUND WRAPPER: This ensures the gradient covers the entire page consistently -->
    <div class="background-wrapper">
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
            <article>
                <section class="hero" id="home">
                    <div class="container">
                        <div class="hero-content reveal">
                                <div class="mobile-app-banner">
        <div class="mobile-app-banner-content">
            <i class="fas fa-mobile-alt"></i>
            <span>Mobile App Coming Soon!</span>
            <i class="fas fa-arrow-right"></i>
            <span class="banner-subtext">Stay tuned for iOS & Android</span>
        </div>
        <div class="banner-close" aria-label="Close banner">
            <i class="fas fa-times"></i>
        </div>
    </div>
                            <div class="cbe-badge">
                                <i class="fas fa-graduation-cap"></i>
                                <span>Built for CBE curriculum</span>
                            </div>
                            
<h1 class="section-title typing-title">
    The Best School <span class="span">Management System</span> for Kenyan Schools
</h1>
                            <p class="hero-text">
                                Streamline administration, enhance learning outcomes, and engage parents seamlessly with Kenya's #1 school management platform.
                            </p>
                            
                            <div class="hero-button-group">
                                <a href="register.php" class="btn-primary-custom" style="background: var(--kappel); color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                                    <span>Start Free Trial</span>
                                    <ion-icon name="arrow-forward-outline"></ion-icon>
                                </a>
                                <a href="parents-portal.php" class="btn-secondary-custom" style="background: var(--selective-yellow); color: var(--eerie-black-1); padding: 12px 24px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-users"></i>
                                    <span>Parents Portal</span>
                                </a>
                                <a href="bulksms.php" class="btn-secondary-custom" style="background: var(--radical-red); color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-sms"></i>
                                    <span>Bulk SMS</span>
                                </a>
                            </div>

                            <div class="hero-stats">
                                <div class="hero-stat-item">
                                    <div class="hero-stat-number" id="heroSchoolsCount">0</div>
                                    <div class="hero-stat-label">Schools</div>
                                </div>
                                <div class="hero-stat-item">
                                    <div class="hero-stat-number" id="heroStudentsCount">0</div>
                                    <div class="hero-stat-label">Students</div>
                                </div>
                                <div class="hero-stat-item">
                                    <div class="hero-stat-number" id="heroTeachersCount">0</div>
                                    <div class="hero-stat-label">Teachers</div>
                                </div>
                                <div class="hero-stat-item">
                                    <div class="hero-stat-number" id="heroResourcesCount">0</div>
                                    <div class="hero-stat-label">Resources</div>
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

                <section class="portal-cards-section">
                    <div class="container">
                        <div class="portal-grid">
                            <div class="portal-card" data-type="analytics">
                                <div class="portal-card-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h3 class="portal-card-title">Analytics Portal</h3>
                                <p class="portal-card-desc">Real-time exam analysis, performance tracking, and detailed reports.</p>
                                <a href="analytics.php" class="portal-card-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                            </div>
                            
                            <div class="portal-card" data-type="fee">
                                <div class="portal-card-icon">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <h3 class="portal-card-title">Fee Portal</h3>
                                <p class="portal-card-desc">Automated fee management, M-Pesa integration, payment tracking.</p>
                                <a href="feesystem.php" class="portal-card-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                            </div>
                            
                            <div class="portal-card" data-type="sms">
                                <div class="portal-card-icon">
                                    <i class="fas fa-sms"></i>
                                </div>
                                <h3 class="portal-card-title">Bulk SMS Portal</h3>
                                <p class="portal-card-desc">Send instant alerts, reminders, and announcements to parents.</p>
                                <a href="bulksms.php" class="portal-card-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                            </div>
                            
                            <div class="portal-card" data-type="timetable">
                                <div class="beta-badge">BETA</div>
                                <div class="portal-card-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h3 class="portal-card-title">Timetable Generator</h3>
                                <p class="portal-card-desc">AI-powered timetable scheduling with conflict resolution.</p>
                                <a href="timetable-generator.php" class="portal-card-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                            </div>
                            
                            <div class="portal-card" data-type="mwalimu">
                                <div class="beta-badge">BETA</div>
                                <div class="portal-card-icon">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <h3 class="portal-card-title">Mwalimu Hub</h3>
                                <p class="portal-card-desc">AI-powered teaching assistant for CBC curriculum.</p>
                                <a href="mwalimu-hub.php" class="portal-card-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                            </div>
                            
                            <div class="portal-card" data-type="parents">
                                <div class="portal-card-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="portal-card-title">Parents Portal</h3>
                                <p class="portal-card-desc">Real-time access to children's academic progress.</p>
                                <a href="parents-portal.php" class="portal-card-btn">Get Started <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </section>
            </article>
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
        <a href="#top" class="back-top-btn" aria-label="back top top" data-back-top-btn>
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
        fetch('ajax/get_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    animateNumber(document.getElementById('heroSchoolsCount'), 0, data.data.schools, 1500);
                    animateNumber(document.getElementById('heroStudentsCount'), 0, data.data.students, 1500);
                    animateNumber(document.getElementById('heroTeachersCount'), 0, data.data.teachers, 1500);
                    animateNumber(document.getElementById('heroResourcesCount'), 0, data.data.reports, 1500);
                }
            })
            .catch(error => console.error('Error fetching stats:', error));
    }
    
    const heroSection = document.querySelector('.hero');
    if (heroSection) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) { fetchStats(); observer.unobserve(entry.target); } });
        }, { threshold: 0.3 });
        observer.observe(heroSection);
    }

    // Set active nav link
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.navbar-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.php')) {
            link.classList.add('active');
        }
    });
    
    // Mobile App Banner Close Functionality
    const bannerClose = document.querySelector('.banner-close');
    const mobileBanner = document.querySelector('.mobile-app-banner');

    if (bannerClose && mobileBanner) {
        const bannerClosed = localStorage.getItem('mobileAppBannerClosed');
        if (bannerClosed === 'true') {
            mobileBanner.style.display = 'none';
        }
        
        bannerClose.addEventListener('click', function() {
            mobileBanner.style.animation = 'fadeOutUp 0.3s ease-out forwards';
            localStorage.setItem('mobileAppBannerClosed', 'true');
            setTimeout(() => {
                mobileBanner.style.display = 'none';
            }, 300);
        });
    }

    // Add fade out animation
    const styleSheet = document.createElement("style");
    styleSheet.textContent = `
        @keyframes fadeOutUp {
            0% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); display: none; }
        }
    `;
    document.head.appendChild(styleSheet);

    // FIXED: Clean Typing Animation without extra characters
    document.addEventListener('DOMContentLoaded', function() {
        const titleElement = document.querySelector('.typing-title');
        if (titleElement && !titleElement.hasAttribute('data-typed')) {
            // Get the clean text content (not HTML)
            const fullText = titleElement.textContent.trim();
            
            // Clear the element
            titleElement.innerHTML = '';
            titleElement.style.opacity = '1';
            
            let i = 0;
            
            function typeWriter() {
                if (i < fullText.length) {
                    // Add one character at a time
                    titleElement.innerHTML = fullText.substring(0, i + 1);
                    i++;
                    
                    // Random typing speed for realism (between 30ms and 80ms)
                    const delay = 40 + Math.random() * 40;
                    setTimeout(typeWriter, delay);
                } else {
                    // Animation complete
                    titleElement.setAttribute('data-typed', 'true');
                    
                    // Add a subtle completion effect
                    titleElement.style.transition = 'text-shadow 0.3s';
                    titleElement.style.textShadow = '0 0 10px rgba(0,0,0,0.1)';
                    setTimeout(() => {
                        titleElement.style.textShadow = 'none';
                    }, 500);
                }
            }
            
            // Start typing after a short delay
            setTimeout(typeWriter, 200);
        }
    });
</script>
</body>
</html>