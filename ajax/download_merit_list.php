<?php
session_start();
require_once '../config/config.php';

$reportId = $_GET['report_id'] ?? 0;

// Find the latest merit list PDF for this report
$filepattern = '../generated_reports/merit_list_' . $reportId . '_*.pdf';
$files = glob($filepattern);

if (!empty($files)) {
    $latestFile = max($files);
    $filename = basename($latestFile);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($latestFile));
    
    readfile($latestFile);
    exit;
} else {
    echo "Merit list not found. Please regenerate it.";
}
?>