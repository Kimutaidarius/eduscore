<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database config
require_once '../../includes/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (empty($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

$school_id = $_SESSION['school_id'];

try {
    // Get school initials
    $stmt = $db->prepare("SELECT school_name FROM tblschoolinfo WHERE id = :school_id");
    $stmt->execute([':school_id' => $school_id]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Generate initials from school name (first 2-3 letters)
    $initials = '';
    if ($school) {
        $words = explode(' ', $school['school_name']);
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        $initials = substr($initials, 0, 3);
    } else {
        $initials = 'STU';
    }
    
    // Get the last admission number for this school
    $stmt = $db->prepare("
        SELECT AdmNo FROM tblstudents 
        WHERE school_id = :school_id 
        AND AdmNo LIKE :pattern 
        ORDER BY id DESC 
        LIMIT 1
    ");
    
    $pattern = $initials . '%';
    $stmt->execute([
        ':school_id' => $school_id,
        ':pattern' => $pattern
    ]);
    
    $last_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $next_number = 1;
    if ($last_student && !empty($last_student['AdmNo'])) {
        // Extract the numeric part from admission number
        $parts = explode('/', $last_student['AdmNo']);
        if (count($parts) == 2) {
            $last_num = intval($parts[1]);
            $next_number = $last_num + 1;
        } else {
            // Try to extract numbers from end
            preg_match('/(\d+)$/', $last_student['AdmNo'], $matches);
            if (isset($matches[1])) {
                $next_number = intval($matches[1]) + 1;
            }
        }
    }
    
    // Format admission number with leading zeros (3 digits)
    $next_admission = $initials . '/' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'next_admission' => $next_admission,
        'initials' => $initials,
        'last_number' => $next_number - 1
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_last_admission: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>