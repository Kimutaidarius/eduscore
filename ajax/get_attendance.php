<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once '../includes/config.php';

$school_id = $_SESSION['school_id'];
$class_id = $_POST['class_id'] ?? 0;
$stream_id = $_POST['stream_id'] ?? null;
$date = $_POST['date'] ?? date('Y-m-d');
$term_id = $_POST['term_id'] ?? null;

// Convert empty strings to null
if ($stream_id === '' || $stream_id === '0' || $stream_id === 'null') $stream_id = null;
if ($term_id === '' || $term_id === '0' || $term_id === 'null') $term_id = null;

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID required']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// First, check if tblattendance table exists, if not create it
$checkTableQuery = "SHOW TABLES LIKE 'tblattendance'";
$checkResult = $conn->query($checkTableQuery);
if ($checkResult->num_rows == 0) {
    // Create attendance table if it doesn't exist
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS `tblattendance` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `school_id` int(11) NOT NULL,
            `student_id` int(11) NOT NULL,
            `attendance_date` date NOT NULL,
            `status` enum('Present','Absent','Late','Excused','Sick') NOT NULL DEFAULT 'Present',
            `remarks` text DEFAULT NULL,
            `term_id` int(11) DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `updated_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `school_id` (`school_id`),
            KEY `student_id` (`student_id`),
            KEY `term_id` (`term_id`),
            KEY `attendance_date` (`attendance_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($createTableQuery);
}

// Build the query
$query = "
    SELECT 
        s.id,
        s.AdmNo as admission_no,
        CONCAT(s.FirstName, ' ', s.LastName) as fullname,
        s.Gender as gender,
        c.class_level as class_name,
        c.stream as stream_name,
        COALESCE(a.status, 'Present') as attendance_status,
        a.remarks
    FROM tblstudents s
    LEFT JOIN tblclasses c ON s.class_id = c.id
    LEFT JOIN tblattendance a ON s.id = a.student_id 
        AND a.attendance_date = ? 
        AND (a.term_id = ? OR (a.term_id IS NULL AND ? IS NULL))
    WHERE s.school_id = ? 
        AND s.Status = 'Active'
        AND s.class_id = ?";

$params = [$date, $term_id, $term_id, $school_id, $class_id];
$types = "sssii"; // string, string, string, integer, integer

// Add stream condition if provided
if ($stream_id) {
    $query .= " AND c.id = ?";
    $params[] = $stream_id;
    $types .= "i"; // integer
}

$query .= " ORDER BY s.FirstName, s.LastName";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
    exit();
}

// Bind parameters dynamically
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Query execution failed: ' . $stmt->error]);
    exit();
}

$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode(['success' => true, 'data' => $students]);

$stmt->close();
$conn->close();
?>