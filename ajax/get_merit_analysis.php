<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = $_POST['class_id'] ?? 0;
$year = $_POST['year'] ?? '';
$term_id = $_POST['term_id'] ?? 0;
$exam_id = $_POST['exam_id'] ?? 0;
$subject_id = $_POST['subject_id'] ?? 0;

if (!$class_id || !$year || !$term_id || !$exam_id || !$subject_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get subject name
$subjectQuery = $conn->prepare("SELECT subject_name FROM tblsubjects WHERE id = ? AND school_id = ?");
$subjectQuery->bind_param("ii", $subject_id, $school_id);
$subjectQuery->execute();
$subjectResult = $subjectQuery->get_result();
$subject = $subjectResult->fetch_assoc();

// Get scores for this subject
$scoresQuery = $conn->prepare("
    SELECT 
        s.student_id,
        st.AdmNo as admission_no,
        CONCAT(st.FirstName, ' ', COALESCE(st.SecondName, ''), ' ', st.LastName) as student_name,
        s.score_value,
        s.total_score,
        s.percentage,
        s.grade,
        s.rubric
    FROM tblscores s
    JOIN tblstudents st ON s.student_id = st.id
    WHERE s.subject_id = ? AND s.exam_id = ? AND s.school_id = ?
    ORDER BY s.score_value DESC
");
$scoresQuery->bind_param("iii", $subject_id, $exam_id, $school_id);
$scoresQuery->execute();
$scoresResult = $scoresQuery->get_result();

$data = [];
while ($row = $scoresResult->fetch_assoc()) {
    $data[] = [
        'admission_no' => $row['admission_no'],
        'student_name' => $row['student_name'],
        'score' => floatval($row['score_value']),
        'grade' => $row['grade'],
        'rubric' => intval($row['rubric'])
    ];
}

$conn->close();

echo json_encode(['success' => true, 'data' => $data, 'subject' => $subject['subject_name'] ?? '']);
?>