<?php
// ajax/save_merged_report.php
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
$academic_year = $input['academic_year'] ?? date('Y');
$pdf_url = $input['pdf_url'] ?? '';
$mean_score = $input['mean_score'] ?? 0;
$grade = $input['mean_grade'] ?? 'N/A';
$subjects_list = $input['subjects_list'] ?? [];
$ranking_method = $input['ranking_method'] ?? 'MEAN';

// Convert class_id to string if your table expects VARCHAR
$class_id_str = (string)$class_id;

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $subjects_json = json_encode($subjects_list);
    
    // Check if record exists
    $check_stmt = $conn->prepare("
        SELECT id FROM merged_reports 
        WHERE school_id = ? AND class_id = ? AND stream_id = ? 
        AND exam_id = ? AND term_id = ? AND academic_year = ?
    ");
    $check_stmt->bind_param("isiiis", $school_id, $class_id_str, $stream_id, $exam_id, $term_id, $academic_year);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $existing = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        // Update existing
        $stmt = $conn->prepare("
            UPDATE merged_reports 
            SET mean_score = ?, grade = ?, pdf_url = ?, subjects = ?, ranking_method = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("dssssi", $mean_score, $grade, $pdf_url, $subjects_json, $ranking_method, $existing['id']);
        $stmt->execute();
        $report_id = $existing['id'];
        $stmt->close();
    } else {
        // Insert new
        $stmt = $conn->prepare("
            INSERT INTO merged_reports 
            (school_id, class_id, stream_id, exam_id, term_id, academic_year, mean_score, grade, pdf_url, subjects, ranking_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isiiisdssss", $school_id, $class_id_str, $stream_id, $exam_id, $term_id, $academic_year, $mean_score, $grade, $pdf_url, $subjects_json, $ranking_method);
        $stmt->execute();
        $report_id = $stmt->insert_id;
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'merged_report_id' => $report_id,
        'message' => 'Merged report saved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Save merged report error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>