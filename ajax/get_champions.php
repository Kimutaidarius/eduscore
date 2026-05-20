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
$limit = intval($_POST['limit'] ?? 10);

if (!$class_id || !$year || !$term_id || !$exam_id || !$subject_id) {
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
$classQuery->bind_param("ii", $class_id, $school_id);
$classQuery->execute();
$classResult = $classQuery->get_result();
$classRow = $classResult->fetch_assoc();

// Get top performers for this subject
$scoresQuery = $conn->prepare("
    SELECT 
        s.student_id,
        st.AdmNo as admission_no,
        CONCAT(st.FirstName, ' ', COALESCE(st.SecondName, ''), ' ', st.LastName) as student_name,
        st.StreamId,
        str.stream_name,
        s.score_value,
        s.percentage,
        s.grade,
        s.rubric,
        (SELECT COUNT(*) + 1 FROM tblscores s2 
         WHERE s2.subject_id = s.subject_id AND s2.exam_id = s.exam_id 
         AND s2.score_value > s.score_value) as stream_position,
        (SELECT COUNT(*) + 1 FROM tblscores s3 
         JOIN tblstudents st3 ON s3.student_id = st3.id
         WHERE s3.subject_id = s.subject_id AND s3.exam_id = s.exam_id 
         AND st3.StreamId = st.StreamId AND s3.score_value > s.score_value) as class_position
    FROM tblscores s
    JOIN tblstudents st ON s.student_id = st.id
    LEFT JOIN tblstreams str ON st.StreamId = str.id
    WHERE s.subject_id = ? AND s.exam_id = ? AND s.school_id = ?
    ORDER BY s.score_value DESC
    LIMIT ?
");
$scoresQuery->bind_param("iiii", $subject_id, $exam_id, $school_id, $limit);
$scoresQuery->execute();
$scoresResult = $scoresQuery->get_result();

$data = [];
while ($row = $scoresResult->fetch_assoc()) {
    $data[] = [
        'admission_no' => $row['admission_no'],
        'student_name' => $row['student_name'],
        'class_name' => $classRow['class_level'] ?? '',
        'stream_name' => $row['stream_name'] ?? '',
        'score' => floatval($row['score_value']),
        'grade' => $row['grade'],
        'rubric' => intval($row['rubric']),
        'stream_position' => $row['stream_position'] ?? '-',
        'class_position' => $row['class_position'] ?? '-'
    ];
}

$conn->close();

echo json_encode(['success' => true, 'data' => $data]);
?>