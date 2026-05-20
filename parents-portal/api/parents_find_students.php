<?php
header('Content-Type: application/json');
session_start();
require_once '../../includes/config.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$phone = isset($input['phone']) ? trim($input['phone']) : '';

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

// Clean phone number for searching
$cleanPhone = preg_replace('/\D/', '', $phone);

// Try different phone formats
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

try {
    // Search for students with matching guardian phone
    $placeholders = implode(',', array_fill(0, count($phoneVariations), '?'));
    $stmt = $db->prepare("
        SELECT 
            s.id, 
            s.FirstName, 
            s.LastName, 
            s.AdmNo, 
            s.Gender,
            c.class_level as class_name
        FROM tblstudents s
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE s.GuardianPhone IN ($placeholders)
        AND s.Status = 'Active'
    ");
    
    $stmt->execute($phoneVariations);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($students) > 0) {
        // Mask sensitive data for privacy
        foreach ($students as &$student) {
            // Only show first name and first letter of last name
            if (!empty($student['LastName'])) {
                $student['LastName'] = substr($student['LastName'], 0, 1) . '***';
            }
        }
        
        echo json_encode([
            'success' => true,
            'students' => $students,
            'message' => 'Students found successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'students' => [],
            'message' => 'No students found with this phone number. Please contact your school administrator.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Parents find students error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>