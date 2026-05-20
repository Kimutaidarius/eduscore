<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => []];

if (empty($_SESSION['school_id'])) {
    $response['message'] = "Authentication required.";
    echo json_encode($response);
    exit();
}

$schoolId = intval($_SESSION['school_id']);
$academicLevel = $_GET['academic_level'] ?? '';

try {
    $sql = "SELECT 
                c.id,
                c.class_level,
                c.academic_level,
                c.teacher_id,
                CONCAT(t.firstname, ' ', t.secondname, ' ', t.lastname) AS teacher_name,
                COUNT(DISTINCT s.id) AS student_count,
                GROUP_CONCAT(DISTINCT st.stream_name ORDER BY st.stream_name ASC SEPARATOR ', ') AS streams
            FROM tblclasses c
            LEFT JOIN tblteachers t 
                ON c.teacher_id = t.id
            LEFT JOIN tblstudents s 
                ON s.class_id = c.id AND s.school_id = c.school_id
            LEFT JOIN tblstreams st
                ON st.class_id = c.id AND st.school_id = c.school_id
            WHERE c.school_id = :school_id";

    if (!empty($academicLevel)) {
        $sql .= " AND c.academic_level = :academic_level";
    }

    $sql .= " GROUP BY 
                  c.id, c.class_level, c.academic_level, 
                  c.teacher_id, t.firstname, t.secondname, t.lastname
              ORDER BY c.class_level ASC";

    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);

    if (!empty($academicLevel)) {
        $query->bindParam(':academic_level', $academicLevel, PDO::PARAM_STR);
    }

    $query->execute();
    $classes = $query->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $classes;
} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
    error_log("Error in get_classes.php: " . $e->getMessage());
}

echo json_encode($response);
