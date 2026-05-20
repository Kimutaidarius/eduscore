<?php
session_start();
require_once '../includes/config.php'; // Changed from '../config/database.php'

if (!isset($_SESSION['authenticated']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$school_id = $input['school_id'] ?? $_SESSION['school_id'];
$teacher_id = $input['teacher_id'] ?? $_SESSION['user_id'];

try {
    // Use the existing $dbh connection from config.php
    $db = $dbh; // $dbh is already defined in config.php
    
    $stats = [];
    
    // Get total classes
    $query = "SELECT COUNT(DISTINCT id) as total_classes FROM tblclasses WHERE school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['total_classes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_classes'] ?? 0;
    
    // Get total subjects
    $query = "SELECT COUNT(DISTINCT id) as total_subjects FROM tblsubjects WHERE school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['total_subjects'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_subjects'] ?? 0;
    
    // Get total exams
    $query = "SELECT COUNT(DISTINCT id) as total_exams FROM tblexam WHERE school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['total_exams'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_exams'] ?? 0;
    
    // Get total students
    $query = "SELECT COUNT(DISTINCT id) as total_students FROM tblstudents WHERE school_id = :school_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'] ?? 0;
    
    // Get recent activity
    $query = "SELECT COUNT(DISTINCT student_id) as recent_activity FROM tblscores 
              WHERE school_id = :school_id AND recorded_by_teacher_id = :teacher_id
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['recent_activity'] = $stmt->fetch(PDO::FETCH_ASSOC)['recent_activity'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch(PDOException $e) {
    error_log("Fetch stats error: " . $e->getMessage());
    // Return empty stats on error to avoid breaking the UI
    echo json_encode([
        'success' => true, // Still return success
        'stats' => [
            'total_classes' => 0,
            'total_subjects' => 0,
            'total_exams' => 0,
            'total_students' => 0,
            'recent_activity' => 0
        ]
    ]);
}
?>