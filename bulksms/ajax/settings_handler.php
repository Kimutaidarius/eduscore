<?php
// ajax/settings_handler.php
session_start();
header('Content-Type: application/json');

// Include database configuration
require_once '../config/config.php';

// Function to send JSON response
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse('error', 'Please login to continue');
}

$user_id = $_SESSION['user_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method');
}

// CSRF check
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    sendJsonResponse('error', 'Invalid security token');
}

// Define sanitize function if not already defined
if (!function_exists('sanitize')) {
    function sanitize($data) {
        if ($data === null || $data === '') {
            return '';
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    switch ($action) {
        case 'get_profile':
            $stmt = $pdo->prepare("SELECT id, username, full_name, email, phone, company_name, sms_balance, status, created_at, updated_at FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendJsonResponse('error', 'User not found');
            }
            
            sendJsonResponse('success', 'Profile retrieved', ['user' => $user]);
            break;
            
        case 'update_profile':
            $full_name = isset($_POST['full_name']) ? sanitize($_POST['full_name']) : '';
            $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
            $company_name = isset($_POST['company_name']) ? sanitize($_POST['company_name']) : '';
            
            if (empty($full_name)) {
                sendJsonResponse('error', 'Full name is required');
            }
            
            if (empty($email)) {
                sendJsonResponse('error', 'Email is required');
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonResponse('error', 'Invalid email format');
            }
            
            // Check if email exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                sendJsonResponse('error', 'Email already in use by another account');
            }
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, company_name = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$full_name, $email, $phone, $company_name, $user_id])) {
                // Update session
                $_SESSION['user_name'] = $full_name;
                
                // Get updated user data
                $stmt = $pdo->prepare("SELECT id, username, full_name, email, phone, company_name, sms_balance, status, updated_at FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $updated_user = $stmt->fetch();
                
                sendJsonResponse('success', 'Profile updated successfully!', ['user' => $updated_user]);
            } else {
                sendJsonResponse('error', 'Failed to update profile');
            }
            break;
            
        case 'change_password':
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                sendJsonResponse('error', 'All password fields are required');
            }
            
            // Get current password hash
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                sendJsonResponse('error', 'Current password is incorrect');
            }
            
            if ($new_password !== $confirm_password) {
                sendJsonResponse('error', 'New passwords do not match');
            }
            
            if (strlen($new_password) < 8) {
                sendJsonResponse('error', 'Password must be at least 8 characters');
            }
            
            // Check password strength
            $strength = 0;
            if (preg_match('/[a-z]/', $new_password)) $strength += 25;
            if (preg_match('/[A-Z]/', $new_password)) $strength += 25;
            if (preg_match('/[0-9]/', $new_password)) $strength += 25;
            if (preg_match('/[$@#&!]/', $new_password)) $strength += 25;
            
            if ($strength < 50) {
                sendJsonResponse('error', 'Password is too weak. Use a mix of uppercase, lowercase, numbers, and special characters.');
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user_id])) {
                sendJsonResponse('success', 'Password changed successfully!');
            } else {
                sendJsonResponse('error', 'Failed to change password');
            }
            break;
            
        case 'get_sender_ids':
            $stmt = $pdo->prepare("SELECT * FROM sender_ids WHERE user_id = ? ORDER BY 
                CASE 
                    WHEN status = 'approved' AND is_default = 1 THEN 1
                    WHEN status = 'approved' THEN 2
                    WHEN status = 'pending' THEN 3
                    ELSE 4
                END, created_at DESC");
            $stmt->execute([$user_id]);
            $sender_ids = $stmt->fetchAll();
            
            sendJsonResponse('success', 'Sender IDs retrieved', ['sender_ids' => $sender_ids]);
            break;
            
        case 'add_sender':
            $sender_id = isset($_POST['sender_id']) ? strtoupper(sanitize($_POST['sender_id'])) : '';
            
            if (empty($sender_id)) {
                sendJsonResponse('error', 'Sender ID is required');
            }
            
            if (strlen($sender_id) > 11) {
                sendJsonResponse('error', 'Sender ID must be 11 characters or less');
            }
            
            if (!preg_match('/^[A-Z0-9]+$/', $sender_id)) {
                sendJsonResponse('error', 'Sender ID can only contain letters and numbers');
            }
            
            // Check if sender ID exists
            $stmt = $pdo->prepare("SELECT id FROM sender_ids WHERE user_id = ? AND sender_id = ?");
            $stmt->execute([$user_id, $sender_id]);
            if ($stmt->fetch()) {
                sendJsonResponse('error', 'Sender ID already exists');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO sender_ids (user_id, sender_id, status, created_at) 
                VALUES (?, ?, 'pending', NOW())
            ");
            
            if ($stmt->execute([$user_id, $sender_id])) {
                // Get the newly created sender ID
                $new_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT * FROM sender_ids WHERE id = ?");
                $stmt->execute([$new_id]);
                $new_sender = $stmt->fetch();
                
                sendJsonResponse('success', 'Sender ID submitted for approval!', ['sender' => $new_sender]);
            } else {
                sendJsonResponse('error', 'Failed to add sender ID');
            }
            break;
            
        case 'delete_sender':
            $sender_id = isset($_POST['sender_id']) ? (int)$_POST['sender_id'] : 0;
            
            // Check if sender belongs to user and is not default
            $stmt = $pdo->prepare("SELECT id, is_default FROM sender_ids WHERE id = ? AND user_id = ?");
            $stmt->execute([$sender_id, $user_id]);
            $sender = $stmt->fetch();
            
            if (!$sender) {
                sendJsonResponse('error', 'Sender ID not found');
            }
            
            if ($sender['is_default'] == 1) {
                sendJsonResponse('error', 'Cannot delete default sender ID. Set another as default first.');
            }
            
            $stmt = $pdo->prepare("DELETE FROM sender_ids WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$sender_id, $user_id])) {
                sendJsonResponse('success', 'Sender ID deleted successfully!', ['sender_id' => $sender_id]);
            } else {
                sendJsonResponse('error', 'Failed to delete sender ID');
            }
            break;
            
        case 'set_default_sender':
            $sender_id = isset($_POST['sender_id']) ? (int)$_POST['sender_id'] : 0;
            
            // Check if sender belongs to user and is approved
            $stmt = $pdo->prepare("SELECT id, status FROM sender_ids WHERE id = ? AND user_id = ?");
            $stmt->execute([$sender_id, $user_id]);
            $sender = $stmt->fetch();
            
            if (!$sender) {
                sendJsonResponse('error', 'Sender ID not found');
            }
            
            if ($sender['status'] !== 'approved') {
                sendJsonResponse('error', 'Only approved sender IDs can be set as default');
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Remove default from all
                $stmt = $pdo->prepare("UPDATE sender_ids SET is_default = 0 WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Set new default
                $stmt = $pdo->prepare("UPDATE sender_ids SET is_default = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$sender_id, $user_id]);
                
                $pdo->commit();
                
                // Get updated sender IDs
                $stmt = $pdo->prepare("SELECT * FROM sender_ids WHERE user_id = ? ORDER BY 
                    CASE 
                        WHEN status = 'approved' AND is_default = 1 THEN 1
                        WHEN status = 'approved' THEN 2
                        WHEN status = 'pending' THEN 3
                        ELSE 4
                    END, created_at DESC");
                $stmt->execute([$user_id]);
                $sender_ids = $stmt->fetchAll();
                
                sendJsonResponse('success', 'Default sender ID updated!', ['sender_ids' => $sender_ids]);
            } catch (Exception $e) {
                $pdo->rollBack();
                sendJsonResponse('error', 'Failed to update default sender ID');
            }
            break;
            
        case 'get_monthly_usage':
            $first_day = date('Y-m-01');
            $last_day = date('Y-m-t');
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(cost), 0) as total FROM sms_messages WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?");
            $stmt->execute([$user_id, $first_day, $last_day]);
            $month_usage = $stmt->fetchColumn();
            
            sendJsonResponse('success', 'Monthly usage retrieved', ['month_usage' => $month_usage]);
            break;
            
        default:
            sendJsonResponse('error', 'Invalid action');
    }
} catch (PDOException $e) {
    error_log("Settings handler error: " . $e->getMessage());
    sendJsonResponse('error', 'Database error occurred');
} catch (Exception $e) {
    error_log("Settings handler error: " . $e->getMessage());
    sendJsonResponse('error', $e->getMessage());
}
?>