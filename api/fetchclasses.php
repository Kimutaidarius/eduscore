<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(null, 'error', 'Invalid request method.');
}

if (!isset($_GET['school_id'])) {
    sendResponse(null, 'error', 'Missing school ID.');
}

$school_id = intval($_GET['school_id']);
$academic_level = $_GET['academic_level'] ?? null;

global $dbh;

$sql = "SELECT 
            c.id,
            c.class_level AS name,   -- or use class_level if it exists
            s.stream_name,
            t.teacher_name
        FROM tblclasses c
        LEFT JOIN tblstreams s ON c.id = s.class_id
        LEFT JOIN tblteachers t ON s.teacher_id = t.id
        WHERE c.school_id = ?";
$params = [$school_id];

if ($academic_level) {
    $sql .= " AND c.academic_level = ?";
    $params[] = $academic_level;
}

$sql .= " ORDER BY c.academic_level, c.class_level, s.stream_name";

try {
    $query = $dbh->prepare($sql);
    $query->execute($params);
    $classes = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($query->rowCount() > 0) {
        sendResponse($classes, 'success', 'Classes fetched successfully.');
    } else {
        sendResponse([], 'success', 'No classes found.');
    }
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    sendResponse(null, 'error', 'Technical error. Please try again later.');
}
