<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

// ==============================
// 📦 Default JSON Response
// ==============================
$response = ['success' => false, 'message' => '', 'data' => []];

// ==============================
// 🔒 Authentication
// ==============================
if (empty($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$schoolId = intval($_SESSION['school_id']);
$data     = json_decode(file_get_contents("php://input"), true);
$action   = $data['action'] ?? ($_GET['action'] ?? '');

// ==============================
// ⚙️ Helper Function
// ==============================
function respond($success, $message, $data = [])
{
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

try {

    // ===========================================================
    // 📘 FETCH SUBJECTS (with teacher + stream)
    // ===========================================================
    if ($action === 'fetch') {
        $classId  = intval($_GET['classId'] ?? 0);
        $streamId = $_GET['streamId'] ?? '';

        if (!$classId) {
            throw new Exception("Missing class ID.");
        }

        $sql = "
            SELECT 
                s.id,
                s.subject_name,
                s.subject_type AS group_name,
                c.class_level,

                -- Stream name
                CASE 
                    WHEN s.stream_id REGEXP '^[0-9]+$' THEN st.stream_name
                    ELSE s.stream_id
                END AS stream_name,

                -- Assigned teacher name (latest assignment)
                CONCAT_WS(' ', t.firstname, t.secondname, t.lastname) AS teacher_name

            FROM tblsubjects s
            JOIN tblclasses c ON s.class_id = c.id

            -- Stream join
            LEFT JOIN tblstreams st 
                ON st.id = s.stream_id AND st.school_id = :school_id

            -- Teacher join (latest assigned teacher)
            LEFT JOIN (
                SELECT sa.subject_id, sa.teacher_id
                FROM  tblsubjectassignments sa
                INNER JOIN (
                    SELECT subject_id, MAX(assignment_date) AS latest_date
                    FROM  tblsubjectassignments
                    WHERE school_id = :school_id
                    GROUP BY subject_id
                ) latest 
                ON sa.subject_id = latest.subject_id AND sa.assignment_date = latest.latest_date
            ) a ON a.subject_id = s.id

            LEFT JOIN tblteachers t ON a.teacher_id = t.id

            WHERE s.school_id = :school_id
              AND s.class_id = :class_id
        ";

        if ($streamId !== '') {
            $sql .= " AND (s.stream_id = :stream_id)";
        }

        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        if ($streamId !== '') {
            $stmt->bindParam(':stream_id', $streamId);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize response
        $rows = array_map(function ($row) {
            $row['teacher_name'] = $row['teacher_name'] ?: '-';
            $row['group_name']   = $row['group_name'] ?: '-';
            $row['stream_name']  = $row['stream_name'] ?: '-';
            return $row;
        }, $rows);

        respond(true, "Subjects loaded successfully.", $rows);
    }

    // ===========================================================
    // ➕ ADD SUBJECT
    // ===========================================================
    elseif ($action === 'add') {
        $subjectName = trim($data['subjectName'] ?? '');
        $subjectType = trim($data['subjectType'] ?? '');
        $classId     = intval($data['classId'] ?? 0);
        $streamId    = $data['streamId'] ?? null;

        if (!$subjectName || !$subjectType || !$classId) {
            throw new Exception("All fields are required.");
        }

        // 🔍 Prevent duplicate subject in class/stream
        $checkSql = "
            SELECT COUNT(*) FROM tblsubjects
            WHERE school_id = :school_id
              AND class_id = :class_id
              AND (stream_id = :stream_id OR (stream_id IS NULL AND :stream_id IS NULL))
              AND LOWER(subject_name) = LOWER(:subject_name)
        ";
        $checkStmt = $dbh->prepare($checkSql);
        $checkStmt->execute([
            ':school_id'    => $schoolId,
            ':class_id'     => $classId,
            ':stream_id'    => $streamId,
            ':subject_name' => $subjectName
        ]);

        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("This subject already exists in the selected class/stream.");
        }

        // ✅ Insert new subject
        $sql = "
            INSERT INTO tblsubjects (school_id, class_id, stream_id, subject_name, subject_type)
            VALUES (:school_id, :class_id, :stream_id, :subject_name, :subject_type)
        ";
        $stmt = $dbh->prepare($sql);
        $stmt->execute([
            ':school_id'    => $schoolId,
            ':class_id'     => $classId,
            ':stream_id'    => $streamId,
            ':subject_name' => $subjectName,
            ':subject_type' => $subjectType
        ]);

        respond(true, "Subject added successfully.");
    }

    // ===========================================================
    // ✏️ UPDATE SUBJECT
    // ===========================================================
    elseif ($action === 'update') {
        $id          = intval($data['id'] ?? 0);
        $subjectName = trim($data['subjectName'] ?? '');
        $subjectType = trim($data['subjectType'] ?? '');

        if (!$id || !$subjectName || !$subjectType) {
            throw new Exception("All fields are required for update.");
        }

        // 🔎 Prevent duplicate name
        $checkSql = "
            SELECT COUNT(*) FROM tblsubjects
            WHERE school_id = :school_id
              AND id != :id
              AND class_id = (SELECT class_id FROM tblsubjects WHERE id = :id)
              AND (stream_id = (SELECT stream_id FROM tblsubjects WHERE id = :id)
                   OR (stream_id IS NULL AND (SELECT stream_id FROM tblsubjects WHERE id = :id) IS NULL))
              AND LOWER(subject_name) = LOWER(:subject_name)
        ";
        $checkStmt = $dbh->prepare($checkSql);
        $checkStmt->execute([
            ':school_id'    => $schoolId,
            ':id'           => $id,
            ':subject_name' => $subjectName
        ]);

        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("Another subject with this name already exists in the same class/stream.");
        }

        // ✅ Update
        $sql = "
            UPDATE tblsubjects 
            SET subject_name = :subject_name, subject_type = :subject_type
            WHERE id = :id AND school_id = :school_id
        ";
        $stmt = $dbh->prepare($sql);
        $stmt->execute([
            ':subject_name' => $subjectName,
            ':subject_type' => $subjectType,
            ':id'           => $id,
            ':school_id'    => $schoolId
        ]);

        respond(true, "Subject updated successfully.");
    }

    // ===========================================================
    // 🗑️ DELETE SUBJECT
    // ===========================================================
    elseif ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        if (!$id) {
            throw new Exception("Invalid subject ID.");
        }

        $stmt = $dbh->prepare("DELETE FROM tblsubjects WHERE id = :id AND school_id = :school_id");
        $stmt->execute([
            ':id'        => $id,
            ':school_id' => $schoolId
        ]);

        respond(true, "Subject deleted successfully.");
    }

    // ===========================================================
    // 🚫 INVALID ACTION
    // ===========================================================
    else {
        throw new Exception("Invalid or missing action.");
    }

} catch (Exception $e) {
    respond(false, $e->getMessage());
}
?>
