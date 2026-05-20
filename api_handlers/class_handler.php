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
                case 'get_classes':
                    getClasses($db, $school_id);
                    break;
                    
                case 'get_class':
                    $class_id = intval($_GET['class_id'] ?? 0);
                    if ($class_id) {
                        getClass($db, $class_id, $school_id);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Class ID required']);
                    }
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
            case 'add_class':
                addClass($db, $_POST, $school_id);
                break;
                
            case 'edit_class':
                editClass($db, $_POST, $school_id);
                break;
                
            case 'delete_class':
                deleteClass($db, $_POST['class_id'] ?? 0, $school_id);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
} catch (Exception $e) {
    error_log("Class handler error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getClasses($db, $school_id) {
    $query = "SELECT c.id, c.class_level, c.academic_level, 
                     c.teacher_id, t.firstname, t.secondname, t.lastname,
                     COUNT(s.id) as student_count
              FROM tblclasses c 
              LEFT JOIN tblteachers t ON c.teacher_id = t.id 
              LEFT JOIN tblstudents s ON c.id = s.class_id AND s.Status = 'Active'
              WHERE c.school_id = :school_id 
              GROUP BY c.id 
              ORDER BY c.academic_level, c.class_level";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $classes]);
}

function getClass($db, $class_id, $school_id) {
    $query = "SELECT c.* FROM tblclasses c 
              WHERE c.id = :class_id AND c.school_id = :school_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($class) {
        echo json_encode(['success' => true, 'data' => $class]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
    }
}

function addClass($db, $data, $school_id) {
    // Validate required fields
    $academic_level = trim($data['academic_level'] ?? '');
    $class_level = trim($data['class_level'] ?? '');
    
    if (empty($academic_level) || empty($class_level)) {
        echo json_encode(['success' => false, 'message' => 'Academic level and class name are required']);
        return;
    }
    
    // Validate academic level
    $valid_levels = ['primary', 'junior_secondary', 'senior_secondary', 'college'];
    if (!in_array($academic_level, $valid_levels)) {
        echo json_encode(['success' => false, 'message' => 'Invalid academic level selected']);
        return;
    }
    
    // Check for duplicate class
    $check_query = "SELECT id, class_level, academic_level FROM tblclasses 
                    WHERE class_level = :class_level 
                    AND academic_level = :academic_level 
                    AND school_id = :school_id";
    
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':class_level', $class_level);
    $check_stmt->bindParam(':academic_level', $academic_level);
    $check_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $existing_class = $check_stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => false, 
            'message' => "Class '{$existing_class['class_level']}' already exists in {$existing_class['academic_level']} level"
        ]);
        return;
    }
    
    // Insert new class (without created_at column)
    $teacher_id = !empty($data['teacher_id']) ? intval($data['teacher_id']) : null;
    
    $query = "INSERT INTO tblclasses 
              (academic_level, class_level, teacher_id, school_id) 
              VALUES (:academic_level, :class_level, :teacher_id, :school_id)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':academic_level', $academic_level);
    $stmt->bindParam(':class_level', $class_level);
    $stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $new_class_id = $db->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => "Class '{$class_level}' added successfully to {$academic_level} level",
            'class_id' => $new_class_id
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Add class error: " . print_r($errorInfo, true));
        
        // Check for specific database errors
        if (strpos($errorInfo[2], 'foreign key constraint') !== false) {
            echo json_encode(['success' => false, 'message' => 'Selected teacher does not exist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add class. Please try again']);
        }
    }
}

function editClass($db, $data, $school_id) {
    $class_id = intval($data['class_id'] ?? 0);
    $academic_level = trim($data['academic_level'] ?? '');
    $class_level = trim($data['class_level'] ?? '');
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    if (empty($academic_level) || empty($class_level)) {
        echo json_encode(['success' => false, 'message' => 'Academic level and class name are required']);
        return;
    }
    
    // Validate academic level
    $valid_levels = ['primary', 'junior_secondary', 'senior_secondary', 'college'];
    if (!in_array($academic_level, $valid_levels)) {
        echo json_encode(['success' => false, 'message' => 'Invalid academic level selected']);
        return;
    }
    
    // Check if class exists
    $check_query = "SELECT id, class_level, academic_level FROM tblclasses 
                    WHERE id = :class_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        return;
    }
    
    $current_class = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check for duplicate (excluding current class)
    $duplicate_query = "SELECT id, class_level, academic_level FROM tblclasses 
                        WHERE class_level = :class_level 
                        AND academic_level = :academic_level 
                        AND school_id = :school_id 
                        AND id != :class_id";
    
    $duplicate_stmt = $db->prepare($duplicate_query);
    $duplicate_stmt->bindParam(':class_level', $class_level);
    $duplicate_stmt->bindParam(':academic_level', $academic_level);
    $duplicate_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $duplicate_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $duplicate_stmt->execute();
    
    if ($duplicate_stmt->rowCount() > 0) {
        $duplicate_class = $duplicate_stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => false, 
            'message' => "Another class '{$duplicate_class['class_level']}' already exists in {$duplicate_class['academic_level']} level"
        ]);
        return;
    }
    
    // Check if class name is changing
    $is_name_changing = ($current_class['class_level'] !== $class_level) || ($current_class['academic_level'] !== $academic_level);
    
    if ($is_name_changing) {
        // Check if class has students
        $student_check = "SELECT COUNT(*) as student_count 
                          FROM tblstudents 
                          WHERE class_id = :class_id AND Status = 'Active'";
        $student_stmt = $db->prepare($student_check);
        $student_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
        $student_stmt->execute();
        $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
        
        if ($student_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot rename class with active students']);
            return;
        }
    }
    
    // Update class
    $teacher_id = !empty($data['teacher_id']) ? intval($data['teacher_id']) : null;
    
    $query = "UPDATE tblclasses 
              SET academic_level = :academic_level, 
                  class_level = :class_level, 
                  teacher_id = :teacher_id
              WHERE id = :class_id AND school_id = :school_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':academic_level', $academic_level);
    $stmt->bindParam(':class_level', $class_level);
    $stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $success_message = $is_name_changing 
            ? "Class renamed from '{$current_class['class_level']}' to '{$class_level}'"
            : "Class '{$class_level}' updated successfully";
        
        echo json_encode(['success' => true, 'message' => $success_message]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Edit class error: " . print_r($errorInfo, true));
        
        // Check for specific database errors
        if (strpos($errorInfo[2], 'foreign key constraint') !== false) {
            echo json_encode(['success' => false, 'message' => 'Selected teacher does not exist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update class. Please try again']);
        }
    }
}

function deleteClass($db, $class_id, $school_id) {
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        return;
    }
    
    // Check if class exists
    $check_query = "SELECT id, class_level, academic_level FROM tblclasses 
                    WHERE id = :class_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        return;
    }
    
    $class_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if class has students
    $student_check = "SELECT COUNT(*) as student_count 
                      FROM tblstudents 
                      WHERE class_id = :class_id AND Status = 'Active'";
    $student_stmt = $db->prepare($student_check);
    $student_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $student_stmt->execute();
    $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
    
    if ($student_count > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete class '{$class_info['class_level']}' - it has {$student_count} active student(s)"
        ]);
        return;
    }
    
    // Check if class has streams
    $stream_check = "SELECT COUNT(*) as stream_count 
                     FROM tblstreams 
                     WHERE class_id = :class_id";
    $stream_stmt = $db->prepare($stream_check);
    $stream_stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stream_stmt->execute();
    $stream_count = $stream_stmt->fetch(PDO::FETCH_ASSOC)['stream_count'];
    
    // Delete class (foreign key constraints should handle stream deletion)
    $query = "DELETE FROM tblclasses WHERE id = :class_id AND school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $message = "Class '{$class_info['class_level']}' deleted successfully";
        if ($stream_count > 0) {
            $message .= " (along with {$stream_count} associated stream(s))";
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Delete class error: " . print_r($errorInfo, true));
        
        // Check for specific database errors
        if (strpos($errorInfo[2], 'foreign key constraint') !== false) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot delete class because it is referenced in other records'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete class. Please try again']);
        }
    }
}