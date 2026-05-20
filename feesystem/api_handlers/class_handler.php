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

// Check if user is logged in - using the same session variables as the main app
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    // Also check for teacher_id as fallback
    if (!isset($_SESSION['teacher_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Please log in again']);
        exit;
    }
}

// Get school_id from session - try different possible session variable names
$school_id = $_SESSION['school_id'] ?? $_SESSION['schoolId'] ?? null;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID not found in session']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch($action) {
        case 'get_classes':
            getClasses($db, $school_id);
            break;
        case 'get_class':
            getClass($db, $school_id);
            break;
        case 'add_class':
            addClass($db, $school_id);
            break;
        case 'edit_class':
            editClass($db, $school_id);
            break;
        case 'delete_class':
            deleteClass($db, $school_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getClasses($db, $school_id) {
    try {
        $stmt = $db->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM tblstreams WHERE class_id = c.id) as stream_count,
                   (SELECT COUNT(*) FROM tblstudents WHERE class_id = c.id AND Status = 'Active') as student_count,
                   CONCAT(COALESCE(t.firstname, ''), ' ', COALESCE(t.secondname, ''), ' ', COALESCE(t.lastname, '')) as teacher_name
            FROM tblclasses c
            LEFT JOIN tblteachers t ON c.teacher_id = t.id AND t.school_id = c.school_id AND t.is_deleted = 0
            WHERE c.school_id = ?
            ORDER BY c.academic_level, c.class_level
        ");
        $stmt->execute([$school_id]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $classes]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getClass($db, $school_id) {
    $class_id = $_GET['class_id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$class_id, $school_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($class) {
        echo json_encode(['success' => true, 'data' => $class]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
    }
}

function addClass($db, $school_id) {
    $class_level = $_POST['class_level'] ?? '';
    $academic_level = $_POST['academic_level'] ?? 'primary';
    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
    
    if (empty($class_level)) {
        echo json_encode(['success' => false, 'message' => 'Class name is required']);
        return;
    }
    
    $stmt = $db->prepare("
        INSERT INTO tblclasses (school_id, class_level, academic_level, teacher_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$school_id, $class_level, $academic_level, $teacher_id]);
    
    echo json_encode(['success' => true, 'message' => 'Class added successfully', 'id' => $db->lastInsertId()]);
}

function editClass($db, $school_id) {
    $class_id = $_POST['class_id'] ?? 0;
    $class_level = $_POST['class_level'] ?? '';
    $academic_level = $_POST['academic_level'] ?? 'primary';
    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
    
    if (empty($class_level)) {
        echo json_encode(['success' => false, 'message' => 'Class name is required']);
        return;
    }
    
    $stmt = $db->prepare("
        UPDATE tblclasses 
        SET class_level = ?, academic_level = ?, teacher_id = ?
        WHERE id = ? AND school_id = ?
    ");
    $stmt->execute([$class_level, $academic_level, $teacher_id, $class_id, $school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Class updated successfully']);
}

function deleteClass($db, $school_id) {
    $class_id = $_POST['class_id'] ?? 0;
    
    // Check if class has students
    $stmt = $db->prepare("SELECT COUNT(*) FROM tblstudents WHERE class_id = ? AND school_id = ? AND Status = 'Active'");
    $stmt->execute([$class_id, $school_id]);
    $studentCount = $stmt->fetchColumn();
    
    if ($studentCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete class with ' . $studentCount . ' enrolled students']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM tblclasses WHERE id = ? AND school_id = ?");
    $stmt->execute([$class_id, $school_id]);
    
    echo json_encode(['success' => true, 'message' => 'Class deleted successfully']);
}
?>