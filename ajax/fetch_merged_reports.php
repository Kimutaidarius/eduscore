<?php
// ajax/fetch_merged_reports.php - FETCH ALL REPORTS (SINGLE & BULK)
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
$report_type = $input['report_type'] ?? 'all';

$class_id_str = (string)$class_id;

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $reports = [];
    
    // Fetch BULK reports from bulk_reports table (matches the generate_bulk_reports.php)
    if ($report_type == 'all' || $report_type == 'bulk') {
        $bulk_query = "
            SELECT 
                br.id,
                br.school_id,
                br.class_id,
                br.stream_id,
                br.exam_id,
                br.term_id,
                br.academic_year,
                br.pdf_url,
                br.total_students,
                br.total_pages,
                br.created_at,
                c.class_level as class_name,
                s.stream_name,
                e.examname as exam_name,
                t.term_name,
                'bulk' as report_type
            FROM bulk_reports br
            LEFT JOIN tblclasses c ON CAST(br.class_id AS UNSIGNED) = c.id
            LEFT JOIN tblstreams s ON br.stream_id = s.id
            LEFT JOIN tblexam e ON br.exam_id = e.id
            LEFT JOIN tblterms t ON br.term_id = t.id
            WHERE br.school_id = ?
        ";
        
        $params = [$school_id];
        $types = "i";
        
        if ($class_id > 0) {
            $bulk_query .= " AND br.class_id = ?";
            $params[] = $class_id_str;
            $types .= "s";
        }
        
        if ($stream_id > 0) {
            $bulk_query .= " AND br.stream_id = ?";
            $params[] = $stream_id;
            $types .= "i";
        }
        
        if ($exam_id > 0) {
            $bulk_query .= " AND br.exam_id = ?";
            $params[] = $exam_id;
            $types .= "i";
        }
        
        if ($term_id > 0) {
            $bulk_query .= " AND br.term_id = ?";
            $params[] = $term_id;
            $types .= "i";
        }
        
        if (!empty($academic_year)) {
            $bulk_query .= " AND br.academic_year = ?";
            $params[] = $academic_year;
            $types .= "s";
        }
        
        $bulk_query .= " ORDER BY br.created_at DESC";
        
        $stmt = $conn->prepare($bulk_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reports[] = [
                'id' => $row['id'],
                'report_type' => 'bulk',
                'class_id' => $row['class_id'],
                'class_name' => $row['class_name'] ?? 'N/A',
                'stream_id' => $row['stream_id'],
                'stream_name' => $row['stream_name'] ?? 'All Streams',
                'exam_id' => $row['exam_id'],
                'exam_name' => $row['exam_name'] ?? 'N/A',
                'term_id' => $row['term_id'],
                'term_name' => $row['term_name'] ?? 'N/A',
                'academic_year' => $row['academic_year'] ?? 'N/A',
                'pdf_url' => $row['pdf_url'],
                'total_students' => $row['total_students'],
                'total_pages' => $row['total_pages'],
                'created_at' => $row['created_at'],
                'description' => "Bulk Report - " . ($row['total_students'] ?? 0) . " students, " . ($row['total_pages'] ?? 0) . " pages"
            ];
        }
        $stmt->close();
    }
    
    // Fetch SINGLE reports from report_cards table
    if ($report_type == 'all' || $report_type == 'single') {
        $single_query = "
            SELECT 
                rc.id,
                rc.school_id,
                rc.class_id,
                rc.stream_id,
                rc.exam_id,
                rc.term_id,
                rc.academic_year,
                rc.pdf_url,
                rc.mean_score,
                rc.grade,
                rc.status,
                rc.created_at,
                rc.student_id,
                c.class_level as class_name,
                s.stream_name,
                e.examname as exam_name,
                t.term_name,
                CONCAT(st.FirstName, ' ', COALESCE(st.SecondName, ''), ' ', COALESCE(st.LastName, '')) as student_name,
                st.AdmNo as admission_no,
                'single' as report_type
            FROM report_cards rc
            LEFT JOIN tblclasses c ON CAST(rc.class_id AS UNSIGNED) = c.id
            LEFT JOIN tblstreams s ON rc.stream_id = s.id
            LEFT JOIN tblexam e ON rc.exam_id = e.id
            LEFT JOIN tblterms t ON rc.term_id = t.id
            LEFT JOIN tblstudents st ON rc.student_id = st.id
            WHERE rc.school_id = ?
        ";
        
        $params = [$school_id];
        $types = "i";
        
        if ($class_id > 0) {
            $single_query .= " AND rc.class_id = ?";
            $params[] = $class_id_str;
            $types .= "s";
        }
        
        if ($stream_id > 0) {
            $single_query .= " AND rc.stream_id = ?";
            $params[] = $stream_id;
            $types .= "i";
        }
        
        if ($exam_id > 0) {
            $single_query .= " AND rc.exam_id = ?";
            $params[] = $exam_id;
            $types .= "i";
        }
        
        if ($term_id > 0) {
            $single_query .= " AND rc.term_id = ?";
            $params[] = $term_id;
            $types .= "i";
        }
        
        if (!empty($academic_year)) {
            $single_query .= " AND rc.academic_year = ?";
            $params[] = $academic_year;
            $types .= "s";
        }
        
        $single_query .= " ORDER BY rc.created_at DESC";
        
        $stmt = $conn->prepare($single_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reports[] = [
                'id' => $row['id'],
                'report_type' => 'single',
                'class_id' => $row['class_id'],
                'class_name' => $row['class_name'] ?? 'N/A',
                'stream_id' => $row['stream_id'],
                'stream_name' => $row['stream_name'] ?? 'All Streams',
                'exam_id' => $row['exam_id'],
                'exam_name' => $row['exam_name'] ?? 'N/A',
                'term_id' => $row['term_id'],
                'term_name' => $row['term_name'] ?? 'N/A',
                'academic_year' => $row['academic_year'] ?? 'N/A',
                'pdf_url' => $row['pdf_url'],
                'student_id' => $row['student_id'],
                'student_name' => $row['student_name'] ?? 'N/A',
                'admission_no' => $row['admission_no'] ?? 'N/A',
                'mean_score' => $row['mean_score'],
                'grade' => $row['grade'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'description' => "Report Card for " . ($row['student_name'] ?? 'Student') . " - Score: " . ($row['mean_score'] ?? 'N/A') . "% - Grade: " . ($row['grade'] ?? 'N/A')
            ];
        }
        $stmt->close();
    }
    
    // Sort by created_at descending
    usort($reports, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    echo json_encode([
        'success' => true,
        'data' => $reports,
        'count' => count($reports),
        'bulk_count' => count(array_filter($reports, function($r) { return $r['report_type'] == 'bulk'; })),
        'single_count' => count(array_filter($reports, function($r) { return $r['report_type'] == 'single'; })),
        'cache_timestamp' => time(),
        'message' => count($reports) . ' report(s) found'
    ]);
    
} catch (Exception $e) {
    error_log("Fetch reports error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>