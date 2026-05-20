<?php
// ajax/fetch_merged_reports.php - FIXED VERSION

session_start();
header('Content-Type: application/json');

require_once '../includes/config.php';

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = $input['class_id'] ?? 0;
$stream_id = $input['stream_id'] ?? 0;
$exam_id = $input['exam_id'] ?? 0;
$term_id = $input['term_id'] ?? 0;
$academic_year = $input['academic_year'] ?? '';
$academic_level = $input['academic_level'] ?? '';

// Convert class_id to string for VARCHAR column
$class_id_str = (string)$class_id;

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $query = "
        SELECT 
            mr.id,
            mr.school_id,
            mr.class_id,
            mr.stream_id,
            mr.exam_id,
            mr.term_id,
            mr.academic_year,
            mr.mean_score,
            mr.grade as mean_grade,
            mr.subjects,
            mr.pdf_url,
            mr.created_at,
            mr.updated_at,
            mr.ranking_method,
            c.class_level as class_name,
            s.stream_name,
            e.examname as exam_name,
            t.term_name
        FROM merged_reports mr
        LEFT JOIN tblclasses c ON CAST(mr.class_id AS UNSIGNED) = c.id
        LEFT JOIN tblstreams s ON mr.stream_id = s.id
        LEFT JOIN tblexam e ON mr.exam_id = e.id
        LEFT JOIN tblterms t ON mr.term_id = t.id
        WHERE mr.school_id = ?
    ";
    
    $params = [$school_id];
    $types = "i";
    
    if ($class_id > 0) {
        $query .= " AND mr.class_id = ?";
        $params[] = $class_id_str;
        $types .= "s";
    }
    
    if ($stream_id > 0) {
        $query .= " AND mr.stream_id = ?";
        $params[] = $stream_id;
        $types .= "i";
    }
    
    if ($exam_id > 0) {
        $query .= " AND mr.exam_id = ?";
        $params[] = $exam_id;
        $types .= "i";
    }
    
    if ($term_id > 0) {
        $query .= " AND mr.term_id = ?";
        $params[] = $term_id;
        $types .= "i";
    }
    
    if (!empty($academic_year)) {
        $query .= " AND mr.academic_year = ?";
        $params[] = $academic_year;
        $types .= "s";
    }
    
    $query .= " ORDER BY mr.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        // Decode subjects JSON
        $subjects_list = [];
        if (!empty($row['subjects'])) {
            $subjects_list = json_decode($row['subjects'], true);
            if (!is_array($subjects_list)) {
                $subjects_list = [];
            }
        }
        
        // Calculate best student (you may need to fetch this from another table)
        $best_student = null;
        
        $reports[] = [
            'id' => $row['id'],
            'class_id' => $row['class_id'],
            'class_name' => $row['class_name'] ?? 'N/A',
            'stream_id' => $row['stream_id'],
            'stream_name' => $row['stream_name'] ?? 'All Streams',
            'exam_id' => $row['exam_id'],
            'exam_name' => $row['exam_name'] ?? 'N/A',
            'term_id' => $row['term_id'],
            'term_name' => $row['term_name'] ?? 'N/A',
            'academic_year' => $row['academic_year'] ?? 'N/A',
            'mean_score' => floatval($row['mean_score'] ?? 0),
            'mean_grade' => $row['mean_grade'] ?? 'N/A',
            'subjects_list' => $subjects_list,
            'pdf_url' => $row['pdf_url'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'ranking_method' => $row['ranking_method'] ?? 'MEAN',
            'best_student' => $best_student,
            'status' => 'Generated'
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $reports,
        'count' => count($reports),
        'cache_timestamp' => time(),
        'message' => count($reports) . ' report(s) found'
    ]);
    
} catch (Exception $e) {
    error_log("Fetch merged reports error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>