<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (empty($_SESSION['school_id'])) {
    $response['message'] = "Authentication required.";
    echo json_encode($response);
    exit();
}

$schoolId = intval($_SESSION['school_id']);
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['action'])) {
    $response['message'] = "Invalid request.";
    echo json_encode($response);
    exit();
}

$action = $data['action'];

try {
    if ($action === 'edit') {
        if (empty($data['id']) || empty($data['className'])) {
            throw new Exception("Class ID and name are required.");
        }

        // --- Update tblclasses ---
        $sql = "UPDATE tblclasses 
                SET class_level = :className,
                    teacher_id = :teacher
                WHERE id = :id AND school_id = :school_id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':className', $data['className'], PDO::PARAM_STR);
        $query->bindParam(':teacher', $data['classTeacher'], PDO::PARAM_INT);
        $query->bindParam(':id', $data['id'], PDO::PARAM_INT);
        $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        if (!$query->execute()) {
            throw new Exception("Failed to update class.");
        }

        // --- Handle Streams ---
        if (!empty($data['classStreams'])) {
            $check = $dbh->prepare("SELECT id FROM tblstreams WHERE class_id = :class_id AND school_id = :school_id LIMIT 1");
            $check->bindParam(':class_id', $data['id'], PDO::PARAM_INT);
            $check->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
            $check->execute();

            if ($check->rowCount() > 0) {
                $row = $check->fetch(PDO::FETCH_ASSOC);
                $updateStream = $dbh->prepare("UPDATE tblstreams SET stream_name = :stream WHERE id = :id");
                $updateStream->bindParam(':stream', $data['classStreams'], PDO::PARAM_STR);
                $updateStream->bindParam(':id', $row['id'], PDO::PARAM_INT);
                $updateStream->execute();
            } else {
                $insertStream = $dbh->prepare("INSERT INTO tblstreams (school_id, stream_name, class_id) 
                                               VALUES (:school_id, :stream, :class_id)");
                $insertStream->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
                $insertStream->bindParam(':stream', $data['classStreams'], PDO::PARAM_STR);
                $insertStream->bindParam(':class_id', $data['id'], PDO::PARAM_INT);
                $insertStream->execute();
            }
        }

        $response['success'] = true;
        $response['message'] = "Class updated successfully.";

    } elseif ($action === 'delete') {
        if (empty($data['id'])) {
            throw new Exception("Class ID is required for deletion.");
        }

        $classId = intval($data['id']);

        // ================================
        // 🚨 CASCADE DELETE STARTS HERE
        // ================================
        $dbh->beginTransaction();

        // 1️⃣ Delete student results/assessments
        $delResults = $dbh->prepare("
            DELETE FROM tblresults 
            WHERE student_id IN (
                SELECT id FROM tblstudents WHERE class_id = :class_id AND school_id = :school_id
            ) AND school_id = :school_id
        ");
        $delResults->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $delResults->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $delResults->execute();

        // 2️⃣ Delete subjects/learning areas assigned to this class
        $delSubjects = $dbh->prepare("DELETE FROM tblsubjects WHERE class_id = :class_id AND school_id = :school_id");
        $delSubjects->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $delSubjects->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $delSubjects->execute();

        // 3️⃣ Delete students in this class
        $delStudents = $dbh->prepare("DELETE FROM tblstudents WHERE class_id = :class_id AND school_id = :school_id");
        $delStudents->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $delStudents->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $delStudents->execute();

        // 4️⃣ Delete streams for this class
        $delStreams = $dbh->prepare("DELETE FROM tblstreams WHERE class_id = :class_id AND school_id = :school_id");
        $delStreams->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $delStreams->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $delStreams->execute();

        // 5️⃣ Finally, delete the class itself
        $delClass = $dbh->prepare("DELETE FROM tblclasses WHERE id = :class_id AND school_id = :school_id");
        $delClass->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $delClass->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $delClass->execute();

        $dbh->commit();

        $response['success'] = true;
        $response['message'] = "Class and all associated data deleted successfully.";

    } else {
        throw new Exception("Unknown action.");
    }

} catch (Exception $e) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    $response['message'] = $e->getMessage();
    error_log("❌ Error in update_classes.php: " . $e->getMessage());
}

echo json_encode($response);
