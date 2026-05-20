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

$page = isset($_POST['page']) ? intval($_POST['page']) : 1;
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "ph.school_id = ?";
$params = [$school_id];
$types = "i";

if ($student_id > 0) {
    $where .= " AND ph.student_id = ?";
    $params[] = $student_id;
    $types .= "i";
}

if ($class_id > 0) {
    $where .= " AND (ph.from_class_id = ? OR ph.to_class_id = ?)";
    $params[] = $class_id;
    $params[] = $class_id;
    $types .= "ii";
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM tblpromotion_history ph WHERE $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Get promotion history with details
$sql = "SELECT 
            ph.*, 
            s.FirstName, 
            s.SecondName,
            s.LastName, 
            s.AdmNo,
            fc.class_level as from_class, 
            fc.stream as from_stream,
            tc.class_level as to_class, 
            tc.stream as to_stream,
            t.firstname as promoted_by_name,
            t.lastname as promoted_by_lastname
        FROM tblpromotion_history ph
        LEFT JOIN tblstudents s ON ph.student_id = s.id
        LEFT JOIN tblclasses fc ON ph.from_class_id = fc.id
        LEFT JOIN tblclasses tc ON ph.to_class_id = tc.id
        LEFT JOIN tblteachers t ON ph.promoted_by = t.id
        WHERE $where
        ORDER BY ph.promoted_at DESC
        LIMIT ? OFFSET ?";

// Add limit parameters
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $student_name = trim(($row['FirstName'] ?? '') . ' ' . ($row['SecondName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
    $student_name = preg_replace('/\s+/', ' ', $student_name);
    
    $history[] = [
        'id' => $row['id'],
        'student_id' => $row['student_id'],
        'student_name' => $student_name ?: 'Unknown',
        'admission_no' => $row['AdmNo'] ?? 'N/A',
        'from_class' => $row['from_class'] ?? 'N/A',
        'from_stream' => $row['from_stream'] ?? '',
        'to_class' => $row['to_class'] ?? 'N/A',
        'to_stream' => $row['to_stream'] ?? '',
        'academic_year' => $row['academic_year'],
        'promoted_by' => trim(($row['promoted_by_name'] ?? '') . ' ' . ($row['promoted_by_lastname'] ?? '')) ?: 'System',
        'promoted_at' => $row['promoted_at'],
        'formatted_date' => date('d/m/Y H:i', strtotime($row['promoted_at']))
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'history' => $history,
    'total' => $total_records,
    'page' => $page,
    'pages' => ceil($total_records / $limit)
]);