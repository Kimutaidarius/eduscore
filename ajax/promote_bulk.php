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
$from_class_id = isset($_POST['from_class_id']) ? intval($_POST['from_class_id']) : 0;
$to_class_level = isset($_POST['to_class_level']) ? trim($_POST['to_class_level']) : '';
$to_stream = isset($_POST['to_stream']) ? trim($_POST['to_stream']) : '';
$to_year = isset($_POST['to_year']) ? trim($_POST['to_year']) : date('Y');

if (!$from_class_id || empty($to_class_level)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Get or create target class
    $target_class_id = getOrCreateClass($conn, $school_id, $to_class_level, $to_stream, $to_year);
    
    if (!$target_class_id) {
        throw new Exception("Failed to create or find target class");
    }
    
    // Check if source and target are the same
    if ($target_class_id == $from_class_id) {
        throw new Exception("Source and target classes cannot be the same");
    }
    
    // Get all active students from source class
    $query = "SELECT id, FirstName, LastName, AdmNo FROM tblstudents WHERE class_id = ? AND school_id = ? AND Status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $from_class_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students_to_promote = [];
    while ($row = $result->fetch_assoc()) {
        $students_to_promote[] = $row;
    }
    $stmt->close();
    
    if (empty($students_to_promote)) {
        throw new Exception("No active students found in the selected class.");
    }
    
    $success_count = 0;
    $failed_students = [];
    
    foreach ($students_to_promote as $student) {
        // Update student
        $query = "UPDATE tblstudents SET class_id = ? WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $target_class_id, $student['id'], $school_id);
        
        if ($stmt->execute()) {
            $success_count++;
            
            // Record promotion
            $query = "INSERT INTO tblpromotion_history (school_id, student_id, from_class_id, to_class_id, academic_year, promoted_by) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiiisi", $school_id, $student['id'], $from_class_id, $target_class_id, $to_year, $teacher_id);
            $stmt->execute();
        } else {
            $failed_students[] = [
                'id' => $student['id'],
                'name' => trim($student['FirstName'] . ' ' . ($student['LastName'] ?? ''))
            ];
        }
    }
    
    $conn->commit();
    
    // Get class names for response
    $query = "SELECT 
                (SELECT class_level FROM tblclasses WHERE id = ?) as from_class,
                (SELECT stream FROM tblclasses WHERE id = ?) as from_stream,
                (SELECT class_level FROM tblclasses WHERE id = ?) as to_class,
                (SELECT stream FROM tblclasses WHERE id = ?) as to_stream
              FROM dual";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $from_class_id, $from_class_id, $target_class_id, $target_class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $class_info = $result->fetch_assoc();
    $stmt->close();
    
    $from_display = $class_info['from_class'] . (!empty($class_info['from_stream']) ? ' - ' . $class_info['from_stream'] : '');
    $to_display = $class_info['to_class'] . (!empty($class_info['to_stream']) ? ' - ' . $class_info['to_stream'] : '');
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully promoted $success_count out of " . count($students_to_promote) . " students.",
        'data' => [
            'total' => count($students_to_promote),
            'successful' => $success_count,
            'failed' => count($failed_students),
            'failed_students' => $failed_students,
            'from_class' => $from_display,
            'to_class' => $to_display,
            'academic_year' => $to_year
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

// Helper function (same as above)
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