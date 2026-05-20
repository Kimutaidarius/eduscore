<?php
header('Content-Type: application/json');
session_start(); // Add this to get session user_id
require_once '../includes/config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    $errors = [];
    if (empty($name)) $errors[] = "Name is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($subject)) $errors[] = "Subject is required";
    if (empty($message)) $errors[] = "Message is required";
    
    if (empty($errors)) {
        try {
            if (isset($db) && $db instanceof PDO) {
                $custom_fields = json_encode([
                    'subject' => $subject,
                    'message' => $message,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                // Since user_id cannot be null, we need to provide a default value
                // If user is logged in, use their ID; otherwise use 0 or create a default guest user
                $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                
                // Alternative: If you want to create a "Guest" user, you can insert with user_id = 1 (assuming you have a guest user)
                // Make sure user_id 0 or 1 exists in your users table, or modify the column to allow NULL
                
                $stmt = $db->prepare("
                    INSERT INTO contacts (user_id, group_id, name, phone, email, custom_fields, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([$user_id, null, $name, $phone, $email ?: null, $custom_fields]);
                
                $response['success'] = true;
                $response['message'] = "Thank you for contacting us! We'll get back to you within 24 hours.";
            } else {
                $response['message'] = "Database connection error";
            }
        } catch (PDOException $e) {
            error_log("Contact save error: " . $e->getMessage());
            $response['message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $response['message'] = implode(", ", $errors);
    }
} else {
    $response['message'] = "Invalid request method";
}

echo json_encode($response);
?>