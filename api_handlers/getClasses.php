<?php
session_start();
include('../includes/config.php');

header('Content-Type: application/json');

$schoolId = $_SESSION['school_id'] ?? null;
$level = $_GET['level'] ?? '';

if (!$schoolId) {
    echo json_encode(['status' => 'error', 'message' => 'School not found']);
    exit;
}

try {
    $response = ['classes' => [], 'students' => []];

// Fetch classes by school + level
$sql = "SELECT * FROM tblclasses WHERE school_id = :school_id";
if (!empty($level)) {
    $sql .= " AND academic_level = :level";
}
$stmt = $dbh->prepare($sql);
$stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
if (!empty($level)) $stmt->bindParam(':level', $level, PDO::PARAM_STR);
$stmt->execute();
$response['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch students by school + level
$sql2 = "SELECT s.*, c.class_level AS class_name, st.stream_name AS stream_name
         FROM tblstudents s
         JOIN tblclasses c ON s.class_id = c.id
         LEFT JOIN tblstreams st ON s.id = st.id
         WHERE s.school_id = :school_id";
if (!empty($level)) {
    $sql2 .= " AND c.academic_level = :level";
}
$stmt2 = $dbh->prepare($sql2);
$stmt2->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
if (!empty($level)) $stmt2->bindParam(':level', $level, PDO::PARAM_STR);
$stmt2->execute();
$response['students'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode(['status' => 'success', 'data' => $response]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
