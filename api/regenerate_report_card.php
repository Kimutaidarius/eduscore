<?php
session_start();
include('../includes/config.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$class_id  = $data['class_id'] ?? null;
$stream_id = $data['stream_id'] ?? null;
$exam_id   = $data['exam_id'] ?? null;
$school_id = $_SESSION['school_id'] ?? null;

if (!$class_id || !$exam_id || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Class, exam, and school are required']);
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
    // Fetch all report cards for this class/exam/stream for this school
    $query = "
        SELECT rc.id, rc.student_id, rc.stream_id
        FROM report_cards rc
        WHERE rc.class_id = ? AND rc.exam_id = ? AND rc.school_id = ?
    ";
    $params = [$class_id, $exam_id, $school_id];

    if ($stream_id) {
        $query .= " AND rc.stream_id = ?";
        $params[] = $stream_id;
    }

    $stmt = $dbh->prepare($query);
    $stmt->execute($params);
    $reportCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$reportCards) {
        echo json_encode(['success' => false, 'message' => 'No report cards found for this class/exam']);
        exit;
    }

    $updatedCards = [];

    // Recalculate each report card
    foreach ($reportCards as $card) {
        $scoreQuery = "SELECT score_value FROM tblscores WHERE student_id = ? AND exam_id = ? AND class_id = ? AND school_id = ?";
        $scoreParams = [$card['student_id'], $exam_id, $class_id, $school_id];

        if ($stream_id) {
            $scoreQuery .= " AND StreamId = ?";
            $scoreParams[] = $stream_id;
        }

        $stmtScores = $dbh->prepare($scoreQuery);
        $stmtScores->execute($scoreParams);
        $scores = $stmtScores->fetchAll(PDO::FETCH_COLUMN);

        $mean = $scores ? array_sum($scores) / count($scores) : 0;
        $grade = getGrade($mean);

        // Update the report card
        $stmtUpdate = $dbh->prepare("
            UPDATE report_cards
            SET mean_score = ?, grade = ?, status = 'Completed', updated_at = NOW()
            WHERE id = ? AND school_id = ?
        ");
        $stmtUpdate->execute([$mean, $grade, $card['id'], $school_id]);

        $updatedCards[$card['id']] = $mean;
    }

    // Determine top student(s)
    $maxScore = max($updatedCards);

    foreach ($updatedCards as $id => $mean) {
        $isTop = ($mean == $maxScore) ? 1 : 0;
        $stmtTop = $dbh->prepare("UPDATE report_cards SET top_student = ? WHERE id = ? AND school_id = ?");
        $stmtTop->execute([$isTop, $id, $school_id]);
    }

    echo json_encode(['success' => true, 'message' => 'All report cards recalculated successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
