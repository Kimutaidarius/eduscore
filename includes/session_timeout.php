<?php
/**
 * Session Timeout Handler
 * This file handles automatic logout after a period of inactivity
 * Include this file on every protected page after session_start()
 */

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout configuration
define('SESSION_TIMEOUT_MINUTES', 30); // Change this value to set timeout in minutes
define('SESSION_TIMEOUT_SECONDS', SESSION_TIMEOUT_MINUTES * 60);

// Check if user is logged in
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    
    // Check if last activity timestamp exists
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        // If user has been inactive for too long
        if ($inactive_time > SESSION_TIMEOUT_SECONDS) {
            // Clear all session variables
            $_SESSION = array();
            
            // Destroy the session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            // Destroy the session
            session_destroy();
            
            // Redirect to login page with timeout message
            header('Location: login.php?timeout=1');
            exit;
        }
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    
    // Optional: Regenerate session ID periodically to prevent session fixation
    // Only regenerate every 5 minutes (300 seconds)
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } else {
        $regeneration_time = time() - $_SESSION['last_regeneration'];
        if ($regeneration_time > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
} else {
    // If not logged in, set last activity anyway for non-logged users
    // (useful for login page to track activity)
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}
?>