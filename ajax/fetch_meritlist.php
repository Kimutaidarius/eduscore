<?php
/**
 * AJAX endpoint to fetch and generate merit list data
 * Uses database grading scale - NO HARDCODED GRADES
 */

session_start();
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access. Please log in.'
    ]);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];
$academic_level = $_SESSION['academic_level'] ?? 'primary';

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$class_id = $input['class_id'] ?? null;
$stream_id = $input['stream_id'] ?? 0;
$exam_id = $input['exam_id'] ?? null;
$term_id = $input['term_id'] ?? null;
$year = $input['year'] ?? null;
$rank_by = $input['rank_by'] ?? 'total_marks';
$include_all_streams = $input['include_all_streams'] ?? false;

// Validate required fields
if (!$class_id || !$exam_id || !$term_id || !$year) {
    echo json_encode([
        'success' => false,
        'message' => 'Class, Exam, Term, and Year are required.'
    ]);
    exit();
}

$conn = $dbh;

try {
    // =============================
    // FETCH GRADING SCALE FROM DATABASE
    // =============================
    $gradingScaleQuery = $conn->prepare("
        SELECT gs.lower_limit, gs.upper_limit, gs.grade, gs.points, gs.remarks, gs.cbc_level, gs.cbc_level_name
        FROM tblgradingscale gs
        WHERE gs.school_id = :school_id 
        AND (gs.class_id = :class_id OR gs.class_id IS NULL OR gs.class_id = 0)
        AND (gs.stream_id = :stream_id OR gs.stream_id IS NULL)
        ORDER BY gs.lower_limit ASC
    ");
    $gradingScaleQuery->execute([
        ':school_id' => $school_id,
        ':class_id' => $class_id,
        ':stream_id' => $stream_id > 0 ? $stream_id : null
    ]);
    $gradingScale = $gradingScaleQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // If no class-specific scale, get school-wide default
    if (empty($gradingScale)) {
        $defaultScaleQuery = $conn->prepare("
            SELECT lower_limit, upper_limit, grade, points, remarks, cbc_level, cbc_level_name
            FROM tblgradingscale 
            WHERE school_id = :school_id 
            AND (class_id IS NULL OR class_id = 0)
            ORDER BY lower_limit ASC
        ");
        $defaultScaleQuery->execute([':school_id' => $school_id]);
        $gradingScale = $defaultScaleQuery->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If still no scale, return error (no hardcoded fallbacks)
    if (empty($gradingScale)) {
        echo json_encode([
            'success' => false,
            'message' => 'No grading scale found. Please configure a grading scale for this school/class before generating merit lists.'
        ]);
        exit();
    }
    
    // =============================
    // VALIDATE TERM EXISTS AND MATCHES YEAR
    // =============================
    $termCheckQuery = $conn->prepare("
        SELECT id, term_name, term_number, academic_year, start_date, end_date,
               CASE 
                   WHEN CURDATE() BETWEEN start_date AND end_date THEN 'active'
                   WHEN CURDATE() < start_date THEN 'upcoming'
                   ELSE 'closed'
               END as term_status
        FROM tblterms 
        WHERE id = ? AND school_id = ? AND academic_year = ?
    ");
    $termCheckQuery->execute([$term_id, $school_id, $year]);
    $term = $termCheckQuery->fetch(PDO::FETCH_ASSOC);
    
    if (!$term) {
        echo json_encode([
            'success' => false,
            'message' => "Term not found or does not match the selected year ($year)."
        ]);
        exit();
    }
    
    // =============================
    // VALIDATE EXAM EXISTS AND IS ACTIVE
    // =============================
    $examCheckQuery = $conn->prepare("
        SELECT e.id, e.examname, e.class_id, e.DateAdded, e.deadline_date, e.status
        FROM tblexam e
        WHERE e.id = ? AND e.school_id = ? AND e.status = 'Active'
    ");
    $examCheckQuery->execute([$exam_id, $school_id]);
    $exam = $examCheckQuery->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        echo json_encode([
            'success' => false,
            'message' => 'Exam not found or is inactive.'
        ]);
        exit();
    }
    
    if ($exam['class_id'] != $class_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Exam does not belong to the selected class.'
        ]);
        exit();
    }
    
    // =============================
    // VALIDATE CLASS EXISTS
    // =============================
    $classCheckQuery = $conn->prepare("
        SELECT id, class_level, academic_level 
        FROM tblclasses 
        WHERE id = ? AND school_id = ?
    ");
    $classCheckQuery->execute([$class_id, $school_id]);
    $class = $classCheckQuery->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        echo json_encode([
            'success' => false,
            'message' => 'Class not found.'
        ]);
        exit();
    }
    
    // =============================
    // FETCH SUBJECTS FOR THE CLASS
    // =============================
    $subjectsQuery = $conn->prepare("
        SELECT DISTINCT s.id, s.subject_name, s.alias, s.subject_type
        FROM tblsubjects s
        JOIN tbllessons l ON l.subject_id = s.id
        WHERE l.class_id = ? 
        AND l.school_id = ?
        AND (l.stream_id = ? OR l.stream_id IS NULL OR ? = 0)
        ORDER BY s.subject_name
    ");
    $subjectsQuery->execute([$class_id, $school_id, $stream_id, $stream_id]);
    $subjects = $subjectsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($subjects)) {
        echo json_encode([
            'success' => false,
            'message' => 'No subjects found for the selected class and stream.'
        ]);
        exit();
    }
    
    // =============================
    // FETCH STUDENTS
    // =============================
    $studentsQuery = $conn->prepare("
        SELECT s.id, s.FirstName, s.SecondName, s.LastName, 
               CONCAT(s.FirstName, ' ', COALESCE(s.SecondName, ''), ' ', s.LastName) as full_name,
               s.AdmNo as admission_no, s.Gender as gender,
               s.StreamId
        FROM tblstudents s
        WHERE s.class_id = ? 
        AND s.school_id = ?
        AND s.Status = 'Active'
        " . ($stream_id && $stream_id != 0 ? "AND s.StreamId = ?" : "") . "
        ORDER BY s.FirstName
    ");
    
    if ($stream_id && $stream_id != 0) {
        $studentsQuery->execute([$class_id, $school_id, $stream_id]);
    } else {
        $studentsQuery->execute([$class_id, $school_id]);
    }
    $students = $studentsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        echo json_encode([
            'success' => false,
            'message' => 'No students found for the selected class and stream.'
        ]);
        exit();
    }
    
    // =============================
    // FETCH SCORES
    // =============================
    $studentIds = array_column($students, 'id');
    $subjectIds = array_column($subjects, 'id');
    
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $subjectPlaceholders = implode(',', array_fill(0, count($subjectIds), '?'));
    
    $scoresQuery = $conn->prepare("
        SELECT sc.student_id, sc.subject_id, sc.score_value, sc.total_score, sc.percentage
        FROM tblscores sc
        WHERE sc.student_id IN ($placeholders)
        AND sc.subject_id IN ($subjectPlaceholders)
        AND sc.exam_id = ?
        AND sc.class_id = ?
        AND (sc.StreamId = ? OR sc.StreamId IS NULL OR ? = 0)
        AND sc.school_id = ?
    ");
    
    $params = array_merge($studentIds, $subjectIds, [$exam_id, $class_id, $stream_id, $stream_id, $school_id]);
    $scoresQuery->execute($params);
    $scores = $scoresQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize scores by student and subject
    $studentScores = [];
    foreach ($scores as $score) {
        $studentScores[$score['student_id']][$score['subject_id']] = [
            'score' => $score['score_value'],
            'total_score' => $score['total_score'] ?? 100
        ];
    }
    
    // =============================
    // HELPER FUNCTION: GET GRADE FROM SCALE
    // =============================
    function getGradeFromScale($percentage, $gradingScale) {
        foreach ($gradingScale as $scale) {
            if ($percentage >= $scale['lower_limit'] && $percentage <= $scale['upper_limit']) {
                return [
                    'grade' => $scale['grade'],
                    'points' => $scale['points'],
                    'remarks' => $scale['remarks'],
                    'cbc_level' => $scale['cbc_level'] ?? null,
                    'cbc_level_name' => $scale['cbc_level_name'] ?? null
                ];
            }
        }
        // If no match found (should not happen with proper scale)
        return [
            'grade' => 'BE',
            'points' => 1,
            'remarks' => 'Below Expectations',
            'cbc_level' => null,
            'cbc_level_name' => null
        ];
    }
    
    // =============================
    // BUILD MERIT LIST
    // =============================
    $meritList = [];
    $classPerformance = [
        'class_name' => $class['class_level'] . ($stream_id && $stream_id != 0 ? ' - ' . ($stream['stream_name'] ?? '') : ''),
        'entry' => count($students),
        'EE' => 0,
        'ME' => 0,
        'AE' => 0,
        'AP' => 0,
        'BE' => 0,
        'X' => 0,
        'mean_score' => 0,
        'rubric' => 0,
        'teacher_name' => ''
    ];
    
    // Get class teacher
    $teacherQuery = $conn->prepare("
        SELECT CONCAT(t.firstname, ' ', t.secondname) as teacher_name
        FROM tblteachers t
        INNER JOIN tblclasses c ON c.teacher_id = t.id
        WHERE c.id = ? AND c.school_id = ?
    ");
    $teacherQuery->execute([$class_id, $school_id]);
    $teacherRow = $teacherQuery->fetch(PDO::FETCH_ASSOC);
    $classPerformance['teacher_name'] = $teacherRow['teacher_name'] ?? 'Not Assigned';
    
    $totalScoreSum = 0;
    $totalRubricSum = 0;
    $validScoreCount = 0;
    $allScores = [];
    
    foreach ($students as $student) {
        $studentId = $student['id'];
        $totalMarks = 0;
        $totalRubric = 0;
        $subjectScores = [];
        $subjectCount = 0;
        
        $firstName = $student['FirstName'] ?? '';
        $secondName = $student['SecondName'] ?? '';
        $lastName = $student['LastName'] ?? '';
        $fullName = trim($firstName . ' ' . $secondName . ' ' . $lastName);
        if (empty($fullName)) {
            $fullName = $student['full_name'] ?? 'Unknown Student';
        }
        
        foreach ($subjects as $subject) {
            $subjectId = $subject['id'];
            $scoreData = $studentScores[$studentId][$subjectId] ?? null;
            
            if ($scoreData && $scoreData['score'] !== null && $scoreData['score'] !== '' && $scoreData['score'] > 0) {
                $score = floatval($scoreData['score']);
                $totalScore = floatval($scoreData['total_score']) > 0 ? floatval($scoreData['total_score']) : 100;
                $percentage = ($score / $totalScore) * 100;
                
                $gradeInfo = getGradeFromScale($percentage, $gradingScale);
                
                $subjectScores[$subjectId] = [
                    'score' => $score,
                    'total_score' => $totalScore,
                    'percentage' => round($percentage, 2),
                    'grade' => $gradeInfo['grade'],
                    'points' => $gradeInfo['points'],
                    'remarks' => $gradeInfo['remarks']
                ];
                
                $totalMarks += $score;
                $totalRubric += $gradeInfo['points'];
                $subjectCount++;
                $totalScoreSum += $score;
                $validScoreCount++;
                $allScores[] = $score;
                
                // Update grade counts
                switch ($gradeInfo['grade']) {
                    case 'EE': $classPerformance['EE']++; break;
                    case 'ME': $classPerformance['ME']++; break;
                    case 'AE': $classPerformance['AE']++; break;
                    case 'AP': $classPerformance['AP']++; break;
                    default: $classPerformance['BE']++; break;
                }
            } else {
                // Score is 0, null, or empty - mark as X (Absent/No score)
                $subjectScores[$subjectId] = [
                    'score' => null,
                    'total_score' => 100,
                    'percentage' => 0,
                    'grade' => 'X',
                    'points' => 0,
                    'remarks' => 'No score / Absent'
                ];
                $classPerformance['X']++;
            }
        }
        
        $meanScore = $subjectCount > 0 ? $totalMarks / $subjectCount : 0;
        $meanRubric = $subjectCount > 0 ? $totalRubric / $subjectCount : 0;
        $meanPercentage = $subjectCount > 0 ? ($totalMarks / ($subjectCount * 100)) * 100 : 0;
        
        // Determine overall grade from mean percentage
        $overallGradeInfo = getGradeFromScale($meanPercentage, $gradingScale);
        
        $meritList[] = [
            'student_id' => $studentId,
            'admission_no' => $student['admission_no'],
            'first_name' => $firstName,
            'second_name' => $secondName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'gender' => $student['gender'],
            'total_marks' => round($totalMarks, 2),
            'total_rubric_points' => round($totalRubric, 2),
            'mean_score' => round($meanScore, 2),
            'mean_percentage' => round($meanPercentage, 2),
            'mean_rubric' => round($meanRubric, 2),
            'overall_grade' => $overallGradeInfo['grade'],
            'overall_points' => $overallGradeInfo['points'],
            'overall_remarks' => $overallGradeInfo['remarks'],
            'subject_scores' => $subjectScores,
            'subjects_count' => $subjectCount
        ];
    }
    
    // Calculate class performance statistics
    if ($validScoreCount > 0) {
        $classPerformance['mean_score'] = round($totalScoreSum / $validScoreCount, 2);
        
        // Calculate standard deviation
        if (count($allScores) > 0) {
            $mean = array_sum($allScores) / count($allScores);
            $variance = 0;
            foreach ($allScores as $score) {
                $variance += pow($score - $mean, 2);
            }
            $variance = $variance / count($allScores);
            $classPerformance['std_dev'] = round(sqrt($variance), 2);
        } else {
            $classPerformance['std_dev'] = 0;
        }
        
        // Determine class rubric grade
        $classRubricInfo = getGradeFromScale($classPerformance['mean_score'], $gradingScale);
        $classPerformance['rubric'] = $classRubricInfo['grade'];
    } else {
        $classPerformance['mean_score'] = 0;
        $classPerformance['std_dev'] = 0;
        $classPerformance['rubric'] = 'N/A';
    }
    
    // =============================
    // RANK STUDENTS
    // =============================
    switch ($rank_by) {
        case 'total_marks':
            usort($meritList, function($a, $b) {
                return $b['total_marks'] <=> $a['total_marks'];
            });
            break;
        case 'total_rubric':
            usort($meritList, function($a, $b) {
                return $b['total_rubric_points'] <=> $a['total_rubric_points'];
            });
            break;
        case 'mean_grade':
            usort($meritList, function($a, $b) {
                if ($a['mean_percentage'] == $b['mean_percentage']) {
                    return $b['total_marks'] <=> $a['total_marks'];
                }
                return $b['mean_percentage'] <=> $a['mean_percentage'];
            });
            break;
        default:
            usort($meritList, function($a, $b) {
                return $b['total_marks'] <=> $a['total_marks'];
            });
            break;
    }
    
    // Assign ranks
    $rank = 1;
    $prevValue = null;
    foreach ($meritList as &$student) {
        $currentValue = $student[$rank_by == 'total_marks' ? 'total_marks' : ($rank_by == 'total_rubric' ? 'total_rubric_points' : 'mean_percentage')];
        
        if ($prevValue !== null && $currentValue < $prevValue) {
            $rank++;
        }
        $student['rank'] = $rank;
        $prevValue = $currentValue;
    }
    
    // =============================
    // SAVE TO DATABASE (Optional)
    // =============================
// =============================
// SAVE TO DATABASE
// =============================
$savedCount = 0;

// Delete existing records
$deleteQuery = $conn->prepare("
    DELETE FROM tblmeritlist 
    WHERE school_id = ? 
    AND class_id = ? 
    AND exam_id = ? 
    AND term_id = ? 
    AND academic_year = ?
    " . ($stream_id != 0 ? "AND stream_id = ?" : "AND (stream_id = 0 OR stream_id IS NULL)") . "
");

if ($stream_id != 0) {
    $deleteQuery->execute([$school_id, $class_id, $exam_id, $term_id, $year, $stream_id]);
} else {
    $deleteQuery->execute([$school_id, $class_id, $exam_id, $term_id, $year]);
}

// Insert new records - MATCHING YOUR TABLE STRUCTURE
$insertQuery = $conn->prepare("
    INSERT INTO tblmeritlist (
        school_id, class_id, stream_id, exam_id, term_id, academic_year,
        ranking_method, academic_level, student_id, admission_no, student_name,
        total_marks, total_points, total_rubric, mean_points, mean_percentage,
        overall_grade, overall_points, overall_remarks, most_common_grade, rank_position,
        subjects_json, subject_scores_json, created_by_teacher_id
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?
    )
");

$subjectsJson = json_encode(array_map(function($s) { 
    return ['id' => $s['id'], 'name' => $s['subject_name']]; 
}, $subjects));

foreach ($meritList as $student) {
    $subjectScoresJson = json_encode($student['subject_scores']);
    
    // Calculate mean_points (average rubric points per subject)
    $meanPoints = $student['subjects_count'] > 0 ? $student['total_rubric_points'] / $student['subjects_count'] : 0;
    
    // Determine most common grade from subject scores
    $gradeCounts = [];
    foreach ($student['subject_scores'] as $subjectScore) {
        $grade = $subjectScore['grade'];
        if (!isset($gradeCounts[$grade])) {
            $gradeCounts[$grade] = 0;
        }
        $gradeCounts[$grade]++;
    }
    arsort($gradeCounts);
    $mostCommonGrade = key($gradeCounts) ?: $student['overall_grade'];
    
    $insertQuery->execute([
        $school_id, 
        $class_id, 
        $stream_id ?: 0, 
        $exam_id, 
        $term_id, 
        $year,
        $rank_by, 
        $academic_level, 
        $student['student_id'], 
        $student['admission_no'], 
        $student['full_name'],
        $student['total_marks'],           // total_marks
        $student['total_marks'],           // total_points (using total_marks as points)
        $student['total_rubric_points'],   // total_rubric
        $meanPoints,                       // mean_points
        $student['mean_percentage'],       // mean_percentage
        $student['overall_grade'],         // overall_grade
        $student['overall_points'],        // overall_points
        $student['overall_remarks'],       // overall_remarks
        $mostCommonGrade,                  // most_common_grade
        $student['rank'],                  // rank_position
        $subjectsJson,                     // subjects_json
        $subjectScoresJson,                // subject_scores_json
        $teacher_id                        // created_by_teacher_id
    ]);
    $savedCount++;
}
    
    // =============================
    // RESPONSE
    // =============================
    echo json_encode([
        'success' => true,
        'message' => "Merit list generated successfully. Saved $savedCount records.",
        'data' => [
            'merit_list' => $meritList,
            'subjects' => $subjects,
            'grading_scale' => $gradingScale,
            'class_performance' => [$classPerformance],
            'stats' => [
                'total_students' => count($students),
                'total_subjects' => count($subjects),
                'generated_at' => date('Y-m-d H:i:s'),
                'term_name' => $term['term_name'],
                'academic_year' => $year,
                'ranking_method' => $rank_by
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in fetch_meritlist.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>