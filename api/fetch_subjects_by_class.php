<?php
// api/fetch_subjects_by_class.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['authenticated']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get parameters from POST or GET
$class_id = $_POST['class_id'] ?? null;
$school_id = $_POST['school_id'] ?? $_SESSION['school_id'];
$teacher_id = $_POST['teacher_id'] ?? $_SESSION['user_id'];

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // IMPORTANT: Fetch subjects assigned to this teacher for this specific class
    $query = "
        SELECT DISTINCT s.id, s.subject_name
        FROM tblsubjects s
        WHERE s.school_id = :school_id 
        AND s.class_id = :class_id
        AND s.teacher_id = :teacher_id  -- CRITICAL: Filter by teacher_id
        ORDER BY s.subject_name ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $stmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
    $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no subjects found for this teacher in this class, show all subjects for the class
    // (optional - depends on your requirements)
    if (empty($subjects)) {
        $query = "
            SELECT DISTINCT s.id, s.subject_name
            FROM tblsubjects s
            WHERE s.school_id = :school_id 
            AND s.class_id = :class_id
            ORDER BY s.subject_name ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        $stmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'subjects' => $subjects,
        'message' => count($subjects) . ' subjects found'
    ]);
    
} catch(PDOException $e) {
    error_log("Fetch subjects by class error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching subjects: ' . $e->getMessage()
    ]);
}
?>