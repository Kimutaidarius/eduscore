<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

require_once '../includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID required']);
    exit();
}

// Get students in this class
$sql = "SELECT 
            s.id, 
            s.FirstName, 
            s.SecondName, 
            s.LastName,
            s.AdmNo,
            s.assessment_no,
            s.Gender,
            s.BoardingStatus,
            c.class_level,
            c.stream as class_stream
        FROM tblstudents s
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = ? 
        AND s.class_id = ?
        AND s.Status = 'Active'
        ORDER BY s.FirstName ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $school_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $full_name = trim($row['FirstName'] . ' ' . ($row['SecondName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
    $full_name = preg_replace('/\s+/', ' ', $full_name);
    
    $students[] = [
        'id' => $row['id'],
        'fullname' => $full_name,
        'admission_no' => $row['AdmNo'] ?? 'N/A',
        'assessment_no' => $row['assessment_no'] ?? 'N/A',
        'gender' => $row['Gender'],
        'boarding_status' => $row['BoardingStatus']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'students' => $students,
    'count' => count($students)
]);