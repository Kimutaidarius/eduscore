<?php
// No whitespace before this line - ensure file starts with <?php

// Set HTTP status code explicitly
http_response_code(200);

// Security and performance headers
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

// Error handling - prevents HTML error messages from breaking XML
ini_set('display_errors', 0);
error_reporting(0);

// XML declaration with proper formatting
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;

$base_url = "https://eduscore.co.ke";
$lastmod = gmdate('Y-m-d'); // Use GMT to prevent timezone inconsistencies

// ============================================
// ONLY PUBLIC MARKETING/LANDING PAGES
// ============================================
$pages = [
    // CORE PAGES
    '/' => ['priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    
    // BLOG
    '/blog' => ['priority' => '0.85', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    
    // PRODUCT LANDING PAGES (ONLY if they contain NO login/dashboard/payment forms)
    '/exam-generator' => ['priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/mwalimu-ai' => ['priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    
    // FEATURE PAGES (marketing only)
    '/features' => ['priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/pricing' => ['priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/contact' => ['priority' => '0.6', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/about' => ['priority' => '0.6', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
];

// Get blog posts dynamically
$config_path = __DIR__ . '/includes/config.php';
if (file_exists($config_path)) {
    try {
        require_once $config_path;
        
        // Set UTF-8 charset for database connection
        if (isset($db) && $db instanceof PDO) {
            $db->exec("SET NAMES utf8mb4");
            
            // Try blogs table first
            try {
                $blog_query = "SELECT slug, updated_at FROM blogs WHERE status = 'published' ORDER BY updated_at DESC LIMIT 50";
                $blog_stmt = $db->prepare($blog_query);
                $blog_stmt->execute();
                $blog_results = $blog_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Fallback to blog_posts table
                try {
                    $blog_query = "SELECT slug, updated_at FROM blog_posts WHERE status = 'published' ORDER BY updated_at DESC LIMIT 50";
                    $blog_stmt = $db->prepare($blog_query);
                    $blog_stmt->execute();
                    $blog_results = $blog_stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e2) {
                    $blog_results = [];
                }
            }
            
            if (!empty($blog_results)) {
                foreach ($blog_results as $row) {
                    // Professional slug sanitization
                    $slug = $row['slug'] ?? '';
                    $slug = strtolower(trim($slug));
                    $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
                    $slug = preg_replace('/-+/', '-', $slug); // Prevent multiple dashes
                    $slug = trim($slug, '-');
                    
                    // Only add if slug is valid and not too long
                    if (!empty($slug) && strlen($slug) < 200) {
                        // Proper date validation with fallback
                        $timestamp = !empty($row['updated_at']) ? strtotime($row['updated_at']) : false;
                        $updated = $timestamp ? gmdate('Y-m-d', $timestamp) : $lastmod;
                        
                        // Prevent duplicate URLs
                        $blog_url = '/blog/' . $slug;
                        if (!isset($pages[$blog_url])) {
                            $pages[$blog_url] = [
                                'priority' => '0.7', 
                                'changefreq' => 'weekly', 
                                'lastmod' => $updated
                            ];
                        }
                    }
                }
            }
            $blog_stmt = null;
        } else {
            error_log("Sitemap: PDO connection not available");
        }
    } catch (PDOException $e) {
        error_log("Sitemap blog query failed (PDO): " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Sitemap blog query failed: " . $e->getMessage());
    }
} else {
    error_log("Sitemap: Config file not found at: " . $config_path);
}

// Sitemap protocol limit is 50,000 URLs - protect against exceeding
if (count($pages) > 50000) {
    $pages = array_slice($pages, 0, 50000, true);
}

// Optional ETag for better crawl efficiency
$etag = md5(json_encode($pages));
header("ETag: \"$etag\"");
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
    
    <?php foreach ($pages as $path => $data): ?>
    <url>
        <loc><?php echo htmlspecialchars($base_url . $path, ENT_XML1, 'UTF-8'); ?></loc>
        <lastmod><?php echo htmlspecialchars($data['lastmod'], ENT_XML1, 'UTF-8'); ?></lastmod>
        <changefreq><?php echo htmlspecialchars($data['changefreq'], ENT_XML1, 'UTF-8'); ?></changefreq>
        <priority><?php echo htmlspecialchars($data['priority'], ENT_XML1, 'UTF-8'); ?></priority>
    </url>
    <?php endforeach; ?>
    
</urlset>
<?php
// Clean exit
exit;
?>