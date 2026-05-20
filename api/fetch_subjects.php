<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please login first'
    ]);
    exit();
}

// Include config file for database connection
require_once __DIR__ . '/../includes/config.php';

class FetchSubjects {
    private $pdo;
    private $teacher_id;
    private $school_id;
    
    public function __construct($pdo, $teacher_id, $school_id) {
        $this->pdo = $pdo;
        $this->teacher_id = $teacher_id;
        $this->school_id = $school_id;
        
        $this->handleRequest();
    }
    
    private function handleRequest() {
        try {
            // Get parameters from both GET and POST
            $class_id = isset($_REQUEST['class_id']) ? (int)$_REQUEST['class_id'] : 0;
            $stream_id = isset($_REQUEST['stream_id']) ? (int)$_REQUEST['stream_id'] : null;
            
            if (!$class_id) {
                $this->sendError('Class ID is required.', 400);
            }
            
            // Fetch subjects from tbllessons where teacher is assigned
            $sql = "SELECT DISTINCT
                        s.id, 
                        s.subject_name,
                        s.alias,
                        s.subject_type,
                        s.category_id,
                        s.teacher_id as subject_teacher_id,
                        l.stream_id
                    FROM tblsubjects s
                    INNER JOIN tbllessons l ON s.id = l.subject_id 
                    WHERE l.class_id = :class_id 
                    AND l.teacher_id = :teacher_id 
                    AND l.school_id = :school_id
                    AND s.school_id = :school_id";
            
            $params = [
                ':class_id' => $class_id,
                ':teacher_id' => $this->teacher_id,
                ':school_id' => $this->school_id
            ];
            
            // If stream is specified, include it in the filter
            if ($stream_id && $stream_id > 0) {
                $sql .= " AND (l.stream_id = :stream_id OR l.stream_id IS NULL OR l.stream_id = 0)";
                $params[':stream_id'] = $stream_id;
            }
            
            $sql .= " ORDER BY s.subject_name ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $subjects = $stmt->fetchAll();
            
            // If no subjects found in lessons, check if teacher is assigned directly in tblsubjects
            if (empty($subjects)) {
                $sql2 = "SELECT DISTINCT
                            s.id, 
                            s.subject_name,
                            s.alias,
                            s.subject_type,
                            s.category_id,
                            s.teacher_id as subject_teacher_id
                        FROM tblsubjects s
                        WHERE s.class_id = :class_id 
                        AND s.school_id = :school_id
                        AND (s.teacher_id = :teacher_id OR s.teacher_id IS NULL)";
                
                if ($stream_id && $stream_id > 0) {
                    $sql2 .= " AND (s.stream_id = :stream_id OR s.stream_id IS NULL OR s.stream_id = 0)";
                }
                
                $sql2 .= " ORDER BY s.subject_name ASC";
                
                $stmt2 = $this->pdo->prepare($sql2);
                $stmt2->execute($params);
                $subjects = $stmt2->fetchAll();
            }
            
            $this->sendResponse($subjects);
            
        } catch (PDOException $e) {
            error_log("Error fetching subjects: " . $e->getMessage());
            $this->sendError('Failed to fetch subjects: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->sendError('An error occurred: ' . $e->getMessage(), 500);
        }
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit();
    }
    
    private function sendResponse($data, $message = 'Subjects fetched successfully') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
}

// Initialize the class using the existing database connection from config.php
new FetchSubjects($dbh, $_SESSION['teacher_id'], $_SESSION['school_id']);
?>