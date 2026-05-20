<?php
/**
 * Generate clean URL or fallback to .php
 */
function url($path) {
    static $use_clean_urls = null;
    
    if ($use_clean_urls === null) {
        // Check if clean URLs are enabled (you can set this in config)
        $use_clean_urls = defined('USE_CLEAN_URLS') ? USE_CLEAN_URLS : true;
    }
    
    if ($use_clean_urls) {
        return '/' . str_replace('.php', '', $path);
    } else {
        return $path;
    }
}