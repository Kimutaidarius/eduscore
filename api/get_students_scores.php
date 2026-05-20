<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php');

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'students' => [],
    'total_score' => null
];

try {
    if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id']) || !is_numeric($_SESSION['school_id'])) {
        throw new Exception("Security Error: School ID is missing or invalid in session. Please log in.");
    }
    $schoolId = intval($_SESSION['school_id']);

    $classId   = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
    $subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
    $examId    = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
    $streamId  = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;

    if ($classId <= 0 || $subjectId <= 0 || $examId <= 0) {
        throw new Exception("Invalid Class, Subject, or Exam ID provided.");
    }
    if (!isset($dbh)) {
        throw new Exception("Database connection not established. Check config.php.");
    }

    // 1. Fetch total score
    $totalScoreSql = "
        SELECT total_score_value 
        FROM tbl_exam_subject_total_scores
        WHERE school_id = :school_id
          AND exam_id = :exam_id
          AND subject_id = :subject_id
          AND class_id = :class_id
          AND stream_id = :stream_id
    ";
    $totalScoreQuery = $dbh->prepare($totalScoreSql);
    $totalScoreQuery->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $totalScoreQuery->bindParam(':exam_id', $examId, PDO::PARAM_INT);
    $totalScoreQuery->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $totalScoreQuery->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $totalScoreQuery->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    $totalScoreQuery->execute();
    $totalScoreResult = $totalScoreQuery->fetch(PDO::FETCH_ASSOC);

    if ($totalScoreResult) {
        $response['total_score'] = $totalScoreResult['total_score_value'];
    }

    // 2. Fetch grading system for matching
    $gradingSql = "
        SELECT grade_alias, points, lower_limit, upper_limit
        FROM tblsubjectgrading
        WHERE school_id = :school_id
          AND (class_id = :class_id OR is_default = 1)
          AND (:subject_id = 0 OR subject_id = :subject_id)
          AND (:stream_id = 0 OR stream_id = :stream_id)
        ORDER BY lower_limit ASC
    ";
    $gradingStmt = $dbh->prepare($gradingSql);
    $gradingStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $gradingStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $gradingStmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $gradingStmt->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    $gradingStmt->execute();
    $gradingSystem = $gradingStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch students with scores only (grade/rubric will be computed)
$studentSql = "
    SELECT
        s.id, s.AdmNo, s.FirstName, s.SecondName, s.LastName,
        COALESCE(sc.score_value, NULL) AS score_value,
        sc.grade AS grade,
        sc.rubric AS rubric
    FROM
        tblstudents s
    LEFT JOIN
        tblscores sc ON s.id = sc.student_id
        AND sc.school_id = :school_id_join
        AND sc.class_id = :class_id_score_join
        AND sc.subject_id = :subject_id_score_join
        AND sc.exam_id = :exam_id_score_join
";

    if ($streamId > 0) {
        $studentSql .= " AND sc.StreamId = :stream_id_join";
    } else {
        $studentSql .= " AND (sc.StreamId = 0 OR sc.StreamId IS NULL)";
    }

    $studentSql .= " WHERE s.school_id = :school_id_where AND s.class_id = :class_id_student_where";

    if ($streamId > 0) {
        $studentSql .= " AND s.StreamId = :stream_id_student_where";
    }

    $studentSql .= " ORDER BY s.AdmNo ASC";

    $studentQuery = $dbh->prepare($studentSql);
    $studentQuery->bindParam(':school_id_join', $schoolId, PDO::PARAM_INT);
    $studentQuery->bindParam(':school_id_where', $schoolId, PDO::PARAM_INT);
    $studentQuery->bindParam(':class_id_score_join', $classId, PDO::PARAM_INT);
    $studentQuery->bindParam(':subject_id_score_join', $subjectId, PDO::PARAM_INT);
    $studentQuery->bindParam(':exam_id_score_join', $examId, PDO::PARAM_INT);
    $studentQuery->bindParam(':class_id_student_where', $classId, PDO::PARAM_INT);

    if ($streamId > 0) {
        $studentQuery->bindParam(':stream_id_join', $streamId, PDO::PARAM_INT);
        $studentQuery->bindParam(':stream_id_student_where', $streamId, PDO::PARAM_INT);
    }

    $studentQuery->execute();
    $students = $studentQuery->fetchAll(PDO::FETCH_ASSOC);

    // 4. Match scores to grading ranges
    $response['success'] = true;
    $response['message'] = "Student scores fetched successfully.";

    foreach ($students as $student) {
$gradeAlias = $student['grade'] ?? '';
$rubricPoints = $student['rubric'] ?? '';

if (empty($gradeAlias) && $student['score_value'] !== null) {
    foreach ($gradingSystem as $g) {
        if ($student['score_value'] >= $g['lower_limit'] && $student['score_value'] <= $g['upper_limit']) {
            $gradeAlias = $g['grade_alias'];
            $rubricPoints = $g['points'];
            break;
        }
    }
}

        $response['students'][] = [
            'id' => (int) $student['id'],
            'FirstName' => $student['FirstName'],
            'SecondName' => $student['SecondName'],
            'LastName' => $student['LastName'],
            'AdmNo' => $student['AdmNo'],
            'score_value' => ($student['score_value'] !== null) ? floatval($student['score_value']) : null,
            'grade' => $gradeAlias,
            'rubric' => $rubricPoints
        ];
    }

} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
