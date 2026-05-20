<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/config.php'); // Adjust path as needed

header('Content-Type: application/json');

$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$streamId = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($classId === 0 || $streamId === 0 || $subjectId === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid Class ID, Stream ID, or Subject ID.']);
    exit();
}

$studentsData = [];
try {
    // Select all students for the given class and stream
    // Then, left join with student_subjects to see if they are assigned to the current subject
    $sql = "SELECT s.id, s.student_name, s.admission_no, c.class_name, sub.subject_name,
                   CASE WHEN ss.student_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
            FROM tblstudents s
            JOIN classes c ON s.class_id = c.id
            JOIN streams st ON s.stream_id = st.id
            LEFT JOIN subjects sub ON sub.id = :subject_id_param_for_name_display -- Join to get subject name for display
            LEFT JOIN student_subjects ss ON s.id = ss.student_id
                                          AND ss.subject_id = :subject_id
                                          AND ss.class_id = :class_id_check
                                          AND ss.stream_id = :stream_id_check
            WHERE s.class_id = :class_id AND s.stream_id = :stream_id
            ORDER BY s.student_name ASC";

    $query = $dbh->prepare($sql);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    $query->bindParam(':subject_id', $subjectId, PDO::PARAM_INT); // For the main subject filter
    $query->bindParam(':subject_id_param_for_name_display', $subjectId, PDO::PARAM_INT); // For displaying the subject name in table
    $query->bindParam(':class_id_check', $classId, PDO::PARAM_INT); // For student_subjects join condition
    $query->bindParam(':stream_id_check', $streamId, PDO::PARAM_INT); // For student_subjects join condition

    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($query->rowCount() > 0) {
        $studentsData = $results;
        echo json_encode(['success' => true, 'students' => $studentsData]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No students found in this class and stream.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?><?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/config.php'); // Adjust path as needed

header('Content-Type: application/json');

$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$streamId = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($classId === 0 || $streamId === 0 || $subjectId === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid Class ID, Stream ID, or Subject ID.']);
    exit();
}

$studentsData = [];
try {
    // Select all students for the given class and stream
    // Then, left join with student_subjects to see if they are assigned to the current subject
    $sql = "SELECT s.id, s.student_name, s.admission_no, c.class_name, sub.subject_name,
                   CASE WHEN ss.student_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
            FROM tblstudents s
            JOIN classes c ON s.class_id = c.id
            JOIN streams st ON s.stream_id = st.id
            LEFT JOIN subjects sub ON sub.id = :subject_id_param_for_name_display -- Join to get subject name for display
            LEFT JOIN student_subjects ss ON s.id = ss.student_id
                                          AND ss.subject_id = :subject_id
                                          AND ss.class_id = :class_id_check
                                          AND ss.stream_id = :stream_id_check
            WHERE s.class_id = :class_id AND s.stream_id = :stream_id
            ORDER BY s.student_name ASC";

    $query = $dbh->prepare($sql);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    $query->bindParam(':subject_id', $subjectId, PDO::PARAM_INT); // For the main subject filter
    $query->bindParam(':subject_id_param_for_name_display', $subjectId, PDO::PARAM_INT); // For displaying the subject name in table
    $query->bindParam(':class_id_check', $classId, PDO::PARAM_INT); // For student_subjects join condition
    $query->bindParam(':stream_id_check', $streamId, PDO::PARAM_INT); // For student_subjects join condition

    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($query->rowCount() > 0) {
        $studentsData = $results;
        echo json_encode(['success' => true, 'students' => $studentsData]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No students found in this class and stream.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>