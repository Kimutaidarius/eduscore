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
if (empty($input['merit_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing merit_id']);
    exit;
}

$merit_id = intval($input['merit_id']);

// Grading function
function getGrade($score) {
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B';
    if ($score >= 60) return 'C';
    if ($score >= 50) return 'D';
    return 'E';
}

try {
    // ================================
    // BEGIN TRANSACTION
    // ================================
    $dbh->beginTransaction();

    // ================================
    // FETCH MERIT LIST DETAILS
    // ================================
    $stmt = $dbh->prepare("
        SELECT class_id, stream_id, exam_id
        FROM tblmeritlist_status
        WHERE id = ? AND school_id = ?
    ");
    $stmt->execute([$merit_id, $school_id]);
    $merit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$merit) {
        $dbh->rollBack();
        echo json_encode(['success' => false, 'message' => 'Merit list not found for your school']);
        exit;
    }

    $class_id = $merit['class_id'];
    $stream_id = $merit['stream_id'];
    $exam_id = $merit['exam_id'];

    // ================================
    // FETCH ALL REPORT CARDS FOR THIS CLASS/STREAM/EXAM
    // ================================
    $stmt = $dbh->prepare("
        SELECT id AS report_card_id, student_id
        FROM report_cards
        WHERE class_id = ? AND exam_id = ? AND school_id = ?
    " . ($stream_id ? " AND stream_id = ?" : ""));
    
    $params = [$class_id, $exam_id, $school_id];
    if ($stream_id) $params[] = $stream_id;
    $stmt->execute($params);
    $reportCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reportCards)) {
        $dbh->rollBack();
        echo json_encode(['success' => false, 'message' => 'No report cards found for this class/exam/stream']);
        exit;
    }

    // ================================
    // REGENERATE EACH REPORT CARD
    // ================================
    $topScores = [];
    foreach ($reportCards as $rc) {
        $student_id = $rc['student_id'];
        $report_card_id = $rc['report_card_id'];

        // Recalculate scores
        $scoreQuery = "SELECT score_value FROM tblscores WHERE student_id = ? AND exam_id = ? AND class_id = ? AND school_id = ?";
        $scoreParams = [$student_id, $exam_id, $class_id, $school_id];
        if ($stream_id) {
            $scoreQuery .= " AND StreamId = ?";
            $scoreParams[] = $stream_id;
        }

        $stmtScores = $dbh->prepare($scoreQuery);
        $stmtScores->execute($scoreParams);
        $scores = $stmtScores->fetchAll(PDO::FETCH_COLUMN);
        $mean = $scores ? array_sum($scores) / count($scores) : 0;
        $grade = getGrade($mean);

        // Update report card
        $stmtUpdate = $dbh->prepare("
            UPDATE report_cards 
            SET mean_score = ?, grade = ?, status = 'Completed', updated_at = NOW() 
            WHERE id = ? AND school_id = ?
        ");
        $stmtUpdate->execute([$mean, $grade, $report_card_id, $school_id]);

        $topScores[$report_card_id] = $mean;
    }

    // ================================
    // MARK TOP STUDENT
    // ================================
    if (!empty($topScores)) {
        $maxScore = max($topScores);
        foreach ($topScores as $report_card_id => $score) {
            $isTop = ($score == $maxScore) ? 1 : 0;
            $stmtTop = $dbh->prepare("UPDATE report_cards SET top_student = ? WHERE id = ? AND school_id = ?");
            $stmtTop->execute([$isTop, $report_card_id, $school_id]);
        }
    }

    // ================================
    // REGENERATE MERIT LIST
    // ================================
    // Remove old rows
    $stmtDel = $dbh->prepare("DELETE FROM tblmeritlist_rows WHERE merit_status_id = ? AND school_id = ?");
    $stmtDel->execute([$merit_id, $school_id]);

    // Insert updated ranks
    $stmtStudents = $dbh->prepare("
        SELECT id AS student_id, mean_score AS total_marks
        FROM report_cards
        WHERE class_id = ? AND exam_id = ? AND school_id = ?
        " . ($stream_id ? " AND stream_id = ?" : "") . " 
        ORDER BY mean_score DESC
    ");
    $params = [$class_id, $exam_id, $school_id];
    if ($stream_id) $params[] = $stream_id;
    $stmtStudents->execute($params);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    $stmtInsert = $dbh->prepare("
        INSERT INTO tblmeritlist_rows (merit_status_id, student_id, total_marks, rank, school_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $rank = 1;
    foreach ($students as $s) {
        $stmtInsert->execute([$merit_id, $s['student_id'], $s['total_marks'], $rank++, $school_id]);
    }

    // Update merit list status
    $stmtUpdateMerit = $dbh->prepare("UPDATE tblmeritlist_status SET status = 'Completed', last_updated = NOW() WHERE id = ? AND school_id = ?");
    $stmtUpdateMerit->execute([$merit_id, $school_id]);

    // Commit transaction
    $dbh->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Report cards and merit list regenerated successfully.'
    ]);

} catch (Exception $e) {
    if ($dbh->inTransaction()) $dbh->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
