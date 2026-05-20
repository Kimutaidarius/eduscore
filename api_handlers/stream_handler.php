<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Enable debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is authenticated
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

try {
    // Handle GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'get_streams':
                    $class_id = intval($_GET['class_id'] ?? 0);
                    if ($class_id) {
                        getStreams($db, $class_id, $school_id);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Class ID required']);
                    }
                    break;
                    
                case 'get_all_streams':
                    getAllStreams($db, $school_id);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No action specified']);
        }
        exit;
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if (empty($action)) {
            echo json_encode(['success' => false, 'message' => 'No action specified']);
            exit;
        }
        
        switch ($action) {
            case 'add_stream':
                addStream($db, $_POST, $school_id);
                break;
                
            case 'delete_stream':
                deleteStream($db, $_POST['stream_id'] ?? 0, $school_id);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
} catch (Exception $e) {
    error_log("Stream handler error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getStreams($db, $class_id, $school_id) {
    // Verify class belongs to school
    $check_query = "SELECT id FROM tblclasses WHERE id = :class_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        return;
    }
    
    // Filter streams by class_id AND school_id
    $query = "SELECT s.* FROM tblstreams s 
              INNER JOIN tblclasses c ON s.class_id = c.id 
              WHERE s.class_id = :class_id AND c.school_id = :school_id 
              ORDER BY s.stream_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $streams]);
}

function getAllStreams($db, $school_id) {
    // Filter streams by school_id from tblstreams table directly
    $query = "SELECT s.*, c.class_level 
              FROM tblstreams s 
              INNER JOIN tblclasses c ON s.class_id = c.id 
              WHERE s.school_id = :school_id 
              ORDER BY s.class_id, s.stream_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $streams]);
}

function addStream($db, $data, $school_id) {
    $class_id = intval($data['class_id'] ?? 0);
    $stream_name = trim($data['stream_name'] ?? '');
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    if (empty($stream_name)) {
        echo json_encode(['success' => false, 'message' => 'Stream name is required']);
        return;
    }
    
    if (strlen($stream_name) > 50) {
        echo json_encode(['success' => false, 'message' => 'Stream name cannot exceed 50 characters']);
        return;
    }
    
    // Verify class belongs to school
    $check_query = "SELECT id FROM tblclasses WHERE id = :class_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        return;
    }
    
    // Check if stream already exists for this class (case insensitive)
    $duplicate_query = "SELECT id FROM tblstreams 
                        WHERE class_id = :class_id AND LOWER(stream_name) = LOWER(:stream_name) 
                        AND school_id = :school_id";
    $duplicate_stmt = $db->prepare($duplicate_query);
    $duplicate_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $duplicate_stmt->bindParam(':stream_name', $stream_name);
    $duplicate_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $duplicate_stmt->execute();
    
    if ($duplicate_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Stream already exists for this class']);
        return;
    }
    
    // Insert new stream with school_id
    $query = "INSERT INTO tblstreams (class_id, stream_name, school_id) 
              VALUES (:class_id, :stream_name, :school_id)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':stream_name', $stream_name);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $new_stream_id = $db->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => 'Stream added successfully',
            'stream_id' => $new_stream_id
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Add stream error: " . print_r($errorInfo, true));
        echo json_encode(['success' => false, 'message' => 'Failed to add stream']);
    }
}

function deleteStream($db, $stream_id, $school_id) {
    if (!$stream_id) {
        echo json_encode(['success' => false, 'message' => 'Stream ID is required']);
        return;
    }
    
    // Verify stream belongs to school directly from tblstreams
    $check_query = "SELECT id FROM tblstreams WHERE id = :stream_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':stream_id', $stream_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Stream not found']);
        return;
    }
    
    // Check if stream has students (optional - if you have student-stream relationship)
    // Uncomment this section if you have a student_stream table or stream_id in tblstudents
    /*
    $student_check = "SELECT COUNT(*) as student_count FROM tblstudents WHERE stream_id = :stream_id";
    $student_stmt = $db->prepare($student_check);
    $student_stmt->bindParam(':stream_id', $stream_id, PDO::PARAM_INT);
    $student_stmt->execute();
    $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
    
    if ($student_count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete stream with assigned students']);
        return;
    }
    */
    
    // Delete stream
    $query = "DELETE FROM tblstreams WHERE id = :stream_id AND school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':stream_id', $stream_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Stream deleted successfully']);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Delete stream error: " . print_r($errorInfo, true));
        echo json_encode(['success' => false, 'message' => 'Failed to delete stream']);
    }
}
?>