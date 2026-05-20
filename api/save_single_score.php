<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php');
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => ''
];

$data = json_decode(file_get_contents('php://input'), true);

try {
    // --- Security: Ensure valid session ---
    if (
        !isset($_SESSION['school_id']) || !is_numeric($_SESSION['school_id']) ||
        !isset($_SESSION['id']) || !is_numeric($_SESSION['id'])
    ) {
        throw new Exception("Security Error: Session expired or invalid. Please log in.");
    }

    $schoolId            = intval($_SESSION['school_id']);
    $recordedByTeacherId = intval($_SESSION['id']); // Teacher ID from session

    // --- Validate incoming data ---
    $studentId  = isset($data['student_id']) ? intval($data['student_id']) : 0;
    $classId    = isset($data['class_id']) ? intval($data['class_id']) : 0;
    $subjectId  = isset($data['subject_id']) ? intval($data['subject_id']) : 0;
    $examId     = isset($data['exam_id']) ? intval($data['exam_id']) : 0;
    $streamId   = isset($data['stream_id']) ? intval($data['stream_id']) : 0;
    $streamIdForDb = ($streamId === 0) ? null : $streamId;

    $scoreValue = isset($data['score_value']) ? floatval($data['score_value']) : null;

    if ($studentId <= 0 || $classId <= 0 || $subjectId <= 0 || $examId <= 0 || $scoreValue === null) {
        throw new Exception("Invalid input: Missing or invalid IDs or score value.");
    }

    if (!isset($dbh)) {
        throw new Exception("Database connection not established. Check config.php.");
    }

    // --- STEP 0: Check if exam is closed ---
    $examStmt = $dbh->prepare("SELECT status FROM tblexam WHERE id = :exam_id AND school_id = :school_id");
    $examStmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
    $examStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $examStmt->execute();
    $exam = $examStmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        throw new Exception("Exam not found.");
    }

    if (strtolower($exam['status']) !== 'active') {
        throw new Exception("Cannot save score: Exam is closed.");
    }

    // --- STEP 1: Fetch grading system ---
    $stmt = $dbh->prepare("
        SELECT lower_limit, upper_limit, points, remarks, grade_alias
        FROM tblsubjectgrading
        WHERE (school_id = :school_id OR school_id = 0)
        AND (class_id = :class_id OR class_id = 0)
        AND (subject_id = :subject_id OR subject_id = 0)
        AND (stream_id = :stream_id OR stream_id IS NULL OR stream_id = 0)
        ORDER BY lower_limit ASC
    ");
    $stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
    $stmt->bindValue(':subject_id', $subjectId, PDO::PARAM_INT);
    $stmt->bindValue(':stream_id', $streamIdForDb, $streamIdForDb !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->execute();

    $gradings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$gradings) {
        throw new Exception("No grading system found for this class/subject/stream.");
    }

    // --- STEP 2: Determine grade & rubric ---
    $grade = null;
    $rubric = null;

    foreach ($gradings as $g) {
        // special case: score = 0 for missing mark
        if ($scoreValue == 0 && $g['lower_limit'] == 0 && $g['upper_limit'] == 0) {
            $grade = $g['grade_alias'];
            $rubric = $g['points'];
            break;
        }
        // normal grading
        if ($scoreValue >= $g['lower_limit'] && $scoreValue <= $g['upper_limit']) {
            $grade = $g['grade_alias'];
            $rubric = $g['points'];
            break;
        }
    }

    if ($grade === null || $rubric === null) {
        throw new Exception("Unable to determine grade/rubric for this score.");
    }

    // --- STEP 3: Check if score already exists ---
    $checkSql = "SELECT id FROM tblscores
                 WHERE student_id = :student_id
                 AND subject_id = :subject_id
                 AND exam_id = :exam_id
                 AND class_id = :class_id
                 AND school_id = :school_id";

    $checkSql .= $streamIdForDb !== null ? " AND StreamId = :stream_id" : " AND StreamId IS NULL";

    $checkStmt = $dbh->prepare($checkSql);
    $checkStmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
    $checkStmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
    $checkStmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
    $checkStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $checkStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    if ($streamIdForDb !== null) {
        $checkStmt->bindParam(':stream_id', $streamIdForDb, PDO::PARAM_INT);
    }
    $checkStmt->execute();
    $existingScore = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // --- STEP 4: Insert or Update ---
    if ($existingScore) {
        $updateSql = "UPDATE tblscores
                      SET score_value = :score_value,
                          grade = :grade,
                          rubric = :rubric,
                          recorded_by_teacher_id = :teacher_id,
                          recorded_at = CURRENT_TIMESTAMP
                      WHERE id = :id AND school_id = :school_id";
        $updateStmt = $dbh->prepare($updateSql);
        $updateStmt->bindParam(':score_value', $scoreValue, PDO::PARAM_STR);
        $updateStmt->bindParam(':grade', $grade, PDO::PARAM_STR);
        $updateStmt->bindParam(':rubric', $rubric, PDO::PARAM_STR);
        $updateStmt->bindParam(':teacher_id', $recordedByTeacherId, PDO::PARAM_INT);
        $updateStmt->bindParam(':id', $existingScore['id'], PDO::PARAM_INT);
        $updateStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $updateStmt->execute();
    } else {
        $insertSql = "INSERT INTO tblscores
                      (student_id, subject_id, exam_id, class_id, StreamId, score_value, grade, rubric, recorded_by_teacher_id, school_id, recorded_at)
                      VALUES (:student_id, :subject_id, :exam_id, :class_id, :stream_id, :score_value, :grade, :rubric, :teacher_id, :school_id, NOW())";
        $insertStmt = $dbh->prepare($insertSql);
        $insertStmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $insertStmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        $insertStmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
        $insertStmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $insertStmt->bindParam(':score_value', $scoreValue, PDO::PARAM_STR);
        $insertStmt->bindParam(':grade', $grade, PDO::PARAM_STR);
        $insertStmt->bindParam(':rubric', $rubric, PDO::PARAM_STR);
        $insertStmt->bindParam(':teacher_id', $recordedByTeacherId, PDO::PARAM_INT);
        $insertStmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);

        if ($streamIdForDb !== null) {
            $insertStmt->bindParam(':stream_id', $streamIdForDb, PDO::PARAM_INT);
        } else {
            $insertStmt->bindValue(':stream_id', null, PDO::PARAM_NULL);
        }
        $insertStmt->execute();
    }

    $response['success'] = true;
    $response['message'] = "Score, grade, and rubric saved successfully.";

} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
    error_log("Database Error in save_single_score.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Application Error in save_single_score.php: " . $e->getMessage());
}

echo json_encode($response);
