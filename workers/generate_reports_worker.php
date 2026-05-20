<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../libs/fpdf/fpdf.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

function logMsg($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

while (true) {
    try {
        $sql = "SELECT * FROM tblreportconfigurations WHERE batch_status = 'pending' ORDER BY id LIMIT 1";
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            logMsg("No pending reports to process.");
            sleep(5);
            continue;
        }

        $reportId = $report['id'];
        logMsg("Starting report batch ID $reportId");

        // Mark as in_progress
        $update = $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'in_progress', updated_at = NOW() WHERE id = :id");
        $update->execute([':id' => $reportId]);

        // Fetch students, subjects, exams, scores, etc...
        // Add your existing logic here with added logging...

        logMsg("Generating PDFs for " . count($reportData) . " students.");
        // PDF generation loop here

        // Save PDF file paths in DB and mark completed
        $filesJson = json_encode($pdfFiles);
        $update = $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'completed', report_files_json = :files_json, updated_at = NOW() WHERE id = :id");
        $update->execute([':files_json' => $filesJson, ':id' => $reportId]);

        logMsg("Report batch $reportId generated successfully. PDFs saved.");

    } catch (Exception $e) {
        logMsg("Error generating report: " . $e->getMessage());
        if (isset($reportId)) {
            $updateFail = $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'failed', updated_at = NOW() WHERE id = :id");
            $updateFail->execute([':id' => $reportId]);
        }
    }

    // Sleep a few seconds before checking for next batch
    sleep(5);
}
