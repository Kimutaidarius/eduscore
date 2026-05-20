<?php
require_once('../includes/config.php');
session_start();
header('Content-Type: application/json');

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School not identified in session.']);
    exit;
}

// ================================
// CHECK INPUT
// ================================
$input = json_decode(file_get_contents("php://input"), true);
$class_id = $input['class_id'] ?? null;
$stream_id = $input['stream_id'] ?? null;
$exam_id = $input['exam_id'] ?? null;

if (!$class_id || !$exam_id) {
    echo json_encode(['success' => false, 'message' => 'Class and exam are required.']);
    exit;
}

// Grading function
function getGrade($score) {
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B';
    if ($score >= 60) return 'C';
    if ($score >= 50) return 'D';
    return 'E';
}

try {
    $dbh->beginTransaction();

    // ================================
    // FETCH STUDENTS IN CLASS/STREAM
    // ================================
    $query = "SELECT id FROM tblstudents WHERE class_id = ? AND school_id = ?";
    $params = [$class_id, $school_id];
    if ($stream_id) {
        $query .= " AND StreamId = ?";
        $params[] = $stream_id;
    }
    $stmt = $dbh->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($students)) {
        $dbh->rollBack();
        echo json_encode(['success' => false, 'message' => 'No students found in this class/stream.']);
        exit;
    }

    $reportCards = [];

    // ================================
    // REGENERATE REPORT CARDS
    // ================================
    $stmtInsert = $dbh->prepare("
        INSERT INTO report_cards (school_id, student_id, class_id, stream_id, exam_id, mean_score, grade, status, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Completed', NOW())
        ON DUPLICATE KEY UPDATE 
            mean_score = VALUES(mean_score),
            grade = VALUES(grade),
            status = 'Completed',
            updated_at = NOW()
    ");

    foreach ($students as $student_id) {
        // Fetch scores
        $scoreQuery = "SELECT score_value FROM tblscores WHERE student_id = ? AND exam_id = ? AND class_id = ? AND school_id = ?";
        $scoreParams = [$student_id, $exam_id, $class_id, $school_id];
        if ($stream_id) {
            $scoreQuery .= " AND StreamId = ?";
            $scoreParams[] = $stream_id;
        }
        $stmtScores = $dbh->prepare($scoreQuery);
        $stmtScores->execute($scoreParams);
        $scores = $stmtScores->fetchAll(PDO::FETCH_COLUMN);

        $mean = $scores ? array_sum($scores)/count($scores) : 0;
        $grade = getGrade($mean);

        $stmtInsert->execute([$school_id, $student_id, $class_id, $stream_id ?: null, $exam_id, $mean, $grade]);

        $reportCards[$student_id] = $mean;
    }

    // ================================
    // MARK TOP STUDENT
    // ================================
    if (!empty($reportCards)) {
        $maxScore = max($reportCards);
        foreach ($reportCards as $student_id => $mean_score) {
            $isTop = ($mean_score == $maxScore) ? 1 : 0;
            $stmtTop = $dbh->prepare("UPDATE report_cards SET top_student = ? WHERE student_id = ? AND class_id = ? AND exam_id = ? AND school_id = ?");
            $stmtTop->execute([$isTop, $student_id, $class_id, $exam_id, $school_id]);
        }
    }

    // ================================
    // REGENERATE MERIT LIST FOR THIS CLASS/STREAM/EXAM
    // ================================
    // Delete existing merit rows
    $stmtDelMerit = $dbh->prepare("DELETE FROM tblmeritlist_rows WHERE class_id = ? AND exam_id = ? AND school_id = ?");
    $paramsDelMerit = [$class_id, $exam_id, $school_id];
    $stmtDelMerit->execute($paramsDelMerit);

    // Insert new merit rows ordered by mean_score
    $stmtMerit = $dbh->prepare("
        INSERT INTO tblmeritlist_rows (merit_status_id, student_id, total_marks, rank, school_id)
        VALUES (?, ?, ?, ?, ?)
    ");

    // Fetch merit_status_id for this class/exam
    $stmtMeritStatus = $dbh->prepare("SELECT id FROM tblmeritlist_status WHERE class_id = ? AND exam_id = ? AND school_id = ?");
    $stmtMeritStatus->execute([$class_id, $exam_id, $school_id]);
    $meritStatus = $stmtMeritStatus->fetch(PDO::FETCH_ASSOC);

    if ($meritStatus) {
        $merit_id = $meritStatus['id'];
        // Order students by mean descending
        arsort($reportCards);
        $rank = 1;
        foreach ($reportCards as $student_id => $mean_score) {
            $stmtMerit->execute([$merit_id, $student_id, $mean_score, $rank++, $school_id]);
        }
        // Update merit list status
        $stmtUpdateMerit = $dbh->prepare("UPDATE tblmeritlist_status SET status = 'Completed', last_updated = NOW() WHERE id = ? AND school_id = ?");
        $stmtUpdateMerit->execute([$merit_id, $school_id]);
    }

    $dbh->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Report cards and merit list regenerated successfully.'
    ]);

} catch (Exception $e) {
    if ($dbh->inTransaction()) $dbh->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
