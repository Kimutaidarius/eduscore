<?php
// ajax/get_subjects_scores.php
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
$teacher_id = (int) $_SESSION['teacher_id'];

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

try {
    // Query to get subjects assigned to this teacher from tbllessons
    // Join with tblsubjects to get subject details
    $query = "
        SELECT DISTINCT s.id, s.subject_name, s.alias, s.subject_type
        FROM tbllessons l
        INNER JOIN tblsubjects s ON l.subject_id = s.id
        WHERE l.class_id = :class_id 
        AND l.school_id = :school_id
        AND l.teacher_id = :teacher_id
    ";
    
    $params = [
        ':class_id' => $class_id,
        ':school_id' => $school_id,
        ':teacher_id' => $teacher_id
    ];
    
    // If stream_id is provided, filter by stream as well
    if ($stream_id) {
        $query .= " AND (l.stream_id = :stream_id OR l.stream_id IS NULL)";
        $params[':stream_id'] = $stream_id;
    }
    
    $query .= " ORDER BY s.subject_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if we got any results
    if (empty($subjects)) {
        echo json_encode([
            'success' => true,
            'message' => 'No subjects assigned to you for this class',
            'subjects' => []
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'count' => count($subjects),
            'subjects' => $subjects
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching assigned subjects: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>