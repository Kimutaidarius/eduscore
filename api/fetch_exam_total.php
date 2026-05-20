<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please login.',
        'total_score' => null,
        'source' => 'error'
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required parameters
if (!isset($data['exam_id']) || !isset($data['class_id']) || !isset($data['subject_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: exam_id, class_id, subject_id',
        'total_score' => null,
        'source' => 'error'
    ]);
    exit();
}

$exam_id = intval($data['exam_id']);
$class_id = intval($data['class_id']);
$subject_id = intval($data['subject_id']);
$stream_id = isset($data['stream_id']) ? intval($data['stream_id']) : 0;
$school_id = $_SESSION['school_id'];

try {
    // Prepare query to fetch exam total score from tblscores
    $query = "
        SELECT 
            total_score,
            COUNT(*) as record_count,
            MAX(recorded_at) as last_updated
        FROM tblscores 
        WHERE school_id = :school_id
        AND exam_id = :exam_id
        AND class_id = :class_id
        AND subject_id = :subject_id
        AND (:stream_id = 0 OR StreamId = :stream_id)
        AND total_score > 0
        GROUP BY total_score
        ORDER BY record_count DESC, last_updated DESC
        LIMIT 1
    ";
    
    $stmt = $dbh->prepare($query);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
    $stmt->bindParam(':stream_id', $stream_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['total_score'] > 0) {
        // Found total score in tblscores
        echo json_encode([
            'success' => true,
            'message' => 'Total score fetched successfully',
            'total_score' => floatval($result['total_score']),
            'source' => 'database',
            'record_count' => intval($result['record_count']),
            'last_updated' => $result['last_updated']
        ]);
    } else {
        // Check if exam has a default total in tblexam table
        $query2 = "
            SELECT total_score 
            FROM tblexam 
            WHERE id = :exam_id 
            AND school_id = :school_id
            AND total_score > 0
            LIMIT 1
        ";
        
        $stmt2 = $dbh->prepare($query2);
        $stmt2->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
        $stmt2->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt2->execute();
        
        $examResult = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($examResult && $examResult['total_score'] > 0) {
            // Found default total in exam table
            echo json_encode([
                'success' => true,
                'message' => 'Default exam total score found',
                'total_score' => floatval($examResult['total_score']),
                'source' => 'exam_default'
            ]);
        } else {
            // No total score found anywhere
            echo json_encode([
                'success' => true,
                'message' => 'No total score set for this exam. Default is 0.',
                'total_score' => null,
                'source' => 'none'
            ]);
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error in fetch_exam_total.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.',
        'total_score' => null,
        'source' => 'error'
    ]);
} catch (Exception $e) {
    error_log("General error in fetch_exam_total.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
        'total_score' => null,
        'source' => 'error'
    ]);
}
?>