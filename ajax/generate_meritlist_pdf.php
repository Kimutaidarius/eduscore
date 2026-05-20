<?php
/**
 * Generate Merit List PDF with CBE (Competency-Based Education) Mean Score Calculation
 * Based on the 8-point scale: EE=8, ME=7, AE=6, AP=5, BE=4, WB=3, BB=2, EM=1
 * 
 * Calculation Formula:
 * - For each subject: Sum points from all assessed competencies for that subject
 * - For student: A.R = (Sum of points from all subjects) ÷ (Number of subjects)
 * - AS.R = Abbreviation of Average Rubric grade (e.g., if A.R = 3.44, AS.R = ME)
 */

// Set appropriate headers based on request type
$print_mode = false;
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['print_mode']) && $input['print_mode'] === true) {
    $print_mode = true;
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="merit_list_print.pdf"');
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="merit_list.pdf"');
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    if ($print_mode) {
        http_response_code(401);
        exit('Unauthorized access. Please login.');
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access. Please login.'
        ]);
    }
    exit();
}

// Database connection
require_once '../includes/config.php';

// Debug log
error_log("Received in generate_meritlist_pdf.php: " . print_r($input, true));

// Extract parameters
$school_id = $_SESSION['school_id'];
$teacher_id = $_SESSION['teacher_id'];
$merit_list = isset($input['merit_list']) ? $input['merit_list'] : [];
$subjects = isset($input['subjects']) ? $input['subjects'] : [];
$selections = isset($input['selections']) ? $input['selections'] : [];
$school_info = isset($input['school_info']) ? $input['school_info'] : [];
$summary = isset($input['summary']) ? $input['summary'] : null;

// Validate that we have data
if (empty($merit_list)) {
    if ($print_mode) {
        header('Content-Type: text/html');
        echo '<html><body><h3>Error: No merit list data to generate PDF.</h3></body></html>';
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No merit list data to generate PDF.'
        ]);
    }
    exit();
}

// Database connection for fetching additional data if needed
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    if ($print_mode) {
        header('Content-Type: text/html');
        echo '<html><body><h3>Database connection failed</h3></body></html>';
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]);
    }
    exit();
}

// Define CBE Grading Scale (8-point scale)
$cbe_grading_scale = [
    'EE' => ['name' => 'Exceeding Expectations', 'points' => 8, 'color' => [22, 163, 74]],
    'ME' => ['name' => 'Meeting Expectations', 'points' => 7, 'color' => [37, 99, 235]],
    'AE' => ['name' => 'Approaching Expectations', 'points' => 6, 'color' => [217, 119, 6]],
    'AP' => ['name' => 'Approaching', 'points' => 5, 'color' => [245, 158, 11]],
    'BE' => ['name' => 'Below Expectations', 'points' => 4, 'color' => [220, 38, 38]],
    'WB' => ['name' => 'Well Below', 'points' => 3, 'color' => [185, 28, 28]],
    'BB' => ['name' => 'Beginning', 'points' => 2, 'color' => [153, 27, 27]],
    'EM' => ['name' => 'Emerging', 'points' => 1, 'color' => [127, 29, 29]]
];

/**
 * Calculate Average Rubric (A.R) for a student based on CBE 8-point scale
 * 
 * Formula: A.R = (Sum of points from all subjects) ÷ (Number of valid subjects)
 */
function calculateAverageRubric($student, $subjects, $grading_scale) {
    $totalPoints = 0;
    
    // First pass: Calculate total points from valid subjects
    foreach ($subjects as $subject) {
        $subjectId = $subject['id'];

        if (!isset($student['subject_scores'][$subjectId])) {
            continue;
        }

        $subjectScore = $student['subject_scores'][$subjectId];
        $achievement = $subjectScore['achievement_abbreviation'] ?? null;

        if (!$achievement || !isset($grading_scale[$achievement])) {
            continue;
        }

        $points = $grading_scale[$achievement]['points'];
        $totalPoints += $points;
    }
    
    // Second pass: Count ONLY valid subjects (subjects with actual grades)
    $validSubjects = 0;
    
    foreach ($subjects as $subject) {
        $subjectId = $subject['id'];

        if (!isset($student['subject_scores'][$subjectId])) {
            continue;
        }

        $achievement = $student['subject_scores'][$subjectId]['achievement_abbreviation'] ?? null;

        if (!$achievement || !isset($grading_scale[$achievement])) {
            continue;
        }

        $validSubjects++;
    }
    
    // Calculate Average Rubric (A.R) using ONLY valid subjects
    $averageRubric = $validSubjects > 0 
        ? $totalPoints / $validSubjects 
        : 0;
    
    // Determine AS.R (Abbreviation of Average Rubric grade)
    $asr = getAbbreviationFromAverageRubric($averageRubric);
    
    return [
        'average_rubric' => round($averageRubric, 2),
        'asr' => $asr,
        'total_points' => $totalPoints,
        'valid_subjects' => $validSubjects
    ];
}

/**
 * Determine AS.R based on A.R value using 8-point scale thresholds
 */
function getAbbreviationFromAverageRubric($averageRubric) {
    if ($averageRubric >= 7.5) return 'EE';
    if ($averageRubric >= 6.5) return 'ME';
    if ($averageRubric >= 5.5) return 'AE';
    if ($averageRubric >= 4.5) return 'AP';
    if ($averageRubric >= 3.5) return 'BE';
    if ($averageRubric >= 2.5) return 'WB';
    if ($averageRubric >= 1.5) return 'BB';
    return 'EM';
}

/**
 * Calculate merit list with A.R and AS.R for ranking
 */
function calculateMeritListWithRanking($meritList, $subjects, $grading_scale) {
    $enrichedList = [];
    
    foreach ($meritList as $student) {
        // Calculate Average Rubric (A.R) for this student
        $arResult = calculateAverageRubric($student, $subjects, $grading_scale);
        
        // Calculate total marks
        $totalMarks = 0;
        foreach ($subjects as $subject) {
            $subjectId = $subject['id'];
            $subjectScore = isset($student['subject_scores'][$subjectId]) ? $student['subject_scores'][$subjectId] : [];
            $score = isset($subjectScore['score']) ? floatval($subjectScore['score']) : 0;
            $totalMarks += $score;
        }
        
        $enrichedStudent = $student;
        $enrichedStudent['average_rubric'] = $arResult['average_rubric'];  // A.R
        $enrichedStudent['asr'] = $arResult['asr'];                         // AS.R
        $enrichedStudent['total_points'] = $arResult['total_points'];
        $enrichedStudent['total_marks'] = $totalMarks;
        
        $enrichedList[] = $enrichedStudent;
    }
    
    // Sort by A.R descending for ranking
    usort($enrichedList, function($a, $b) {
        $scoreA = floatval($a['average_rubric']);
        $scoreB = floatval($b['average_rubric']);
        
        if ($scoreA == $scoreB) {
            $pointsA = floatval($a['total_points']);
            $pointsB = floatval($b['total_points']);
            return $pointsB <=> $pointsA;
        }
        return $scoreB <=> $scoreA;
    });
    
    // Assign ranks
    foreach ($enrichedList as $index => &$student) {
        $student['rank'] = $index + 1;
    }
    
    return $enrichedList;
}

/**
 * Calculate class summary statistics
 */
function calculateClassSummaryStatistics($meritList, $subjects, $grading_scale) {
    if (empty($meritList)) return null;
    
    $studentCount = count($meritList);
    $meanGradeCounts = [
        'EE' => 0, 'ME' => 0, 'AE' => 0, 'AP' => 0, 
        'BE' => 0, 'WB' => 0, 'BB' => 0, 'EM' => 0
    ];
    
    $totalAverageRubric = 0;
    $totalRubricPoints = 0;
    $subjectCount = count($subjects);
    
    foreach ($meritList as $student) {
        $averageRubric = floatval($student['average_rubric'] ?? 0);
        $asr = $student['asr'] ?? 'BE';
        $totalPoints = floatval($student['total_points'] ?? 0);
        
        $totalAverageRubric += $averageRubric;
        $totalRubricPoints += $totalPoints;
        
        if (isset($meanGradeCounts[$asr])) {
            $meanGradeCounts[$asr]++;
        }
    }
    
    $classAverageRubric = $studentCount > 0 ? round($totalAverageRubric / $studentCount, 2) : 0;
    $classAsr = getAbbreviationFromAverageRubric($classAverageRubric);
    
    // Calculate standard deviation
    $scores = array_map(function($student) {
        return floatval($student['average_rubric'] ?? 0);
    }, $meritList);
    
    $mean = $totalAverageRubric / $studentCount;
    $variance = array_reduce($scores, function($carry, $score) use ($mean) {
        return $carry + pow($score - $mean, 2);
    }, 0) / $studentCount;
    $stdDev = round(sqrt($variance), 2);
    
    return [
        'studentCount' => $studentCount,
        'subjectCount' => $subjectCount,
        'meanGradeCounts' => $meanGradeCounts,
        'classAverageRubric' => $classAverageRubric,
        'classAsr' => $classAsr,
        'stdDev' => $stdDev
    ];
}

try {
    // Calculate merit list with A.R and AS.R
    $enrichedMeritList = calculateMeritListWithRanking($merit_list, $subjects, $cbe_grading_scale);
    
    // Debug: Log the first student to verify A.R and AS.R are calculated
    if (!empty($enrichedMeritList)) {
        error_log("First student A.R: " . ($enrichedMeritList[0]['average_rubric'] ?? 'NOT SET'));
        error_log("First student AS.R: " . ($enrichedMeritList[0]['asr'] ?? 'NOT SET'));
        error_log("First student total_points: " . ($enrichedMeritList[0]['total_points'] ?? 'NOT SET'));
    }
    
    // Calculate class summary statistics
    $classSummary = calculateClassSummaryStatistics($enrichedMeritList, $subjects, $cbe_grading_scale);
    
    // Generate PDF using FPDF
    require_once('../assets/fpdf/fpdf.php');
    
    class MeritListPDF extends FPDF {
        private $gradingScale;
        
        function __construct($gradingScale = null) {
            parent::__construct('L', 'mm', 'A4');
            $this->gradingScale = $gradingScale;
        }
        
        function Header() {
            global $school_info, $selections, $print_mode;
            
            if (!empty($school_info['school_logo']) && file_exists('../' . $school_info['school_logo'])) {
                $this->Image('../' . $school_info['school_logo'], 10, 8, 30);
            }
            
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(30, 58, 138);
            $this->Cell(0, 8, $school_info['school_name'] ?? 'School Name', 0, 1, 'C');
            
            $this->SetFont('Arial', 'I', 10);
            $this->SetTextColor(107, 114, 128);
            $this->Cell(0, 5, $school_info['school_motto'] ?? 'Excellence in Education', 0, 1, 'C');
            
            $this->Ln(5);
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(30, 58, 138);
            
            $title = 'MERIT LIST (CBE Average Rubric)';
            if (!empty($selections['class_name'])) {
                $title .= ' - ' . $selections['class_name'];
                if (!empty($selections['stream_name'])) {
                    $title .= ' (' . $selections['stream_name'] . ')';
                }
            }
            $this->Cell(0, 8, $title, 0, 1, 'C');
            
            $this->SetFont('Arial', '', 11);
            $this->SetTextColor(0, 0, 0);
            $details = '';
            if (!empty($selections['exam_name'])) $details .= $selections['exam_name'] . ' - ';
            if (!empty($selections['term_name'])) $details .= $selections['term_name'] . ' - ';
            if (!empty($selections['year'])) $details .= $selections['year'];
            $this->Cell(0, 6, $details, 0, 1, 'C');
            
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(107, 114, 128);
            $this->Cell(0, 4, 'Generated on: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
            
            $this->Ln(8);
        }

        function Footer() {
            global $print_mode;
            
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128, 128, 128);
            $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            
            $this->SetY(-10);
            $this->SetFont('Arial', 'I', 6);
            $this->Cell(0, 5, 'Generated by EduScore Merit List System | A.R = Average Rubric | AS.R = Abbreviation of Average Rubric Grade', 0, 0, 'C');
        }

        function GradeCountAnalysisTable($summary, $selections, $teacher_name = 'Class Teacher') {
            if (!$summary) return;
            
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(30, 58, 138);
            $this->Cell(0, 8, 'CBE GRADE COUNT ANALYSIS (8-Point Scale)', 0, 1, 'L');
            $this->Ln(2);
            
            $this->SetFillColor(30, 58, 138);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 9);
            
            $headers = ['CLS', 'ENT', 'EE', 'ME', 'AE', 'AP', 'BE', 'X', 'A.R', 'AS.R', 'DEV', 'TEACHER'];
            $widths = [30, 15, 12, 12, 12, 12, 12, 12, 20, 20, 15, 40];
            
            for ($i = 0; $i < count($headers); $i++) {
                $this->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            $this->SetFillColor(240, 248, 255);
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', '', 9);
            
            $className = $selections['class_name'] ?? 'Current Class';
            if (!empty($selections['stream_name'])) {
                $className .= ' - ' . $selections['stream_name'];
            }
            
            $data = [
                $className,
                $summary['studentCount'],
                $summary['meanGradeCounts']['EE'] ?? 0,
                $summary['meanGradeCounts']['ME'] ?? 0,
                $summary['meanGradeCounts']['AE'] ?? 0,
                $summary['meanGradeCounts']['AP'] ?? 0,
                $summary['meanGradeCounts']['BE'] ?? 0,
                $summary['subjectCount'],
                number_format($summary['classAverageRubric'], 2),
                $summary['classAsr'],
                $summary['stdDev'],
                $teacher_name
            ];
            
            $this->SetFont('Arial', '', 8);
            $y = $this->GetY();
            $x = $this->GetX();
            
            for ($i = 0; $i < count($data); $i++) {
                $this->MultiCell($widths[$i], 4, (string)$data[$i], 1, 'C');
                $x += $widths[$i];
                $this->SetXY($x, $y);
            }
            $this->Ln();
        }

        function CbeSummaryTable($summary, $selections, $teacher_name = 'Class Teacher') {
            if (!$summary) return;
            
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(30, 58, 138);
            $this->Cell(0, 8, 'CLASS PERFORMANCE SUMMARY (CBE 8-Point Scale)', 0, 1, 'L');
            $this->Ln(2);
            
            $this->SetFillColor(30, 58, 138);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 9);
            
            $headers = ['Class', 'Total\nStudents', 'EE', 'ME', 'AE', 'AP', 'BE', 'Subjects', 'A.R', 'AS.R', 'DEV', 'Teacher'];
            $widths = [30, 20, 12, 12, 12, 12, 12, 15, 20, 20, 15, 40];
            
            for ($i = 0; $i < count($headers); $i++) {
                $this->Cell($widths[$i], 10, $headers[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            $this->SetFillColor(240, 248, 255);
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', '', 9);
            
            $className = $selections['class_name'] ?? 'Current Class';
            if (!empty($selections['stream_name'])) {
                $className .= ' - ' . $selections['stream_name'];
            }
            
            $data = [
                $className,
                $summary['studentCount'],
                $summary['meanGradeCounts']['EE'] ?? 0,
                $summary['meanGradeCounts']['ME'] ?? 0,
                $summary['meanGradeCounts']['AE'] ?? 0,
                $summary['meanGradeCounts']['AP'] ?? 0,
                $summary['meanGradeCounts']['BE'] ?? 0,
                $summary['subjectCount'],
                number_format($summary['classAverageRubric'], 2),
                $summary['classAsr'],
                $summary['stdDev'],
                $teacher_name
            ];
            
            $y = $this->GetY();
            $x = $this->GetX();
            
            for ($i = 0; $i < count($data); $i++) {
                $this->MultiCell($widths[$i], 5, (string)$data[$i], 1, 'C');
                $x += $widths[$i];
                $this->SetXY($x, $y);
            }
            $this->Ln();
        }
    }

    // Create PDF instance
    $pdf = new MeritListPDF($cbe_grading_scale);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 25);

    // Add summary tables
    if ($classSummary) {
        $pdf->GradeCountAnalysisTable($classSummary, $selections, $teacher_name ?? 'Class Teacher');
        $pdf->Ln(5);
        $pdf->CbeSummaryTable($classSummary, $selections, $teacher_name ?? 'Class Teacher');
        $pdf->Ln(5);
    }

    // Merit List Table Title
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(30, 58, 138);
    $pdf->Cell(0, 8, 'STUDENT PERFORMANCE DETAILS (Ranked by A.R - Average Rubric)', 0, 1, 'L');
    $pdf->Ln(2);

    // Calculate column widths
    $pageWidth = 277;
    $colWidths = [
        'sn' => 8,
        'adm' => 18,
        'name' => 35,
        'tm' => 12,
        'tp' => 12,
        'ar' => 18,
        'asr' => 12,
        'crank' => 12,
        'rank' => 12
    ];
    
    $subjectColWidth = 14;
    $totalSubjectWidth = count($subjects) * $subjectColWidth;
    
    if ($totalSubjectWidth + array_sum($colWidths) > $pageWidth) {
        $subjectColWidth = 12;
    }

    // Table Header
    $pdf->SetFillColor(30, 58, 138);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8);

    $pdf->Cell($colWidths['sn'], 12, '#', 1, 0, 'C', true);
    $pdf->Cell($colWidths['adm'], 12, 'Adm No', 1, 0, 'C', true);
    $pdf->Cell($colWidths['name'], 12, 'Student Name', 1, 0, 'C', true);
    
    foreach ($subjects as $subject) {
        $subjectName = isset($subject['subject_name']) ? 
            (strlen($subject['subject_name']) > 8 ? substr($subject['subject_name'], 0, 6) . '..' : $subject['subject_name']) : 
            'Subj';
        $pdf->Cell($subjectColWidth, 12, $subjectName, 1, 0, 'C', true);
    }
    
    $pdf->Cell($colWidths['tm'], 12, 'TM', 1, 0, 'C', true);
    $pdf->Cell($colWidths['tp'], 12, 'TP', 1, 0, 'C', true);
    $pdf->Cell($colWidths['ar'], 12, 'A.R', 1, 0, 'C', true);
    $pdf->Cell($colWidths['asr'], 12, 'AS.R', 1, 0, 'C', true);
    $pdf->Cell($colWidths['crank'], 12, 'C.P', 1, 0, 'C', true);
    $pdf->Cell($colWidths['rank'], 12, 'Rank', 1, 1, 'C', true);

    // Student data rows
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 8);
    
    $fill = false;
    $totalTM = 0;
    $totalTP = 0;
    $totalAR = 0;
    $subjectTotals = array_fill(0, count($subjects), 0);
    
    foreach ($enrichedMeritList as $index => $student) {
        $fill = !$fill;
        
        $pdf->Cell($colWidths['sn'], 8, $index + 1, 1, 0, 'C', $fill);
        $pdf->Cell($colWidths['adm'], 8, $student['admission_no'] ?? 'N/A', 1, 0, 'C', $fill);
        $pdf->Cell($colWidths['name'], 8, $student['full_name'] ?? 'Unknown', 1, 0, 'L', $fill);
        
        // Subject scores - show points (FIXED: No fake grades)
        if (!empty($subjects)) {
            foreach ($subjects as $subIdx => $subject) {
                $subjectId = $subject['id'];
                $subjectScore = isset($student['subject_scores'][$subjectId]) ? $student['subject_scores'][$subjectId] : [];
                $achievement = $subjectScore['achievement_abbreviation'] ?? null;
                
                // Check if achievement exists and is valid
                if (!$achievement || !isset($cbe_grading_scale[$achievement])) {
                    // No valid grade - show dash and skip adding to totals
                    $pdf->Cell($subjectColWidth, 8, '-', 1, 0, 'C', $fill);
                    continue;
                }
                
                $points = $cbe_grading_scale[$achievement]['points'] ?? 4;
                
                if (isset($cbe_grading_scale[$achievement]['color'])) {
                    $color = $cbe_grading_scale[$achievement]['color'];
                    $pdf->SetTextColor($color[0], $color[1], $color[2]);
                }
                
                $pdf->Cell($subjectColWidth, 8, $points, 1, 0, 'C', $fill);
                
                $pdf->SetTextColor(0, 0, 0);
                $subjectTotals[$subIdx] += $points;
            }
        }
        
        // Total Marks
        $tm = floatval($student['total_marks'] ?? 0);
        $pdf->Cell($colWidths['tm'], 8, $tm > 0 ? number_format($tm, 1) : '-', 1, 0, 'C', $fill);
        $totalTM += $tm;
        
        // Total Points
        $tp = floatval($student['total_points'] ?? 0);
        $pdf->Cell($colWidths['tp'], 8, number_format($tp, 1), 1, 0, 'C', $fill);
        $totalTP += $tp;
        
        // A.R - Average Rubric (CORRECTLY CALCULATED)
        $averageRubric = floatval($student['average_rubric'] ?? 0);
        $totalAR += $averageRubric;
        
        // AS.R - Abbreviation of Average Rubric grade (CORRECTLY CALCULATED)
        $asr = $student['asr'] ?? getAbbreviationFromAverageRubric($averageRubric);
        
        // Color code based on AS.R
        if (isset($cbe_grading_scale[$asr]['color'])) {
            $color = $cbe_grading_scale[$asr]['color'];
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
        }
        
        $pdf->Cell($colWidths['ar'], 8, 
            $averageRubric > 0 ? number_format($averageRubric, 2) : '-', 
            1, 0, 'C', $fill);
        
        $pdf->Cell($colWidths['asr'], 8, 
            !empty($asr) ? $asr : '-', 
            1, 0, 'C', $fill);
        
        $pdf->SetTextColor(0, 0, 0);
        
        // Class Position (based on A.R)
        $classPos = $index + 1;
        $pdf->Cell($colWidths['crank'], 8, $classPos, 1, 0, 'C', $fill);
        
        // Overall Rank
        $rank = intval($student['rank'] ?? ($index + 1));
        $pdf->Cell($colWidths['rank'], 8, $rank, 1, 1, 'C', $fill);
    }

    // Totals row
    $studentCount = count($enrichedMeritList);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Cell($colWidths['sn'] + $colWidths['adm'] + $colWidths['name'], 8, 'TOTALS', 1, 0, 'L', true);
    
    foreach ($subjectTotals as $total) {
        $pdf->Cell($subjectColWidth, 8, number_format($total, 1), 1, 0, 'C', true);
    }
    
    $pdf->Cell($colWidths['tm'], 8, number_format($totalTM, 1), 1, 0, 'C', true);
    $pdf->Cell($colWidths['tp'], 8, number_format($totalTP, 1), 1, 0, 'C', true);
    $pdf->Cell($colWidths['ar'], 8, '-', 1, 0, 'C', true);
    $pdf->Cell($colWidths['asr'], 8, '', 1, 0, 'C', true);
    $pdf->Cell($colWidths['crank'], 8, '', 1, 0, 'C', true);
    $pdf->Cell($colWidths['rank'], 8, '', 1, 1, 'C', true);

    // Averages row
    $pdf->Cell($colWidths['sn'] + $colWidths['adm'] + $colWidths['name'], 8, 'AVERAGES', 1, 0, 'L', true);
    
    foreach ($subjectTotals as $total) {
        $avg = $studentCount > 0 ? $total / $studentCount : 0;
        $pdf->Cell($subjectColWidth, 8, number_format($avg, 1), 1, 0, 'C', true);
    }
    
    $pdf->Cell($colWidths['tm'], 8, $totalTM > 0 ? number_format($totalTM / $studentCount, 1) : '-', 1, 0, 'C', true);
    $pdf->Cell($colWidths['tp'], 8, number_format($totalTP / $studentCount, 1), 1, 0, 'C', true);
    $pdf->Cell($colWidths['ar'], 8, number_format($classSummary['classAverageRubric'] ?? 0, 2), 1, 0, 'C', true);
    $pdf->Cell($colWidths['asr'], 8, $classSummary['classAsr'] ?? 'N/A', 1, 0, 'C', true);
    $pdf->Cell($colWidths['crank'], 8, '', 1, 0, 'C', true);
    $pdf->Cell($colWidths['rank'], 8, '', 1, 1, 'C', true);

    // Legend
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->MultiCell(0, 4, 
        "CBE AVERAGE RUBRIC (A.R) CALCULATION:\n" .
        "• A.R = (Sum of points from ALL subjects) ÷ (Number of valid subjects with grades)\n" .
        "• Points: EE=8, ME=7, AE=6, AP=5, BE=4, WB=3, BB=2, EM=1\n" .
        "• AS.R = Abbreviation of the Average Rubric grade (e.g., if A.R = 3.44, AS.R = ME)\n" .
        "• TM = Total Marks | TP = Total Points | C.P = Class Position (based on A.R)", 0, 'L');

    // Output PDF
    if ($print_mode) {
        $pdf->Output('I', 'merit_list_print.pdf');
    } else {
        $pdf->Output('D', 'merit_list_' . date('Ymd_His') . '.pdf');
    }

} catch (Exception $e) {
    error_log('Error in generate_meritlist_pdf.php: ' . $e->getMessage());
    
    if ($print_mode) {
        header('Content-Type: text/html');
        echo '<html><body>';
        echo '<h3>Error Generating PDF</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</body></html>';
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error generating PDF: ' . $e->getMessage()
        ]);
    }
}

$conn->close();
?>