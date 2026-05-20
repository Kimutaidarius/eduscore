<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Default CBE grading scale
    $default_grades = [
        ['lower_limit' => 0, 'upper_limit' => 24, 'grade_alias' => 'BE', 'points' => 1.00, 'remarks' => 'Below Expectation'],
        ['lower_limit' => 25, 'upper_limit' => 49, 'grade_alias' => 'AE', 'points' => 2.00, 'remarks' => 'Approaching Expectation'],
        ['lower_limit' => 50, 'upper_limit' => 74, 'grade_alias' => 'ME', 'points' => 3.00, 'remarks' => 'Meet Expectation'],
        ['lower_limit' => 75, 'upper_limit' => 100, 'grade_alias' => 'EE', 'points' => 4.00, 'remarks' => 'Exceeding Expectation']
    ];
    
    // Get action from request
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    
    switch ($action) {
        case 'get_grades':
            $class_id = $_GET['class_id'] ?? null;
            $stream_id = $_GET['stream_id'] ?? null;
            $subject_id = 0; // Always 0 for general grading
            
            if (!$class_id) {
                echo json_encode(['success' => false, 'message' => 'Class ID is required']);
                exit;
            }
            
            // Fetch grades for selected class and stream (subject_id = 0 for general grading)
            $query = "SELECT id, lower_limit, upper_limit, grade, grade_alias, points, remarks 
                      FROM tblsubjectgrading 
                      WHERE class_id = :class_id 
                      AND subject_id = :subject_id
                      AND (:stream_id IS NULL OR stream_id = :stream_id)
                      AND school_id = :school_id 
                      ORDER BY lower_limit ASC";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
            $stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
            $stmt->bindParam(":stream_id", $stream_id, PDO::PARAM_INT);
            $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
            $stmt->execute();
            $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no grades found, return default grades
            if (empty($grades)) {
                $grades = $default_grades;
            }
            
            echo json_encode(['success' => true, 'grades' => $grades]);
            break;
            
        case 'add_grade':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $query = "INSERT INTO tblsubjectgrading 
                      (class_id, stream_id, school_id, subject_id, grade, lower_limit, upper_limit, points, remarks, grade_alias, is_default) 
                      VALUES (:class_id, :stream_id, :school_id, :subject_id, :grade, :lower_limit, :upper_limit, :points, :remarks, :grade_alias, 1)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":class_id", $data['class_id'], PDO::PARAM_INT);
            $stmt->bindParam(":stream_id", $data['stream_id'], PDO::PARAM_NULL);
            $stmt->bindParam(":school_id", $data['school_id'], PDO::PARAM_INT);
            $stmt->bindParam(":subject_id", $data['subject_id'], PDO::PARAM_INT);
            $stmt->bindParam(":grade", $data['grade']);
            $stmt->bindParam(":grade_alias", $data['grade_alias']);
            $stmt->bindParam(":lower_limit", $data['lower_limit'], PDO::PARAM_INT);
            $stmt->bindParam(":upper_limit", $data['upper_limit'], PDO::PARAM_INT);
            $stmt->bindParam(":points", $data['points']);
            $stmt->bindParam(":remarks", $data['remarks']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Grade added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add grade']);
            }
            break;
            
        case 'update_grade':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $query = "UPDATE tblsubjectgrading 
                      SET lower_limit = :lower_limit, 
                          upper_limit = :upper_limit, 
                          grade = :grade,
                          grade_alias = :grade_alias, 
                          points = :points, 
                          remarks = :remarks 
                      WHERE id = :id AND school_id = :school_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $data['id'], PDO::PARAM_INT);
            $stmt->bindParam(":lower_limit", $data['lower_limit'], PDO::PARAM_INT);
            $stmt->bindParam(":upper_limit", $data['upper_limit'], PDO::PARAM_INT);
            $stmt->bindParam(":grade", $data['grade']);
            $stmt->bindParam(":grade_alias", $data['grade_alias']);
            $stmt->bindParam(":points", $data['points']);
            $stmt->bindParam(":remarks", $data['remarks']);
            $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Grade updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update grade']);
            }
            break;
            
        case 'delete_grade':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $query = "DELETE FROM tblsubjectgrading WHERE id = :id AND school_id = :school_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $data['grade_id'], PDO::PARAM_INT);
            $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Grade deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete grade']);
            }
            break;
            
        case 'save_default_grades':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // First, delete existing grades for this class/stream/subject combination
            $deleteQuery = "DELETE FROM tblsubjectgrading 
                           WHERE class_id = :class_id 
                           AND subject_id = :subject_id
                           AND (:stream_id IS NULL OR stream_id = :stream_id)
                           AND school_id = :school_id";
            
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindParam(":class_id", $data['class_id'], PDO::PARAM_INT);
            $deleteStmt->bindParam(":subject_id", $data['subject_id'], PDO::PARAM_INT);
            $deleteStmt->bindParam(":stream_id", $data['stream_id'], PDO::PARAM_NULL);
            $deleteStmt->bindParam(":school_id", $data['school_id'], PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // Then insert default grades
            $insertQuery = "INSERT INTO tblsubjectgrading 
                           (class_id, stream_id, school_id, subject_id, grade, grade_alias, lower_limit, upper_limit, points, remarks, is_default) 
                           VALUES (:class_id, :stream_id, :school_id, :subject_id, :grade, :grade_alias, :lower_limit, :upper_limit, :points, :remarks, 1)";
            
            $success = true;
            foreach ($data['grades'] as $grade) {
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(":class_id", $data['class_id'], PDO::PARAM_INT);
                $insertStmt->bindParam(":stream_id", $data['stream_id'], PDO::PARAM_NULL);
                $insertStmt->bindParam(":school_id", $data['school_id'], PDO::PARAM_INT);
                $insertStmt->bindParam(":subject_id", $data['subject_id'], PDO::PARAM_INT);
                $insertStmt->bindParam(":grade", $grade['grade_alias']);
                $insertStmt->bindParam(":grade_alias", $grade['grade_alias']);
                $insertStmt->bindParam(":lower_limit", $grade['lower_limit'], PDO::PARAM_INT);
                $insertStmt->bindParam(":upper_limit", $grade['upper_limit'], PDO::PARAM_INT);
                $insertStmt->bindParam(":points", $grade['points']);
                $insertStmt->bindParam(":remarks", $grade['remarks']);
                
                if (!$insertStmt->execute()) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Default grades saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save default grades']);
            }
            break;
            
        case 'reset_to_default':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Delete existing grades for this class/stream/subject combination
            $deleteQuery = "DELETE FROM tblsubjectgrading 
                           WHERE class_id = :class_id 
                           AND subject_id = :subject_id
                           AND (:stream_id IS NULL OR stream_id = :stream_id)
                           AND school_id = :school_id";
            
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindParam(":class_id", $data['class_id'], PDO::PARAM_INT);
            $deleteStmt->bindParam(":subject_id", $data['subject_id'], PDO::PARAM_INT);
            $deleteStmt->bindParam(":stream_id", $data['stream_id'], PDO::PARAM_NULL);
            $deleteStmt->bindParam(":school_id", $data['school_id'], PDO::PARAM_INT);
            
            if ($deleteStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Grades reset to default successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reset grades']);
            }
            break;
            
        case 'copy_grades':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // First, delete existing grades in target class/stream/subject
            $deleteQuery = "DELETE FROM tblsubjectgrading 
                           WHERE class_id = :to_class_id 
                           AND subject_id = :subject_id
                           AND (:to_stream_id IS NULL OR stream_id = :to_stream_id)
                           AND school_id = :school_id";
            
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindParam(":to_class_id", $data['to_class_id'], PDO::PARAM_INT);
            $deleteStmt->bindParam(":subject_id", $data['subject_id'], PDO::PARAM_INT);
            $deleteStmt->bindParam(":to_stream_id", $data['to_stream_id'], PDO::PARAM_NULL);
            $deleteStmt->bindParam(":school_id", $data['school_id'], PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // Get grades from source class/stream/subject
            $sourceQuery = "SELECT * FROM tblsubjectgrading 
                           WHERE class_id = :from_class_id 
                           AND subject_id = :subject_id
                           AND (:from_stream_id IS NULL OR stream_id = :from_stream_id)
                           AND school_id = :school_id";
            
            $sourceStmt = $db->prepare($sourceQuery);
            $sourceStmt->bindParam(":from_class_id", $data['from_class_id'], PDO::PARAM_INT);
            $sourceStmt->bindParam(":subject_id", $data['subject_id'], PDO::PARAM_INT);
            $sourceStmt->bindParam(":from_stream_id", $data['from_stream_id'], PDO::PARAM_NULL);
            $sourceStmt->bindParam(":school_id", $data['school_id'], PDO::PARAM_INT);
            $sourceStmt->execute();
            $sourceGrades = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no grades in source, use default grades
            if (empty($sourceGrades)) {
                $sourceGrades = array_map(function($grade) {
                    return [
                        'grade' => $grade['grade_alias'],
                        'grade_alias' => $grade['grade_alias'],
                        'lower_limit' => $grade['lower_limit'],
                        'upper_limit' => $grade['upper_limit'],
                        'points' => $grade['points'],
                        'remarks' => $grade['remarks']
                    ];
                }, $default_grades);
            }
            
            // Copy grades to target
            $insertQuery = "INSERT INTO tblsubjectgrading 
                           (class_id, stream_id, school_id, subject_id, grade, grade_alias, lower_limit, upper_limit, points, remarks, is_default) 
                           VALUES (:class_id, :stream_id, :school_id, :subject_id, :grade, :grade_alias, :lower_limit, :upper_limit, :points, :remarks, 1)";
            
            $success = true;
            foreach ($sourceGrades as $grade) {
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(":class_id", $data['to_class_id'], PDO::PARAM_INT);
                $insertStmt->bindParam(":stream_id", $data['to_stream_id'], PDO::PARAM_NULL);
                $insertStmt->bindParam(":school_id", $data['school_id'], PDO::PARAM_INT);
                $insertStmt->bindParam(":subject_id", $data['subject_id'], PDO::PARAM_INT);
                $insertStmt->bindParam(":grade", $grade['grade']);
                $insertStmt->bindParam(":grade_alias", $grade['grade_alias']);
                $insertStmt->bindParam(":lower_limit", $grade['lower_limit'], PDO::PARAM_INT);
                $insertStmt->bindParam(":upper_limit", $grade['upper_limit'], PDO::PARAM_INT);
                $insertStmt->bindParam(":points", $grade['points']);
                $insertStmt->bindParam(":remarks", $grade['remarks']);
                
                if (!$insertStmt->execute()) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Grades copied successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to copy grades']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch(PDOException $e) {
    error_log("Grading AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>