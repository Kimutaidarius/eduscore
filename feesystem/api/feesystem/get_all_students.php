<?php
// /feesystem/api/feesystem/get_all_students.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];

try {
    $query = "SELECT 
                s.id, 
                s.AdmNo as admission_no, 
                CONCAT(COALESCE(s.FirstName, ''), ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as full_name,
                s.FirstName,
                s.SecondName,
                s.LastName,
                s.Gender as gender,
                s.admission_date,
                s.class_id,
                c.class_level as class_name,
                c.academic_level,
                st.stream_name as stream_name
              FROM tblstudents s
              LEFT JOIN tblclasses c ON s.class_id = c.id
              LEFT JOIN tblstreams st ON s.StreamId = st.id
              WHERE s.school_id = :school_id 
                AND s.Status = 'Active'
              ORDER BY s.FirstName ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process students to ensure class_name is properly set
    foreach ($students as &$student) {
        // If class_name is empty but academic_level exists, use that
        if (empty($student['class_name']) && !empty($student['academic_level'])) {
            $student['class_name'] = $student['academic_level'];
        }
        // If still empty, set default
        if (empty($student['class_name'])) {
            $student['class_name'] = 'Not Assigned';
        }
        // Add stream to class name if stream exists
        if (!empty($student['stream_name'])) {
            $student['class_name'] = $student['class_name'] . ' - ' . $student['stream_name'];
        }
        // Clean up full name
        $student['full_name'] = trim($student['full_name']);
        if (empty($student['full_name'])) {
            $student['full_name'] = trim($student['FirstName'] . ' ' . ($student['SecondName'] ?: '') . ' ' . ($student['LastName'] ?: ''));
        }
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
    
} catch (PDOException $e) {
    error_log("Get all students error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>