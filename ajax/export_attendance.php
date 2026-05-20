<?php
session_start();

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    die('Not authenticated');
}

require_once '../includes/config.php';

$school_id = $_SESSION['school_id'];
$class_id = $_GET['class_id'] ?? 0;
$stream_id = $_GET['stream_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');
$term_id = $_GET['term_id'] ?? null;

// Convert empty strings to null
if ($stream_id === '' || $stream_id === '0' || $stream_id === 'null') $stream_id = null;
if ($term_id === '' || $term_id === '0' || $term_id === 'null') $term_id = null;

if (!$class_id) {
    die('Class ID required');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database connection failed');
}

// Build the query
$query = "
    SELECT 
        s.AdmNo as 'Admission No.',
        CONCAT(s.FirstName, ' ', s.LastName) as 'Student Name',
        s.Gender as 'Gender',
        c.class_level as 'Class',
        COALESCE(c.stream, 'No Stream') as 'Stream',
        COALESCE(a.status, 'Not Marked') as 'Status',
        COALESCE(a.remarks, '') as 'Remarks'
    FROM tblstudents s
    LEFT JOIN tblclasses c ON s.class_id = c.id
    LEFT JOIN tblattendance a ON s.id = a.student_id 
        AND a.attendance_date = ? 
        AND (a.term_id = ? OR (a.term_id IS NULL AND ? IS NULL))
    WHERE s.school_id = ? 
        AND s.Status = 'Active'
        AND s.class_id = ?";

$params = [$date, $term_id, $term_id, $school_id, $class_id];
$types = "sssii";

// Add stream condition if provided
if ($stream_id) {
    $query .= " AND c.id = ?";
    $params[] = $stream_id;
    $types .= "i";
}

$query .= " ORDER BY s.FirstName, s.LastName";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Query preparation failed');
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
$filename = "attendance_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Add headers
fputcsv($output, ['Admission No.', 'Student Name', 'Gender', 'Class', 'Stream', 'Status', 'Remarks']);

// Add data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
$stmt->close();
$conn->close();
?>