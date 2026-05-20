<?php
/**
 * Fetch students preview for merit list
 * Used to show students when term and year are selected
 */

session_start();
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];
$academic_level = $_SESSION['academic_level'] ?? 'primary';

$class_id = $_POST['class_id'] ?? null;
$stream_id = $_POST['stream_id'] ?? 0;
$term_id = $_POST['term_id'] ?? null;
$year = $_POST['year'] ?? null;

if (!$class_id || !$term_id || !$year) {
    echo json_encode(['success' => false, 'message' => 'Class, term and year are required']);
    exit();
}

$conn = $dbh;

try {
    // Validate term exists
    $termCheck = $conn->prepare("SELECT id FROM tblterms WHERE id = ? AND school_id = ? AND academic_year = ?");
    $termCheck->execute([$term_id, $school_id, $year]);
    if (!$termCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid term for selected year']);
        exit();
    }

    // Fetch students for the selected class/stream
    $query = "
        SELECT s.id, s.FirstName, s.SecondName, s.LastName, 
               CONCAT(s.FirstName, ' ', s.SecondName) as full_name,
               s.AdmNo as admission_no, s.Gender as gender,
               s.StreamId
        FROM tblstudents s
        WHERE s.class_id = ? 
        AND s.school_id = ?
        AND s.Status = 'Active'
    ";
    
    $params = [$class_id, $school_id];
    
    if ($stream_id && $stream_id != 0) {
        $query .= " AND s.StreamId = ?";
        $params[] = $stream_id;
    }
    
    $query .= " ORDER BY s.FirstName";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'count' => count($students),
        'class_id' => $class_id,
        'term_id' => $term_id,
        'year' => $year
    ]);
    
} catch (Exception $e) {
    error_log("Error in fetch_students_preview.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>