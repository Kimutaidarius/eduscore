<?php
// This file handles the 'updateStudent' API action.
// It assumes $dbh, $schoolId, and sendResponse() are available from the including script.

$input = json_decode(file_get_contents('php://input'), true);

$student_id = $input['student_id'] ?? null;
$FirstName = trim($input['FirstName'] ?? '');
$SecondName = trim($input['SecondName'] ?? '');
$LastName = trim($input['LastName'] ?? '');
$Gender = trim($input['Gender'] ?? '');
$AdmNo = trim($input['AdmNo'] ?? '');
$Nemis = trim($input['Nemis'] ?? '');
$academic_level_string = trim($input['academic_level'] ?? '');
$class_fk_id = trim($input['class_id'] ?? ''); // This is the numeric ID of the class (class_id column)
$StreamId = trim($input['stream_id'] ?? '');
$Status = trim($input['Status'] ?? '');
$ContactNo = trim($input['ContactNo'] ?? ''); // Guardian contact number

if (empty($student_id) || empty($FirstName) || empty($SecondName) || empty($Gender) || empty($AdmNo) || empty($academic_level_string) || empty($class_fk_id) || empty($Status)) {
    sendResponse(null, 'error', 'Missing required student fields for update. (Student ID, First Name, Second Name, Gender, Admission No, Academic Level, Class, and Status are mandatory)');
}

try {
    // Verify student exists and belongs to the current school
    $stmtCheckStudent = $dbh->prepare("SELECT COUNT(*) FROM tblstudents WHERE id = :student_id AND school_id = :school_id");
    $stmtCheckStudent->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmtCheckStudent->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmtCheckStudent->execute();
    if ($stmtCheckStudent->fetchColumn() == 0) {
        sendResponse(null, 'error', 'Student not found or does not belong to your school.');
        exit();
    }

    // --- Global Admission Number Uniqueness Check (excluding current student) ---
    $stmt_check_admno = $dbh->prepare("SELECT id, school_id FROM tblstudents WHERE AdmNo = :admno AND id != :student_id LIMIT 1");
    $stmt_check_admno->bindParam(':admno', $AdmNo, PDO::PARAM_STR);
    $stmt_check_admno->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt_check_admno->execute();
    $existingAdmNo = $stmt_check_admno->fetch(PDO::FETCH_ASSOC);

    if ($existingAdmNo) {
        sendResponse([], 'error', 'Error: Admission Number "' . htmlspecialchars($AdmNo) . '" is already registered to another student (ID: ' . htmlspecialchars($existingAdmNo['id']) . ', School ID: ' . htmlspecialchars($existingAdmNo['school_id']) . '). Admission numbers must be unique globally.');
        exit();
    }

    // --- Global Guardian Contact Number Uniqueness Check (excluding current student, if provided) ---
    if (!empty($ContactNo)) {
        $stmt_check_contact = $dbh->prepare("SELECT id, school_id FROM tblstudents WHERE ContactNo = :contactno AND id != :student_id LIMIT 1");
        $stmt_check_contact->bindParam(':contactno', $ContactNo, PDO::PARAM_STR);
        $stmt_check_contact->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt_check_contact->execute();
        $existingContact = $stmt_check_contact->fetch(PDO::FETCH_ASSOC);

        if ($existingContact) {
            sendResponse([], 'error', 'Error: Guardian Contact Number "' . htmlspecialchars($ContactNo) . '" is already registered to another student (ID: ' . htmlspecialchars($existingContact['id']) . ', School ID: ' . htmlspecialchars($existingContact['school_id']) . '). Contact numbers must be unique globally.');
            exit();
        }
    }

    $stream_pk_id = !empty($StreamId) ? (int)$StreamId : null;

    // Validate StreamId if provided
    if (!empty($stream_pk_id)) {
        $stmt_validate_stream = $dbh->prepare("SELECT id FROM tblstreams WHERE id = :stream_id AND class_id = :class_id AND school_id = :school_id LIMIT 1");
        $stmt_validate_stream->bindParam(':stream_id', $stream_pk_id, PDO::PARAM_INT);
        $stmt_validate_stream->bindParam(':class_id', (int)$class_fk_id, PDO::PARAM_INT);
        $stmt_validate_stream->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt_validate_stream->execute();
        $valid_stream_id = $stmt_validate_stream->fetchColumn();

        if (!$valid_stream_id) {
            sendResponse([], 'error', 'Selected Stream is invalid or does not belong to the chosen Class or your school.');
            exit();
        }
    }

    $sql = "UPDATE tblstudents SET
                FirstName = :FirstName,
                SecondName = :SecondName,
                LastName = :LastName,
                Gender = :Gender,
                AdmNo = :AdmNo,
                Nemis = :Nemis,
                class_id = :class_fk_id,
                StreamId = :StreamId,
                Status = :Status,
                ContactNo = :ContactNo,
                UpdationDate = NOW()
            WHERE id = :student_id AND school_id = :school_id";

    $query = $dbh->prepare($sql);

    $query->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->bindParam(':FirstName', $FirstName, PDO::PARAM_STR);
    $query->bindParam(':SecondName', $SecondName, PDO::PARAM_STR);
    $query->bindValue(':LastName', !empty($LastName) ? $LastName : null, PDO::PARAM_STR);
    $query->bindParam(':Gender', $Gender, PDO::PARAM_STR);
    $query->bindParam(':AdmNo', $AdmNo, PDO::PARAM_STR);
    $query->bindValue(':Nemis', !empty($Nemis) ? $Nemis : null, PDO::PARAM_STR);
    $query->bindParam(':class_fk_id', (int)$class_fk_id, PDO::PARAM_INT);
    $query->bindValue(':StreamId', $stream_pk_id, PDO::PARAM_INT);
    $query->bindParam(':Status', $Status, PDO::PARAM_STR);
    $query->bindValue(':ContactNo', !empty($ContactNo) ? $ContactNo : null, PDO::PARAM_STR);

    $query->execute();

    if ($query->rowCount() > 0) {
        sendResponse(null, 'success', 'Student details updated successfully!');
    } else {
        sendResponse(null, 'info', 'No changes made to student details or student not found.');
    }

} catch (PDOException $e) {
    error_log("Database error in updateStudent: " . $e->getMessage());
    if ($e->getCode() == '23000') {
        sendResponse([], 'error', 'Database Error: A duplicate Admission Number or Contact Number already exists.');
    } else {
        sendResponse(null, 'error', 'Database error: ' . $e->getMessage());
    }
}
exit; // Important: Exit after sending response
?>
