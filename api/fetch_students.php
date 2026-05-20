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

class FetchStudents {
    private $conn;
    private $teacher_id;
    private $school_id;
    private $class_id;
    private $stream_id;
    private $subject_id;
    private $exam_id;
    
    public function __construct($conn, $teacher_id, $school_id) {
        $this->conn = $conn;
        $this->teacher_id = $teacher_id;
        $this->school_id = $school_id;
        
        $this->handleRequest();
    }
    
    private function handleRequest() {
        try {
            // Get parameters
            $this->class_id = isset($_REQUEST['class_id']) ? $this->conn->real_escape_string($_REQUEST['class_id']) : '';
            $this->stream_id = isset($_REQUEST['stream_id']) ? (int)$_REQUEST['stream_id'] : 0;
            $this->subject_id = isset($_REQUEST['subject_id']) ? (int)$_REQUEST['subject_id'] : 0;
            $this->exam_id = isset($_REQUEST['exam_id']) ? (int)$_REQUEST['exam_id'] : null;
            
            if (!$this->class_id || !$this->subject_id) {
                $this->sendError('Class ID and Subject ID are required.', 400);
            }

            // Verify teacher has access
            if (!$this->verifyTeacherAccess()) {
                $this->sendError('Access denied to this subject and class combination.', 403);
            }

            // Fetch students
            $students = $this->fetchStudents();
            
            // If exam ID is provided, fetch existing scores and exam total score
            if ($this->exam_id && !empty($students)) {
                // Get exam total score
                $examTotalScore = $this->getExamTotalScore();
                
                // Fetch existing scores
                $students = $this->fetchExistingScores($students);
                
                // Add exam total score to response
                $examTotalData = ['exam_total_score' => $examTotalScore];
            } else {
                $examTotalData = ['exam_total_score' => 100]; // Default value
            }

            $this->sendResponse($students, $examTotalData);
            
        } catch (Exception $e) {
            error_log("Error fetching students: " . $e->getMessage());
            $this->sendError('Failed to fetch students: ' . $e->getMessage(), 500);
        }
    }
    
    private function verifyTeacherAccess() {
        $sql = "SELECT 1 FROM tbllessons 
                WHERE class_id = ? 
                AND subject_id = ? 
                AND teacher_id = ? 
                AND school_id = ?";
        
        $params = [$this->class_id, $this->subject_id, $this->teacher_id, $this->school_id];
        $types = "siii";
        
        if ($this->stream_id && $this->stream_id > 0) {
            $sql .= " AND (stream_id = ? OR stream_id IS NULL OR stream_id = 0)";
            $params[] = $this->stream_id;
            $types .= "i";
        }
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $hasAccess = $result->fetch_assoc();
        $stmt->close();
        
        return (bool)$hasAccess;
    }
    
    private function fetchStudents() {
        $sql = "SELECT 
                    st.id,
                    st.AdmNo,
                    st.FirstName,
                    st.LastName,
                    st.SecondName,
                    st.ProfilePic,
                    st.Gender,
                    st.StreamId,
                    st.Class,
                    st.AdmNo as admission_no,
                    st.admission_date,
                    st.GuardianName,
                    st.GuardianPhone,
                    st.BoardingStatus,
                    st.Nemis,
                    CONCAT(st.FirstName, ' ', 
                           CASE WHEN st.SecondName IS NOT NULL AND st.SecondName != '' 
                                THEN CONCAT(st.SecondName, ' ') 
                                ELSE '' 
                           END,
                           st.LastName) as full_name
                FROM tblstudents st
                WHERE st.class_id = ? 
                AND st.school_id = ?
                AND st.Status = 'Active'";
        
        $params = [$this->class_id, $this->school_id];
        $types = "si";
        
        // Handle stream filtering
        if ($this->stream_id && $this->stream_id > 0) {
            $sql .= " AND (st.StreamId = ? OR st.StreamId IS NULL OR st.StreamId = 0)";
            $params[] = $this->stream_id;
            $types .= "i";
        }

        $sql .= " ORDER BY st.AdmNo ASC, st.FirstName ASC";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $students;
    }
    
    private function getExamTotalScore() {
        // First try to get specific exam total
        $sql = "SELECT total_score 
                FROM tblexam_subject_totals 
                WHERE exam_id = ? 
                AND subject_id = ? 
                AND school_id = ?
                AND (class_id = ? OR class_id IS NULL)
                AND (stream_id = ? OR stream_id IS NULL)
                ORDER BY 
                    CASE WHEN class_id IS NOT NULL THEN 0 ELSE 1 END,
                    CASE WHEN stream_id IS NOT NULL THEN 0 ELSE 1 END
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iiisi", $this->exam_id, $this->subject_id, $this->school_id, $this->class_id, $this->stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row && isset($row['total_score'])) {
            return (float)$row['total_score'];
        }
        
        // Try general exam total
        $sql2 = "SELECT total_score 
                FROM tblexam_subject_totals 
                WHERE exam_id = ? 
                AND subject_id = ? 
                AND school_id = ?
                ORDER BY creation_date DESC 
                LIMIT 1";
        
        $stmt2 = $this->conn->prepare($sql2);
        $stmt2->bind_param("iii", $this->exam_id, $this->subject_id, $this->school_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $stmt2->close();
        
        if ($row2 && isset($row2['total_score'])) {
            return (float)$row2['total_score'];
        }
        
        return 100.00;
    }
    
    private function fetchExistingScores($students) {
        if (empty($students)) {
            return $students;
        }
        
        $studentIds = array_column($students, 'id');
        
        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        
        $sql = "SELECT 
                    s.student_id, 
                    s.score_value, 
                    s.id as score_id,
                    s.recorded_at,
                    s.recorded_by_teacher_id
                FROM tblscores s
                WHERE s.student_id IN ($placeholders) 
                AND s.subject_id = ? 
                AND s.exam_id = ? 
                AND s.school_id = ?";
        
        // Prepare types string
        $types = str_repeat('i', count($studentIds)) . "iii";
        $params = array_merge($studentIds, [$this->subject_id, $this->exam_id, $this->school_id]);
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingScores = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Map scores to students
        $scoreMap = [];
        foreach ($existingScores as $score) {
            $scoreMap[$score['student_id']] = [
                'score' => $score['score_value'],
                'score_id' => $score['score_id'],
                'recorded_at' => $score['recorded_at'],
                'recorded_by' => $score['recorded_by_teacher_id']
            ];
        }
        
        // Merge existing scores with student data
        foreach ($students as &$student) {
            if (isset($scoreMap[$student['id']])) {
                $student['existing_score'] = $scoreMap[$student['id']]['score'];
                $student['score_id'] = $scoreMap[$student['id']]['score_id'];
                $student['recorded_at'] = $scoreMap[$student['id']]['recorded_at'];
                $student['recorded_by'] = $scoreMap[$student['id']]['recorded_by'];
            } else {
                $student['existing_score'] = null;
                $student['score_id'] = null;
                $student['recorded_at'] = null;
                $student['recorded_by'] = null;
            }
            
            // Ensure proper data types
            $student['existing_score'] = $student['existing_score'] !== null ? (float)$student['existing_score'] : null;
        }
        
        return $students;
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit();
    }
    
    private function sendResponse($students, $additionalData = []) {
        // Format the response
        $formattedStudents = array_map(function($student) {
            return [
                'id' => $student['id'],
                'admission_no' => $student['AdmNo'],
                'full_name' => $student['full_name'],
                'FirstName' => $student['FirstName'],
                'LastName' => $student['LastName'],
                'SecondName' => $student['SecondName'],
                'ProfilePic' => $student['ProfilePic'],
                'Gender' => $student['Gender'],
                'StreamId' => $student['StreamId'],
                'Class' => $student['Class'],
                'admission_date' => $student['admission_date'],
                'GuardianName' => $student['GuardianName'],
                'GuardianPhone' => $student['GuardianPhone'],
                'BoardingStatus' => $student['BoardingStatus'],
                'Nemis' => $student['Nemis'],
                'existing_score' => isset($student['existing_score']) ? $student['existing_score'] : null,
                'score_id' => isset($student['score_id']) ? $student['score_id'] : null,
                'recorded_at' => isset($student['recorded_at']) ? $student['recorded_at'] : null,
                'recorded_by' => isset($student['recorded_by']) ? $student['recorded_by'] : null
            ];
        }, $students);
        
        $response = [
            'success' => true,
            'message' => 'Students fetched successfully',
            'data' => $formattedStudents,
            'meta' => [
                'total_students' => count($formattedStudents),
                'class_id' => $this->class_id,
                'subject_id' => $this->subject_id,
                'exam_id' => $this->exam_id,
                'stream_id' => $this->stream_id
            ]
        ];
        
        // Merge additional data
        if (!empty($additionalData)) {
            $response = array_merge($response, $additionalData);
        }
        
        echo json_encode($response, JSON_NUMERIC_CHECK);
        exit();
    }
}

// Initialize database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    new FetchStudents($conn, $_SESSION['teacher_id'], $_SESSION['school_id']);
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please try again.'
    ]);
    exit();
}
?>