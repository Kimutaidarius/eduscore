<?php
/**
 * URL Helper for Clean URLs
 * Centralized URL management for the entire application
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if clean URLs are enabled
 * @return bool
 */
function use_clean_urls() {
    static $use_clean_urls = null;
    
    if ($use_clean_urls === null) {
        // Check if defined in config
        if (defined('USE_CLEAN_URLS')) {
            $use_clean_urls = USE_CLEAN_URLS;
        } else {
            // Default to true if not defined
            $use_clean_urls = true;
        }
        
        // Optional: Auto-detect based on server
        /*
        if (!isset($_SESSION['clean_urls_tested'])) {
            // Test if clean URLs are working
            $test_url = '/dashboard';
            $headers = @get_headers('http://' . $_SERVER['HTTP_HOST'] . $test_url);
            $use_clean_urls = ($headers && strpos($headers[0], '200') !== false);
            $_SESSION['clean_urls_tested'] = true;
        }
        */
    }
    
    return $use_clean_urls;
}

/**
 * Generate URL for a page
 * @param string $page The page filename (e.g., 'index.php', 'login.php')
 * @param array $params Optional query parameters
 * @return string The full URL
 */
function url($page, $params = []) {
    $use_clean_urls = use_clean_urls();
    
    // Remove .php extension for clean URLs
    if ($use_clean_urls) {
        $path = '/' . str_replace('.php', '', $page);
    } else {
        $path = $page;
    }
    
    // Add query parameters if any
    if (!empty($params)) {
        $path .= '?' . http_build_query($params);
    }
    
    return $path;
}

/**
 * Generate absolute URL (with domain)
 * @param string $page The page filename
 * @param array $params Optional query parameters
 * @return string The full absolute URL
 */
function absolute_url($page, $params = []) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    return $protocol . $host . url($page, $params);
}

/**
 * Get current page name without extension
 * @return string
 */
function current_page() {
    $uri = $_SERVER['REQUEST_URI'];
    
    // Remove query string
    if (strpos($uri, '?') !== false) {
        $uri = substr($uri, 0, strpos($uri, '?'));
    }
    
    // Remove leading slash
    $uri = ltrim($uri, '/');
    
    // If empty, it's the homepage
    if (empty($uri)) {
        return 'index';
    }
    
    return $uri;
}

/**
 * Check if current page matches a given page
 * @param string $page The page to check (e.g., 'index.php', 'login')
 * @return bool
 */
function is_current_page($page) {
    $current = current_page();
    $clean_page = str_replace('.php', '', $page);
    
    return ($current === $clean_page || $current === $page);
}

/**
 * Redirect to a page
 * @param string $page The page to redirect to
 * @param array $params Optional query parameters
 */
function redirect($page, $params = []) {
    header('Location: ' . url($page, $params));
    exit();
}