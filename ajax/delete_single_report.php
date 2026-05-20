<?php
// ajax/delete_single_report.php - Delete a single student report card
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
$report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;

if ($report_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit();
}

try {
    $deleted = false;
    
    // Check if single_report_cards table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'single_report_cards'");
    
    if ($table_check->num_rows > 0) {
        // Get PDF URL before deleting
        $stmt = $conn->prepare("SELECT pdf_url FROM single_report_cards WHERE id = ? AND school_id = ?");
        $stmt->bind_param("ii", $report_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            // Delete the physical PDF file
            if (!empty($row['pdf_url'])) {
                $pdf_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($row['pdf_url'], '/');
                if (file_exists($pdf_path)) {
                    unlink($pdf_path);
                }
            }
            
            // Delete database record
            $stmt = $conn->prepare("DELETE FROM single_report_cards WHERE id = ? AND school_id = ?");
            $stmt->bind_param("ii", $report_id, $school_id);
            $stmt->execute();
            $stmt->close();
            
            $deleted = true;
        }
    }
    
    // Fallback: Try deleting from directory
    if (!$deleted) {
        $single_reports_dir = $_SERVER['DOCUMENT_ROOT'] . '/single_reports/' . $school_id;
        
        if (is_dir($single_reports_dir)) {
            $files = glob($single_reports_dir . '/*.pdf');
            
            foreach ($files as $file) {
                // Match by CRC32 ID (same as used in fetch_single_reports.php)
                if (crc32(basename($file)) === $report_id) {
                    unlink($file);
                    $deleted = true;
                    break;
                }
            }
        }
    }
    
    if ($deleted) {
        echo json_encode([
            'success' => true,
            'message' => 'Report deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Report not found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in delete_single_report.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting report: ' . $e->getMessage()
    ]);
}

$conn->close();