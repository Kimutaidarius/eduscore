<?php
session_start();
header('Content-Type: application/json');
include('../includes/config.php');

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School not identified in session.']);
    exit;
}

try {
    $input = json_decode(file_get_contents("php://input"), true);

    $class_id = intval($input['class_id'] ?? 0);
    $stream_id = !empty($input['stream_id']) ? intval($input['stream_id']) : null;
    $exam_id = intval($input['exam_id'] ?? 0);
    $term_id = intval($input['term_id'] ?? 0);

    if (!$class_id || !$exam_id || !$term_id) {
        echo json_encode(['success' => false, 'message' => 'Class, exam, and term are required.']);
        exit;
    }

    $dbh->beginTransaction();

    // Insert merit list status
    $stmt = $dbh->prepare("
        INSERT INTO tblmeritlist_status 
        (class_id, stream_id, exam_id, term_id, status, progress, school_id) 
        VALUES (:class_id, :stream_id, :exam_id, :term_id, 'Processing', 0, :school_id)
    ");
    $stmt->execute([
        ':class_id' => $class_id,
        ':stream_id' => $stream_id,
        ':exam_id' => $exam_id,
        ':term_id' => $term_id,
        ':school_id' => $school_id
    ]);

    $merit_id = $dbh->lastInsertId();

    // Fetch student total scores filtered by school_id
    $sqlScores = "
        SELECT s.id AS student_id, SUM(sc.score_value) AS total_marks
        FROM tblstudents s
        INNER JOIN tblscores sc ON s.id = sc.student_id
        WHERE s.class_id = :class_id
          AND s.school_id = :school_id
          AND (:stream_id IS NULL OR s.StreamId = :stream_id)
          AND sc.exam_id = :exam_id
          AND sc.school_id = :school_id
        GROUP BY s.id
        ORDER BY total_marks DESC
    ";
    $stmtScores = $dbh->prepare($sqlScores);
    $stmtScores->execute([
        ':class_id' => $class_id,
        ':school_id' => $school_id,
        ':stream_id' => $stream_id,
        ':exam_id' => $exam_id
    ]);

    $students = $stmtScores->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        $dbh->rollBack();
        echo json_encode(['success' => false, 'message' => 'No student scores found.']);
        exit;
    }

    $totalStudents = count($students);
    $batchSize = 50;
    $insertValues = [];
    $insertParams = [];
    $rank = 1;
    $prevScore = null;
    $sameRankCount = 0;
    $lastProgress = 0;

    foreach ($students as $index => $s) {
        $score = $s['total_marks'] ?? 0;

        // Standard competition ranking
        if ($prevScore !== null) {
            if ($score === $prevScore) {
                $sameRankCount++;
            } else {
                $rank += $sameRankCount;
                $sameRankCount = 1;
            }
        } else {
            $sameRankCount = 1;
        }

        $insertValues[] = "(?, ?, ?, ?, ?)";
        $insertParams[] = $merit_id;
        $insertParams[] = $s['student_id'];
        $insertParams[] = $score;
        $insertParams[] = $rank;
        $insertParams[] = $school_id;

        $prevScore = $score;

        if (count($insertValues) >= $batchSize || $index === $totalStudents - 1) {
            $sqlInsert = "INSERT INTO tblmeritlist_rows (merit_status_id, student_id, total_marks, rank, school_id) VALUES " 
                        . implode(',', $insertValues);
            $stmtInsert = $dbh->prepare($sqlInsert);
            $stmtInsert->execute($insertParams);

            $insertValues = [];
            $insertParams = [];
        }

        $progress = intval((($index + 1) / $totalStudents) * 100);
        if ($progress > $lastProgress) {
            $lastProgress = $progress;
            $dbh->prepare("
                UPDATE tblmeritlist_status 
                SET progress = :progress, updated_at = NOW() 
                WHERE id = :merit_id AND school_id = :school_id
            ")->execute([':progress' => $progress, ':merit_id' => $merit_id, ':school_id' => $school_id]);
        }
    }

    $dbh->prepare("
        UPDATE tblmeritlist_status 
        SET status = 'Completed', progress = 100, updated_at = NOW() 
        WHERE id = :merit_id AND school_id = :school_id
    ")->execute([':merit_id' => $merit_id, ':school_id' => $school_id]);

    $dbh->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Merit list generated successfully with school isolation.',
        'merit_id' => $merit_id
    ]);

} catch (Exception $e) {
    if ($dbh->inTransaction()) $dbh->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
