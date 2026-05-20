<?php
// ajax/fetch_exams_meritlist.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORRECTED PATH - use includes/config.php
require_once __DIR__ . '/../includes/config.php';

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
$class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
$stream_id = isset($_POST['stream_id']) ? (int)$_POST['stream_id'] : 0;
$academic_level = $_POST['academic_level'] ?? $_SESSION['academic_level'] ?? 'primary';

if ($class_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Class ID is required'
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    // Check if database connection exists
    if (!isset($db) && !isset($dbh)) {
        throw new Exception('Database connection not available');
    }
    
    $connection = isset($db) ? $db : $dbh;
    
    // Fetch exams for the selected class and stream - ONLY ACTIVE EXAMS
    // status column is VARCHAR with values 'Active' or 'Inactive'
    $query = "SELECT e.id, e.examname, e.DateAdded, e.status, e.deadline_date
              FROM tblexam e
              INNER JOIN tblclasses c ON e.class_id = c.id
              WHERE e.school_id = :school_id 
              AND e.class_id = :class_id 
              AND e.status = 'Active'
              AND c.academic_level = :academic_level";
    
    $params = [
        ':school_id' => $school_id,
        ':class_id' => $class_id,
        ':academic_level' => $academic_level
    ];
    
    if ($stream_id > 0) {
        $query .= " AND (e.stream_id = :stream_id OR e.stream_id IS NULL)";
        $params[':stream_id'] = $stream_id;
    }
    
    $query .= " ORDER BY e.DateAdded DESC, e.examname";
    
    $stmt = $connection->prepare($query);
    $stmt->execute($params);
    
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $exams,
        'count' => count($exams)
    ]);
    
} catch (PDOException $e) {
    error_log("Fetch exams error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Fetch exams error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>