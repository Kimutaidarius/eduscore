<?php
// ajax/get_stream_student_numbers.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['school_id'])) {
    echo json_encode(['numbers' => []]);
    exit();
}

$school_id = $_SESSION['school_id'];

// Get POST data
$stream_id = isset($_POST['stream_id']) ? intval($_POST['stream_id']) : 0;

if (!$stream_id) {
    echo json_encode(['numbers' => []]);
    exit();
}

// Database connection
require_once '../includes/config.php';

try {
    // Get student guardian phone numbers for a specific stream
    $stmt = $pdo->prepare("
        SELECT s.GuardianPhone as phone 
        FROM tblstudents s
        WHERE s.school_id = ? 
        AND s.StreamId = ?
        AND s.Status = 'Active' 
        AND s.GuardianPhone IS NOT NULL 
        AND s.GuardianPhone != ''
        AND s.GuardianPhone != '0'
        AND LENGTH(TRIM(s.GuardianPhone)) > 0
        ORDER BY s.FirstName
    ");
    $stmt->execute([$school_id, $stream_id]);
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
    error_log("Error in get_stream_student_numbers.php: " . $e->getMessage());
    echo json_encode(['numbers' => [], 'error' => $e->getMessage()]);
}
?>