<?php
// ajax/get_class_student_count.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['school_id'])) {
    echo json_encode(['total' => 0, 'with_phone' => 0]);
    exit();
}

$school_id = $_SESSION['school_id'];

// Get POST data
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

if (!$class_id) {
    echo json_encode(['total' => 0, 'with_phone' => 0]);
    exit();
}

// Database connection
require_once '../includes/config.php';

try {
    // Get total students and students with phone in a class
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE 
                WHEN GuardianPhone IS NOT NULL 
                AND GuardianPhone != '' 
                AND GuardianPhone != '0'
                AND LENGTH(TRIM(GuardianPhone)) > 0 
                THEN 1 ELSE 0 
            END) as with_phone
        FROM tblstudents 
        WHERE school_id = ? 
        AND class_id = ?
        AND Status = 'Active'
    ");
    $stmt->execute([$school_id, $class_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'total' => intval($result['total']),
        'with_phone' => intval($result['with_phone'])
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_class_student_count.php: " . $e->getMessage());
    echo json_encode(['total' => 0, 'with_phone' => 0, 'error' => $e->getMessage()]);
}
?>