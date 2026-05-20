<?php
session_start();
header("Content-Type: application/json");

include('../includes/config.php');

if (!isset($_SESSION['school_id'])) {
    echo json_encode(["success" => false, "message" => "Session expired."]);
    exit;
}

$school_id = $_SESSION['school_id'];

try {
    $stmt = $dbh->prepare("
        SELECT 
            cls.class_level AS class_name,
            str.stream_name,
            sub.subject_name,
            CONCAT(t.firstname, ' ', COALESCE(t.secondname, ''), ' ', t.lastname) AS teacher_name,
            COUNT(sa.student_id) AS student_count
        FROM tblsubjectassignments sa
        JOIN tblclasses cls ON sa.class_id = cls.id
        LEFT JOIN tblstreams str ON sa.stream_id = str.id
        JOIN tblsubjects sub ON sa.subject_id = sub.id
        JOIN tblteachers t ON sa.teacher_id = t.id
        WHERE sa.school_id = :school_id
        GROUP BY sa.class_id, sa.stream_id, sa.subject_id, sa.teacher_id
        ORDER BY cls.class_level, str.stream_name, sub.subject_name
    ");
    $stmt->execute([':school_id' => $school_id]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "lessons" => $lessons]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
