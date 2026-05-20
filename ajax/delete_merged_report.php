<?php
// ajax/delete_merged_report.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$school_id = (int)$_SESSION['school_id'];
$report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;

if (!$report_id) {
    echo json_encode(['success' => false, 'error' => 'Report ID required']);
    exit;
}

try {
    $deleted = false;
    
    // Try database first
    $tableCheck = $conn->query("SHOW TABLES LIKE 'merged_reports'");
    if ($tableCheck->num_rows > 0) {
        $stmt = $conn->prepare("SELECT pdf_url FROM merged_reports WHERE id = ? AND school_id = ?");
        $stmt->bind_param("ii", $report_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        $stmt->close();
        
        if ($report) {
            // Delete physical file
            if (!empty($report['pdf_url'])) {
                $filepath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($report['pdf_url'], '/');
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
            
            // Delete database record
            $stmt = $conn->prepare("DELETE FROM merged_reports WHERE id = ? AND school_id = ?");
            $stmt->bind_param("ii", $report_id, $school_id);
            $stmt->execute();
            $stmt->close();
            $deleted = true;
        }
    }
    
    // Fallback: try directory scan
    if (!$deleted) {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/merged_reports/' . $school_id;
        if (is_dir($dir)) {
            $files = glob($dir . '/*.pdf');
            foreach ($files as $file) {
                if (crc32(basename($file)) == $report_id) {
                    unlink($file);
                    $deleted = true;
                    break;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => $deleted,
        'message' => $deleted ? 'Report deleted successfully' : 'Report not found'
    ]);
    
} catch (Exception $e) {
    error_log("Delete merged report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();