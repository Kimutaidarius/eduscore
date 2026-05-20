<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once('../../includes/config.php');

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $input['school_id'] ?? $_SESSION['school_id'];
$class_id = $input['class_id'] ?? null;
$stream_id = $input['stream_id'] ?? null;
$year = $input['year'] ?? date('Y');
$gender = $input['gender'] ?? '';
$search = $input['search'] ?? '';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    $query = "SELECT 
                s.id,
                s.AdmNo as admission_no,
                CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) as full_name,
                s.Gender as gender,
                s.GuardianName as parent_name,
                s.GuardianPhone as phone,
                s.date_of_birth as dob,
                c.class_level as class_name,
                st.stream_name
              FROM tblstudents s
              LEFT JOIN tblclasses c ON s.class_id = c.id
              LEFT JOIN tblstreams st ON s.StreamId = st.id
              WHERE s.school_id = :school_id 
              AND s.Status = 'Active'";
    
    $params = [':school_id' => $school_id];
    
    if ($class_id) {
        $query .= " AND s.class_id = :class_id";
        $params[':class_id'] = $class_id;
    }
    
    if ($stream_id) {
        $query .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }
    
    if ($gender) {
        $query .= " AND s.Gender = :gender";
        $params[':gender'] = $gender;
    }
    
    if ($search) {
        $query .= " AND (s.AdmNo LIKE :search OR CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY s.AdmNo";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total' => count($students)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_class_list: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch class list: ' . $e->getMessage()
    ]);
}
?>