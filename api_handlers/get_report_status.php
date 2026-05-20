<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    http_response_code(500);
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

$response = [
    'success' => false,
    'message' => 'Report status not found.',
    'data' => null
];

try {
    // Config & DB connection
    $configPath = __DIR__ . '/../includes/config.php';
    if (!file_exists($configPath)) {
        throw new Exception("Configuration file not found at $configPath");
    }
    require_once($configPath);

    if (!isset($dbh)) {
        throw new Exception("Database connection (dbh) not established.");
    }

    // Validate input
    if (!isset($_GET['report_id']) || !is_numeric($_GET['report_id'])) {
        http_response_code(400);
        throw new Exception("Missing or invalid 'report_id' parameter.");
    }
    $reportId = intval($_GET['report_id']);
    if ($reportId <= 0) {
        http_response_code(400);
        throw new Exception("Invalid report ID provided.");
    }

    // Fetch report info (including updated_at if exists)
    $sql = "SELECT batch_status, report_files_json, updated_at FROM tblreportconfigurations WHERE id = :report_id";
    $query = $dbh->prepare($sql);
    $query->bindParam(':report_id', $reportId, PDO::PARAM_INT);
    $query->execute();

    $report = $query->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        throw new Exception("Report batch with ID $reportId not found.");
    }

    // Decode report files JSON safely
    $files = json_decode($report['report_files_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $files = null; // fallback
    }

    // Compose response data
    $response['success'] = true;
    $response['message'] = "Report status fetched successfully.";
    $response['data'] = [
        'status' => $report['batch_status'],  // e.g. pending, in_progress, completed, failed
        'files' => $files,                     // array or null
        'last_updated' => isset($report['updated_at']) ? $report['updated_at'] : null
    ];

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = "Database error: " . $e->getMessage();
    error_log("get_report_status.php: Database error: " . $e->getMessage());
} catch (Exception $e) {
    if (!http_response_code()) {
        http_response_code(500);
    }
    $response['message'] = "Error: " . $e->getMessage();
    error_log("get_report_status.php: Error: " . $e->getMessage());
}

echo json_encode($response);
exit();
