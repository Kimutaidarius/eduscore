<?php
// ajax/check_availability.php
session_start();
header('Content-Type: application/json');

// Include database configuration
require_once '../config/config.php';

// Function to send JSON response
function sendAvailabilityResponse($available, $field, $message = '') {
    echo json_encode([
        'available' => $available,
        'field' => $field,
        'message' => $message
    ]);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendAvailabilityResponse(false, '', 'Invalid request method');
}

// Validate CSRF token
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    sendAvailabilityResponse(false, '', 'Invalid security token');
}

try {
    // Check username availability
    if (isset($_POST['check_only']) && $_POST['check_only'] === 'username' && isset($_POST['username'])) {
        $username = sanitize($_POST['username']);
        
        if (empty($username)) {
            sendAvailabilityResponse(false, 'username', 'Username cannot be empty');
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            sendAvailabilityResponse(false, 'username', 'Username already taken');
        } else {
            sendAvailabilityResponse(true, 'username', 'Username available');
        }
    }
    
    // Check email availability
    if (isset($_POST['check_only']) && $_POST['check_only'] === 'email' && isset($_POST['email'])) {
        $email = sanitize($_POST['email']);
        
        if (empty($email)) {
            sendAvailabilityResponse(false, 'email', 'Email cannot be empty');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendAvailabilityResponse(false, 'email', 'Invalid email format');
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            sendAvailabilityResponse(false, 'email', 'Email already registered');
        } else {
            sendAvailabilityResponse(true, 'email', 'Email available');
        }
    }
    
    sendAvailabilityResponse(false, '', 'Invalid request');
    
} catch (PDOException $e) {
    error_log("Availability check error: " . $e->getMessage());
    sendAvailabilityResponse(false, '', 'Database error');
}
?>