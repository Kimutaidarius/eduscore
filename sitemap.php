<?php
// No whitespace before this line - ensure file starts with <?php
header('Content-Type: application/xml; charset=utf-8');

// Error handling - prevents HTML error messages from breaking XML
ini_set('display_errors', 0);
error_reporting(0);

echo '<?xml version="1.0" encoding="UTF-8"?>';

$base_url = "https://eduscore.co.ke";
$lastmod = date('Y-m-d');

// Important pages to index
$pages = [
    // ============================================
    // CORE PAGES
    // ============================================
    '/' => ['priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    '/index.php' => ['priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    
    // ============================================
    // ANALYTICS & EXAM ANALYSIS - SEO LANDING PAGES (HIGH PRIORITY)
    // ============================================
    '/analytics' => ['priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    '/exam-analysis' => ['priority' => '0.85', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    '/exam-analytics' => ['priority' => '0.80', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    '/student-performance' => ['priority' => '0.85', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    '/report-cards' => ['priority' => '0.85', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    '/analytics-dashboard' => ['priority' => '0.6', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    
    // ============================================
    // BLOG - CLEAN URLs
    // ============================================
    '/blog' => ['priority' => '0.85', 'changefreq' => 'weekly', 'lastmod' => $lastmod],
    
    // ============================================
    // FEE SYSTEM - CLEAN SEO URLs (MASKED .php)
    // ============================================
    '/feesystem' => ['priority' => '0.85', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/fee-system' => ['priority' => '0.80', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/fee-management' => ['priority' => '0.80', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    
    // ============================================
    // PARENTS PORTAL - CLEAN SEO URLs (MASKED .php)
    // ============================================
    '/parents-portal' => ['priority' => '0.85', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/parents' => ['priority' => '0.80', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/parent-portal' => ['priority' => '0.80', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    
    // ============================================
    // BULK SMS - CLEAN URLs
    // ============================================
    '/bulksms' => ['priority' => '0.80', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/bulk-sms' => ['priority' => '0.75', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    
    // ============================================
    // OTHER PRODUCTS
    // ============================================
    '/exam-generator/' => ['priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
    '/mwalimu-ai/' => ['priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $lastmod],
];

// Get blog posts dynamically using PDO
$config_path = __DIR__ . '/includes/config.php';
if (file_exists($config_path)) {
    try {
        require_once $config_path;
        
        // Use the existing PDO connection from config.php
        if (isset($db) && $db instanceof PDO) {
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
                    $slug = $row['slug'] ?? '';
                    if (!empty($slug)) {
                        $pages['/blog/' . $slug] = [
                            'priority' => '0.7', 
                            'changefreq' => 'weekly', 
                            'lastmod' => date('Y-m-d', strtotime($row['updated_at'] ?? 'now'))
                        ];
                    }
                }
            }
            $blog_stmt = null; // Close the statement
        } else {
            error_log("Sitemap: PDO connection not available");
        }
    } catch (PDOException $e) {
        // Log error but continue - sitemap still works without dynamic pages
        error_log("Sitemap blog query failed (PDO): " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Sitemap blog query failed: " . $e->getMessage());
    }
} else {
    error_log("Sitemap: Config file not found at: " . $config_path);
}
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