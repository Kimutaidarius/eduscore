<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include config
require_once 'includes/config.php';

// Define base URL
$base_url = "https://eduscore.co.ke";
$current_url = $base_url . $_SERVER['REQUEST_URI'];
$canonical_url = $base_url . "/blog.php";

// Get user IP and session for tracking
$user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$session_id = session_id();

// ========== FETCH CATEGORIES FROM BLOGS TABLE ==========
$all_categories = [];
try {
    if (isset($db) && $db instanceof PDO) {
        $stmt = $db->prepare("
            SELECT category, COUNT(*) as post_count 
            FROM blogs 
            WHERE category IS NOT NULL AND category != ''
            GROUP BY category
            ORDER BY post_count DESC
        ");
        $stmt->execute();
        $all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Categories fetch error: " . $e->getMessage());
}

// ========== FETCH AUTHORS FROM BLOGS TABLE ==========
$all_authors = [];
try {
    if (isset($db) && $db instanceof PDO) {
        $stmt = $db->prepare("
            SELECT author, COUNT(*) as post_count 
            FROM blogs 
            WHERE author IS NOT NULL AND author != ''
            GROUP BY author
            ORDER BY post_count DESC
        ");
        $stmt->execute();
        $all_authors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Authors fetch error: " . $e->getMessage());
}

// ========== FETCH POPULAR TAGS FROM BLOGS TABLE ==========
$all_tags = [];
try {
    if (isset($db) && $db instanceof PDO) {
        $stmt = $db->prepare("
            SELECT tags 
            FROM blogs 
            WHERE tags IS NOT NULL AND tags != ''
        ");
        $stmt->execute();
        $tag_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process tags (split comma-separated tags)
        $tag_counts = [];
        foreach ($tag_rows as $row) {
            $tags = explode(',', $row['tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    if (!isset($tag_counts[$tag])) {
                        $tag_counts[$tag] = 0;
                    }
                    $tag_counts[$tag]++;
                }
            }
        }
        
        // Sort by count and get top 15
        arsort($tag_counts);
        $all_tags = array_slice($tag_counts, 0, 15);
    }
} catch (PDOException $e) {
    error_log("Tags fetch error: " . $e->getMessage());
}

// ========== ENHANCED SEO META DATA ==========
$page_title = "EduScore Blog | School Management Insights & Education Technology in Kenya";
$page_description = "Latest insights on school management systems, education technology, CBC curriculum, and digital transformation in Kenyan schools. Expert tips for administrators and teachers.";
$page_keywords = "school management blog Kenya, education technology Kenya, CBC curriculum resources, school ERP tips, Kenyan education news, school administration, teacher resources";
$page_url = $current_url;
$page_image = $base_url . "/images/blog-og-image.jpg";

// Function to get blog image URL
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

// Get single blog post if ID is provided
$single_blog = null;
$is_single_view = false;
$user_has_liked = false;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_single_view = true;
    $blog_id = (int)$_GET['id'];
    
    try {
        if (isset($db) && $db instanceof PDO) {
            // Fetch blog post
            $stmt = $db->prepare("SELECT * FROM blogs WHERE id = ?");
            $stmt->execute([$blog_id]);
            $single_blog = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($single_blog) {
                // Update page metadata for single blog
                $page_title = htmlspecialchars($single_blog['title']) . " | EduScore Blog";
                $page_description = htmlspecialchars(substr(strip_tags($single_blog['content']), 0, 160));
                $page_url = $base_url . "/blog.php?id=" . $blog_id;
                $page_keywords = htmlspecialchars($single_blog['category']) . ", school management, education technology Kenya, " . htmlspecialchars($single_blog['title']);
                
                // Fix image path for single blog view
                $imageUrl = getBlogImageUrl($single_blog['image']);
                if ($imageUrl) {
                    $page_image = $imageUrl;
                }
                
                // Check if user has already viewed this blog (prevent multiple view counts from same session)
                $checkViewStmt = $db->prepare("SELECT id FROM blog_views WHERE blog_id = ? AND user_session = ?");
                $checkViewStmt->execute([$blog_id, $session_id]);
                
                if (!$checkViewStmt->fetch()) {
                    // Record view
                    $viewStmt = $db->prepare("INSERT INTO blog_views (blog_id, user_ip, user_session, created_at) VALUES (?, ?, ?, NOW())");
                    $viewStmt->execute([$blog_id, $user_ip, $session_id]);
                    
                    // Update views count in blogs table
                    $updateStmt = $db->prepare("UPDATE blogs SET views = views + 1 WHERE id = ?");
                    $updateStmt->execute([$blog_id]);
                    $single_blog['views'] = ($single_blog['views'] ?? 0) + 1;
                }
                
                // Check if user has liked this blog
                $checkLikeStmt = $db->prepare("SELECT id FROM blog_likes_ip WHERE blog_id = ? AND ip_address = ?");
                $checkLikeStmt->execute([$blog_id, $user_ip]);
                $user_has_liked = $checkLikeStmt->fetch() ? true : false;
            }
        }
    } catch (PDOException $e) {
        error_log("Blog fetch error: " . $e->getMessage());
    }
}

// Fetch all blog posts for listing (with category filter if applied)
$selected_category = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$all_blogs = [];

try {
    if (isset($db) && $db instanceof PDO) {
        if (!empty($selected_category)) {
            $stmt = $db->prepare("
                SELECT id, title, description, image, category, created_at, author, views, likes, tags 
                FROM blogs 
                WHERE category = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$selected_category]);
        } else {
            $stmt = $db->prepare("
                SELECT id, title, description, image, category, created_at, author, views, likes, tags 
                FROM blogs 
                ORDER BY created_at DESC
            ");
            $stmt->execute();
        }
        $all_blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Blogs fetch error: " . $e->getMessage());
}

// ========== GENERATE BREADCRUMB SCHEMA ==========
function getBreadcrumbSchema($base_url, $is_single_view = false, $blog_title = null, $category_name = null) {
    $items = [
        [
            "@type" => "ListItem",
            "position" => 1,
            "name" => "Home",
            "item" => $base_url . "/"
        ]
    ];
    
    if ($is_single_view && $blog_title) {
        $items[] = [
            "@type" => "ListItem",
            "position" => 2,
            "name" => "Blog",
            "item" => $base_url . "/blog.php"
        ];
        if ($category_name) {
            $items[] = [
                "@type" => "ListItem",
                "position" => 3,
                "name" => $category_name,
                "item" => $base_url . "/blog.php?cat=" . urlencode($category_name)
            ];
        }
        $items[] = [
            "@type" => "ListItem",
            "position" => $category_name ? 4 : 3,
            "name" => $blog_title,
            "item" => $base_url . "/blog.php?id=" . ($_GET['id'] ?? '')
        ];
    } else {
        $items[] = [
            "@type" => "ListItem",
            "position" => 2,
            "name" => "Blog",
            "item" => $base_url . "/blog.php"
        ];
    }
    
    return [
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => $items
    ];
}

// ========== GENERATE ARTICLE SCHEMA FOR SINGLE BLOG ==========
$article_schema = null;
if ($is_single_view && $single_blog) {
    $display_category = $single_blog['category'] ?? 'Education';
    $article_schema = [
        "@context" => "https://schema.org",
        "@type" => "Article",
        "headline" => htmlspecialchars($single_blog['title']),
        "description" => htmlspecialchars(substr(strip_tags($single_blog['content']), 0, 200)),
        "image" => $page_image,
        "datePublished" => date('c', strtotime($single_blog['created_at'])),
        "dateModified" => date('c', strtotime($single_blog['updated_at'] ?? $single_blog['created_at'])),
        "author" => [
            "@type" => "Person",
            "name" => htmlspecialchars($single_blog['author'] ?? 'EduScore Team')
        ],
        "publisher" => [
            "@type" => "Organization",
            "name" => "EduScore Kenya",
            "logo" => [
                "@type" => "ImageObject",
                "url" => $base_url . "/images/logo.png"
            ]
        ],
        "mainEntityOfPage" => [
            "@type" => "WebPage",
            "@id" => $page_url
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en-KE">
<head>
    <!-- Google Search Console Verification -->
    <meta name="google-site-verification" content="HZsZNr2Rfno72qnurFjgV4UEMnMM3H0qjWryXqIzxpI" />
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <!-- Primary SEO Meta Tags -->
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="EduScore Kenya">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($is_single_view ? $page_url : $canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="en-ke" href="<?php echo htmlspecialchars($is_single_view ? $page_url : $canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph Meta Tags for Social Sharing -->
    <meta property="og:type" content="<?php echo $is_single_view ? 'article' : 'website'; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($is_single_view ? $single_blog['title'] : 'EduScore Blog', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="EduScore Kenya">
    <meta property="og:locale" content="en_KE">
    
    <?php if ($is_single_view && $single_blog): ?>
    <meta property="article:published_time" content="<?php echo date('c', strtotime($single_blog['created_at'])); ?>">
    <meta property="article:modified_time" content="<?php echo date('c', strtotime($single_blog['updated_at'] ?? $single_blog['created_at'])); ?>">
    <meta property="article:author" content="<?php echo htmlspecialchars($single_blog['author'] ?? 'EduScore Team'); ?>">
    <meta property="article:section" content="<?php echo htmlspecialchars($single_blog['category'] ?? 'Education'); ?>">
    <?php endif; ?>
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:site" content="@eduscoreke">
    
    <!-- WhatsApp-Specific Meta Tags -->
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="EduScore Kenya">
    
    <!-- Structured Data / Schema Markup -->
    <script type="application/ld+json">
    <?php 
    $category_name_for_schema = ($is_single_view && $single_blog) ? ($single_blog['category'] ?? null) : null;
    echo json_encode(getBreadcrumbSchema($base_url, $is_single_view, $single_blog ? $single_blog['title'] : null, $category_name_for_schema), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); 
    ?>
    </script>
    
    <?php if ($article_schema): ?>
    <script type="application/ld+json">
    <?php echo json_encode($article_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <?php endif; ?>
    
    <!-- WebSite Schema -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "EduScore Kenya",
        "url": "<?php echo $base_url; ?>",
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "<?php echo $base_url; ?>/search.php?q={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#00BFFF">
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
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
        
        body.dark-mode .blog-card,
        body.dark-mode .single-blog-container {
            background-color: hsl(0, 0%, 20%);
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        li { list-style: none; }
        
        a { text-decoration: none; color: inherit; }
        
        img { max-width: 100%; height: auto; }
        
        button {
            background: none;
            border: none;
            cursor: pointer;
        }
        
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
        
        /*-----------------------------------*\
          #REUSED STYLE
        \*-----------------------------------*/
        .container { padding-inline: 15px; max-width: 1200px; margin: 0 auto; }
        
        .section { padding-block: var(--section-padding); }
        
        .btn {
            background-color: var(--kappel);
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: var(--fs-4);
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: var(--radius-5);
            transition: var(--transition-1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,191,255,0.2);
        }
        
        /*-----------------------------------*\
          #HEADER
        \*-----------------------------------*/
        .header {
            position: sticky;
            top: 0;
            background-color: var(--white);
            padding-block: 12px;
            box-shadow: var(--shadow-1);
            z-index: 100;
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        .logo img { height: 40px; width: auto; }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .theme-toggle {
            font-size: 2rem;
            cursor: pointer;
            color: var(--eerie-black-1);
            background: none;
            border: none;
        }
        
        .theme-toggle .fa-sun { display: none; }
        body.dark-mode .theme-toggle .fa-moon { display: none; }
        body.dark-mode .theme-toggle .fa-sun { display: block; }
        
        .menu-btn {
            font-size: 2.4rem;
            cursor: pointer;
            color: var(--eerie-black-1);
            background: none;
            border: none;
        }
        
        /* Mobile Navbar */
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
            box-shadow: 2px 0 20px rgba(0,0,0,0.15);
        }
        
        .navbar.active { left: 0; }
        
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
            border-bottom: 1px solid var(--platinum);
        }
        
        .nav-close-btn {
            font-size: 2rem;
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .navbar-list { padding: 15px 20px; }
        
        .navbar-item { margin-bottom: 10px; }
        
        .navbar-link {
            display: block;
            padding: 10px 0;
            font-weight: 500;
            transition: var(--transition-1);
        }
        
        .navbar-link:hover,
        .navbar-link.active { color: var(--kappel); }
        
        .overlay {
            position: fixed;
            inset: 0;
            background-color: var(--black_80);
            opacity: 0;
            visibility: hidden;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Desktop Navbar */
        @media (min-width: 992px) {
            .menu-btn { display: none; }
            
            .navbar {
                position: static;
                left: auto;
                width: auto;
                max-width: none;
                height: auto;
                background: none;
                transform: none;
                box-shadow: none;
            }
            
            body.navbar-open {
                overflow: auto;
                position: relative;
                width: auto;
            }
            
            .navbar .wrapper { display: none; }
            
            .navbar-list {
                display: flex;
                gap: 30px;
                padding: 0;
            }
            
            .navbar-item { margin-bottom: 0; }
            
            .navbar-link { padding: 0; }
            
            .overlay { display: none; }
        }
        
        /*-----------------------------------*\
          #BREADCRUMB NAVIGATION
        \*-----------------------------------*/
        .breadcrumb {
            background: var(--isabelline);
            padding: 12px 0;
            font-size: 1.3rem;
        }
        
        .breadcrumb .container {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--kappel);
            transition: var(--transition-1);
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb .separator {
            color: var(--gray-web);
        }
        
        .breadcrumb .current {
            color: var(--eerie-black-1);
            font-weight: 600;
        }
        
        /*-----------------------------------*\
          #BLOG PAGE STYLES
        \*-----------------------------------*/
        .page-header {
            text-align: center;
            padding: 4rem 1rem 2rem;
        }
        
        .page-header h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            color: var(--eerie-black-1);
            font-family: var(--ff-league_spartan);
        }
        
        .page-header p {
            margin-top: 1rem;
            font-size: 1.6rem;
            color: var(--gray-web);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Category Filter Bar */
        .category-filter {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 20px 0 30px;
        }
        
        .category-filter a {
            padding: 6px 18px;
            background: var(--isabelline);
            border-radius: 30px;
            font-size: 1.3rem;
            font-weight: 500;
            transition: var(--transition-1);
            color: var(--eerie-black-1);
        }
        
        .category-filter a:hover,
        .category-filter a.active {
            background: var(--kappel);
            color: white;
        }
        
        /* Blog Grid */
        .blog-grid-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem 2rem;
        }
        
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .blog-card {
            background: var(--white);
            border-radius: var(--radius-10);
            overflow: hidden;
            transition: var(--transition-1);
            box-shadow: var(--shadow-1);
            border: 1px solid var(--platinum);
        }
        
        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2);
        }
        
        .blog-card-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background-color: var(--light-gray);
        }
        
        .blog-card-content {
            padding: 1.5rem;
        }
        
        .blog-category {
            display: inline-block;
            background: var(--kappel_15);
            color: var(--kappel);
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .blog-card-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--eerie-black-1);
            margin-bottom: 1rem;
            line-height: 1.4;
            transition: var(--transition-1);
        }
        
        .blog-card-title:hover {
            color: var(--kappel);
        }
        
        .blog-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 1.3rem;
            color: var(--gray-web);
        }
        
        .blog-meta i {
            margin-right: 0.5rem;
            width: 1.4rem;
        }
        
        .blog-description {
            color: var(--gray-web);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .blog-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--platinum);
        }
        
        .blog-stats {
            display: flex;
            gap: 1rem;
        }
        
        .blog-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.3rem;
            color: var(--gray-web);
        }
        
        .like-btn {
            background: none;
            border: none;
            cursor: pointer;
            transition: var(--transition-1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.3rem;
            color: var(--gray-web);
        }
        
        .like-btn:hover { color: #ef4444; }
        .like-btn.liked { color: #ef4444; }
        
        .read-more-btn {
            background: none;
            border: none;
            color: var(--kappel);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .read-more-btn:hover {
            color: hsl(170, 75%, 35%);
            gap: 1rem;
        }
        
        /* Single Blog View */
        .single-blog-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        
        .single-blog-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .single-blog-category {
            display: inline-block;
            background: var(--kappel_15);
            color: var(--kappel);
            padding: 0.3rem 1.5rem;
            border-radius: 20px;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .single-blog-title {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            color: var(--eerie-black-1);
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        
        .single-blog-meta {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            font-size: 1.4rem;
            color: var(--gray-web);
        }
        
        .single-blog-meta i {
            margin-right: 0.5rem;
            width: 1.6rem;
        }
        
        .single-blog-image {
            width: 100%;
            border-radius: var(--radius-10);
            margin-bottom: 2rem;
            background-color: var(--light-gray);
        }
        
        .single-blog-content {
            font-size: 1.6rem;
            line-height: 1.8;
            color: var(--eerie-black-1);
        }
        
        .single-blog-content p {
            margin-bottom: 1.5rem;
        }
        
        .single-blog-content h2 {
            font-size: 2.2rem;
            margin: 2rem 0 1rem;
        }
        
        .single-blog-content h3 {
            font-size: 1.8rem;
            margin: 1.5rem 0 1rem;
        }
        
        .single-blog-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--platinum);
        }
        
        .single-blog-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
        }
        
        .tag {
            background: var(--isabelline);
            color: var(--eerie-black-1);
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 1.2rem;
        }
        
        .single-blog-actions {
            display: flex;
            gap: 1.5rem;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 1.4rem;
            color: var(--gray-web);
            transition: var(--transition-1);
            background: none;
            border: none;
        }
        
        .action-btn:hover { color: var(--kappel); }
        .action-btn.liked { color: #ef4444; }
        
        .back-to-blog {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            color: var(--kappel);
            font-weight: 600;
        }
        
        .back-to-blog:hover { gap: 1rem; }
        
        /* Author Bio Box */
        .author-bio {
            background: var(--isabelline);
            padding: 20px;
            border-radius: var(--radius-10);
            margin: 40px 0;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .author-avatar {
            width: 70px;
            height: 70px;
            background: var(--kappel);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .author-info h4 {
            color: var(--eerie-black-1);
            font-size: 1.6rem;
            margin-bottom: 5px;
        }
        
        .author-info p {
            color: var(--gray-web);
            font-size: 1.4rem;
        }
        
        /* Related Posts */
        .related-posts {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid var(--platinum);
        }
        
        .related-posts h3 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--eerie-black-1);
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .related-item {
            background: var(--white);
            border-radius: var(--radius-10);
            overflow: hidden;
            border: 1px solid var(--platinum);
            transition: var(--transition-1);
        }
        
        .related-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-1);
        }
        
        .related-item img {
            width: 100%;
            height: 140px;
            object-fit: cover;
        }
        
        .related-item h4 {
            padding: 12px;
            font-size: 1.4rem;
            color: var(--eerie-black-1);
        }
        
        /* Bottom Widgets Section (Categories & Authors) */
        .bottom-widgets {
            max-width: 1200px;
            margin: 0 auto 4rem;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .bottom-widget {
            background: var(--white);
            border-radius: var(--radius-10);
            padding: 2rem;
            border: 1px solid var(--platinum);
            box-shadow: var(--shadow-1);
        }
        
        .bottom-widget h3 {
            font-size: 1.8rem;
            color: var(--eerie-black-1);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--kappel);
            font-family: var(--ff-league_spartan);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .bottom-widget h3 i {
            color: var(--kappel);
        }
        
        .bottom-category-list,
        .bottom-author-list {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.8rem;
        }
        
        .bottom-category-list li,
        .bottom-author-list li {
            padding: 0.5rem;
            border-bottom: 1px solid var(--platinum);
        }
        
        .bottom-category-list li a,
        .bottom-author-list li a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition-1);
        }
        
        .bottom-category-list li a:hover,
        .bottom-author-list li a:hover {
            color: var(--kappel);
            transform: translateX(5px);
        }
        
        .category-name,
        .author-name {
            font-weight: 500;
        }
        
        .category-count,
        .author-count {
            background: var(--kappel_15);
            color: var(--kappel);
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .bottom-tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
        }
        
        .bottom-tag-cloud a {
            display: inline-block;
            background: var(--isabelline);
            color: var(--eerie-black-1);
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 1.3rem;
            transition: var(--transition-1);
        }
        
        .bottom-tag-cloud a:hover {
            background: var(--kappel);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Newsletter Section */
        .newsletter-section {
            background: var(--isabelline);
            padding: 40px 20px;
            text-align: center;
            border-radius: var(--radius-10);
            margin: 0 1.5rem 4rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .newsletter-section h3 {
            font-size: 1.8rem;
            color: var(--eerie-black-1);
            margin-bottom: 10px;
        }
        
        .newsletter-section p {
            color: var(--gray-web);
            margin-bottom: 20px;
        }
        
        .newsletter-form {
            display: flex;
            max-width: 500px;
            margin: 0 auto;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .newsletter-form input {
            flex: 1;
            padding: 12px 15px;
            border-radius: var(--radius-5);
            border: 1px solid var(--platinum);
            background: var(--white);
            font-size: 1.4rem;
        }
        
        .newsletter-form input:focus {
            outline: none;
            border-color: var(--kappel);
        }
        
        /* Toast Notification for Share */
        .toast-notification {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--eerie-black-1);
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 1.4rem;
            z-index: 9999;
            opacity: 0;
            transition: all 0.3s ease;
            pointer-events: none;
            white-space: nowrap;
            box-shadow: var(--shadow-2);
        }
        
        .toast-notification.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--platinum);
            margin-bottom: 1rem;
        }
        
        .no-image {
            background: linear-gradient(135deg, var(--kappel) 0%, var(--kappel_15) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: var(--radius-5);
            background: var(--white);
            border: 1px solid var(--platinum);
            color: var(--eerie-black-1);
            transition: var(--transition-1);
        }
        
        .pagination a:hover {
            background: var(--kappel);
            color: white;
            border-color: var(--kappel);
        }
        
        .pagination .active {
            background: var(--kappel);
            color: white;
            border-color: var(--kappel);
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
            gap: 30px;
            padding-block-end: 40px;
            max-width: 1200px;
            margin: 0 auto;
            padding-inline: 1.5rem;
        }
        
        .footer-brand-text { margin-block: 20px; }
        
        .footer-brand .wrapper {
            display: flex;
            gap: 5px;
            margin-block: 10px;
        }
        
        .footer-link { transition: var(--transition-1); }
        .footer-link:hover { color: var(--kappel); }
        
        .footer-list-title {
            color: var(--white);
            font-family: var(--ff-league_spartan);
            font-size: var(--fs-3);
            font-weight: var(--fw-600);
            margin-block-end: 10px;
        }
        
        .footer-list .footer-link { padding-block: 5px; }
        
        .social-list {
            display: flex;
            gap: 25px;
            margin-top: 1.5rem;
        }
        
        .social-link {
            font-size: 2rem;
            transition: var(--transition-1);
        }
        
        .social-link:hover {
            color: var(--kappel);
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            text-align: center;
            padding-block: 30px;
            border-top: 1px solid var(--eerie-black-1);
        }
        
        .copyright-link { color: var(--kappel); display: inline-block; }
        
        /* Responsive */
        @media (min-width: 575px) {
            .footer-top { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (min-width: 768px) {
            .footer-top { grid-template-columns: repeat(4, 1fr); }
        }
        
        @media (max-width: 768px) {
            .blog-grid { grid-template-columns: 1fr; }
            .single-blog-meta { gap: 1rem; }
            .single-blog-footer { flex-direction: column; align-items: flex-start; }
            .author-bio { flex-direction: column; text-align: center; }
            .toast-notification { white-space: normal; text-align: center; width: 90%; }
            .bottom-category-list,
            .bottom-author-list {
                grid-template-columns: 1fr;
            }
        }
        
        /* Reveal Animation */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s ease;
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
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
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="navbar-list">
                <li class="navbar-item"><a href="index.php" class="navbar-link">Home</a></li>
                <li class="navbar-item"><a href="index.php#about" class="navbar-link">About</a></li>
                <li class="navbar-item"><a href="index.php#pricing" class="navbar-link">Pricing</a></li>
                <li class="navbar-item"><a href="blog.php" class="navbar-link active">Blog</a></li>
                <li class="navbar-item"><a href="index.php#faq" class="navbar-link">FAQ</a></li>
                <li class="navbar-item"><a href="index.php#contact" class="navbar-link">Contact</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                <i class="fas fa-moon"></i><i class="fas fa-sun"></i>
            </button>
            <button class="menu-btn" aria-label="open menu" data-nav-toggler>
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="overlay" data-nav-toggler data-overlay></div>
    </div>
</header>

<!-- Breadcrumb Navigation -->
<div class="breadcrumb">
    <div class="container">
        <a href="index.php">Home</a>
        <span class="separator">›</span>
        <?php if ($is_single_view): ?>
            <a href="blog.php">Blog</a>
            <span class="separator">›</span>
            <?php if (!empty($selected_category)): ?>
                <a href="blog.php?cat=<?php echo urlencode($selected_category); ?>">
                    <?php echo htmlspecialchars($selected_category); ?>
                </a>
                <span class="separator">›</span>
            <?php endif; ?>
            <span class="current"><?php echo htmlspecialchars($single_blog['title'] ?? 'Article'); ?></span>
        <?php elseif (!empty($selected_category)): ?>
            <a href="blog.php">Blog</a>
            <span class="separator">›</span>
            <span class="current"><?php echo htmlspecialchars($selected_category); ?></span>
        <?php else: ?>
            <span class="current">Blog</span>
        <?php endif; ?>
    </div>
</div>

<main>
    <?php if ($single_blog && $is_single_view): ?>
        <!-- Single Blog View -->
        <div class="single-blog-container">
            <a href="blog.php<?php echo !empty($selected_category) ? '?cat=' . urlencode($selected_category) : ''; ?>" class="back-to-blog">
                <i class="fas fa-arrow-left"></i> Back to all articles
            </a>
            
            <article>
                <div class="single-blog-header">
                    <a href="blog.php?cat=<?php echo urlencode($single_blog['category'] ?? 'Education'); ?>" class="single-blog-category" style="text-decoration: none;">
                        <?php echo htmlspecialchars($single_blog['category'] ?? 'Education'); ?>
                    </a>
                    <h1 class="single-blog-title"><?php echo htmlspecialchars($single_blog['title']); ?></h1>
                    <div class="single-blog-meta">
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($single_blog['author'] ?? 'EduScore Team'); ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($single_blog['created_at'])); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo $single_blog['read_time'] ?? 5; ?> min read</span>
                        <span><i class="fas fa-eye"></i> <?php echo number_format($single_blog['views'] ?? 0); ?> views</span>
                    </div>
                </div>
                
                <?php 
                $imageUrl = getBlogImageUrl($single_blog['image']);
                if ($imageUrl): 
                ?>
                    <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($single_blog['title']); ?>" class="single-blog-image" onerror="this.onerror=null; this.src='/images/placeholder-blog.jpg'; this.classList.add('no-image');">
                <?php else: ?>
                    <div class="single-blog-image no-image" style="height: 400px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--kappel) 0%, var(--kappel_15) 100%);">
                        <i class="fas fa-newspaper" style="font-size: 6rem; color: white; opacity: 0.5;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="single-blog-content">
                    <?php echo nl2br(htmlspecialchars_decode($single_blog['content'])); ?>
                </div>
                
                <div class="single-blog-footer">
                    <?php if (!empty($single_blog['tags'])): ?>
                        <div class="single-blog-tags">
                            <?php 
                            $tags = explode(',', $single_blog['tags']);
                            foreach ($tags as $tag): ?>
                                <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="single-blog-actions">
                        <button class="action-btn like-btn" data-id="<?php echo $single_blog['id']; ?>" data-liked="<?php echo $user_has_liked ? 'true' : 'false'; ?>">
                            <i class="fas fa-heart"></i> <span class="like-count"><?php echo number_format($single_blog['likes'] ?? 0); ?></span>
                        </button>
                        <button class="action-btn share-btn" 
                                data-title="<?php echo htmlspecialchars($single_blog['title']); ?>"
                                data-url="<?php echo htmlspecialchars($page_url); ?>"
                                data-image="<?php echo htmlspecialchars($page_image); ?>"
                                data-description="<?php echo htmlspecialchars(substr(strip_tags($single_blog['content']), 0, 200)); ?>">
                            <i class="fas fa-share-alt"></i> Share
                        </button>
                    </div>
                </div>
            </article>
            
            <!-- Author Bio Box for E-E-A-T -->
            <div class="author-bio">
                <div class="author-avatar">
                    <?php echo substr(htmlspecialchars($single_blog['author'] ?? 'E'), 0, 1); ?>
                </div>
                <div class="author-info">
                    <h4>About <?php echo htmlspecialchars($single_blog['author'] ?? 'EduScore Team'); ?></h4>
                    <p>Education technology expert passionate about transforming school management in Kenya through innovative digital solutions.</p>
                </div>
            </div>
            
            <!-- Related Posts -->
            <?php
            $related_posts = [];
            if (isset($db) && $db instanceof PDO && $single_blog) {
                try {
                    $relatedStmt = $db->prepare("SELECT id, title, image, category FROM blogs WHERE category = ? AND id != ? LIMIT 3");
                    $relatedStmt->execute([$single_blog['category'], $single_blog['id']]);
                    $related_posts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("Related posts error: " . $e->getMessage());
                }
            }
            
            if (!empty($related_posts)):
            ?>
            <div class="related-posts">
                <h3>You might also like</h3>
                <div class="related-grid">
                    <?php foreach ($related_posts as $related): 
                        $relImageUrl = getBlogImageUrl($related['image']);
                    ?>
                        <a href="blog.php?id=<?php echo $related['id']; ?>" class="related-item">
                            <?php if ($relImageUrl): ?>
                                <img src="<?php echo $relImageUrl; ?>" alt="<?php echo htmlspecialchars($related['title']); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="no-image" style="height: 140px;"><i class="fas fa-newspaper"></i></div>
                            <?php endif; ?>
                            <h4><?php echo htmlspecialchars($related['title']); ?></h4>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Newsletter CTA -->
            <div class="newsletter-section">
                <h3>Subscribe to our newsletter</h3>
                <p>Get the latest school management insights delivered to your inbox</p>
                <form class="newsletter-form" action="subscribe.php" method="POST">
                    <input type="email" name="email" placeholder="Your email address" required>
                    <button type="submit" class="btn">Subscribe</button>
                </form>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Blog Listing View -->
        <div class="page-header">
            <h1><?php echo !empty($selected_category) ? htmlspecialchars($selected_category) : 'EDUSCORE BLOG'; ?></h1>
            <p><?php echo !empty($selected_category) ? 'Explore articles and insights about ' . htmlspecialchars($selected_category) . ' in Kenya.' : 'Educational insights, technology in education, and expert tips for Kenyan schools.'; ?></p>
        </div>
        
        <!-- Category Filter Bar -->
        <div class="category-filter">
            <a href="blog.php" class="<?php echo empty($selected_category) ? 'active' : ''; ?>">All</a>
            <?php foreach ($all_categories as $cat): ?>
                <a href="blog.php?cat=<?php echo urlencode($cat['category']); ?>" 
                   class="<?php echo ($selected_category === $cat['category']) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['category']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Blog Grid Section -->
        <section class="blog-grid-section">
            <?php if (!empty($all_blogs)): ?>
                <div class="blog-grid">
                    <?php foreach ($all_blogs as $blog): 
                        $imageUrl = getBlogImageUrl($blog['image']);
                    ?>
                        <div class="blog-card reveal">
                            <?php if ($imageUrl): ?>
                                <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>" class="blog-card-image" loading="lazy" onerror="this.onerror=null; this.src='/images/placeholder-blog.jpg';">
                            <?php else: ?>
                                <div class="blog-card-image no-image" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--kappel) 0%, var(--kappel_15) 100%);">
                                    <i class="fas fa-newspaper" style="font-size: 3rem; color: white; opacity: 0.5;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="blog-card-content">
                                <a href="blog.php?cat=<?php echo urlencode($blog['category'] ?? 'Education'); ?>" class="blog-category" style="text-decoration: none;">
                                    <?php echo htmlspecialchars($blog['category'] ?? 'Education'); ?>
                                </a>
                                <a href="blog.php?id=<?php echo $blog['id']; ?>">
                                    <h3 class="blog-card-title"><?php echo htmlspecialchars($blog['title']); ?></h3>
                                </a>
                                <div class="blog-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($blog['author'] ?? 'EduScore Team'); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($blog['created_at'])); ?></span>
                                </div>
                                <p class="blog-description"><?php echo htmlspecialchars(substr(strip_tags($blog['description'] ?? $blog['title']), 0, 120)) . '...'; ?></p>
                                <div class="blog-card-footer">
                                    <div class="blog-stats">
                                        <span class="blog-stat"><i class="fas fa-eye"></i> <?php echo number_format($blog['views'] ?? 0); ?></span>
                                        <button class="like-btn" data-id="<?php echo $blog['id']; ?>">
                                            <i class="fas fa-heart"></i> <span class="like-count"><?php echo number_format($blog['likes'] ?? 0); ?></span>
                                        </button>
                                    </div>
                                    <a href="blog.php?id=<?php echo $blog['id']; ?>" class="read-more-btn">
                                        Read More <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Simple Pagination Placeholder -->
                <div class="pagination">
                    <span class="active">1</span>
                    <a href="#">2</a>
                    <a href="#">3</a>
                    <a href="#">Next →</a>
                </div>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-newspaper"></i>
                    <h3>No Blog Posts Yet</h3>
                    <p>Check back soon for updates and insights!</p>
                    <a href="index.php" class="btn" style="margin-top: 1.5rem; display: inline-flex;">Back to Home</a>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- Bottom Widgets Section (Categories, Popular Tags, Top Contributors) -->
        <div class="bottom-widgets">
            <!-- Categories Widget -->
            <?php if (!empty($all_categories)): ?>
            <div class="bottom-widget">
                <h3><i class="fas fa-folder-open"></i> Categories</h3>
                <ul class="bottom-category-list">
                    <?php foreach ($all_categories as $cat): ?>
                    <li>
                        <a href="blog.php?cat=<?php echo urlencode($cat['category']); ?>">
                            <span class="category-name"><?php echo htmlspecialchars($cat['category']); ?></span>
                            <span class="category-count"><?php echo intval($cat['post_count']); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Popular Tags Widget -->
            <?php if (!empty($all_tags)): ?>
            <div class="bottom-widget">
                <h3><i class="fas fa-tags"></i> Popular Tags</h3>
                <div class="bottom-tag-cloud">
                    <?php foreach ($all_tags as $tag => $count): ?>
                        <a href="blog.php?tag=<?php echo urlencode($tag); ?>">
                            <?php echo htmlspecialchars($tag); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Top Contributors / Authors Widget -->
            <?php if (!empty($all_authors)): ?>
            <div class="bottom-widget">
                <h3><i class="fas fa-users"></i> Top Contributors</h3>
                <ul class="bottom-author-list">
                    <?php foreach ($all_authors as $author): ?>
                    <li>
                        <a href="blog.php?author=<?php echo urlencode($author['author']); ?>">
                            <span class="author-name">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($author['author']); ?>
                            </span>
                            <span class="author-count"><?php echo intval($author['post_count']); ?> posts</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Newsletter Section -->
        <div class="newsletter-section reveal">
            <h3>Don't miss our latest insights</h3>
            <p>Subscribe to get the best school management tips and education technology updates</p>
            <form class="newsletter-form" action="subscribe.php" method="POST">
                <input type="email" name="email" placeholder="Your email address" required>
                <button type="submit" class="btn">Subscribe Now</button>
            </form>
        </div>
        
    <?php endif; ?>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="footer-top">
        <div class="footer-brand">
            <a href="index.php"><img src="/images/logo.png" alt="EduScore logo" style="height: 40px;"></a>
            <p class="footer-brand-text">Modern school management system for Kenyan educational institutions.</p>
            <div class="wrapper"><span>Add:</span><address>Ngara - Nairobi, Kenya</address></div>
            <div class="wrapper"><span>Call:</span><a href="tel:+254799115282" class="footer-link">+254 799 115 282</a></div>
            <div class="wrapper"><span>Email:</span><a href="mailto:eduscoreke@gmail.com" class="footer-link">eduscoreke@gmail.com</a></div>
        </div>
        <ul class="footer-list">
            <li><p class="footer-list-title">Online Platform</p></li>
            <li><a href="index.php#features" class="footer-link">Features</a></li>
            <li><a href="index.php#pricing" class="footer-link">Pricing</a></li>
            <li><a href="index.php#faq" class="footer-link">FAQ</a></li>
        </ul>
        <ul class="footer-list">
            <li><p class="footer-list-title">Links</p></li>
            <li><a href="index.php#contact" class="footer-link">Contact Us</a></li>
            <li><a href="blog.php" class="footer-link">Blog</a></li>
            <li><a href="index.php#about" class="footer-link">About</a></li>
        </ul>
        <div class="footer-list">
            <p class="footer-list-title">Newsletter</p>
            <p>Enter your email to subscribe</p>
            <form action="subscribe.php" method="POST" style="display: flex; gap: 10px; margin-top: 1rem; flex-wrap: wrap;">
                <input type="email" name="email" placeholder="Your email" style="padding: 10px; border-radius: 5px; border: none; flex: 1;">
                <button type="submit" class="btn" style="padding: 10px 20px;">Subscribe</button>
            </form>
            <div class="social-list">
                <a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                <a href="#" class="social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>Copyright <?php echo date('Y'); ?> All Rights Reserved by <a href="#" class="copyright-link">EduScore Kenya</a></p>
    </div>
</footer>

<!-- Toast Notification -->
<div id="shareToast" class="toast-notification">Link copied to clipboard! Share with your network.</div>

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

    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('active'); });
    }, { threshold: 0.15 });
    reveals.forEach(el => revealObserver.observe(el));

    // Like functionality with database integration
    async function updateLike(blogId, button) {
        const likeSpan = button.querySelector('.like-count');
        const currentLikes = parseInt(likeSpan.textContent);
        const wasLiked = button.classList.contains('liked');
        
        // Optimistic update
        if (wasLiked) {
            button.classList.remove('liked');
            likeSpan.textContent = currentLikes - 1;
        } else {
            button.classList.add('liked');
            likeSpan.textContent = currentLikes + 1;
        }
        
        try {
            const response = await fetch('ajax/update_like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ blog_id: blogId, action: wasLiked ? 'unlike' : 'like' })
            });
            const result = await response.json();
            if (result.success) {
                likeSpan.textContent = result.likes;
                if (result.liked) {
                    button.classList.add('liked');
                } else {
                    button.classList.remove('liked');
                }
            } else {
                // Revert on error
                if (wasLiked) {
                    button.classList.add('liked');
                    likeSpan.textContent = currentLikes;
                } else {
                    button.classList.remove('liked');
                    likeSpan.textContent = currentLikes;
                }
            }
        } catch (error) {
            console.error('Error:', error);
            // Revert on error
            if (wasLiked) {
                button.classList.add('liked');
                likeSpan.textContent = currentLikes;
            } else {
                button.classList.remove('liked');
                likeSpan.textContent = currentLikes;
            }
        }
    }

    // Initialize like buttons
    document.querySelectorAll('.like-btn').forEach(btn => {
        const blogId = btn.dataset.id;
        const isLiked = btn.dataset.liked === 'true';
        if (isLiked) {
            btn.classList.add('liked');
        }
        
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            await updateLike(blogId, btn);
        });
    });

    // Enhanced Share functionality with rich metadata
    function showToast(message) {
        const toast = document.getElementById('shareToast');
        toast.textContent = message || 'Link copied to clipboard! Share with your network.';
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    async function shareWithRichData(title, url, imageUrl, description) {
        // For devices with Web Share API (mobile)
        if (navigator.share) {
            try {
                const shareData = {
                    title: title,
                    text: description || `Check out this article: ${title}`,
                    url: url
                };
                
                // Add image if supported (some browsers support sharing images)
                if (imageUrl && navigator.canShare && navigator.canShare({ files: [] })) {
                    try {
                        const response = await fetch(imageUrl);
                        const blob = await response.blob();
                        const file = new File([blob], 'share-image.jpg', { type: blob.type });
                        shareData.files = [file];
                    } catch (e) {
                        console.log('Could not attach image for share:', e);
                    }
                }
                
                await navigator.share(shareData);
                return true;
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Share failed:', err);
                }
                return false;
            }
        }
        
        // Fallback: Copy link with rich preview
        const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(`${title}\n\n${description.substring(0, 100)}...\n\nRead more: ${url}`)}`;
        
        // Show option for desktop users
        if (confirm('Share this article?\n\nClick OK to share via WhatsApp (opens in new window), or Cancel to copy link.')) {
            window.open(whatsappUrl, '_blank');
            return true;
        } else {
            // Copy to clipboard as fallback
            try {
                await navigator.clipboard.writeText(url);
                showToast('Link copied! The link already contains the article image preview.');
                return true;
            } catch (err) {
                // Fallback for old browsers
                const textarea = document.createElement('textarea');
                textarea.value = url;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Link copied! The link already contains the article image preview.');
                return true;
            }
        }
    }

    // Initialize share buttons
    document.querySelectorAll('.share-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            // Get share data from button attributes or meta tags
            let title = btn.dataset.title;
            let url = btn.dataset.url;
            let imageUrl = btn.dataset.image;
            let description = btn.dataset.description;
            
            // If not set in data attributes, get from page meta tags
            if (!title) {
                title = document.querySelector('meta[property="og:title"]')?.content || document.title;
            }
            if (!url) {
                url = window.location.href;
            }
            if (!imageUrl) {
                imageUrl = document.querySelector('meta[property="og:image"]')?.content;
            }
            if (!description) {
                description = document.querySelector('meta[property="og:description"]')?.content || 
                             document.querySelector('meta[name="description"]')?.content ||
                             'Check out this article from EduScore Kenya';
            }
            
            // Add "Read more" call to action
            description = `${description.substring(0, 150)}... Read more on EduScore Kenya`;
            
            await shareWithRichData(title, url, imageUrl, description);
        });
    });
</script>
</body>
</html>