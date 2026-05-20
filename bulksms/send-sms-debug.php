<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

try {
    require_once 'config/config.php';
    require_once 'includes/sms_gateway.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $response = ['success' => false, 'message' => ''];
    
    // Test database connection
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Get user's SMS balance
    $stmt = $pdo->prepare("SELECT sms_balance, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Get default sender ID
    $stmt = $pdo->prepare("SELECT sender_id FROM sender_ids WHERE user_id = ? AND status = 'approved' ORDER BY is_default DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $sender = $stmt->fetch();
    $default_sender = $sender ? $sender['sender_id'] : '';
    
    // Get user's contact groups
    $groups = $pdo->prepare("SELECT id, name, (SELECT COUNT(*) FROM contacts WHERE group_id = contact_groups.id) as contact_count FROM contact_groups WHERE user_id = ? ORDER BY name");
    $groups->execute([$user_id]);
    
    // Get recent messages
    $recent = $pdo->prepare("SELECT * FROM sms_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $recent->execute([$user_id]);
    
    // Get templates
    $templates = $pdo->prepare("SELECT * FROM message_templates WHERE user_id = ? ORDER BY name LIMIT 5");
    $templates->execute([$user_id]);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>
<!DOCTYPE html>
<!-- Rest of your HTML remains the same -->