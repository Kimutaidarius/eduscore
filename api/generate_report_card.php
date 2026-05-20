<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

include('../includes/config.php'); // $dbh from config.php (PDO)

if (!isset($_SESSION['id'], $_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$schoolId     = $_SESSION['school_id'];

// --- Get POST data ---
$data         = json_decode(file_get_contents('php://input'), true);
$classId      = $data['class_id'] ?? null;
$streamId     = $data['stream_id'] ?? null;
$examIds      = $data['exam_ids'] ?? [];
$rankingType  = $data['ranking_type'] ?? 'marks';

if (!$classId || empty($examIds)) {
    echo json_encode(['success' => false, 'message' => 'Missing class or exams']);
    exit();
}

// --- Helper Functions ---
function fetchStudentScores(PDO $dbh, $schoolId, $classId, $streamId, $examIds) {
    $params = [$schoolId, $classId];
    $sql = "SELECT s.id AS student_id, s.FirstName, s.SecondName, s.LastName, 
                   sub.subject_name, sc.score_value, sc.grade, sc.rubric, sc.exam_id
            FROM tblscores sc
            INNER JOIN students s ON s.id = sc.student_id
            INNER JOIN subjects sub ON sub.id = sc.subject_id
            WHERE sc.school_id = ? AND sc.class_id = ?";

    if ($streamId) {
        $sql .= " AND sc.StreamId = ?";
        $params[] = $streamId;
    }

    $in = implode(',', array_fill(0, count($examIds), '?'));
    $sql .= " AND sc.exam_id IN ($in)";
    $params = array_merge($params, $examIds);

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scores = [];
    foreach ($rows as $row) {
        $sid = $row['student_id'];
        if (!isset($scores[$sid])) {
            $scores[$sid] = [
                'student_name' => trim($row['FirstName'].' '.$row['SecondName'].' '.$row['LastName']),
                'subjects' => []
            ];
        }
        $scores[$sid]['subjects'][$row['subject_name']] = [
            'score_value' => (float)$row['score_value'],
            'grade'       => $row['grade'],
            'rubric'      => (float)$row['rubric'],
            'exam_id'     => $row['exam_id']
        ];
    }
    return $scores;
}

function computeTotalsAndRank($scores, $rankingBy = 'marks') {
    $studentsData = [];

    foreach ($scores as $sid => $data) {
        $totalMarks = 0;
        $totalRubric = 0;
        $numSubjects = count($data['subjects']);

        foreach ($data['subjects'] as $subject) {
            $totalMarks += $subject['score_value'];
            $totalRubric += $subject['rubric'];
        }

        $averageMarks = $numSubjects ? round($totalMarks / $numSubjects, 2) : 0;
        $averageRubric = $numSubjects ? round($totalRubric / $numSubjects, 2) : 0;

        $studentsData[$sid] = [
            'student_name'   => $data['student_name'],
            'total_marks'    => $totalMarks,
            'total_rubric'   => $totalRubric,
            'average_marks'  => $averageMarks,
            'average_rubric' => $averageRubric,
            'subjects'       => $data['subjects']
        ];
    }

    $sortField = ($rankingBy === 'rubrics') ? 'total_rubric' : 'total_marks';
    uasort($studentsData, function($a, $b) use ($sortField) {
        return $b[$sortField] <=> $a[$sortField];
    });

    $rank = 1;
    foreach ($studentsData as &$data) {
        $data['rank'] = $rank++;
    }

    return $studentsData;
}

function generateMeritList($computedData) {
    $meritList = [];
    foreach ($computedData as $data) {
        $meritList[] = [
            'student_name'   => $data['student_name'],
            'total_marks'    => $data['total_marks'],
            'total_rubric'   => $data['total_rubric'],
            'average_marks'  => $data['average_marks'],
            'average_rubric' => $data['average_rubric'],
            'rank'           => $data['rank']
        ];
    }
    return $meritList;
}

function generateBroadsheet($computedData) {
    $subjectsSet = [];
    foreach ($computedData as $data) {
        foreach ($data['subjects'] as $subjectName => $subjectData) {
            $subjectsSet[$subjectName] = true;
        }
    }
    $allSubjects = array_keys($subjectsSet);

    $broadsheet = [];
    foreach ($computedData as $data) {
        $row = [
            'student_name'   => $data['student_name'],
            'rank'           => $data['rank'],
            'total_marks'    => $data['total_marks'],
            'total_rubric'   => $data['total_rubric'],
            'average_marks'  => $data['average_marks'],
            'average_rubric' => $data['average_rubric'],
            'subjects'       => []
        ];

        foreach ($allSubjects as $subject) {
            if (isset($data['subjects'][$subject])) {
                $row['subjects'][$subject] = $data['subjects'][$subject];
            } else {
                $row['subjects'][$subject] = ['score_value'=>0, 'grade'=>'', 'rubric'=>0];
            }
        }

        $broadsheet[] = $row;
    }

    return ['subjects' => $allSubjects, 'data' => $broadsheet];
}

// --- Main Execution ---
try {
    $scores = fetchStudentScores($dbh, $schoolId, $classId, $streamId, $examIds);

    if (empty($scores)) {
        echo json_encode(['success' => false, 'message' => 'No scores found for selected criteria']);
        exit();
    }

    $computedData = computeTotalsAndRank($scores, $rankingType);

    $response = [
        'success'      => true,
        'merit_list'   => generateMeritList($computedData),
        'broadsheet'   => generateBroadsheet($computedData),
        'report_cards' => $computedData
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error generating reports: '.$e->getMessage()]);
}
