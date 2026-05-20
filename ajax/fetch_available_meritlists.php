<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['school_id']) || !isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$school_id = $_SESSION['school_id'];
$data = $_POST;

// Validate required fields
if (!isset($data['class_id'])) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

$class_id = intval($data['class_id']);
$academic_level = isset($data['academic_level']) ? $data['academic_level'] : 'primary';

// Include config
require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Fetch available merit lists for this class
$query = "
    SELECT 
        m.exam_id,
        m.term_id,
        m.academic_year,
        COUNT(m.student_id) as student_count,
        MAX(m.created_at) as created_at,
        e.examname as exam_name,
        t.term_name
    FROM tblmeritlist m
    LEFT JOIN tblexam e ON m.exam_id = e.id
    LEFT JOIN tblterms t ON m.term_id = t.id
    WHERE m.school_id = ? 
    AND m.class_id = ?
    AND m.academic_level = ?
    GROUP BY m.exam_id, m.term_id, m.academic_year, e.examname, t.term_name
    ORDER BY m.academic_year DESC, m.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iis", $school_id, $class_id, $academic_level);
$stmt->execute();
$result = $stmt->get_result();

$merit_lists = [];
while ($row = $result->fetch_assoc()) {
    $merit_lists[] = [
        'exam_id' => $row['exam_id'],
        'term_id' => $row['term_id'],
        'academic_year' => $row['academic_year'],
        'student_count' => $row['student_count'],
        'created_at' => $row['created_at'],
        'exam_name' => $row['exam_name'],
        'term_name' => $row['term_name']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'data' => $merit_lists
]);
exit();
?>