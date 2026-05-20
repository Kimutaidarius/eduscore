<?php
header('Content-Type: application/json');
session_start();

require_once('../../includes/config.php');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get the request data (supports both POST and GET)
$data = json_decode(file_get_contents('php://input'), true);
if ($data) {
    $class_id = $data['class_id'] ?? '';
    $search = $data['search'] ?? '';
    $school_id = $data['school_id'] ?? $_SESSION['school_id'];
} else {
    $class_id = $_GET['class_id'] ?? '';
    $search = $_GET['search'] ?? '';
    $school_id = $_SESSION['school_id'];
}

try {
    // Use $db instead of $pdo (as defined in config.php)
    $sql = "SELECT s.id, CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as full_name, 
                   s.AdmNo as admission_no, c.class_level as class_name, s.GuardianPhone as phone
            FROM tblstudents s
            LEFT JOIN tblclasses c ON s.class_id = c.id
            WHERE s.school_id = ? AND s.Status = 'active'";
    $params = [$school_id];
    
    if (!empty($class_id)) {
        $sql .= " AND s.class_id = ?";
        $params[] = $class_id;
    }
    if (!empty($search)) {
        $sql .= " AND (s.FirstName LIKE ? OR s.SecondName LIKE ? OR s.LastName LIKE ? OR s.AdmNo LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $sql .= " ORDER BY s.FirstName ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'students' => $students]);
} catch(Exception $e) {
    error_log("Error in get_students.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>