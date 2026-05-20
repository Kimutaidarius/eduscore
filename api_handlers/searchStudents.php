<?php
// This file handles the 'searchStudents' API action.
// It assumes $dbh, $schoolId, and sendResponse() are available from the including script.

$searchTerm = '%' . trim($_REQUEST['search_term'] ?? '') . '%';

if (empty(trim($_REQUEST['search_term'] ?? ''))) {
     sendResponse([], 'error', 'Search term cannot be empty.');
     exit();
}

try {
    $stmt = $dbh->prepare("
        SELECT
            s.id AS student_pk_id,
            s.FirstName,
            s.SecondName,
            s.LastName,
            s.Gender,
            s.AdmNo,
            s.Nemis,
            s.class_id AS class_fk_id,
            s.StreamId,
            s.Status,
            s.ContactNo AS GuardiansContact,
            tc.academic_level AS AcademicLevelName,
            tc.class_level AS ClassName,
            ts.stream_name AS StreamName
        FROM
            tblstudents s
        LEFT JOIN
            tblclasses tc ON s.class_id = tc.id
        LEFT JOIN
            tblstreams ts ON s.StreamId = ts.id
        WHERE
            s.school_id = :school_id AND (
            s.AdmNo LIKE :search_term OR
            s.FirstName LIKE :search_term OR
            s.SecondName LIKE :search_term OR
            s.LastName LIKE :search_term OR
            s.Nemis LIKE :search_term OR
            s.ContactNo LIKE :search_term
        )
        ORDER BY tc.academic_level ASC, tc.class_level ASC, s.LastName ASC, s.FirstName ASC
    ");
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->bindParam(':search_term', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse($students, 'success', 'Students searched successfully.');
} catch (PDOException $e) {
    error_log("Database error searching students: " . $e->getMessage());
    sendResponse([], 'error', 'Failed to search students: ' . $e->getMessage());
}
exit; // Important: Exit after sending response
?>
