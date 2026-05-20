<?php
/**
 * Keep Session Alive
 * Called by JavaScript to extend session
 */

session_start();

// Return JSON response
header('Content-Type: application/json');

if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
}
?>