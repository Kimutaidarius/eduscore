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

// Get POST data
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$target_class_level = isset($_POST['target_class']) ? trim($_POST['target_class']) : '';
$target_stream = isset($_POST['target_stream']) ? trim($_POST['target_stream']) : '';
$target_year = isset($_POST['target_year']) ? trim($_POST['target_year']) : date('Y');

if (!$student_id || empty($target_class_level)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Get current student class
    $query = "SELECT class_id FROM tblstudents WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $student_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception("Student not found");
    }
    
    $student = $result->fetch_assoc();
    $from_class_id = $student['class_id'];
    $stmt->close();
    
    // Get or create target class
    $target_class_id = getOrCreateClass($conn, $school_id, $target_class_level, $target_stream, $target_year);
    
    if (!$target_class_id) {
        throw new Exception("Failed to create or find target class");
    }
    
    // Check if target class is the same as current
    if ($target_class_id == $from_class_id) {
        throw new Exception("Student is already in this class");
    }
    
    // Update student
    $query = "UPDATE tblstudents SET class_id = ? WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $target_class_id, $student_id, $school_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update student: " . $conn->error);
    }
    
    // Record promotion
    $query = "INSERT INTO tblpromotion_history (school_id, student_id, from_class_id, to_class_id, academic_year, promoted_by) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiisi", $school_id, $student_id, $from_class_id, $target_class_id, $target_year, $teacher_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record promotion: " . $conn->error);
    }
    
    $conn->commit();
    
    // Get student details for response
    $query = "SELECT s.FirstName, s.LastName, s.AdmNo,
                     fc.class_level as from_class_name, fc.stream as from_stream,
                     tc.class_level as to_class_name, tc.stream as to_stream
              FROM tblstudents s
              LEFT JOIN tblclasses fc ON fc.id = ?
              LEFT JOIN tblclasses tc ON tc.id = ?
              WHERE s.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $from_class_id, $target_class_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_assoc();
    $stmt->close();
    
    $student_name = trim(($details['FirstName'] ?? '') . ' ' . ($details['LastName'] ?? ''));
    
    echo json_encode([
        'success' => true,
        'message' => 'Student promoted successfully!',
        'data' => [
            'student_id' => $student_id,
            'student_name' => $student_name,
            'admission_no' => $details['AdmNo'] ?? 'N/A',
            'from_class' => $details['from_class_name'] . (!empty($details['from_stream']) ? ' - ' . $details['from_stream'] : ''),
            'to_class' => $details['to_class_name'] . (!empty($details['to_stream']) ? ' - ' . $details['to_stream'] : ''),
            'academic_year' => $target_year
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

// Helper function (same as in promotion.php)
function getOrCreateClass($conn, $school_id, $class_level, $stream, $academic_year) {
    // Try to find existing class
    $query = "SELECT id FROM tblclasses WHERE school_id = ? AND class_level = ? AND academic_year = ?";
    $params = [$school_id, $class_level, $academic_year];
    $types = "iss";
    
    if (!empty($stream)) {
        $query .= " AND stream = ?";
        $params[] = $stream;
        $types .= "s";
    } else {
        $query .= " AND (stream IS NULL OR stream = '')";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['id'];
    }
    $stmt->close();
    
    // Create new class
    $query = "INSERT INTO tblclasses (school_id, class_level, stream, academic_year) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stream_value = empty($stream) ? null : $stream;
    $stmt->bind_param("issi", $school_id, $class_level, $stream_value, $academic_year);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $stmt->close();
        return $new_id;
    }
    
    $stmt->close();
    return null;
}