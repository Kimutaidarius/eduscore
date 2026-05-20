<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Error handler to return JSON
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error: $message in $file on line $line",
        'type' => 'php_error',
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);
    exit();
});

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Read raw input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// If JSON failed to decode, fallback to $_POST
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    $data = $_POST;
}

// Extract variables safely
$reportId = isset($data['report_id']) ? intval($data['report_id']) : 0;
$schoolId = isset($data['school_id']) ? intval($data['school_id']) : 0;

try {
    if (!file_exists('../includes/config.php')) {
        throw new Exception("Configuration file not found at ../includes/config.php. Please check the path.");
    }
    require_once('../includes/config.php');

    if (!isset($dbh)) {
        throw new Exception("Database connection (dbh) not established in config.php.");
    }

    if ($reportId === 0 || $schoolId === 0) {
        throw new Exception("Missing or invalid report ID or school ID.");
    }

    // Authorization check
    if (!isset($_SESSION['school_id']) || $_SESSION['school_id'] != $schoolId) {
        throw new Exception("Unauthorized access: You are not authorized to delete reports for this school.");
    }

    // Fetch and delete files if present
    $fetchSql = "SELECT report_files_json FROM tblreportconfigurations WHERE id = :report_id AND school_id = :school_id";
    $fetchQuery = $dbh->prepare($fetchSql);
    $fetchQuery->bindParam(':report_id', $reportId, PDO::PARAM_INT);
    $fetchQuery->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $fetchQuery->execute();
    $report = $fetchQuery->fetch(PDO::FETCH_ASSOC);

    if ($report) {
        $reportFiles = json_decode($report['report_files_json'], true);
        if (is_array($reportFiles) && !empty($reportFiles)) {
            $baseDir = dirname(__DIR__) . '/';
            foreach ($reportFiles as $file) {
                if (!empty($file['path'])) {
                    $filePath = $baseDir . $file['path'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
        }
    }

    // Delete DB record
    $deleteSql = "DELETE FROM tblreportconfigurations WHERE id = :report_id AND school_id = :school_id";
    $deleteQuery = $dbh->prepare($deleteSql);
    $deleteQuery->bindParam(':report_id', $reportId, PDO::PARAM_INT);
    $deleteQuery->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $deleteQuery->execute();

    if ($deleteQuery->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = "Report batch and associated files deleted successfully.";
    } else {
        $response['message'] = "Failed to delete report batch. It might not exist or you don't have permission.";
    }

} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
exit();
