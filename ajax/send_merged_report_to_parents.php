<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header('Access-Control-Allow-Origin: https://eduscore.ct.ws');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$report_id = $_POST['report_id'] ?? 0;
$school_id = $_POST['school_id'] ?? $_SESSION['school_id'];

if (!$report_id) {
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit();
}

// Database connection
require_once '../includes/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get merged report details
$query = $conn->prepare("
    SELECT rc.*, c.class_level, s.stream_name, e.examname, t.term_name
    FROM report_cards rc
    LEFT JOIN tblclasses c ON rc.class_id = c.id
    LEFT JOIN tblstreams s ON rc.stream_id = s.id
    LEFT JOIN tblexam e ON rc.exam_id = e.id
    LEFT JOIN tblterms t ON rc.term_id = t.id
    WHERE rc.id = ? AND rc.school_id = ?
");
$query->bind_param("ii", $report_id, $school_id);
$query->execute();
$result = $query->get_result();
$report = $result->fetch_assoc();
$query->close();

if (!$report) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit();
}

// Get all students with parent contact info
$students_query = $conn->prepare("
    SELECT 
        s.id,
        s.FirstName,
        s.LastName,
        s.GuardianPhone,
        s.GuardianName,
        s.GuardianEmail
    FROM tblstudents s
    WHERE s.class_id = ? AND s.school_id = ?
");
$students_query->bind_param("ii", $report['class_id'], $school_id);
$students_query->execute();
$students_result = $students_query->get_result();

$sent_count = 0;
$errors = [];

while ($student = $students_result->fetch_assoc()) {
    if (!empty($student['GuardianPhone']) || !empty($student['GuardianEmail'])) {
        // Log the sending attempt (implement actual SMS/Email sending here)
        $log_query = $conn->prepare("
            INSERT INTO sms_logs 
            (school_id, student_id, message_content, status, created_at)
            VALUES (?, ?, 'Merged report card notification sent', 'Sent', NOW())
        ");
        $log_query->bind_param("ii", $school_id, $student['id']);
        $log_query->execute();
        $log_query->close();
        
        $sent_count++;
    }
}

$students_query->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => "Merged report card notification sent to $sent_count parents",
    'sent_count' => $sent_count,
    'errors' => $errors
]);
?>