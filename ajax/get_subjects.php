<?php
// ajax/get_compulsory_subjects.php (if it exists)
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['class_id']) || empty($_POST['class_id'])) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit;
}

$class_id = $_POST['class_id'];
$school_id = $_SESSION['school_id'];

try {
    // Query to get compulsory subjects for the specified class
    // REMOVED: AND s.status = 'Active' since there's no status column
    $query = "SELECT s.id, s.subject_name, s.subject_type 
              FROM tblsubjects s
              WHERE s.class_id = :class_id 
              AND s.school_id = :school_id
              AND s.subject_type = 'Compulsory'
              ORDER BY s.subject_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if we got any results
    if (empty($subjects)) {
        echo json_encode([
            'success' => true,
            'message' => 'No compulsory subjects found for this class',
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
    error_log("Error fetching compulsory subjects: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'subjects' => []
    ]);
}
?>