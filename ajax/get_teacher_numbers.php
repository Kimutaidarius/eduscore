<?php
// ajax/get_teacher_numbers.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['school_id'])) {
    echo json_encode(['numbers' => []]);
    exit();
}

$school_id = $_SESSION['school_id'];

// Database connection
require_once '../includes/config.php';

try {
    // Get teacher phone numbers
    $stmt = $pdo->prepare("
        SELECT phonenumber as phone 
        FROM tblteachers 
        WHERE school_id = ? 
        AND status = 'Active' 
        AND is_deleted = 0
        AND phonenumber IS NOT NULL 
        AND phonenumber != ''
        AND phonenumber != '0'
        AND LENGTH(TRIM(phonenumber)) > 0
        ORDER BY firstname
    ");
    $stmt->execute([$school_id]);
    $numbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Format numbers to international format
    $formatted = [];
    foreach ($numbers as $num) {
        $clean_num = preg_replace('/[^0-9]/', '', $num);
        
        if (strlen($clean_num) == 9 && ($clean_num[0] == '7' || $clean_num[0] == '1')) {
            $formatted[] = '+254' . $clean_num;
        } elseif (strlen($clean_num) == 10 && $clean_num[0] == '0') {
            $formatted[] = '+254' . substr($clean_num, 1);
        } elseif (strlen($clean_num) == 12 && substr($clean_num, 0, 3) == '254') {
            $formatted[] = '+' . $clean_num;
        } elseif (strlen($clean_num) == 13 && $clean_num[0] == '+') {
            $formatted[] = $clean_num;
        } else {
            $formatted[] = $clean_num;
        }
    }
    
    echo json_encode(['numbers' => $formatted]);
    
} catch (Exception $e) {
    error_log("Error in get_teacher_numbers.php: " . $e->getMessage());
    echo json_encode(['numbers' => [], 'error' => $e->getMessage()]);
}
?>