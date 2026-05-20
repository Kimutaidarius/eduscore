<?php
session_start();
header('Content-Type: application/json');
include('../includes/config.php');

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    echo json_encode([
        'success' => false,
        'merit_lists' => [],
        'message' => 'School not identified in session.'
    ]);
    exit;
}

try {
    $stmt = $dbh->prepare("
        SELECT m.id, 
               c.class_level AS class_name, 
               s.stream_name, 
               e.examname AS exam_name, 
               t.term_name, 
               m.status, 
               COALESCE(m.progress, 0) AS progress
        FROM tblmeritlist_status m
        JOIN tblclasses c ON m.class_id = c.id AND c.school_id = m.school_id
        LEFT JOIN tblstreams s ON m.stream_id = s.id AND s.school_id = m.school_id
        JOIN tblexam e ON m.exam_id = e.id AND e.school_id = m.school_id
        JOIN tblterms t ON m.term_id = t.id AND t.school_id = m.school_id
        WHERE m.school_id = ?
        ORDER BY m.id DESC
    ");
    $stmt->execute([$school_id]);
    
    $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'merit_lists' => $lists
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'merit_lists' => [],
        'message' => $e->getMessage()
    ]);
}
?>
