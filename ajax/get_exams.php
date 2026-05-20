<?php
// ajax/get_exams.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Check authentication
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized session'
    ]);
    exit;
}

$school_id = (int) $_SESSION['school_id'];

header('Content-Type: application/json');

// Check if class_id is provided
if (!isset($_POST['class_id']) || empty($_POST['class_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Class ID is required'
    ]);
    exit;
}

$class_id = (int) $_POST['class_id'];
$stream_id = isset($_POST['stream_id']) && !empty($_POST['stream_id']) ? (int) $_POST['stream_id'] : null;
$subject_id = isset($_POST['subject_id']) && !empty($_POST['subject_id']) ? (int) $_POST['subject_id'] : null;

try {
    // Get current date for deadline comparison
    $today = date('Y-m-d');
    
    // Base query to fetch exams - ONLY THOSE WITH DEADLINE NOT PASSED
    $query = "
        SELECT id, examname, DateAdded, deadline_date, status, class_id, stream_id
        FROM tblexam
        WHERE school_id = :school_id
        AND class_id = :class_id
        AND status = 'Active'
        AND (deadline_date IS NULL OR deadline_date >= :today)
    ";
    
    $params = [
        ':school_id' => $school_id,
        ':class_id' => $class_id,
        ':today' => $today
    ];
    
    // Filter by stream if provided
    if ($stream_id) {
        $query .= " AND (stream_id = :stream_id OR stream_id IS NULL)";
        $params[':stream_id'] = $stream_id;
    } else {
        $query .= " AND stream_id IS NULL";
    }
    
    // Order by deadline date (soonest first) then by exam name
    $query .= " ORDER BY 
                CASE 
                    WHEN deadline_date IS NULL THEN 1
                    ELSE 0
                END,
                deadline_date ASC,
                examname ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process exams to add formatted information
    $processed_exams = [];
    
    foreach ($exams as $exam) {
        $days_remaining = null;
        $status_message = 'Open';
        
        // Calculate days remaining if deadline exists
        if (!empty($exam['deadline_date'])) {
            $deadline = new DateTime($exam['deadline_date']);
            $current = new DateTime($today);
            $interval = $current->diff($deadline);
            $days_remaining = $interval->days;
            
            if ($deadline > $current) {
                if ($days_remaining == 0) {
                    $status_message = 'Ends today';
                } elseif ($days_remaining == 1) {
                    $status_message = '1 day remaining';
                } else {
                    $status_message = $days_remaining . ' days remaining';
                }
            }
        } else {
            $status_message = 'No deadline';
        }
        
        $processed_exams[] = [
            'id' => $exam['id'],
            'examname' => $exam['examname'],
            'date_added' => $exam['DateAdded'],
            'deadline_date' => $exam['deadline_date'],
            'status' => $exam['status'],
            'class_id' => $exam['class_id'],
            'stream_id' => $exam['stream_id'],
            'days_remaining' => $days_remaining,
            'status_message' => $status_message,
            'is_open' => true
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($processed_exams),
        'exams' => $processed_exams,
        'current_date' => $today
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>