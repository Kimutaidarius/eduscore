<?php
// This file handles the 'filterStudents' API action.
// It assumes $dbh, $schoolId, and sendResponse() are available from the including script.

$academicLevelFilter = $_REQUEST['academic_level_value'] ?? null;
$classIdFilter = $_REQUEST['class_id_value'] ?? null;
$streamIdFilter = $_REQUEST['stream_id_value'] ?? null;
$searchTermFilter = trim($_REQUEST['search_term'] ?? ''); // Get search term from filter requests too

$sql = "
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
    WHERE s.school_id = :school_id
";
$params = [':school_id' => $schoolId];

if ($academicLevelFilter && $academicLevelFilter !== '') {
    $sql .= " AND tc.academic_level = :academic_level_filter ";
    $params[':academic_level_filter'] = $academicLevelFilter;
}
if ($classIdFilter && $classIdFilter !== '') {
    $sql .= " AND s.class_id = :class_id_filter ";
    $params[':class_id_filter'] = $classIdFilter;
}
if ($streamIdFilter && $streamIdFilter !== '') {
    $sql .= " AND s.StreamId = :stream_id_filter ";
    $params[':stream_id_filter'] = $streamIdFilter;
}

if (!empty($searchTermFilter)) {
    $sql .= " AND (
        s.AdmNo LIKE :search_term OR
        s.FirstName LIKE :search_term OR
        s.SecondName LIKE :search_term OR
        s.LastName LIKE :search_term OR
        s.Nemis LIKE :search_term OR
        s.ContactNo LIKE :search_term
    )";
    $params[':search_term'] = '%' . $searchTermFilter . '%';
}

$sql .= " ORDER BY tc.academic_level ASC, tc.class_level ASC, s.LastName ASC, s.FirstName ASC";

try {
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse($students, 'success', 'Students filtered successfully.');
} catch (PDOException $e) {
    error_log("Database error filtering students: " . $e->getMessage());
    sendResponse([], 'error', 'Failed to filter students: ' . $e->getMessage());
}
exit; // Important: Exit after sending response
?>
