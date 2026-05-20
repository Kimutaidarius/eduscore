<?php
// fetch_students_classes.php
header('Content-Type: application/json');
session_start();
include('includes/config.php'); // Your DB connection

// Make sure school_id is set
$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID missing']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$level = $input['level'] ?? null;

if (!$level) {
    echo json_encode(['success' => false, 'message' => 'Academic level not provided']);
    exit;
}

// Fetch classes for this school and level
$stmt = $conn->prepare("SELECT id, class_level AS name FROM tblclasses WHERE school_id = ? AND academic_level = ?");
$stmt->bind_param("is", $school_id, $level);
$stmt->execute();
$result = $stmt->get_result();
$classes = [];
$classIds = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
    $classIds[] = $row['id'];
}
$stmt->close();

// Fetch streams for these classes
$streams = [];
if (!empty($classIds)) {
    $in = implode(',', array_map('intval', $classIds));
    $streamQuery = "SELECT id, stream_name, class_id FROM tblstreams WHERE school_id = ? AND class_id IN ($in)";
    $stmt = $conn->prepare($streamQuery);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $streamResult = $stmt->get_result();
    while ($row = $streamResult->fetch_assoc()) {
        $streams[$row['class_id']][] = $row;
    }
    $stmt->close();
}

// Fetch students for these classes
$students = [];
if (!empty($classIds)) {
    $in = implode(',', array_map('intval', $classIds));
    $studentQuery = "SELECT s.AdmNo AS admission_no, s.FirstName, s.SecondName AS last_name, s.Class AS class_name, st.stream_name
                     FROM tblstudents s
                     LEFT JOIN tblstreams st ON s.StreamId = st.id
                     WHERE s.school_id = ? AND s.class_id IN ($in)";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    while ($row = $studentResult->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'classes' => $classes,
    'students' => $students
]);
