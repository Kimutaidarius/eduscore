<?php
session_start();
header("Content-Type: application/json");
include('../includes/config.php');

$data = json_decode(file_get_contents("php://input"), true);

$class_id = $data['class_id'] ?? null;
$stream_id = $data['stream_id'] ?? null;
$subject_id = $data['subject_id'] ?? null;
$school_id = $_SESSION['school_id'] ?? null;

if (!$school_id || !$class_id || !$subject_id) {
    echo json_encode(["success" => false, "message" => "Missing required data."]);
    exit;
}

try {
    $query = "SELECT id, FirstName, SecondName, LastName FROM tblstudents WHERE class_id = :class_id";
    $params = [':class_id' => $class_id];

    if ($stream_id !== null) {
        $query .= " AND StreamId = :stream_id";
        $params[':stream_id'] = $stream_id;
    }

    $stmt = $dbh->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assignedStmt = $dbh->prepare("
        SELECT student_id FROM tblsubjectassignments 
        WHERE school_id = :school_id AND subject_id = :subject_id AND class_id = :class_id"
        . ($stream_id !== null ? " AND stream_id = :stream_id" : "")
    );

    $assignParams = [
        ':school_id' => $school_id,
        ':subject_id' => $subject_id,
        ':class_id' => $class_id
    ];
    if ($stream_id !== null) $assignParams[':stream_id'] = $stream_id;

    $assignedStmt->execute($assignParams);
    $assignedIds = $assignedStmt->fetchAll(PDO::FETCH_COLUMN);

    $response = [];
    foreach ($students as $student) {
        $response[] = [
            'id' => $student['id'],
            'name' => $student['FirstName'] . ' ' . $student['SecondName'],
            'assigned' => in_array($student['id'], $assignedIds)
        ];
    }

    echo json_encode(["success" => true, "students" => $response]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
