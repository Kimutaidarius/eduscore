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

if (!isset($_GET['admission_no']) || empty($_GET['admission_no'])) {
    echo json_encode(['success' => false, 'message' => 'Admission number is required']);
    exit;
}

$admission_no = $_GET['admission_no'];

try {
    // Fix: Use correct column names from database
    $sql = "SELECT 
                s.id, 
                s.FirstName, 
                s.SecondName, 
                s.LastName, 
                s.AdmNo,
                s.admission_date, 
                s.Gender, 
                s.GuardianName, 
                s.GuardianRelationship,
                s.GuardianPhone, 
                s.guardian_email as GuardianEmail,  -- Fix: Use guardian_email not GuardianEmail
                s.ProfilePic, 
                s.class_id, 
                s.StreamId,
                c.class_level as class_name, 
                st.stream_name
            FROM tblstudents s
            LEFT JOIN tblclasses c ON s.class_id = c.id
            LEFT JOIN tblstreams st ON s.StreamId = st.id
            WHERE s.school_id = ? AND s.AdmNo = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$school_id, $admission_no]);
    
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        echo json_encode([
            'success' => true,
            'data' => $student
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Student not found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching student: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>