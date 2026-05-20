<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get report ID from POST data
$report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
$school_id = isset($_POST['school_id']) ? (int)$_POST['school_id'] : (int)$_SESSION['school_id'];

if (!$report_id) {
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit();
}

// Include config - this gives us $dbh PDO connection
require_once dirname(__DIR__) . '/includes/config.php';

// Use the PDO connection from config.php
global $dbh;

if (!$dbh) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    $pdf_url = null;
    $student_name = '';
    $class_name = '';
    $stream_name = '';
    $exam_name = '';
    $term_name = '';
    $academic_year = '';
    $admission_no = '';
    $exam_id = 0;
    $student_id = 0;
    $class_id = 0;
    $term_id = 0;

    // ============ GET REPORT DATA FROM MERITLIST FIRST ============
    $stmt = $dbh->prepare("
        SELECT m.*, 
               s.AdmNo as student_adm,
               s.id as student_id,
               s.FirstName, s.SecondName, s.LastName,
               s.ProfilePic,
               c.class_level as class_name,
               c.id as class_id,
               st.stream_name,
               e.examname as exam_name,
               e.id as exam_id,
               t.id as term_id,
               t.term_name,
               t.academic_year
        FROM tblmeritlist m
        LEFT JOIN tblstudents s ON m.student_id = s.id AND s.school_id = m.school_id
        LEFT JOIN tblclasses c ON m.class_id = c.id AND c.school_id = m.school_id
        LEFT JOIN tblstreams st ON m.stream_id = st.id AND st.school_id = m.school_id
        LEFT JOIN tblexam e ON m.exam_id = e.id AND e.school_id = m.school_id
        LEFT JOIN tblterms t ON m.term_id = t.id AND t.school_id = m.school_id
        WHERE m.id = ? AND m.school_id = ?
    ");
    $stmt->execute([$report_id, $school_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $student_name = trim($row['FirstName'] ?? '');
        if (!empty($row['SecondName'])) $student_name .= ' ' . trim($row['SecondName']);
        if (!empty($row['LastName'])) $student_name .= ' ' . trim($row['LastName']);
        if (empty($student_name)) $student_name = $row['student_name'] ?? 'Student';
        
        $class_name = $row['class_name'] ?? '';
        $stream_name = $row['stream_name'] ?? 'No Stream';
        $exam_name = $row['exam_name'] ?? '';
        $term_name = $row['term_name'] ?? '';
        $academic_year = $row['academic_year'] ?? '';
        $admission_no = $row['student_adm'] ?? '';
        $exam_id = (int)($row['exam_id'] ?? 0);
        $student_id = (int)($row['student_id'] ?? 0);
        $class_id = (int)($row['class_id'] ?? 0);
        $term_id = (int)($row['term_id'] ?? 0);
    }

    // ============ PRIORITY 1: CHECK REPORT_CARDS TABLE WITH ALL PARAMETERS ============
    if ($student_id > 0 && $exam_id > 0 && $class_id > 0 && $term_id > 0) {
        $stmt = $dbh->prepare("
            SELECT pdf_url, id, created_at
            FROM report_cards 
            WHERE student_id = ? AND exam_id = ? AND class_id = ? AND term_id = ? AND school_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$student_id, $exam_id, $class_id, $term_id, $school_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $pdf_url = $row['pdf_url'] ?? null;
        }
    }

    // ============ PRIORITY 2: CHECK REPORT_CARDS WITH STUDENT AND EXAM ONLY ============
    if (empty($pdf_url) && $student_id > 0 && $exam_id > 0) {
        $stmt = $dbh->prepare("
            SELECT pdf_url, id 
            FROM report_cards 
            WHERE student_id = ? AND exam_id = ? AND school_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$student_id, $exam_id, $school_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $pdf_url = $row['pdf_url'] ?? null;
        }
    }

    // ============ PRIORITY 3: CHECK REPORT_CARDS BY PATTERN MATCH ============
    if (empty($pdf_url)) {
        $search_pattern = '%' . $report_id . '%';
        $stmt = $dbh->prepare("
            SELECT pdf_url 
            FROM report_cards 
            WHERE school_id = ? AND pdf_url LIKE ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$school_id, $search_pattern]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $pdf_url = $row['pdf_url'] ?? null;
        }
    }

    // ============ PRIORITY 4: FILESYSTEM SCAN ============
    if (empty($pdf_url)) {
        $reports_dir = '../reports/';
        $student_reports_dir = '../student_reports/';
        $possible_dirs = [$reports_dir, $student_reports_dir];
        
        foreach ($possible_dirs as $base_dir) {
            if (!is_dir($base_dir)) continue;
            
            // Look in student_reports subdirectories
            if ($base_dir == $student_reports_dir && $school_id > 0 && $class_id > 0) {
                $specific_dir = $student_reports_dir . $school_id . '/' . $class_id . '/';
                if (is_dir($specific_dir)) {
                    $pattern = $specific_dir . 'report_' . $student_id . '_' . $exam_id . '_' . $term_id . '_*.pdf';
                    $matches = glob($pattern);
                    
                    if (!empty($matches)) {
                        usort($matches, function($a, $b) {
                            return filemtime($b) - filemtime($a);
                        });
                        $pdf_url = 'student_reports/' . $school_id . '/' . $class_id . '/' . basename($matches[0]);
                        break;
                    }
                }
            }
            
            // Find by student_id + exam_id + term_id
            if (empty($pdf_url) && $student_id > 0 && $exam_id > 0 && $term_id > 0) {
                $pattern = $base_dir . 'report_' . $student_id . '_' . $exam_id . '_' . $term_id . '_*.pdf';
                $matches = glob($pattern);
                
                if (!empty($matches)) {
                    usort($matches, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $pdf_url = ($base_dir == $reports_dir ? 'reports/' : 'student_reports/') . basename($matches[0]);
                    break;
                }
            }
            
            // Find by student_id + exam_id
            if (empty($pdf_url) && $student_id > 0 && $exam_id > 0) {
                $pattern = $base_dir . 'report_' . $student_id . '_' . $exam_id . '_*.pdf';
                $matches = glob($pattern);
                
                if (!empty($matches)) {
                    usort($matches, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $pdf_url = ($base_dir == $reports_dir ? 'reports/' : 'student_reports/') . basename($matches[0]);
                    break;
                }
            }
            
            // Find by admission number + exam ID
            if (empty($pdf_url) && !empty($admission_no) && $exam_id > 0) {
                $pattern = $base_dir . '*_' . $admission_no . '_' . $exam_id . '_*.pdf';
                $matches = glob($pattern);
                
                if (!empty($matches)) {
                    usort($matches, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $pdf_url = ($base_dir == $reports_dir ? 'reports/' : 'student_reports/') . basename($matches[0]);
                    break;
                }
            }
            
            // Find by student name slug + exam ID
            if (empty($pdf_url) && !empty($student_name) && $exam_id > 0) {
                $student_name_slug = preg_replace('/[^A-Za-z0-9]/', '_', $student_name);
                $student_name_slug = preg_replace('/_+/', '_', $student_name_slug);
                $student_name_slug = trim($student_name_slug, '_');
                
                $pattern = $base_dir . 'report_' . $student_name_slug . '_*_' . $exam_id . '_*.pdf';
                $matches = glob($pattern);
                
                if (!empty($matches)) {
                    usort($matches, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $pdf_url = ($base_dir == $reports_dir ? 'reports/' : 'student_reports/') . basename($matches[0]);
                    break;
                }
            }
            
            // Find by report ID in filename
            if (empty($pdf_url)) {
                $pattern = $base_dir . '*' . $report_id . '*.pdf';
                $matches = glob($pattern);
                
                if (!empty($matches)) {
                    usort($matches, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $pdf_url = ($base_dir == $reports_dir ? 'reports/' : 'student_reports/') . basename($matches[0]);
                    break;
                }
            }
        }
    }

    // ============ VERIFY THE PDF FILE EXISTS ============
    if (!empty($pdf_url)) {
        $full_path = '../' . ltrim($pdf_url, '/');
        if (!file_exists($full_path) || filesize($full_path) == 0) {
            $pdf_url = null;
        }
    }

    // ============ GET SCHOOL INFORMATION ============
    $stmt = $dbh->prepare("
        SELECT school_name, school_motto, school_email as email, school_phone, 
               COALESCE(school_logo, school_logo_url) as logo_path 
        FROM tblschoolinfo 
        WHERE id = ?
    ");
    $stmt->execute([$school_id]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    // ============ RETURN THE PDF URL ============
    if (!empty($pdf_url)) {
        // Ensure the URL is properly formatted
        if (strpos($pdf_url, 'http') !== 0) {
            if (strpos($pdf_url, 'reports/') !== 0 && strpos($pdf_url, 'student_reports/') !== 0) {
                $pdf_url = 'reports/' . ltrim($pdf_url, '/');
            }
        }
        
        echo json_encode([
            'success' => true,
            'pdf_url' => $pdf_url,
            'student_name' => $student_name,
            'class_name' => $class_name,
            'stream_name' => $stream_name,
            'exam_name' => $exam_name,
            'term_name' => $term_name,
            'academic_year' => $academic_year,
            'report_id' => $report_id,
            'message' => 'PDF URL retrieved successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No PDF file found for this report. Please generate the report card first.',
            'report_id' => $report_id,
            'debug' => [
                'student_name' => $student_name,
                'admission_no' => $admission_no,
                'exam_id' => $exam_id,
                'student_id' => $student_id,
                'class_id' => $class_id,
                'term_id' => $term_id
            ]
        ]);
    }

} catch (PDOException $e) {
    error_log("get_report_pdf_url.php PDO Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("get_report_pdf_url.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving PDF: ' . $e->getMessage()
    ]);
}
?>