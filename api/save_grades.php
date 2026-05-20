<?php
// Start the session
session_start();

// Enable error reporting for debugging (keep this ON during development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Adjust path as necessary - Assuming config.php contains $dbh PDO connection
include('../includes/config.php'); 

header('Content-Type: application/json');

$response = ["success" => false, "message" => "", "error" => ""];

// Get the JSON data from the request body
$json_data = file_get_contents('php://input');

// --- Debugging: Log the raw input received ---
// In a production environment, use error_log() or a proper logging system.
// For development, you can temporarily echo it, but remove this line for production.
// error_log("save_grades.php received raw input: " . $json_data); 

$receivedData = json_decode($json_data, true); // Decode into an associative array

// Check for JSON decoding errors first
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['error'] = 'Invalid JSON received: ' . json_last_error_msg() . '. Raw input was: ' . $json_data;
    echo json_encode($response);
    exit();
}

// --- EXPECTATION: The JS sends { "grades": [...] } ---
// Therefore, we always expect $receivedData to be an array containing a 'grades' key.
if (!isset($receivedData['grades']) || !is_array($receivedData['grades'])) {
    $response['error'] = "Invalid data format. Expected a 'grades' array in the JSON payload.";
    // Log for debugging what was actually received
    error_log("save_grades.php: 'grades' array not found or not an array. Received data: " . json_encode($receivedData));
    echo json_encode($response);
    exit();
}

$gradesToProcess = $receivedData['grades'];

if (empty($gradesToProcess)) {
    $response['success'] = true; // No error, just nothing to do
    $response['message'] = "No grading scale entries provided to save.";
    echo json_encode($response);
    exit();
}

// Determine classId, streamId, and subjectId from the first grade entry
// This assumes all grades in a batch belong to the same class/stream/subject
// This is critical for the "delete all then insert all" strategy.
$firstGrade = $gradesToProcess[0];
$classId = $firstGrade['class_id'] ?? null;
$streamId = $firstGrade['stream_id'] ?? null;
$subjectId = $firstGrade['subject_id'] ?? null;

// Crucial check: Ensure that the essential IDs are present BEFORE attempting any DB operations
if (!$classId || !$streamId || !$subjectId) {
    $response['error'] = "Error saving grades: Missing Class ID, Stream ID, or Subject ID in the first grade entry.";
    // For debugging, log the raw input that led to this error
    error_log("save_grades.php: Missing essential IDs. First grade entry: " . json_encode($firstGrade));
    echo json_encode($response);
    exit();
}

// Proceed with the transaction and database operations
try {
    // Ensure $dbh is a valid PDO object from config.php
    if (!($dbh instanceof PDO)) {
        throw new Exception("Database connection (PDO object \$dbh) not properly initialized.");
    }

    $dbh->beginTransaction();

    // 1. Delete existing data for this specific class_id, stream_id, and subject_id
    // This assumes a "replace all" strategy for the given context (class, stream, subject).
    // This is appropriate for managing a single set of grading scale definitions.
    $deleteSql = "DELETE FROM tblsubjectgrading WHERE class_id = :class_id AND stream_id = :stream_id AND subject_id = :subject_id";
    $deleteStmt = $dbh->prepare($deleteSql);
    $deleteStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $deleteStmt->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    $deleteStmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $deleteStmt->execute();

    // 2. Prepare the INSERT statement for new/updated data
    // Assuming 'id' is AUTO_INCREMENT and will be ignored for inserts.
    // If you need to handle updates on existing specific 'id's without deleting the whole set,
    // your client-side logic for sending data and PHP logic for UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
    // would need to be different. For grading scales, delete-then-insert is often simpler.
    $insertSql = "INSERT INTO tblsubjectgrading
                  (class_id, subject_id, stream_id, grade, lower_limit, upper_limit, points, remarks, grade_alias, principal_remarks)
                  VALUES (:class_id, :subject_id, :stream_id, :grade, :lower_limit, :upper_limit, :points, :remarks, :grade_alias, :principal_remarks)";
    $insertStmt = $dbh->prepare($insertSql);

    $rowCount = 0;
    foreach ($gradesToProcess as $gradeData) {
        // Use null coalescing operator (?? '') to safely get values and default to empty string/0
        // This avoids PHP warnings if a key is missing from a grade object
        $grade = $gradeData['grade'] ?? '';
        $lower_limit = (int)($gradeData['lower_limit'] ?? 0); // Cast to int
        $upper_limit = (int)($gradeData['upper_limit'] ?? 0); // Cast to int
        $points = (int)($gradeData['points'] ?? 0);           // Cast to int
        $remarks = $gradeData['remarks'] ?? '';
        $grade_alias = $gradeData['grade_alias'] ?? '';
        $principal_remarks = $gradeData['principal_remarks'] ?? '';

        // Bind parameters using the IDs determined for the batch
        $insertStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $insertStmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        $insertStmt->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
        $insertStmt->bindParam(':grade', $grade, PDO::PARAM_STR);
        $insertStmt->bindParam(':lower_limit', $lower_limit, PDO::PARAM_INT);
        $insertStmt->bindParam(':upper_limit', $upper_limit, PDO::PARAM_INT);
        $insertStmt->bindParam(':points', $points, PDO::PARAM_INT);
        $insertStmt->bindParam(':remarks', $remarks, PDO::PARAM_STR);
        $insertStmt->bindParam(':grade_alias', $grade_alias, PDO::PARAM_STR);
        $insertStmt->bindParam(':principal_remarks', $principal_remarks, PDO::PARAM_STR);

        // Execute the insert for each grade entry
        $insertStmt->execute();
        $rowCount++;
    }

    $dbh->commit(); // Commit the transaction if all inserts were successful
    $response['success'] = true;
    $response['message'] = "Grading scale saved successfully. " . $rowCount . " entries processed.";

} catch (Exception $e) {
    // Rollback the transaction on any error
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    $response['error'] = "Error saving grading scale: " . $e->getMessage();
    // Log the detailed error for server-side debugging
    error_log("Error in api/save_grades.php: " . $e->getMessage() . " on line " . $e->getLine());
}

echo json_encode($response);
exit();
?>