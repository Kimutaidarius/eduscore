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

$class_level = isset($_POST['class_level']) ? trim($_POST['class_level']) : '';
$stream = isset($_POST['stream']) ? trim($_POST['stream']) : '';
$academic_year = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : date('Y');

if (empty($class_level)) {
    echo json_encode(['success' => false, 'message' => 'Class level required']);
    exit();
}

// Check if class exists
$sql = "SELECT id, class_level, stream, academic_year,
        (SELECT COUNT(*) FROM tblstudents WHERE class_id = id AND Status = 'Active') as student_count
        FROM tblclasses 
        WHERE school_id = ? AND class_level = ? AND academic_year = ?";
$params = [$school_id, $class_level, $academic_year];
$types = "iss";

if (!empty($stream)) {
    $sql .= " AND stream = ?";
    $params[] = $stream;
    $types .= "s";
} else {
    $sql .= " AND (stream IS NULL OR stream = '')";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $display_name = $row['class_level'];
    if (!empty($row['stream'])) {
        $display_name .= ' - ' . $row['stream'];
    }
    
    echo json_encode([
        'success' => true,
        'exists' => true,
        'class' => [
            'id' => $row['id'],
            'class_level' => $row['class_level'],
            'stream' => $row['stream'] ?? '',
            'academic_year' => $row['academic_year'],
            'display_name' => $display_name,
            'student_count' => $row['student_count']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'exists' => false,
        'message' => 'Class does not exist. It will be created automatically.'
    ]);
}

$stmt->close();
$conn->close();