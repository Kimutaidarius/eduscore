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

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit();
}

// Get student details with class information
$sql = "SELECT 
            s.id,
            s.FirstName,
            s.SecondName,
            s.LastName,
            s.AdmNo,
            s.assessment_no,
            s.Gender,
            s.date_of_birth,
            s.GuardianName,
            s.GuardianRelationship,
            s.GuardianPhone,
            s.guardian_email,
            s.address,
            s.BoardingStatus,
            s.Status,
            s.academic_year,
            c.id as class_id,
            c.class_level,
            c.stream,
            c.academic_year as class_academic_year
        FROM tblstudents s
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE s.id = ? AND s.school_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $school_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $full_name = trim($row['FirstName'] . ' ' . ($row['SecondName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
    $full_name = preg_replace('/\s+/', ' ', $full_name);
    
    $class_display = $row['class_level'] ?? 'Not Assigned';
    if (!empty($row['stream'])) {
        $class_display .= ' - ' . $row['stream'];
    }
    
    $student = [
        'id' => $row['id'],
        'firstname' => $row['FirstName'],
        'secondname' => $row['SecondName'] ?? '',
        'lastname' => $row['LastName'] ?? '',
        'fullname' => $full_name,
        'admission_no' => $row['AdmNo'] ?? 'N/A',
        'assessment_no' => $row['assessment_no'] ?? 'N/A',
        'gender' => $row['Gender'],
        'date_of_birth' => $row['date_of_birth'],
        'guardian_name' => $row['GuardianName'] ?? 'Not Provided',
        'guardian_relationship' => $row['GuardianRelationship'] ?? '',
        'guardian_phone' => $row['GuardianPhone'] ?? '',
        'guardian_email' => $row['guardian_email'] ?? '',
        'address' => $row['address'] ?? '',
        'boarding_status' => $row['BoardingStatus'],
        'status' => $row['Status'],
        'academic_year' => $row['academic_year'],
        'class_id' => $row['class_id'],
        'class_name' => $class_display,
        'class_level' => $row['class_level'],
        'stream' => $row['stream'] ?? ''
    ];
    
    echo json_encode(['success' => true, 'student' => $student]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
}

$stmt->close();
$conn->close();