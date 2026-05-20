<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => null];

if (empty($_SESSION['school_id'])) {
    $response['message'] = "Authentication required.";
    echo json_encode($response);
    exit();
}

$schoolId = intval($_SESSION['school_id']);
$data = json_decode(file_get_contents("php://input"), true);

$className     = trim($data['className'] ?? '');
$streams       = trim($data['classStreams'] ?? '');
$teacherId     = !empty($data['classTeacher']) ? intval($data['classTeacher']) : null;
$academicLevel = trim($data['academicLevel'] ?? '');

if (empty($className) || empty($academicLevel)) {
    $response['message'] = "Class name and academic level are required.";
    echo json_encode($response);
    exit();
}

try {
    $dbh->beginTransaction();

    // Insert class (teacher_id may be NULL)
    $sql = "INSERT INTO tblclasses (class_level, academic_level, school_id, teacher_id) 
            VALUES (:class_level, :academic_level, :school_id, :teacher_id)";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':class_level'    => $className,
        ':academic_level' => $academicLevel,
        ':school_id'      => $schoolId,
        ':teacher_id'     => $teacherId, // can be null
    ]);
    $classId = $dbh->lastInsertId();

    // Insert streams if provided
    $streamsArray = [];
    if (!empty($streams)) {
        $streamsArray = array_map('trim', explode(',', $streams));
        foreach ($streamsArray as $stream) {
            if (!empty($stream)) {
                $stmtStream = $dbh->prepare("INSERT INTO tblstreams (class_id, stream_name, school_id) 
                                             VALUES (:class_id, :stream_name, :school_id)");
                $stmtStream->execute([
                    ':class_id'    => $classId,
                    ':stream_name' => $stream,
                    ':school_id'   => $schoolId
                ]);
            }
        }
    }

    // Fetch teacher full name (if set)
    $teacherName = '';
    if ($teacherId) {
        $tStmt = $dbh->prepare("SELECT CONCAT(firstname,' ',secondname,' ',lastname) AS full_name 
                                FROM tblteachers WHERE id = :tid");
        $tStmt->execute([':tid' => $teacherId]);
        $teacherRow = $tStmt->fetch(PDO::FETCH_ASSOC);
        if ($teacherRow) {
            $teacherName = $teacherRow['full_name'];
        }
    }

    $dbh->commit();

    $response['success'] = true;
    $response['message'] = "Class added successfully!";
    $response['data'] = [
        'id'            => $classId,
        'class_level'   => $className,
        'academic_level'=> $academicLevel,
        'teacher_id'    => $teacherId,
        'teacher_name'  => $teacherName,
        'streams'       => $streamsArray
    ];
} catch (Exception $e) {
    $dbh->rollBack();
    $response['message'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
