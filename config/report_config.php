<?php
// config/report_config.php

define('BASE_PATH', dirname(__DIR__));
define('REPORTS_BASE_PATH', BASE_PATH . '/merged_reports');
define('STUDENT_REPORTS_BASE_PATH', BASE_PATH . '/student_reports');
define('BASE_URL', 'https://yourdomain.com'); // Replace with actual domain

// Ensure directories exist with proper permissions
function ensureDirectoryExists($path) {
    if (!file_exists($path)) {
        if (!mkdir($path, 0755, true)) {
            throw new Exception("Failed to create directory: $path");
        }
    }
    return $path;
}

// Create base directories if they don't exist
ensureDirectoryExists(REPORTS_BASE_PATH);
ensureDirectoryExists(STUDENT_REPORTS_BASE_PATH);