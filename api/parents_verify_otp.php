<?php
header('Content-Type: application/json');
session_start();
require_once '../includes/config.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$otp = isset($input['otp']) ? trim($input['otp']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if (empty($phone) || empty($otp) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Phone number, OTP, and password are required']);
    exit;
}

// Validate password
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// Check if OTP exists in session
if (!isset($_SESSION['parent_otp']) || !isset($_SESSION['parent_otp_phone']) || !isset($_SESSION['parent_otp_expiry'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request a new code.']);
    exit;
}

// Verify OTP
if ($_SESSION['parent_otp_phone'] !== $phone) {
    echo json_encode(['success' => false, 'message' => 'Phone number mismatch. Please request a new code.']);
    exit;
}

if (time() > $_SESSION['parent_otp_expiry']) {
    echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new code.']);
    exit;
}

if ($_SESSION['parent_otp'] !== $otp) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if parent already exists
    $stmt = $db->prepare("SELECT id FROM parents WHERE phone = ?");
    $stmt->execute([$phone]);
    $existingParent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingParent) {
        // Update existing parent
        $stmt = $db->prepare("UPDATE parents SET password = ?, is_verified = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $existingParent['id']]);
    } else {
        // Create new parent
        $stmt = $db->prepare("INSERT INTO parents (phone, password, is_verified, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())");
        $stmt->execute([$phone, $hashed_password]);
    }
    
    // Clear OTP session
    unset($_SESSION['parent_otp']);
    unset($_SESSION['parent_otp_phone']);
    unset($_SESSION['parent_otp_expiry']);
    
    // Create session for parent
    $_SESSION['is_logged_in'] = true;
    $_SESSION['user_id'] = $phone;
    $_SESSION['user_type'] = 'parent';
    
    echo json_encode([
        'success' => true, 
        'message' => 'Account activated successfully!',
        'redirect' => 'dashboard.php'
    ]);
    
} catch (PDOException $e) {
    error_log("Parents verify OTP error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>