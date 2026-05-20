<?php
// /feesystem/api/feesystem/get_student_by_admission.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../includes/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? $_SESSION['school_id'];
$admission_no = $data['admission_no'] ?? '';

if (empty($admission_no)) {
    echo json_encode(['success' => false, 'message' => 'Admission number is required']);
    exit;
}

try {
    $sql = "SELECT 
                s.id,
                s.AdmNo as admission_no,
                s.FirstName as first_name,
                s.SecondName as middle_name,
                s.LastName as last_name,
                s.Gender as gender,
                s.admission_date,
                s.Class as class_name,
                s.class_id,
                s.StreamId as stream_id,
                (SELECT SUM(ft.amount) FROM fee_transactions ft WHERE ft.student_id = s.id AND ft.transaction_type = 'debit') as required_fees
            FROM tblstudents s
            WHERE s.school_id = :school_id 
                AND s.AdmNo = :admission_no
                AND s.Status = 'Active'
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':school_id' => $school_id,
        ':admission_no' => $admission_no
    ]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        // Get class name from tblclasses if not set
        if (empty($student['class_name']) && !empty($student['class_id'])) {
            $class_stmt = $db->prepare("SELECT class_level FROM tblclasses WHERE id = :class_id");
            $class_stmt->execute([':class_id' => $student['class_id']]);
            $class = $class_stmt->fetch(PDO::FETCH_ASSOC);
            $student['class_name'] = $class['class_level'] ?? '';
        }
        $student['required_fees'] = $student['required_fees'] ?? 0;
    }
    
    echo json_encode(['success' => true, 'student' => $student]);
    
} catch (PDOException $e) {
    error_log("Get student by admission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>