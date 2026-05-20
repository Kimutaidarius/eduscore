<?php
/**
 * Extend Session - AJAX endpoint
 * Called when user clicks "Stay Logged In" button
 */

session_start();
require_once 'session_timeout.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Update last activity timestamp
updateSessionActivity();

echo json_encode(['success' => true, 'message' => 'Session extended']);