<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'];
$first_class_id = $_POST['first_class_id'] ?? 0;
$first_year = $_POST['first_year'] ?? '';
$first_term_id = $_POST['first_term_id'] ?? 0;
$first_exam_id = $_POST['first_exam_id'] ?? 0;
$current_class_id = $_POST['current_class_id'] ?? 0;
$current_year = $_POST['current_year'] ?? '';
$current_term_id = $_POST['current_term_id'] ?? 0;
$current_exam_id = $_POST['current_exam_id'] ?? 0;

if (!$first_class_id || !$first_year || !$first_term_id || !$first_exam_id || 
    !$current_class_id || !$current_year || !$current_term_id || !$current_exam_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get class name
$classQuery = $conn->prepare("SELECT class_level FROM tblclasses WHERE id = ? AND school_id = ?");
$classQuery->bind_param("ii", $current_class_id, $school_id);
$classQuery->execute();
$classResult = $classQuery->get_result();
$classRow = $classResult->fetch_assoc();

// Get students who have scores in both exams
$studentsQuery = $conn->prepare("
    SELECT DISTINCT s1.student_id,
           st.AdmNo as admission_no,
           CONCAT(st.FirstName, ' ', COALESCE(st.SecondName, ''), ' ', st.LastName) as student_name,
           st.StreamId,
           str.stream_name,
           AVG(s1.score_value) as first_score,
           AVG(s2.score_value) as current_score
    FROM tblscores s1
    JOIN tblscores s2 ON s1.student_id = s2.student_id
    JOIN tblstudents st ON s1.student_id = st.id
    LEFT JOIN tblstreams str ON st.StreamId = str.id
    WHERE s1.exam_id = ? AND s2.exam_id = ? 
          AND s1.school_id = ? AND s2.school_id = ?
    GROUP BY s1.student_id
    ORDER BY (AVG(s2.score_value) - AVG(s1.score_value)) DESC
");
$studentsQuery->bind_param("iiii", $first_exam_id, $current_exam_id, $school_id, $school_id);
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();

$data = [];
while ($row = $studentsResult->fetch_assoc()) {
    $first_score = floatval($row['first_score']);
    $current_score = floatval($row['current_score']);
    $improvement = $current_score - $first_score;
    $percentage_change = $first_score > 0 ? ($improvement / $first_score) * 100 : 0;
    
    $data[] = [
        'student_name' => $row['student_name'],
        'admission_no' => $row['admission_no'],
        'class_name' => $classRow['class_level'] ?? '',
        'stream_name' => $row['stream_name'] ?? '',
        'first_score' => $first_score,
        'current_score' => $current_score,
        'improvement' => $improvement,
        'percentage_change' => $percentage_change
    ];
}

$conn->close();

echo json_encode(['success' => true, 'data' => $data]);
?>