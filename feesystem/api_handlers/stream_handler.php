<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session with proper settings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../includes/config.php');

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    if (!isset($_SESSION['teacher_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Please log in again']);
        exit;
    }
}

$school_id = $_SESSION['school_id'] ?? $_SESSION['schoolId'] ?? null;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID not found in session']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch($action) {
        case 'get_all_streams':
            getAllStreams($db, $school_id);
            break;
        case 'get_streams':
            getStreams($db, $school_id);
            break;
        case 'get_stream':
            getStream($db, $school_id);
            break;
        case 'add_stream':
            addStream($db, $school_id);
            break;
        case 'edit_stream':
            editStream($db, $school_id);
            break;
        case 'delete_stream':
            deleteStream($db, $school_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getAllStreams($db, $school_id) {
    try {
        $stmt = $db->prepare("
            SELECT s.*, c.class_level,
                   (SELECT COUNT(*) FROM tblstudents WHERE StreamId = s.id AND Status = 'Active') as student_count
            FROM tblstreams s
            JOIN tblclasses c ON s.class_id = c.id
            WHERE c.school_id = ?
            ORDER BY c.class_level, s.stream_name
        ");
        $stmt->execute([$school_id]);
        $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $streams]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getStreams($db, $school_id) {
    $class_id = $_GET['class_id'] ?? 0;
    $stmt = $db->prepare("
        SELECT s.* FROM tblstreams s
        JOIN tblclasses c ON s.class_id = c.id
        WHERE c.school_id = ? AND s.class_id = ?
        ORDER BY s.stream_name
    ");
    $stmt->execute([$school_id, $class_id]);
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $streams]);
}

function getStream($db, $school_id) {
    $stream_id = $_GET['stream_id'] ?? 0;
    $stmt = $db->prepare("
        SELECT s.*, c.class_level FROM tblstreams s
        JOIN tblclasses c ON s.class_id = c.id
        WHERE s.id = ? AND c.school_id = ?
    ");
    $stmt->execute([$stream_id, $school_id]);
    $stream = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stream) {
        echo json_encode(['success' => true, 'data' => $stream]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Stream not found']);
    }
}

function addStream($db, $school_id) {
    $class_id = $_POST['class_id'] ?? 0;
    $stream_name = trim($_POST['stream_name'] ?? '');
    
    if (empty($stream_name) || empty($class_id)) {
        echo json_encode(['success' => false, 'message' => 'Class and stream name are required']);
        return;
    }
    
    // Verify class belongs to school
    $stmt = $db->prepare("SELECT id FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$class_id, $school_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid class']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO tblstreams (school_id, class_id, stream_name) VALUES (?, ?, ?)");
    $stmt->execute([$school_id, $class_id, $stream_name]);
    
    echo json_encode(['success' => true, 'message' => 'Stream added successfully', 'id' => $db->lastInsertId()]);
}

function editStream($db, $school_id) {
    $stream_id = $_POST['stream_id'] ?? 0;
    $class_id = $_POST['class_id'] ?? 0;
    $stream_name = trim($_POST['stream_name'] ?? '');
    
    if (empty($stream_name) || empty($class_id)) {
        echo json_encode(['success' => false, 'message' => 'Class and stream name are required']);
        return;
    }
    
    $stmt = $db->prepare("
        UPDATE tblstreams s
        JOIN tblclasses c ON s.class_id = c.id
        SET s.stream_name = ?, s.class_id = ?
        WHERE s.id = ? AND c.school_id = ?
    ");
    $stmt->execute([$stream_name, $class_id, $stream_id, $school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Stream updated successfully']);
}

function deleteStream($db, $school_id) {
    $stream_id = $_POST['stream_id'] ?? 0;
    
    // Check if stream has students
    $stmt = $db->prepare("SELECT COUNT(*) FROM tblstudents WHERE StreamId = ? AND school_id = ? AND Status = 'Active'");
    $stmt->execute([$stream_id, $school_id]);
    $studentCount = $stmt->fetchColumn();
    
    if ($studentCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete stream with ' . $studentCount . ' enrolled students']);
        return;
    }
    
    $stmt = $db->prepare("
        DELETE s FROM tblstreams s
        JOIN tblclasses c ON s.class_id = c.id
        WHERE s.id = ? AND c.school_id = ?
    ");
    $stmt->execute([$stream_id, $school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Stream deleted successfully']);
}
?>