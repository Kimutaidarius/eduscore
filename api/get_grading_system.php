<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php');
require_once('../includes/session_timeout.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

if (
    !isset($_SESSION['id'], $_SESSION['login'], $_SESSION['school_id']) ||
    empty($_SESSION['id']) || empty($_SESSION['login']) || empty($_SESSION['school_id'])
) {
    echo json_encode(["success" => false, "message" => "You are not logged in."]);
    exit;
}

$schoolId   = (int) $_SESSION['school_id'];
$classId    = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
$subjectId  = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
$streamId   = isset($_GET['stream_id']) && $_GET['stream_id'] !== '' ? (int) $_GET['stream_id'] : null;
$scoreValue = isset($_GET['score_value']) && $_GET['score_value'] !== '' ? (float) $_GET['score_value'] : null;

// STEP 1: Load defaults (including the 0 / Missing Mark grade)
$defaultGradings = $dbh->query("
    SELECT lower_limit, upper_limit, points, remarks, grade_alias
    FROM tblsubjectgrading
    WHERE is_default = 1
    ORDER BY lower_limit ASC
")->fetchAll(PDO::FETCH_ASSOC);

// STEP 2: Load custom school/class/subject grading
$sql = "
    SELECT lower_limit, upper_limit, points, remarks, grade_alias
    FROM tblsubjectgrading
    WHERE 
        school_id = :school_id
        AND (:class_id = 0 OR class_id = :class_id)
        AND (:subject_id = 0 OR subject_id = :subject_id)
        AND (:stream_id IS NULL OR stream_id = :stream_id)
        AND is_default = 0
    ORDER BY lower_limit ASC
";
$stmt = $dbh->prepare($sql);
$stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
$stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
$stmt->bindValue(':subject_id', $subjectId, PDO::PARAM_INT);
$stmt->bindValue(':stream_id', $streamId, $streamId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
$stmt->execute();
$customGradings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// STEP 3: Merge defaults with custom grading
$mergedGradings = [];
foreach ($defaultGradings as $def) {
    $found = false;
    foreach ($customGradings as $cust) {
        if ($cust['lower_limit'] == $def['lower_limit'] && $cust['upper_limit'] == $def['upper_limit']) {
            $mergedGradings[] = $cust; // use custom
            $found = true;
            break;
        }
    }
    if (!$found) {
        $mergedGradings[] = $def; // use default
    }
}

// STEP 4: Format results
$formatted = array_map(function ($row) {
    return [
        "grade"       => $row['grade_alias'],
        "rubric"      => $row['points'],
        "lower_limit" => $row['lower_limit'],
        "upper_limit" => $row['upper_limit'],
        "remarks"     => $row['remarks']
    ];
}, $mergedGradings);

// STEP 5: If score provided, find matching grade
$matchedGrade = null;
if ($scoreValue !== null) {
    if ($scoreValue == 0) {
        // Special grade for missing marks (assumes NM row exists in default)
        foreach ($formatted as $g) {
            if ($g['lower_limit'] == 0 && $g['upper_limit'] == 0) {
                $matchedGrade = [
                    "grade"  => $g['grade'],
                    "rubric" => $g['rubric']
                ];
                break;
            }
        }
    } else {
        foreach ($formatted as $g) {
            if ($scoreValue >= $g['lower_limit'] && $scoreValue <= $g['upper_limit']) {
                $matchedGrade = [
                    "grade"  => $g['grade'],
                    "rubric" => $g['rubric']
                ];
                break;
            }
        }
    }
}

// STEP 6: Return
echo json_encode([
    "success" => true,
    "grading" => $formatted,
    "match"   => $matchedGrade
]);
