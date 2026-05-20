<?php
session_start();
header('Content-Type: application/json');

require_once '../includes/config.php';

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
$stream_id = isset($_POST['stream_id']) ? (int)$_POST['stream_id'] : 0;
$exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
$academic_level = $_POST['academic_level'] ?? $_SESSION['academic_level'] ?? 'primary';

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid class ID']);
    exit();
}

try {
    $connection = isset($db) ? $db : $dbh;
    
    $query = "
        SELECT s.id, s.FirstName, s.SecondName, s.LastName, s.AdmNo as admission_no,
               CONCAT(
                   COALESCE(s.FirstName, ''),
                   ' ',
                   COALESCE(s.SecondName, ''),
                   ' ',
                   COALESCE(s.LastName, '')
               ) as full_name
        FROM tblstudents s
        INNER JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = :school_id 
        AND s.class_id = :class_id
        AND s.Status = 'Active'
        AND c.academic_level = :academic_level
    ";
    
    $params = [
        ':school_id' => $school_id,
        ':class_id' => $class_id,
        ':academic_level' => $academic_level
    ];
    
    if ($stream_id > 0) {
        $query .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }
    
    $query .= " ORDER BY s.FirstName, s.SecondName";
    
    $stmt = $connection->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total' => count($students)
    ]);
    
} catch (PDOException $e) {
    error_log("Get student list error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>