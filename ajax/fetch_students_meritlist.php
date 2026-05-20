<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../includes/config.php';

$school_id = $_SESSION['school_id'];
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
$academic_level = isset($_POST['academic_level']) ? $_POST['academic_level'] : 'primary';

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Fetch students for the selected class
$studentQuery = $conn->prepare("
    SELECT 
        s.id,
        s.AdmNo as admission_no,
        CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', COALESCE(s.LastName, '')) as full_name,
        s.StreamId as stream_id,
        s.Status as status,
        st.stream_name,
        s.Gender
    FROM tblstudents s
    LEFT JOIN tblstreams st ON s.StreamId = st.id
    WHERE s.class_id = ? 
    AND s.school_id = ?
    AND s.Status = 'Active'
    ORDER BY s.AdmNo
");

$studentQuery->bind_param("ii", $class_id, $school_id);
$studentQuery->execute();
$studentResult = $studentQuery->get_result();

$students = [];
while ($row = $studentResult->fetch_assoc()) {
    $students[] = $row;
}

$studentQuery->close();
$conn->close();

echo json_encode([
    'success' => true,
    'data' => $students
]);
?>