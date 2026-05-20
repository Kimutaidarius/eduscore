<?php
/**
 * AJAX Request Handler
 * Detects AJAX requests and returns only content, not full layout
 */

function isAjaxRequest() {
    return (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || isset($_GET['ajax']) && $_GET['ajax'] == 1;
}

function isSpaRequest() {
    return isAjaxRequest();
}

// This function should be called at the beginning of each page
// Instead of including header/sidebar, just return content
function handleSpaRequest() {
    if (isSpaRequest()) {
        // Don't include header, sidebar, or footer
        return true;
    }
    return false;
}
?>