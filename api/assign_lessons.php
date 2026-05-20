<?php
session_start();
header("Content-Type: application/json");
include('../includes/config.php');

// Check session
if (!isset($_SESSION['school_id'])) {
    echo json_encode(["success" => false, "message" => "Session expired."]);
    exit;
}

// Decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Extract and sanitize inputs
$school_id   = $_SESSION['school_id'];
$class_id    = (string)($data['class_id'] ?? null);
$stream_id   = $data['stream_id'] ?? null; // nullable
$teacher_id  = $data['teacher_id'] ?? null;
$assign_all  = $data['assign_all'] ?? false;
$subject_ids = $data['subjects'] ?? [];

// Validate
if (!$class_id || !$teacher_id || empty($subject_ids)) {
    echo json_encode(["success" => false, "message" => "Missing required data."]);
    exit;
}

try {
    $dbh->beginTransaction();

    if ($assign_all) {
        // ✅ Fetch students
        $query = "SELECT id FROM tblstudents WHERE class_id = :class_id";
        $params = [':class_id' => $class_id];

        if (!is_null($stream_id)) {
            $query .= " AND StreamId = :stream_id"; // ✅ Case-sensitive
            $params[':stream_id'] = $stream_id;
        }

        $stmt = $dbh->prepare($query);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($students)) {
            echo json_encode(["success" => false, "message" => "No students found."]);
            $dbh->rollBack();
            exit;
        }

        // ✅ Prepare insert with tutor_id
        $insertStmt = $dbh->prepare("
            INSERT INTO tblsubjectassignments (school_id, subject_id, tutor_id, student_id, class_id, stream_id)
            SELECT :school_id, :subject_id, :teacher_id, :student_id, :class_id, :stream_id
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM tblsubjectassignments
                WHERE school_id = :school_id
                  AND subject_id = :subject_id
                  AND student_id = :student_id
                  AND class_id = :class_id
                  AND stream_id <=> :stream_id
            )
        ");

        foreach ($students as $student_id) {
            foreach ($subject_ids as $subject_id) {
                $insertStmt->execute([
                    ':school_id'  => $school_id,
                    ':subject_id' => $subject_id,
                    ':teacher_id' => $teacher_id,
                    ':student_id' => $student_id,
                    ':class_id'   => $class_id,
                    ':stream_id'  => $stream_id
                ]);
            }
        }
    }

    $dbh->commit();
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $dbh->rollBack();
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
