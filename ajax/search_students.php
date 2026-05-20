<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Database connection
require_once '../includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get search query
$query = isset($_POST['query']) ? trim($_POST['query']) : '';

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search query too short']);
    exit();
}

// Prepare search term with wildcards
$search_term = "%{$query}%";

// Search students by name, admission number, or assessment number
$sql = "SELECT 
            s.id, 
            s.FirstName, 
            s.SecondName, 
            s.LastName,
            s.AdmNo,
            s.assessment_no,
            s.Gender,
            s.Status,
            c.class_level,
            c.stream as class_stream,
            c.academic_year
        FROM tblstudents s
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = ? 
        AND s.Status = 'Active'
        AND (
            s.FirstName LIKE ? 
            OR s.SecondName LIKE ? 
            OR s.LastName LIKE ? 
            OR s.AdmNo LIKE ? 
            OR s.assessment_no LIKE ?
            OR CONCAT(s.FirstName, ' ', s.LastName) LIKE ?
            OR CONCAT(s.FirstName, ' ', s.SecondName, ' ', s.LastName) LIKE ?
        )
        ORDER BY s.FirstName ASC
        LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "isssssss", 
    $school_id, 
    $search_term, 
    $search_term, 
    $search_term, 
    $search_term, 
    $search_term,
    $search_term,
    $search_term
);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    // Format full name
    $full_name = trim($row['FirstName'] . ' ' . ($row['SecondName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
    $full_name = preg_replace('/\s+/', ' ', $full_name);
    
    // Format class display
    $class_display = $row['class_level'];
    if (!empty($row['class_stream'])) {
        $class_display .= ' - ' . $row['class_stream'];
    }
    
    $students[] = [
        'id' => $row['id'],
        'firstname' => $row['FirstName'],
        'secondname' => $row['SecondName'] ?? '',
        'lastname' => $row['LastName'] ?? '',
        'fullname' => $full_name,
        'admission_no' => $row['AdmNo'] ?? 'N/A',
        'assessment_no' => $row['assessment_no'] ?? 'N/A',
        'gender' => $row['Gender'],
        'status' => $row['Status'],
        'class_name' => $class_display,
        'academic_year' => $row['academic_year']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'students' => $students,
    'count' => count($students)
]);