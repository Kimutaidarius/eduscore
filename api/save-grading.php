<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once '../includes/config.php';

$data = json_decode(file_get_contents("php://input"), true);

$school_id         = $_SESSION['school_id'];
$class_id          = !empty($data['class_id']) ? intval($data['class_id']) : 0;
$stream_id         = isset($data['stream_id']) && $data['stream_id'] !== '' ? intval($data['stream_id']) : null;
$subject_id        = !empty($data['subject_id']) ? intval($data['subject_id']) : 0;
$lower_limit       = isset($data['lower_limit']) ? intval($data['lower_limit']) : null;
$upper_limit       = isset($data['upper_limit']) ? intval($data['upper_limit']) : null;
$grade_alias       = isset($data['grade_alias']) ? trim($data['grade_alias']) : null;
$points            = isset($data['points']) ? floatval($data['points']) : null;
$remarks           = isset($data['remarks']) ? trim($data['remarks']) : null;
$principal_remarks = isset($data['principal_remarks']) ? trim($data['principal_remarks']) : null;

// If not even lower or upper is set, do not save
if (is_null($lower_limit) && is_null($upper_limit)) {
    echo json_encode(['success' => false, 'message' => 'Nothing to save yet.']);
    exit();
}

// Check for existing record with same class/subject/limits/stream
$checkSql = "SELECT id FROM tblsubjectgrading 
    WHERE class_id = ? AND school_id = ? AND subject_id = ? 
    AND lower_limit " . (is_null($lower_limit) ? "IS NULL" : "= ?") . " 
    AND upper_limit " . (is_null($upper_limit) ? "IS NULL" : "= ?") . " 
    AND stream_id " . (is_null($stream_id) ? "IS NULL" : "= ?");

$checkParams = [$class_id, $school_id, $subject_id];
if (!is_null($lower_limit)) $checkParams[] = $lower_limit;
if (!is_null($upper_limit)) $checkParams[] = $upper_limit;
if (!is_null($stream_id))   $checkParams[] = $stream_id;

$checkStmt = $dbh->prepare($checkSql);
$checkStmt->execute($checkParams);

if ($checkStmt->rowCount() > 0) {
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $updateFields = [];
    $updateParams = [];

    if (!is_null($grade_alias))        { $updateFields[] = 'grade_alias = ?';         $updateParams[] = $grade_alias; }
    if (!is_null($points))             { $updateFields[] = 'points = ?';              $updateParams[] = $points; }
    if (!is_null($remarks))            { $updateFields[] = 'remarks = ?';             $updateParams[] = $remarks; }
    if (!is_null($principal_remarks))  { $updateFields[] = 'principal_remarks = ?';   $updateParams[] = $principal_remarks; }
    if (!is_null($stream_id))          { $updateFields[] = 'stream_id = ?';           $updateParams[] = $stream_id; }

    if (!empty($updateFields)) {
        $updateSql = "UPDATE tblsubjectgrading SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $updateParams[] = $row['id'];

        $updateStmt = $dbh->prepare($updateSql);
        $updateStmt->execute($updateParams);

        $action = 'updated';
    } else {
        $action = 'no changes';
    }

} else {
    // Insert new row even if partially filled
// Insert new row even if partially filled
$insertSql = "INSERT INTO tblsubjectgrading 
    (class_id, school_id, subject_id, stream_id, lower_limit, upper_limit, points, remarks, grade_alias, principal_remarks, is_default)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

$insertStmt = $dbh->prepare($insertSql);
$insertStmt->execute([
    $class_id,
    $school_id,
    $subject_id,
    $stream_id,
    $lower_limit,
    $upper_limit,
    $points,
    $remarks,
    $grade_alias,
    $principal_remarks
]);

    $action = 'inserted';
}

echo json_encode([
    'success' => true,
    'message' => "Grading $action successfully."
]);
