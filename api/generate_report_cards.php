<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php'); // DB connection ($dbh)

// =======================
// Get POST data
// =======================
$data = json_decode(file_get_contents('php://input'), true);
$class_id  = $data['class_id'] ?? '';
$stream_id = $data['stream_id'] ?? '';
$exam_id   = $data['exam_id'] ?? '';

$school_id = $_SESSION['school_id'] ?? null;

if (!$class_id || !$exam_id || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Class, exam, and school are required.']);
    exit;
}

// =======================
// Grading function
// =======================
function getGrade($score) {
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B';
    if ($score >= 60) return 'C';
    if ($score >= 50) return 'D';
    return 'E';
}

try {
    // =======================
    // Fetch students in this class/stream for this school
    // =======================
    $query = "SELECT id, CONCAT(FirstName, ' ', SecondName, ' ', COALESCE(LastName, '')) AS full_name 
              FROM tblstudents 
              WHERE Class = ? AND school_id = ?";
    $params = [$class_id, $school_id];

    if ($stream_id) {
        $query .= " AND StreamId = ?";
        $params[] = $stream_id;
    }

    $stmt = $dbh->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$students) {
        echo json_encode(['success' => false, 'message' => 'No students found in this class/stream.']);
        exit;
    }

    $reportCards = [];

    // =======================
    // Generate report card for each student
    // =======================
    foreach ($students as $student) {
        // Fetch scores for this student from tblscores with class_id, StreamId, and school_id matching
        $scoreQuery = "SELECT score_value 
                       FROM tblscores 
                       WHERE student_id = ? AND exam_id = ? AND class_id = ? AND school_id = ?";
        $scoreParams = [$student['id'], $exam_id, $class_id, $school_id];

        if ($stream_id) {
            $scoreQuery .= " AND StreamId = ?";
            $scoreParams[] = $stream_id;
        }

        $stmtScores = $dbh->prepare($scoreQuery);
        $stmtScores->execute($scoreParams);
        $scores = $stmtScores->fetchAll(PDO::FETCH_COLUMN);

        $mean = $scores ? array_sum($scores) / count($scores) : 0;
        $grade = getGrade($mean);

        // Insert or update report card (school-level unique)
        $stmtInsert = $dbh->prepare("
            INSERT INTO report_cards (school_id, student_id, class_id, stream_id, exam_id, mean_score, grade, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Completed')
            ON DUPLICATE KEY UPDATE 
                mean_score = VALUES(mean_score),
                grade = VALUES(grade),
                status = 'Completed',
                updated_at = NOW()
        ");
        $stmtInsert->execute([$school_id, $student['id'], $class_id, $stream_id ?: null, $exam_id, $mean, $grade]);

        $reportCards[$student['id']] = [
            'mean' => $mean,
            'name' => $student['full_name']
        ];
    }

    // =======================
    // Mark top student(s) within this school
    // =======================
    if ($reportCards) {
        $topScore = max(array_column($reportCards, 'mean'));
        $topStudents = [];

        foreach ($reportCards as $studentId => $data) {
            $isTop = ($data['mean'] == $topScore) ? 1 : 0;
            $stmtTop = $dbh->prepare("
                UPDATE report_cards 
                SET top_student = ? 
                WHERE student_id = ? AND exam_id = ? AND school_id = ?
            ");
            $stmtTop->execute([$isTop, $studentId, $exam_id, $school_id]);

            if ($isTop) $topStudents[] = $data['name'];
        }
    }

    // =======================
    // Update exam status to processed
    // =======================
    $stmtExam = $dbh->prepare("UPDATE tblexam SET status = 'Processed', last_updated = NOW() WHERE id = ?");
    $stmtExam->execute([$exam_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Report cards generated successfully!',
        'top_students' => $topStudents ?? []
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
