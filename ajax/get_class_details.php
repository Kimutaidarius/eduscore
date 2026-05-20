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

// Get class details with student count
$sql = "SELECT 
            c.id,
            c.class_level,
            c.stream,
            c.academic_year,
            c.academic_level,
            (SELECT COUNT(*) FROM tblstudents WHERE class_id = c.id AND Status = 'Active') as student_count,
            (SELECT COUNT(*) FROM tblstudents WHERE class_id = c.id AND Status = 'Active' AND Gender = 'Male') as male_count,
            (SELECT COUNT(*) FROM tblstudents WHERE class_id = c.id AND Status = 'Active' AND Gender = 'Female') as female_count,
            t.firstname as teacher_firstname,
            t.lastname as teacher_lastname
        FROM tblclasses c
        LEFT JOIN tblteachers t ON c.teacher_id = t.id
        WHERE c.id = ? AND c.school_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $class_id, $school_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $class_details = [
        'id' => $row['id'],
        'class_level' => $row['class_level'],
        'stream' => $row['stream'] ?? '',
        'academic_year' => $row['academic_year'],
        'academic_level' => $row['academic_level'],
        'student_count' => $row['student_count'],
        'male_count' => $row['male_count'],
        'female_count' => $row['female_count'],
        'teacher_name' => ($row['teacher_firstname'] ?? '') ? trim($row['teacher_firstname'] . ' ' . ($row['teacher_lastname'] ?? '')) : 'Not Assigned'
    ];
    
    echo json_encode(['success' => true, 'class' => $class_details]);
} else {
    echo json_encode(['success' => false, 'message' => 'Class not found']);
}

$stmt->close();
$conn->close();