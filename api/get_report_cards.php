<?php
session_start();
include('../includes/config.php');

header('Content-Type: application/json');

$class_id  = $_GET['class_id'] ?? null;
$stream_id = $_GET['stream_id'] ?? null;
$exam_id   = $_GET['exam_id'] ?? null;  // Optional exam filter
$school_id = $_SESSION['school_id'] ?? null;

if (!$class_id || !$school_id) {
    echo json_encode(['report_cards' => []]);
    exit;
}

try {
    $query = "
        SELECT 
            rc.id, 
            CONCAT(s.FirstName, ' ', s.SecondName, ' ', COALESCE(s.LastName, '')) AS student_name,
            e.exam_name,
            rc.mean_score,
            rc.grade,
            rc.status,
            rc.top_student
        FROM report_cards rc
        JOIN tblstudents s ON rc.student_id = s.id AND s.school_id = rc.school_id
        LEFT JOIN tblexam e ON rc.exam_id = e.id AND e.school_id = rc.school_id
        WHERE rc.class_id = ? AND rc.school_id = ?
    ";
    $params = [$class_id, $school_id];

    if ($stream_id) {
        $query .= " AND rc.stream_id = ?";
        $params[] = $stream_id;
    }

    if ($exam_id) {
        $query .= " AND rc.exam_id = ?";
        $params[] = $exam_id;
    }

    // =======================
    // Sort by mean_score descending so top students appear first
    // =======================
    $query .= " ORDER BY rc.mean_score DESC";

    $stmt = $dbh->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['report_cards' => $reports]);
} catch (Exception $e) {
    echo json_encode(['report_cards' => [], 'message' => $e->getMessage()]);
}
