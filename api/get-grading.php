<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/config.php'; // adjust path if needed

// Validate session
if (!isset($_SESSION['school_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: School ID missing in session'
    ]);
    exit;
}

$school_id = intval($_SESSION['school_id']);
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$stream_id = isset($_GET['stream_id']) && $_GET['stream_id'] !== '' ? intval($_GET['stream_id']) : null;

if ($class_id === 0 || $subject_id === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: class_id and/or subject_id'
    ]);
    exit;
}

try {
    $sql = "SELECT lower_limit, upper_limit, grade_alias, points, remarks, principal_remarks
            FROM tblsubjectgrading
            WHERE school_id = :school_id
              AND class_id = :class_id
              AND subject_id = :subject_id";

    $params = [
        ':school_id' => $school_id,
        ':class_id' => $class_id,
        ':subject_id' => $subject_id
    ];

    if ($stream_id !== null) {
        $sql .= " AND stream_id = :stream_id";
        $params[':stream_id'] = $stream_id;
    } else {
        $sql .= " AND stream_id IS NULL";
    }

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'grading' => $rows
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
