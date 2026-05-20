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

$current_class_id = isset($_POST['current_class_id']) ? intval($_POST['current_class_id']) : 0;

// Get available target classes (excluding the current one)
$sql = "SELECT 
            c.id,
            c.class_level,
            c.stream,
            c.academic_year,
            (SELECT COUNT(*) FROM tblstudents WHERE class_id = c.id AND Status = 'Active') as student_count
        FROM tblclasses c
        WHERE c.school_id = ? 
        AND c.id != ?
        ORDER BY c.academic_level, c.class_level, c.stream";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $school_id, $current_class_id);
$stmt->execute();
$result = $stmt->get_result();

$classes = [];
while ($row = $result->fetch_assoc()) {
    $display_name = $row['class_level'];
    if (!empty($row['stream'])) {
        $display_name .= ' - ' . $row['stream'];
    }
    
    $classes[] = [
        'id' => $row['id'],
        'class_level' => $row['class_level'],
        'stream' => $row['stream'] ?? '',
        'academic_year' => $row['academic_year'],
        'display_name' => $display_name,
        'student_count' => $row['student_count']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'classes' => $classes,
    'count' => count($classes)
]);