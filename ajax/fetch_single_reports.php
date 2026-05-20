<?php
// ajax/fetch_single_reports.php - Fetch single student report cards
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}
$conn->set_charset("utf8mb4");

$school_id = (int)$_SESSION['school_id'];

// Get input parameters
$class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
$stream_id = isset($_POST['stream_id']) ? (int)$_POST['stream_id'] : 0;
$exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
$term_id = isset($_POST['term_id']) ? (int)$_POST['term_id'] : 0;
$academic_year = isset($_POST['academic_year']) ? $_POST['academic_year'] : '';

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

try {
    // Check if single_report_cards table exists, if not check single_reports directory
    $table_check = $conn->query("SHOW TABLES LIKE 'single_report_cards'");
    
    if ($table_check->num_rows > 0) {
        // Query from database table
        $sql = "
            SELECT 
                r.id,
                r.school_id,
                r.class_id,
                r.stream_id,
                r.exam_id,
                r.term_id,
                r.academic_year,
                r.student_id,
                r.pdf_url,
                r.pages,
                r.created_at,
                r.status,
                'single' as report_type,
                s.AdmNo as student_adm,
                CONCAT(
                    TRIM(s.FirstName), ' ', 
                    COALESCE(NULLIF(TRIM(s.SecondName), ''), ''), 
                    CASE WHEN TRIM(s.LastName) IS NOT NULL AND TRIM(s.LastName) != '' 
                         THEN CONCAT(' ', TRIM(s.LastName)) ELSE '' 
                    END
                ) as student_name,
                c.class_level as class_name,
                e.examname as exam_name,
                t.term_name,
                r.pages as students_processed,
                NULL as mean_score,
                NULL as stream_name
            FROM single_report_cards r
            LEFT JOIN tblstudents s ON r.student_id = s.id
            LEFT JOIN tblclasses c ON r.class_id = c.id
            LEFT JOIN tblexam e ON r.exam_id = e.id
            LEFT JOIN tblterms t ON r.term_id = t.id
            WHERE r.school_id = ?
            AND r.class_id = ?
        ";
        
        $params = [$school_id, $class_id];
        $types = "ii";
        
        if ($stream_id > 0) {
            $sql .= " AND r.stream_id = ?";
            $params[] = $stream_id;
            $types .= "i";
        }
        
        if ($exam_id > 0) {
            $sql .= " AND r.exam_id = ?";
            $params[] = $exam_id;
            $types .= "i";
        }
        
        if ($term_id > 0) {
            $sql .= " AND r.term_id = ?";
            $params[] = $term_id;
            $types .= "i";
        }
        
        if (!empty($academic_year)) {
            $sql .= " AND r.academic_year = ?";
            $params[] = $academic_year;
            $types .= "s";
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        $stmt->close();
        
    } else {
        // No database table, scan the single_reports directory for PDF files
        $reports = [];
        $single_reports_dir = $_SERVER['DOCUMENT_ROOT'] . '/single_reports/' . $school_id;
        
        if (is_dir($single_reports_dir)) {
            $files = glob($single_reports_dir . '/*.pdf');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $web_url = '/single_reports/' . $school_id . '/' . $filename;
                $file_time = filemtime($file);
                
                // Try to extract student ID from filename (format: report_NAME_ID_TIMESTAMP.pdf)
                $student_id = null;
                $student_name = '';
                if (preg_match('/report_(.+?)_(\d+)_\d+\.pdf/', $filename, $matches)) {
                    $student_name = str_replace('_', ' ', $matches[1]);
                    $student_id = (int)$matches[2];
                }
                
                // Get file size as pages estimate (rough: 1 page per 50KB)
                $file_size_kb = filesize($file) / 1024;
                $estimated_pages = max(1, ceil($file_size_kb / 50));
                
                $reports[] = [
                    'id' => crc32($filename),
                    'school_id' => $school_id,
                    'class_id' => $class_id,
                    'stream_id' => $stream_id,
                    'exam_id' => $exam_id,
                    'term_id' => $term_id,
                    'academic_year' => $academic_year,
                    'student_id' => $student_id,
                    'pdf_url' => $web_url,
                    'pages' => $estimated_pages,
                    'created_at' => date('Y-m-d H:i:s', $file_time),
                    'status' => 'Generated',
                    'report_type' => 'single',
                    'student_adm' => '',
                    'student_name' => $student_name,
                    'class_name' => '',
                    'exam_name' => '',
                    'term_name' => '',
                    'students_processed' => 1,
                    'mean_score' => null,
                    'stream_name' => ''
                ];
            }
            
            // Sort by date (newest first)
            usort($reports, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $reports,
        'total' => count($reports),
        'message' => count($reports) . ' single reports found'
    ]);
    
} catch (Exception $e) {
    error_log("Error in fetch_single_reports.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reports: ' . $e->getMessage()
    ]);
}

$conn->close();
?>