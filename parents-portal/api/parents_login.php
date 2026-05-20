<?php
header('Content-Type: application/json');
session_start();
require_once '../../includes/config.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if (empty($phone) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Phone number and password are required']);
    exit;
}

// Clean and normalize phone number
function normalizePhoneNumber($phone) {
    // Remove all non-digit characters
    $cleanPhone = preg_replace('/\D/', '', $phone);
    
    // Remove leading 0 if present
    if (strlen($cleanPhone) === 10 && substr($cleanPhone, 0, 1) === '0') {
        $cleanPhone = substr($cleanPhone, 1);
    }
    
    // Remove leading 254 if present
    if (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 3) === '254') {
        $cleanPhone = substr($cleanPhone, 3);
    }
    
    // Remove leading +254 if present (already removed digits only)
    // Ensure we have 9 digits
    if (strlen($cleanPhone) === 9) {
        return $cleanPhone;
    }
    
    // Return original cleaned phone
    return $cleanPhone;
}

// Try different phone formats for lookup
$normalizedPhone = normalizePhoneNumber($phone);
$phoneVariations = [
    $phone, // Original input
    $normalizedPhone, // Normalized 9-digit
    '0' . $normalizedPhone, // With leading 0
    '254' . $normalizedPhone, // With 254 prefix
    '+' . '254' . $normalizedPhone, // With +254 prefix
];

// Also try variations of the original
$originalClean = preg_replace('/\D/', '', $phone);
if ($originalClean) {
    $phoneVariations[] = $originalClean;
    if (strlen($originalClean) === 10 && substr($originalClean, 0, 1) === '0') {
        $phoneVariations[] = substr($originalClean, 1);
        $phoneVariations[] = '254' . substr($originalClean, 1);
    }
    if (strlen($originalClean) === 9) {
        $phoneVariations[] = '0' . $originalClean;
        $phoneVariations[] = '254' . $originalClean;
    }
    if (strlen($originalClean) === 12 && substr($originalClean, 0, 3) === '254') {
        $phoneVariations[] = '0' . substr($originalClean, 3);
        $phoneVariations[] = substr($originalClean, 3);
    }
}

$phoneVariations = array_unique($phoneVariations);

try {
    // Search for parent by phone number using variations
    $foundParent = null;
    $foundPhone = null;
    
    foreach ($phoneVariations as $variant) {
        $stmt = $db->prepare("SELECT id, phone, password, is_verified FROM parents WHERE phone = ?");
        $stmt->execute([$variant]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($parent) {
            $foundParent = $parent;
            $foundPhone = $variant;
            break;
        }
    }
    
    if (!$foundParent) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number or account not found. Please register first.']);
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $foundParent['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid password. Please try again.']);
        exit;
    }
    
    // Check if account is verified
    if ($foundParent['is_verified'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Account not activated. Please complete registration first.']);
        exit;
    }
    
    // Get linked students
    $studentsStmt = $db->prepare("
        SELECT 
            s.id, 
            s.FirstName, 
            s.LastName, 
            s.AdmNo, 
            s.Gender,
            c.class_level as class_name
        FROM parent_students ps
        JOIN tblstudents s ON ps.student_id = s.id
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE ps.parent_id = ?
    ");
    $studentsStmt->execute([$foundParent['id']]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create session
    $_SESSION['is_logged_in'] = true;
    $_SESSION['parent_id'] = $foundParent['id'];
    $_SESSION['parent_phone'] = $foundParent['phone'];
    $_SESSION['user_type'] = 'parent';
    $_SESSION['parent_students'] = $students;
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect' => 'dashboard.php',
        'data' => [
            'parent_id' => $foundParent['id'],
            'students' => $students
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Parents login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>