<?php
session_start();
header('Content-Type: application/json');

require_once '../includes/config.php';

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$school_id = $_SESSION['school_id'];
$class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
$stream_id = isset($_POST['stream_id']) && $_POST['stream_id'] > 0 ? (int)$_POST['stream_id'] : null;
$academic_level = $_SESSION['academic_level'] ?? 'primary';

try {
    // First, try to get class-specific grading scale
    if ($class_id > 0) {
        $query = "SELECT * FROM tblgradingscale 
                  WHERE school_id = :school_id 
                  AND class_id = :class_id 
                  AND (stream_id = :stream_id OR stream_id IS NULL)
                  ORDER BY lower_limit ASC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
        $stmt->bindParam(':stream_id', $stream_id, $stream_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();
        $gradingScale = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If no class-specific scale found, get school-wide default
    if (empty($gradingScale)) {
        $query = "SELECT * FROM tblgradingscale 
                  WHERE school_id = :school_id 
                  AND (class_id IS NULL OR class_id = 0)
                  ORDER BY lower_limit ASC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $gradingScale = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If still no scale found, return default CBC grading scale
    if (empty($gradingScale)) {
        $gradingScale = [
            ['lower_limit' => 80, 'upper_limit' => 100, 'grade' => 'EE', 'points' => 4.00, 'remarks' => 'Exceeding Expectations'],
            ['lower_limit' => 65, 'upper_limit' => 79, 'grade' => 'ME', 'points' => 3.00, 'remarks' => 'Meeting Expectations'],
            ['lower_limit' => 50, 'upper_limit' => 64, 'grade' => 'AE', 'points' => 2.00, 'remarks' => 'Approaching Expectations'],
            ['lower_limit' => 0, 'upper_limit' => 49, 'grade' => 'BE', 'points' => 1.00, 'remarks' => 'Below Expectations']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'grading_scale' => $gradingScale
    ]);
    
} catch (PDOException $e) {
    error_log("Get grading scale error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>