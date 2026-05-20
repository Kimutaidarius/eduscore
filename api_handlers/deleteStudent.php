<?php
// This file handles the 'deleteStudent' API action.
// It assumes $dbh, $schoolId, and sendResponse() are available from the including script.

$data = json_decode(file_get_contents('php://input'), true);
$student_id = $data['student_id'] ?? null;

if (!$student_id) {
    sendResponse([], 'error', 'Student ID is required for deletion.');
    exit;
}

try {
    // Ensure only students from the current school can be deleted
    $sql = "DELETE FROM tblstudents WHERE id = :student_id AND school_id = :school_id";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        sendResponse([], 'success', 'Student deleted successfully.');
    } else {
        sendResponse([], 'error', 'Student not found or could not be deleted (might not belong to your school).');
    }

} catch (PDOException $e) {
    error_log("Error deleting student: " . $e->getMessage());
    sendResponse([], 'error', 'Database error while deleting student.');
}
exit; // Important: Exit after sending response
?>
