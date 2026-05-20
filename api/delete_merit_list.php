<?php
session_start();
include('../includes/config.php'); // DB connection ($dbh)

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$report_ids = $data['ids'] ?? []; // Array of report card IDs for bulk operation
$school_id = $_SESSION['school_id'] ?? null;

if (empty($report_ids) || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Report card IDs and school are required']);
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
    // Begin transaction
    $dbh->beginTransaction();

    // Fetch report card info for all IDs for this school
    $placeholders = implode(',', array_fill(0, count($report_ids), '?'));
    $stmt = $dbh->prepare("
        SELECT id, student_id, class_id, stream_id, exam_id 
        FROM report_cards 
        WHERE id IN ($placeholders) AND school_id = ?
    ");
    $stmt->execute([...$report_ids, $school_id]);
    $reportCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reportCards)) {
        $dbh->rollBack();
        echo json_encode(['success' => false, 'message' => 'No report cards found for your school with the given IDs']);
        exit;
    }

    // Recalculate scores and update each report card
    foreach ($reportCards as $report) {
        $student_id = $report['student_id'];
        $class_id   = $report['class_id'];
        $stream_id  = $report['stream_id'];
        $exam_id    = $report['exam_id'];

        // Fetch scores for this student
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
        $stmtUpdate->execute([$mean, $grade, $report['id'], $school_id]);
    }

    // Recalculate top student for each class/exam/stream combination
    $groupQuery = "
        SELECT DISTINCT class_id, stream_id, exam_id 
        FROM report_cards 
        WHERE id IN ($placeholders) AND school_id = ?
    ";
    $stmtGroup = $dbh->prepare($groupQuery);
    $stmtGroup->execute([...$report_ids, $school_id]);
    $groups = $stmtGroup->fetchAll(PDO::FETCH_ASSOC);

    foreach ($groups as $grp) {
        $topQuery = "
            SELECT id, mean_score 
            FROM report_cards 
            WHERE class_id = ? AND exam_id = ? AND school_id = ?
        ";
        $topParams = [$grp['class_id'], $grp['exam_id'], $school_id];
        if ($grp['stream_id']) {
            $topQuery .= " AND stream_id = ?";
            $topParams[] = $grp['stream_id'];
        }
        $stmtTop = $dbh->prepare($topQuery);
        $stmtTop->execute($topParams);
        $allCards = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

        if ($allCards) {
            $maxScore = max(array_column($allCards, 'mean_score'));
            foreach ($allCards as $card) {
                $isTop = ($card['mean_score'] == $maxScore) ? 1 : 0;
                $stmtMarkTop = $dbh->prepare("
                    UPDATE report_cards 
                    SET top_student = ? 
                    WHERE id = ? AND school_id = ?
                ");
                $stmtMarkTop->execute([$isTop, $card['id'], $school_id]);
            }
        }
    }

    // Commit transaction
    $dbh->commit();

    echo json_encode(['success' => true, 'message' => 'Report cards recalculated and top students updated successfully.']);

} catch (Exception $e) {
    if ($dbh->inTransaction()) $dbh->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
