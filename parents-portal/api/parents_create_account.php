<?php
header('Content-Type: application/json');
session_start();
require_once '../../includes/config.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$student_id = isset($input['student_id']) ? intval($input['student_id']) : 0;
$password = isset($input['password']) ? trim($input['password']) : '';

if (empty($phone) || empty($student_id) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Phone number, student ID, and password are required']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // Verify student exists and is linked to this phone
    $cleanPhone = preg_replace('/\D/', '', $phone);
    $phoneVariations = [$cleanPhone];
    if (strlen($cleanPhone) === 9) {
        $phoneVariations[] = '0' . $cleanPhone;
        $phoneVariations[] = '254' . $cleanPhone;
    } elseif (strlen($cleanPhone) === 10 && substr($cleanPhone, 0, 1) === '0') {
        $phoneVariations[] = substr($cleanPhone, 1);
        $phoneVariations[] = '254' . substr($cleanPhone, 1);
    } elseif (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 3) === '254') {
        $phoneVariations[] = '0' . substr($cleanPhone, 3);
        $phoneVariations[] = substr($cleanPhone, 3);
    }
    $phoneVariations = array_unique($phoneVariations);
    
    $placeholders = implode(',', array_fill(0, count($phoneVariations), '?'));
    $stmt = $db->prepare("
        SELECT id, FirstName, LastName, GuardianName 
        FROM tblstudents 
        WHERE id = ? AND GuardianPhone IN ($placeholders)
    ");
    
    $params = array_merge([$student_id], $phoneVariations);
    $stmt->execute($params);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found or not linked to this phone number']);
        exit;
    }
    
    // Check if parents table exists, if not create it
    try {
        $db->query("SELECT 1 FROM parents LIMIT 1");
    } catch (PDOException $e) {
        // Create parents table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS `parents` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `phone` varchar(20) NOT NULL,
                `password` varchar(255) DEFAULT NULL,
                `is_verified` tinyint(1) DEFAULT 0,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `phone` (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    // Check if parent_students table exists, if not create it
    try {
        $db->query("SELECT 1 FROM parent_students LIMIT 1");
    } catch (PDOException $e) {
        // Create parent_students table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS `parent_students` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `parent_id` int(11) NOT NULL,
                `student_id` int(11) NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `parent_student_unique` (`parent_id`, `student_id`),
                KEY `parent_id` (`parent_id`),
                KEY `student_id` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    // Check if parent already has an account
    $checkStmt = $db->prepare("SELECT id FROM parents WHERE phone = ?");
    $checkStmt->execute([$phone]);
    $existingParent = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    if ($existingParent) {
        // Update existing parent
        $updateStmt = $db->prepare("UPDATE parents SET password = ?, is_verified = 1, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$hashed_password, $existingParent['id']]);
        $parent_id = $existingParent['id'];
    } else {
        // Create new parent
        $insertStmt = $db->prepare("
            INSERT INTO parents (phone, password, is_verified, created_at, updated_at) 
            VALUES (?, ?, 1, NOW(), NOW())
        ");
        $insertStmt->execute([$phone, $hashed_password]);
        $parent_id = $db->lastInsertId();
    }
    
    // Link parent to student if not already linked
    $linkStmt = $db->prepare("
        INSERT INTO parent_students (parent_id, student_id, created_at) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE updated_at = NOW()
    ");
    $linkStmt->execute([$parent_id, $student_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully! You can now login.'
    ]);
    
} catch (PDOException $e) {
    error_log("Parents create account error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>