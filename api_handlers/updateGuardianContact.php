<?php
// This file handles the 'updateGuardianContact' API action.
// It assumes $dbh, $schoolId, and sendResponse() are available from the including script.

$input = json_decode(file_get_contents('php://input'), true);

$student_id = $input['student_id'] ?? null;
$ContactNo = trim($input['ContactNo'] ?? '');

if (empty($student_id) || empty($ContactNo)) {
    sendResponse(null, 'error', 'Missing required fields: Student ID and Contact Number are mandatory for guardian update.');
    exit();
}

try {
    // Global Guardian Contact Number Uniqueness Check (excluding current student)
    $stmt_check_contact = $dbh->prepare("SELECT id, school_id FROM tblstudents WHERE ContactNo = :contactno AND id != :student_id LIMIT 1");
    $stmt_check_contact->bindParam(':contactno', $ContactNo, PDO::PARAM_STR);
    $stmt_check_contact->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt_check_contact->execute();
    $existingContact = $stmt_check_contact->fetch(PDO::FETCH_ASSOC);

    if ($existingContact) {
        sendResponse([], 'error', 'Error: Guardian Contact Number "' . htmlspecialchars($ContactNo) . '" is already registered to another student (ID: ' . htmlspecialchars($existingContact['id']) . ', School ID: ' . htmlspecialchars($existingContact['school_id']) . '). Contact numbers must be unique globally.');
        exit();
    }

    // Ensure student belongs to the current school before updating
    $sql = "UPDATE tblstudents SET ContactNo = :ContactNo, UpdationDate = NOW() WHERE id = :student_id AND school_id = :school_id";
    $query = $dbh->prepare($sql);
    $query->bindParam(':ContactNo', $ContactNo, PDO::PARAM_STR);
    $query->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->execute();

    if ($query->rowCount() > 0) {
        sendResponse(null, 'success', 'Guardian contact updated successfully!');
    } else {
        sendResponse(null, 'info', 'Failed to update guardian contact or no changes were made.');
    }
} catch (PDOException $e) {
    error_log("Database error in updateGuardianContact: " . $e->getMessage());
     if ($e->getCode() == '23000') {
        sendResponse([], 'error', 'Database Error: A duplicate Contact Number already exists.');
    } else {
        sendResponse(null, 'error', 'Database error: ' . $e->getMessage());
    }
}
exit; // Important: Exit after sending response
?>
