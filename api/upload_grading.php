<?php
// Start the session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // Adjust path as necessary

header('Content-Type: application/json');

$response = ["success" => false, "message" => "", "error" => ""];

try {
    // --- CRITICAL: Validate and get school_id from session ---
    if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id']) || !is_numeric($_SESSION['school_id'])) {
        throw new Exception("Security Error: Your Session has Expired.");
    }
    $schoolId = intval($_SESSION['school_id']);

    // Check if a file was uploaded and necessary IDs are present
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK ||
        !isset($_POST['class_id']) || empty($_POST['class_id']) ||
        !isset($_POST['stream_id']) || (!isset($_POST['stream_id']) && $_POST['stream_id'] !== '0') || // Check for stream_id, allowing '0'
        !isset($_POST['subject_id']) || empty($_POST['subject_id'])) {
        throw new Exception("No CSV file uploaded or missing Class/Stream/Subject ID.");
    }

    $classId = intval($_POST['class_id']);
    $streamId = intval($_POST['stream_id']); // Get streamId (will be 0 for 'All Streams')
    $subjectId = intval($_POST['subject_id']);
    $csvFile = $_FILES['csv_file']['tmp_name'];
    $mimeType = mime_content_type($csvFile);

    // Convert streamId 0 to NULL for database if your column is NULLable for 'All Streams'
    // If your DB uses 0 for 'All Streams', remove this line.
    $streamIdForDb = ($streamId === 0) ? null : $streamId;

    // Validate file type
    if (!in_array($mimeType, ['text/csv', 'text/plain', 'application/vnd.ms-excel'])) {
        throw new Exception("Invalid file type. Only CSV files are allowed.");
    }

    if (!isset($dbh)) {
        throw new Exception("Technical Error please try again!!");
    }

    // --- DEBUGGING LOGS START ---
    error_log("--- upload_grading.php Debug ---");
    error_log("School ID from Session: " . $schoolId);
    error_log("Received class_id: " . $classId);
    error_log("Received stream_id (from POST): " . $streamId);
    error_log("Stream ID (for DB): " . ($streamIdForDb === null ? 'NULL' : $streamIdForDb));
    error_log("Received subject_id: " . $subjectId);
    // --- DEBUGGING LOGS END ---


    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        // --- START OF INNER TRY BLOCK ---
        try {
            $dbh->beginTransaction(); // Start a transaction

            // 1. Delete existing data for this specific school_id, class_id, stream_id, and subject_id
            $deleteSql = "DELETE FROM tblsubjectgrading 
                          WHERE school_id = :school_id 
                          AND class_id = :class_id 
                          AND subject_id = :subject_id";

            if ($streamIdForDb !== null) {
                $deleteSql .= " AND stream_id = :stream_id";
            } else {
                $deleteSql .= " AND stream_id IS NULL";
            }

            $deleteStmt = $dbh->prepare($deleteSql);
            $deleteStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
            $deleteStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
            $deleteStmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
            if ($streamIdForDb !== null) {
                $deleteStmt->bindParam(':stream_id', $streamIdForDb, PDO::PARAM_INT);
            }
            $deleteStmt->execute();

            // 2. Prepare the INSERT statement
            $insertSql = "INSERT INTO tblsubjectgrading 
                          (school_id, class_id, subject_id, stream_id, grade, lower_limit, upper_limit, points, remarks, grade_alias, principal_remarks) 
                          VALUES (:school_id, :class_id, :subject_id, :stream_id, :grade, :lower_limit, :upper_limit, :points, :remarks, :grade_alias, :principal_remarks)";
            $insertStmt = $dbh->prepare($insertSql);

            $header = fgetcsv($handle); // Read the header row (and discard it)

            $rowCount = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Assuming CSV columns are: grade, lower_limit, upper_limit, points, remarks, grade_alias, principal_remarks
                $grade = $data[0] ?? '';
                $lower_limit = (int)($data[1] ?? 0);
                $upper_limit = (int)($data[2] ?? 0);
                $points = (int)($data[3] ?? 0);
                $remarks = $data[4] ?? '';
                $grade_alias = $data[5] ?? '';
                $principal_remarks = $data[6] ?? '';

                $insertStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
                $insertStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
                $insertStmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
                if ($streamIdForDb !== null) {
                    $insertStmt->bindParam(':stream_id', $streamIdForDb, PDO::PARAM_INT);
                } else {
                    $insertStmt->bindValue(':stream_id', null, PDO::PARAM_NULL);
                }
                $insertStmt->bindParam(':grade', $grade, PDO::PARAM_STR);
                $insertStmt->bindParam(':lower_limit', $lower_limit, PDO::PARAM_INT);
                $insertStmt->bindParam(':upper_limit', $upper_limit, PDO::PARAM_INT);
                $insertStmt->bindParam(':points', $points, PDO::PARAM_INT);
                $insertStmt->bindParam(':remarks', $remarks, PDO::PARAM_STR);
                $insertStmt->bindParam(':grade_alias', $grade_alias, PDO::PARAM_STR);
                $insertStmt->bindParam(':principal_remarks', $principal_remarks, PDO::PARAM_STR);
                
                $insertStmt->execute();
                $rowCount++;
            }

            $dbh->commit();
            $response['success'] = true;
            $response['message'] = "CSV data uploaded successfully. " . $rowCount . " rows inserted.";

        } catch (PDOException $e) { // <-- This catch belongs to the inner try
            $dbh->rollBack();
            $response['error'] = "Database error during upload: " . $e->getMessage();
            error_log("Database error in api/upload_grading.php: " . $e->getMessage());
        } finally { // <-- This finally belongs to the inner try
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        // --- END OF INNER TRY BLOCK ---
    } else {
        $response['error'] = "Failed to open the uploaded CSV file.";
    }
} catch (Exception $e) { // <-- This catch belongs to the outer try
    $response['error'] = "Application Error: " . $e->getMessage();
    error_log("Application error in api/upload_grading.php: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>