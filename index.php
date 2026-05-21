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
  #HEADER - GLASS MORPHISM & RESPONSIVE (FIXED)
\*-----------------------------------*/

.header .btn { display: none; }

/* ---------- 1. GLASS MORPHISM HEADER ---------- */
.header {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  width: calc(100% - 40px);
  max-width: 1400px;
  padding-block: 12px;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.25);
  border-radius: 20px;
  z-index: 1000;
  transition: background 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
}

/* ---------- 2. STICKY ACTIVE STATE WITH ANIMATION ---------- */
.header.active {
  top: 12px;
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(22px);
  -webkit-backdrop-filter: blur(22px);
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.25);
  animation: slideIn 0.4s ease forwards;
}

@keyframes slideIn {
  0% { transform: translateY(-100%) translateX(-50%); }
  100% { transform: translateY(0) translateX(-50%); }
}

/* ---------- FLOATING HOVER EFFECT ---------- */
.header:hover {
  transform: translateX(-50%) translateY(-2px);
}

/* Header Container */
.header .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
  max-width: 100%;
  overflow: visible;
  padding: 0 15px;
}

/* Logo */
.logo { flex-shrink: 0; }
.logo img {
  width: auto;
  height: 40px;
  max-width: 120px;
  object-fit: contain;
}

/* Header Actions */
.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-shrink: 0;
}

/* Theme Toggle */
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
  transition: all 0.3s ease;
}
.theme-toggle:hover {
  transform: scale(1.1);
}
.theme-toggle .fa-sun { display: none; }
body.dark-mode .theme-toggle .fa-moon { display: none; }
body.dark-mode .theme-toggle .fa-sun { display: block; }

/* Portal Buttons Container */
.portal-buttons-header {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}



/* ---------- 3. PREMIUM PORTAL BUTTONS (GLASS) ---------- */
.portal-btn {
  padding: 10px 18px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.14);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  font-weight: 600;
  transition: all 0.3s ease;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.portal-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 191, 255, 0.22);
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

/* ---------- 4. GLASS MORPHISM MOBILE NAVBAR (DRAWER) ---------- */
.navbar {
  position: fixed;
  top: 0;
  left: -100%;
  width: 85%;
  max-width: 340px;
  height: 100vh;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-right: 1px solid rgba(255, 255, 255, 0.18);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
  z-index: 1001;
  transition: left 0.35s ease;
  overflow-y: auto;
}
.navbar.active { left: 0; }

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
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
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

/* ---------- 5. PREMIUM NAV LINKS (GLASS HOVER) ---------- */
.navbar-link {
  position: relative;
  padding: 10px 16px;
  border-radius: 12px;
  font-weight: 600;
  color: #111827;
  transition: all 0.3s ease;
  display: block;
  text-decoration: none;
}
.navbar-link:hover {
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  color: #00BFFF;
  transform: translateY(-1px);
}

/* Mobile Portal Buttons */
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
.mobile-portal-btn-analytics:hover { background: #009ac9; }
.mobile-portal-btn-finance {
  background: transparent;
  color: #00BFFF;
  border: 1.5px solid #00BFFF;
}
.mobile-portal-btn-finance:hover {
  background: #00BFFF;
  color: #ffffff;
}

/* ---------- 6. GLASS MORPHISM DROPDOWN ---------- */
.dropdown { position: relative; }
.dropdown-menu {
  position: absolute;
  top: calc(100% + 15px);
  left: 0;
  min-width: 240px;
  padding: 12px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.14);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.14);
  opacity: 0;
  visibility: hidden;
  transform: translateY(10px);
  transition: all 0.3s ease;
  z-index: 100;
}
.dropdown.active .dropdown-menu { 
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}

/* Dropdown Items Hover Effect */
.dropdown-menu li a {
  display: flex;
  align-items: center;
  padding: 12px 14px;
  border-radius: 12px;
  color: #111827;
  transition: all 0.3s ease;
  text-decoration: none;
}
.dropdown-menu li a:hover {
  background: rgba(255, 255, 255, 0.22);
  color: #00BFFF;
  transform: translateX(4px);
}

/* Dropdown Arrow Animation */
.dropdown-arrow {
  transition: transform 0.3s ease;
  display: inline-block;
  margin-left: 5px;
  font-size: 1rem;
}
.dropdown.active .dropdown-arrow { transform: rotate(180deg); }

/* ---------- 7. OVERLAY WITH BLUR (GLASS) ---------- */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  opacity: 0;
  pointer-events: none;
  transition: 0.3s ease;
  z-index: 1000;
}
.overlay.active {
  opacity: 1;
  pointer-events: all;
}

/* Menu Button */
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
  transition: all 0.3s ease;
}
.menu-btn:hover {
  transform: scale(1.1);
}

/* ---------- 8. DARK MODE GLASS EFFECTS ---------- */
body.dark-mode .header,
body.dark-mode .navbar,
body.dark-mode .dropdown-menu {
  background: rgba(17, 25, 40, 0.55);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
}

body.dark-mode .navbar-link {
  color: #e0e0e0;
}

body.dark-mode .dropdown-menu li a {
  color: #e0e0e0;
}

body.dark-mode .dropdown-menu li a:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #00BFFF;
}

/* ---------- 9. FULLY RESPONSIVE: DESKTOP ---------- */
@media (min-width: 992px) {
  .menu-btn { display: none; }

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
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
  }

  body.navbar-open {
    overflow: auto;
    position: relative;
    width: auto;
  }

  .navbar .wrapper { display: none; }

  .navbar-list {
    flex-direction: row;
    padding: 0;
    gap: 30px;
    align-items: center;
  }

  .navbar-item:not(:last-child) { border-block-end: none; }
  .navbar-link { padding-block: 0; }
  .mobile-portal-buttons { display: none; }

  /* Desktop Dropdown - Glass */
  .dropdown { position: relative; }
  .dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    min-width: 220px;
    padding: 12px;
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

  .dropdown-arrow { display: inline-block; }
  .overlay { display: none; }
}

/* ---------- 10. RESPONSIVE: TABLET & MOBILE ---------- */
@media (max-width: 991px) {
  .portal-buttons-header { display: none; }
  .header .btn { display: none; }
}

/* Small Mobile Adjustments */
@media (max-width: 480px) {
  .header {
    width: calc(100% - 20px);
    top: 10px;
    padding-block: 8px;
  }
  .header.active { top: 6px; }
  .logo img {
    height: 32px;
    max-width: 100px;
  }
  .navbar-link { font-size: 1.4rem; }
}
/*-----------------------------------*\
  #HERO SECTION WITH STATS - OPTIMIZED SPACING
\*-----------------------------------*/

/* Hero Section - Appears just below header */
.hero {
    position: relative;
    padding-top: 70px;
    padding-bottom: 70px;
    background: linear-gradient(135deg, 
        rgba(0, 191, 255, 0.05) 50%,
        rgba(0, 191, 255, 0.02) 25%, 
        transparent 50%);
    overflow: hidden;
}

/* Gradient overlay for depth */
.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 10% 20%, rgba(0, 191, 255, 0.03) 0%, transparent 80%);
    pointer-events: none;
    z-index: 0;
}

/* Container positioning */
.hero .container {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 50px;
}

/* Hero Content */
.hero-content {
    text-align: center;
}

/* CBE Badge - Orange Background */
.cbe-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: white;
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 1.4rem;
    font-weight: 600;
    margin-bottom: 15px;
    box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
    animation: pulse 2s infinite;
}

.cbe-badge i {
    font-size: 1.4rem;
}

/* Pulse Animation */
@keyframes pulse {
    0% {
        box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
    }
    50% {
        box-shadow: 0 4px 25px rgba(249, 115, 22, 0.6);
    }
    100% {
        box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
    }
}

/* Hero Title */
.hero-text {
    color: var(--eerie-black-1);
    font-size: var(--fs-4);
    text-align: center;
    margin-block: 18px 20px;
}

.hero .btn { 
    margin-inline: auto; 
}

/* Hero Stats Section */
.hero-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-top: 40px;
    padding: 30px;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.hero-stat-item {
    text-align: center;
    padding: 15px;
}

.hero-stat-number {
    font-size: 3rem;
    font-weight: 800;
    color: var(--kappel);
    font-family: var(--ff-league_spartan);
    margin-bottom: 8px;
}

.hero-stat-label {
    font-size: 1.3rem;
    color: var(--gray-web);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Hero Banner - Original Design */
.hero-banner {
    display: grid;
    grid-template-columns: 1fr 0.8fr;
    align-items: flex-start;
    gap: 30px;
    position: relative;
    z-index: 1;
}

.hero-banner .img-holder.one {
    border-top-right-radius: 70px;
    border-bottom-left-radius: 110px;
}

.hero-banner .img-holder.two {
    border-top-left-radius: 50px;
    border-bottom-right-radius: 90px;
}

.hero-shape-1 {
    position: absolute;
    bottom: -40px;
    left: -60px;
    z-index: -1;
    display: block;
}

/* Responsive: Tablet and Mobile */
@media (min-width: 768px) {
    .hero-stats {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .hero-stat-number {
        font-size: 3.5rem;
    }
}

@media (min-width: 992px) {
    .hero {
        padding-top: 100px;
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
    
    .hero-text {
        text-align: left;
    }
    
    .hero-button-group {
        justify-content: flex-start !important;
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

@media (max-width: 768px) {
    .hero {
        padding-top: 85px;
        padding-bottom: 60px;
    }
    
    .cbe-badge {
        margin-left: auto;
        margin-right: auto;
        font-size: 1.2rem;
        padding: 6px 14px;
        margin-bottom: 15px;
    }
}

@media (max-width: 480px) {
    .hero {
        padding-top: 75px;
        padding-bottom: 50px;
    }
    
    .hero-stats {
        padding: 20px;
        gap: 10px;
    }
    
    .hero-stat-item {
        padding: 10px;
    }
    
    .hero-stat-number {
        font-size: 2rem;
    }
    
    .hero-stat-label {
        font-size: 1rem;
    }
}
/*-----------------------------------*\
  #PORTAL CARDS SECTION - GLASSMORPHISM
\*-----------------------------------*/

.portal-cards-section {
    background: linear-gradient(135deg, 
        rgba(0, 191, 255, 0.03) 0%,
        rgba(0, 191, 255, 0.01) 100%);
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

/* Glassmorphism effect background */
.portal-cards-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at center, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.portal-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Glassmorphism Card */
.portal-card {
    position: relative;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 24px;
    padding: 35px 25px;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

/* Glassmorphism hover effect */
.portal-card:hover {
    transform: translateY(-10px);
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1);
}

/* Animated gradient border on hover */
.portal-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, 
        transparent, 
        rgba(0, 191, 255, 0.8), 
        rgba(0, 191, 255, 0.8), 
        transparent);
    transition: left 0.6s ease;
}

.portal-card:hover::before {
    left: 100%;
}

/* Card icon with glass effect */
.portal-card-icon {
    width: 85px;
    height: 85px;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    font-size: 2.8rem;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.portal-card:hover .portal-card-icon {
    transform: scale(1.05) translateY(-5px);
    background: rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Card title with glass effect */
.portal-card-title {
    font-size: 1.8rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--eerie-black-1), var(--gray-web));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 12px;
    letter-spacing: -0.3px;
}

/* Dark mode title */
body.dark-mode .portal-card-title {
    background: linear-gradient(135deg, #ffffff, #a0a0a0);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

/* Card description */
.portal-card-desc {
    font-size: 1.4rem;
    color: var(--gray-web);
    margin-bottom: 25px;
    line-height: 1.6;
    opacity: 0.85;
}

/* Glass button */
.portal-card-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 50px;
    font-weight: 600;
    font-size: 1.3rem;
    transition: all 0.3s ease;
    text-decoration: none;
    color: var(--kappel);
}

.portal-card-btn:hover {
    background: rgba(0, 191, 255, 0.2);
    border-color: rgba(0, 191, 255, 0.5);
    transform: translateX(5px);
    gap: 12px;
}

/* Individual portal card icon colors (glass style) */
.portal-card[data-type="analytics"] .portal-card-icon { 
    color: #00BFFF;
    text-shadow: 0 0 10px rgba(0, 191, 255, 0.3);
}
.portal-card[data-type="analytics"]:hover .portal-card-icon { 
    background: rgba(0, 191, 255, 0.2);
    box-shadow: 0 0 20px rgba(0, 191, 255, 0.3);
}

.portal-card[data-type="fee"] .portal-card-icon { 
    color: #10B981;
    text-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
}
.portal-card[data-type="fee"]:hover .portal-card-icon { 
    background: rgba(16, 185, 129, 0.2);
    box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
}

.portal-card[data-type="sms"] .portal-card-icon { 
    color: #F59E0B;
    text-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
}
.portal-card[data-type="sms"]:hover .portal-card-icon { 
    background: rgba(245, 158, 11, 0.2);
    box-shadow: 0 0 20px rgba(245, 158, 11, 0.3);
}

.portal-card[data-type="mwalimu"] .portal-card-icon { 
    color: #8B5CF6;
    text-shadow: 0 0 10px rgba(139, 92, 246, 0.3);
}
.portal-card[data-type="mwalimu"]:hover .portal-card-icon { 
    background: rgba(139, 92, 246, 0.2);
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
}

.portal-card[data-type="parents"] .portal-card-icon { 
    color: #EC4899;
    text-shadow: 0 0 10px rgba(236, 72, 153, 0.3);
}
.portal-card[data-type="parents"]:hover .portal-card-icon { 
    background: rgba(236, 72, 153, 0.2);
    box-shadow: 0 0 20px rgba(236, 72, 153, 0.3);
}

/* Dark mode adjustments for glass cards */
body.dark-mode .portal-card {
    background: rgba(17, 25, 40, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

body.dark-mode .portal-card:hover {
    background: rgba(17, 25, 40, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

body.dark-mode .portal-card-icon {
    background: rgba(255, 255, 255, 0.08);
}

body.dark-mode .portal-card-desc {
    color: var(--gray-x-11);
}

/* Responsive Grid Layouts */
@media (min-width: 768px) {
    .portal-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
    }
}

@media (min-width: 992px) {
    .portal-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1200px) {
    .portal-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}

/* Mobile adjustments */
@media (max-width: 767px) {
    .portal-cards-section {
        padding: 60px 0;
    }
    
    .portal-card {
        padding: 30px 20px;
    }
    
    .portal-card-icon {
        width: 70px;
        height: 70px;
        font-size: 2.2rem;
    }
    
    .portal-card-title {
        font-size: 1.6rem;
    }
    
    .portal-card-desc {
        font-size: 1.3rem;
    }
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
            .hero-stats { margin-top: 30px; }
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
                <li class="navbar-item"><a href="about.php" class="navbar-link">About</a></li>
                <li class="navbar-item"><a href="pricing.php" class="navbar-link">Pricing</a></li>
               <li class="navbar-item"><a href="career-pathways.php" class="navbar-link" data-nav-link>Career Pathways</a></li>
                <li class="navbar-item"><a href="blog.php" class="navbar-link" data-nav-link>Blog</a></li>
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

<section class="section hero has-bg-image" id="home" aria-label="home">
    <div class="container">
        <div class="hero-content reveal">
            <!-- Orange Badge - Built for CBE curriculum - Appears immediately after header -->
            <div class="cbe-badge">
                <i class="fas fa-graduation-cap"></i>
                <span>Built for CBE curriculum</span>
            </div>
            
            <h1 class="h1 section-title">
                The Best School <span class="span">Management System</span> for Kenyan Schools
            </h1>
            <p class="hero-text">
                Streamline administration, enhance learning outcomes, and engage parents seamlessly with Kenya's #1 school management platform.
            </p>
            
            <!-- Button Group -->
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

            <!-- Stats Counter inside Hero -->
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
                    <div class="hero-stat-number" id="heroReportsCount">0</div>
                    <div class="hero-stat-label">Reports Generated</div>
                </div>
            </div>
        </div>
        
        <!-- Original Hero Banner with Two Images -->
        <div class="hero-banner">
            <div class="img-holder one" style="--width: 270; --height: 300;">
                <img src="/images/school-bg.png" width="270" height="300" alt="hero banner" class="img-cover">
            </div>
            <div class="img-holder two" style="--width: 240; --height: 370;">
                <img src="/images/logo.png" width="240" height="370" alt="hero banner" class="img-cover">
            </div>
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 380 190'%3E%3Cpath fill='%2300BFFF' fill-opacity='0.1' d='M0,0 L380,0 L380,190 L0,190 Z'/%3E%3C/svg%3E" width="380" height="190" alt="" class="shape hero-shape-1">
        </div>
    </div>
</section>

            <!-- Portal Cards Section -->
<section class="section portal-cards-section">
    <div class="container">
        <div class="portal-grid">
            <!-- Analytics Portal Card -->
            <div class="portal-card" data-type="analytics">
                <div class="portal-card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="portal-card-title">Analytics Portal</h3>
                <p class="portal-card-desc">Real-time exam analysis, performance tracking, and detailed reports for informed decision-making.</p>
                <a href="analytics.php" class="portal-card-btn">Explore <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <!-- Fee Portal Card -->
            <div class="portal-card" data-type="fee">
                <div class="portal-card-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h3 class="portal-card-title">Fee Portal</h3>
                <p class="portal-card-desc">Automated fee management, M-Pesa integration, payment tracking, and instant receipts.</p>
                <a href="feesystem.php" class="portal-card-btn">Explore <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <!-- Bulk SMS Portal Card -->
            <div class="portal-card" data-type="sms">
                <div class="portal-card-icon">
                    <i class="fas fa-sms"></i>
                </div>
                <h3 class="portal-card-title">Bulk SMS Portal</h3>
                <p class="portal-card-desc">Send instant alerts, reminders, and announcements to parents and staff effortlessly.</p>
                <a href="bulksms.php" class="portal-card-btn">Explore <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <!-- Mwalimu Hub Card -->
            <div class="portal-card" data-type="mwalimu">
                <div class="portal-card-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <h3 class="portal-card-title">Mwalimu Hub</h3>
                <p class="portal-card-desc">AI-powered teaching assistant for CBC curriculum, lesson plans, and assessments.</p>
                <a href="mwalimu-hub.php" class="portal-card-btn">Explore <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <!-- Parents Portal Card -->
            <div class="portal-card" data-type="parents">
                <div class="portal-card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="portal-card-title">Parents Portal</h3>
                <p class="portal-card-desc">Real-time access to children's academic progress, fee balances, and attendance.</p>
                <a href="parents-portal.php" class="portal-card-btn">Explore <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>



        </article>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-top section">
            <div class="container grid-list">
                <div class="footer-brand"><a href="#" class="logo"><img src="/images/logo.png" alt="EduScore logo"></a><p class="footer-brand-text">Modern school management system for Kenyan educational institutions.</p><div class="wrapper"><span class="span">Add:</span><address class="address">Ngara - Nairobi, Kenya</address></div><div class="wrapper"><span class="span">Call:</span><a href="tel:+254799115282" class="footer-link">+254 799 115 282</a></div><div class="wrapper"><span class="span">Email:</span><a href="mailto:eduscoreke@gmail.com" class="footer-link">eduscoreke@gmail.com</a></div></div>
                <ul class="footer-list"><li><p class="footer-list-title">Online Platform</p></li><li><a href="about.php" class="footer-link">About</a></li><li><a href="#courses" class="footer-link">Courses</a></li><li><a href="#" class="footer-link">Instructor</a></li><li><a href="#" class="footer-link">Events</a></li><li><a href="#" class="footer-link">Purchase Guide</a></li></ul>
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

        // Stats counter animation function
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
        
        // Fetch stats for both hero stats and original stats section
        function fetchStats() {
            fetch('ajax/get_stats.php').then(response => response.json()).then(data => {
                if (data.success) {
                    // Hero stats
                    const heroSchools = document.getElementById('heroSchoolsCount');
                    const heroStudents = document.getElementById('heroStudentsCount');
                    const heroTeachers = document.getElementById('heroTeachersCount');
                    const heroReports = document.getElementById('heroReportsCount');
                    
                    if (heroSchools) animateNumber(heroSchools, 0, data.data.schools, 1500);
                    if (heroStudents) animateNumber(heroStudents, 0, data.data.students, 1500);
                    if (heroTeachers) animateNumber(heroTeachers, 0, data.data.teachers, 1500);
                    if (heroReports) animateNumber(heroReports, 0, data.data.reports, 1500);
                    
                    // Original stats section (if it exists)
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
        
        // Trigger stats when hero section is visible
        const heroSection = document.querySelector('.hero');
        if (heroSection) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => { if (entry.isIntersecting) { fetchStats(); observer.unobserve(entry.target); } });
            }, { threshold: 0.3 });
            observer.observe(heroSection);
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
                body.classList.remove('navbar-open');
                body.style.top = '';
            }
        });
    </script>
</body>
</html>