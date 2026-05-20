<?php
// /feesystem/api/feesystem/search_students_by_admission.php
session_start();
header('Content-Type: application/json');
require_once('../../includes/config.php');

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];
$search = $data['search'] ?? '';

if (empty($search)) {
    echo json_encode(['success' => true, 'students' => []]);
    exit;
}

try {
    $search_term = "%$search%";
    
    $query = "SELECT 
                id, 
                AdmNo as admission_no, 
                CONCAT(COALESCE(FirstName, ''), ' ', COALESCE(SecondName, ''), ' ', COALESCE(LastName, '')) as full_name,
                Gender,
                Class as class_name
              FROM tblstudents 
              WHERE school_id = :school_id 
                AND Status = 'Active'
                AND AdmNo LIKE :search
              ORDER BY AdmNo ASC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':school_id' => $school_id,
        ':search' => $search_term
    ]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'students' => $students]);
    
} catch (PDOException $e) {
    error_log("Search students by admission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>