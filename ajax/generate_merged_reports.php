<?php
// ajax/generate_bulk_reports.php - GENERATE INDIVIDUAL REPORT CARDS FOR ALL STUDENTS IN CLASS
session_start();
error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}
$conn->set_charset("utf8mb4");

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$class_id = (int)($input['class_id'] ?? 0);
$stream_id = (int)($input['stream_id'] ?? 0);
$exam_id = (int)($input['exam_id'] ?? 0);
$term_id = (int)($input['term_id'] ?? 0);
$academic_year = $input['academic_year'] ?? date('Y');
$school_id = (int)($input['school_id'] ?? $_SESSION['school_id']);

if ($class_id <= 0 || $exam_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Class and Exam are required']);
    exit();
}

$document_root = $_SERVER['DOCUMENT_ROOT'];
$bulk_reports_dir = $document_root . '/bulk_reports/' . $school_id;
$web_path = '/bulk_reports/' . $school_id;
if (!is_dir($bulk_reports_dir)) mkdir($bulk_reports_dir, 0755, true);

$fpdf_path = dirname(__DIR__) . '/assets/fpdf/fpdf.php';
if (!file_exists($fpdf_path)) {
    echo json_encode(['success' => false, 'message' => 'FPDF library not found']);
    exit();
}
require_once $fpdf_path;

// ==================== PDF CLASS FOR INDIVIDUAL REPORT CARD ====================
class IndividualReportCardPDF extends FPDF {
    public $page_count = 0;
    private $si, $ci, $ti, $ei, $sn, $gs;
    
    public function __construct($si, $ci, $ti, $ei, $sn, $gs) {
        parent::__construct();
        $this->si = $si;
        $this->ci = $ci;
        $this->ti = $ti;
        $this->ei = $ei;
        $this->sn = $sn;
        $this->gs = $gs;
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(false);
    }
    
    private function DB() {
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.6);
        $this->Rect(8, 8, $this->GetPageWidth() - 16, $this->GetPageHeight() - 16);
        $this->SetLineWidth(0.3);
        $this->Rect(10, 10, $this->GetPageWidth() - 20, $this->GetPageHeight() - 20);
    }
    
    public function AddStudentPage($data) {
        $this->AddPage();
        $this->page_count++;
        $this->DB();
        $pw = $this->GetPageWidth();
        $this->SetY(12);
        
        // School header
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, strtoupper($this->si['school_name'] ?? 'SCHOOL'), 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $d = [];
        if (!empty($this->si['school_address'])) $d[] = 'P.O. Box ' . $this->si['school_address'];
        if (!empty($this->si['school_phone'])) $d[] = 'Tel: ' . $this->si['school_phone'];
        if (!empty($this->si['school_email'])) $d[] = 'Email: ' . $this->si['school_email'];
        $this->Cell(0, 4, implode(' | ', $d), 0, 1, 'C');
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.4);
        $this->Line(14, $this->GetY() + 1, $pw - 14, $this->GetY() + 1);
        $this->Ln(4);
        
        // Title
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, 'STUDENT REPORT FORM', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 4, $this->ti['name'] ?? 'Term', 0, 1, 'C');
        $this->SetLineWidth(0.4);
        $this->Line(14, $this->GetY() + 1, $pw - 14, $this->GetY() + 1);
        $this->Ln(4);
        
        // Student details boxes
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $cw = ($pw - 28) / 3;
        $this->Cell($cw, 6, 'STUDENT DETAILS', 1, 0, 'C', true);
        $this->Cell($cw, 6, 'CLASS INFORMATION', 1, 0, 'C', true);
        $this->Cell($cw, 6, 'EXAMINATION DETAILS', 1, 1, 'C', true);
        
        $this->SetTextColor(0, 0, 0);
        $rh = 5.5;
        $lw = 25;
        $x1 = 14;
        $y1 = $this->GetY();
        
        // Student details
        $this->SetXY($x1 + 2, $y1 + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'ADM NO:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, $data['student_adm'] ?? '', 0, 0, 'L');
        
        $this->SetXY($x1 + 2, $y1 + $rh + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'NAME:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, $data['student_name'] ?? '', 0, 0, 'L');
        
        $this->SetXY($x1 + 2, $y1 + 2 * $rh + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'UPI NO:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, $data['upi_no'] ?? 'N/A', 0, 0, 'L');
        
        // Class information
        $x2 = $x1 + $cw;
        $this->SetXY($x2 + 2, $y1 + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'CLASS:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, $data['class_name'] ?? '', 0, 0, 'L');
        
        $this->SetXY($x2 + 2, $y1 + $rh + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'STREAM:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, !empty($data['stream_name']) ? $data['stream_name'] : 'N/A', 0, 0, 'L');
        
        $this->SetXY($x2 + 2, $y1 + 2 * $rh + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'YEAR:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, $data['academic_year'] ?? '', 0, 0, 'L');
        
        // Examination details
        $x3 = $x2 + $cw;
        $this->SetXY($x3 + 2, $y1 + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'ENTRY:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, $data['class_total'] ?? '0', 0, 0, 'L');
        
        $this->SetXY($x3 + 2, $y1 + $rh + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'TERM:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, $this->ti['name'] ?? '', 0, 0, 'L');
        
        $this->SetXY($x3 + 2, $y1 + 2 * $rh + 1);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($lw, $rh, 'EXAM:', 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->Cell($cw - $lw - 4, $rh, $data['exam_name'] ?? '', 0, 0, 'L');
        
        $bh = 3 * $rh + 3;
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->Rect($x1, $y1, $cw, $bh);
        $this->Rect($x2, $y1, $cw, $bh);
        $this->Rect($x3, $y1, $cw, $bh);
        $this->SetY($y1 + $bh + 3);
        
        // Academic Performance Table
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($pw - 28, 6, 'ACADEMIC PERFORMANCE', 1, 1, 'C', true);
        
        $subjects = $data['subjects'] ?? [];
        
        if (!empty($subjects)) {
            $w = [48, 20, 18, 12, 18, 26, 30];
            $this->SetFont('Arial', 'B', 7);
            $this->SetTextColor(0, 0, 0);
            $this->SetFillColor(230, 230, 230);
            $hd = ['SUBJECT', 'MARKS', 'GRADE', 'POINTS', 'RANK', 'REMARK', 'TEACHER'];
            $this->SetX(14);
            foreach ($hd as $i => $h) $this->Cell($w[$i], 6, $h, 1, 0, 'C', true);
            $this->Ln();
            $this->SetFont('Arial', '', 6.5);
            $fl = false;
            
            foreach ($subjects as $i => $subject) {
                $this->SetFillColor($fl ? 245 : 255, $fl ? 245 : 255, $fl ? 245 : 255);
                $this->SetTextColor(0, 0, 0);
                $this->SetX(14);
                
                $this->Cell($w[0], 5, substr($subject['name'] ?? '', 0, 30), 1, 0, 'L', true);
                $this->Cell($w[1], 5, round($subject['score'] ?? 0, 2) . '/' . round($subject['total'] ?? 100, 2), 1, 0, 'C', true);
                $this->Cell($w[2], 5, $subject['grade'] ?? 'N/A', 1, 0, 'C', true);
                $this->Cell($w[3], 5, $subject['points'] ?? 0, 1, 0, 'C', true);
                $this->Cell($w[4], 5, $subject['rank'] ?? ($i + 1), 1, 0, 'C', true);
                $this->Cell($w[5], 5, substr($subject['remarks'] ?? '', 0, 25), 1, 0, 'L', true);
                $this->Cell($w[6], 5, substr($subject['teacher'] ?? 'Staff', 0, 20), 1, 1, 'L', true);
                $fl = !$fl;
            }
        }
        
        // Summary Boxes
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($pw - 28, 6, 'PERFORMANCE SUMMARY', 1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        
        $bw = ($pw - 32) / 4;
        $bhy = 10;
        $y = $this->GetY();
        $x = 14;
        $subject_count = count($subjects);
        $total_points_possible = $subject_count * 8;
        $total_points_earned = array_sum(array_column($subjects, 'points'));
        
        // Total Marks
        $this->SetXY($x, $y);
        $this->Cell($bw, $bhy, '', 1, 0, 'C');
        $this->SetXY($x, $y + 1);
        $this->SetFont('Arial', '', 6);
        $this->Cell($bw, 3, 'TOTAL MARKS', 0, 0, 'C');
        $this->SetXY($x, $y + 4);
        $this->SetFont('Arial', 'B', 8);
        $total_possible_marks = $subject_count * 100;
        $this->Cell($bw, 4, ($data['total_raw_score'] ?? 0) . '/' . $total_possible_marks, 0, 0, 'C');
        $x += $bw + 2;
        
        // CBC Points
        $this->SetXY($x, $y);
        $this->Cell($bw, $bhy, '', 1, 0, 'C');
        $this->SetXY($x, $y + 1);
        $this->SetFont('Arial', '', 6);
        $this->Cell($bw, 3, 'CBC POINTS', 0, 0, 'C');
        $this->SetXY($x, $y + 4);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($bw, 4, $total_points_earned . '/' . $total_points_possible, 0, 0, 'C');
        $x += $bw + 2;
        
        // Overall Grade
        $this->SetXY($x, $y);
        $this->Cell($bw, $bhy, '', 1, 0, 'C');
        $this->SetXY($x, $y + 1);
        $this->SetFont('Arial', '', 6);
        $this->Cell($bw, 3, 'OVERALL GRADE', 0, 0, 'C');
        $this->SetXY($x, $y + 4);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($bw, 4, $data['overall_grade'] ?? 'N/A', 0, 0, 'C');
        $x += $bw + 2;
        
        // Class Position
        $this->SetXY($x, $y);
        $this->Cell($bw, $bhy, '', 1, 0, 'C');
        $this->SetXY($x, $y + 1);
        $this->SetFont('Arial', '', 6);
        $this->Cell($bw, 3, 'CLASS POSITION', 0, 0, 'C');
        $this->SetXY($x, $y + 4);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($bw, 4, ($data['class_rank'] ?? 'N/A') . ' / ' . ($data['class_total'] ?? '0'), 0, 0, 'C');
        $this->SetY($y + $bhy + 2);
        
        $y2 = $this->GetY();
        $x = 14;
        $bw2 = ($pw - 32) / 3;
        
        // Mean Points
        $this->SetXY($x, $y2);
        $this->Cell($bw2, $bhy, '', 1, 0, 'C');
        $this->SetXY($x, $y2 + 1);
        $this->SetFont('Arial', '', 6);
        $this->Cell($bw2, 3, 'MEAN POINTS', 0, 0, 'C');
        $this->SetXY($x, $y2 + 4);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($bw2, 4, number_format($data['mean_points'] ?? 0, 2), 0, 0, 'C');
        $x += $bw2 + 2;
        
        // Mean Percentage
        $this->SetXY($x, $y2);
        $this->Cell($bw2, $bhy, '', 1, 0, 'C');
        $this->SetXY($x, $y2 + 1);
        $this->SetFont('Arial', '', 6);
        $this->Cell($bw2, 3, 'MEAN SCORE (%)', 0, 0, 'C');
        $this->SetXY($x, $y2 + 4);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($bw2, 4, round($data['mean_percentage'] ?? 0, 1) . '%', 0, 0, 'C');
        $x += $bw2 + 2;
        
        // Pathway Recommendation
        if (!empty($data['pathway_recommendation'])) {
            $this->SetXY($x, $y2);
            $this->Cell($bw2, $bhy, '', 1, 0, 'C');
            $this->SetXY($x, $y2 + 1);
            $this->SetFont('Arial', '', 6);
            $this->Cell($bw2, 3, 'RECOMMENDED PATHWAY', 0, 0, 'C');
            $this->SetXY($x, $y2 + 4);
            $this->SetFont('Arial', 'B', 7);
            $pathway_text = $data['pathway_recommendation'];
            if (strlen($pathway_text) > 25) {
                $pathway_text = substr($pathway_text, 0, 22) . '...';
            }
            $this->Cell($bw2, 4, $pathway_text, 0, 0, 'C');
        }
        
        $this->SetY($y2 + $bhy + 3);
        
        // Remarks sections
        $ry = $this->GetY();
        $rh2 = 18;
        $rw2 = ($pw - 32) / 2;
        
        $this->SetXY(14, $ry);
        $this->SetFont('Arial', 'B', 7);
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($rw2, 5, "CLASS TEACHER'S REMARKS", 1, 0, 'C', true);
        $this->SetXY(14, $ry + 5);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($rw2, $rh2 - 5, '', 1, 0);
        
        $this->SetXY(14 + $rw2 + 4, $ry);
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($rw2, 5, "PRINCIPAL'S REMARKS", 1, 0, 'C', true);
        $this->SetXY(14 + $rw2 + 4, $ry + 5);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($rw2, $rh2 - 5, '', 1, 0);
        $this->SetY($ry + $rh2 + 3);
        
        // Footer signatures
        $fy = $this->GetY();
        $fww = ($pw - 32) / 3;
        $ff = [
            ['Teacher:', 'Signature:', 'Date:'],
            ['Closing Date:', 'Re-opening:', 'Balance:'],
            ['Next Fees:', 'Total Fees:', 'Parent Sign:']
        ];
        for ($r = 0; $r < 3; $r++) {
            $fx = 14;
            for ($c = 0; $c < 3; $c++) {
                $this->SetXY($fx, $fy + $r * 10);
                $this->SetFont('Arial', 'B', 6);
                $this->Cell($fww, 4, $ff[$c][$r], 0, 0, 'L');
                $this->SetDrawColor(0, 0, 0);
                $this->Line($fx + 22, $fy + $r * 10 + 4, $fx + $fww - 2, $fy + $r * 10 + 4);
                $fx += $fww + 2;
            }
        }
        
        $this->SetY(-16);
        $this->SetFont('Arial', 'I', 6);
        $this->Cell($pw - 28, 4, $this->si['school_motto'] ?? '', 0, 0, 'L');
        $this->Cell(0, 4, 'Page ' . $this->PageNo(), 0, 0, 'R');
    }
}

// ==================== KENYAN CBC (CBE) 8-LEVEL GRADING SCALE ====================
function getDefaultKenyanCBCGradingScale() {
    return [
        ['lower_limit' => 90, 'upper_limit' => 100, 'grade' => 'EE1', 'points' => 8.00, 'remarks' => 'Exceeding Expectations - Outstanding'],
        ['lower_limit' => 75, 'upper_limit' => 89, 'grade' => 'EE2', 'points' => 7.00, 'remarks' => 'Exceeding Expectations - Excellent'],
        ['lower_limit' => 58, 'upper_limit' => 74, 'grade' => 'ME1', 'points' => 6.00, 'remarks' => 'Meeting Expectations - Good'],
        ['lower_limit' => 41, 'upper_limit' => 57, 'grade' => 'ME2', 'points' => 5.00, 'remarks' => 'Meeting Expectations - Satisfactory'],
        ['lower_limit' => 31, 'upper_limit' => 40, 'grade' => 'AE1', 'points' => 4.00, 'remarks' => 'Approaching Expectations - Developing'],
        ['lower_limit' => 21, 'upper_limit' => 30, 'grade' => 'AE2', 'points' => 3.00, 'remarks' => 'Approaching Expectations - Progressing'],
        ['lower_limit' => 11, 'upper_limit' => 20, 'grade' => 'BE1', 'points' => 2.00, 'remarks' => 'Below Expectations - Minimal'],
        ['lower_limit' => 1, 'upper_limit' => 10, 'grade' => 'BE2', 'points' => 1.00, 'remarks' => 'Below Expectations - Basic']
    ];
}

function getGradingScale($conn, $school_id, $class_id = null, $stream_id = null) {
    if ($class_id && $class_id > 0 && $stream_id && $stream_id > 0) {
        $sql = "SELECT lower_limit, upper_limit, grade, points, remarks FROM tblgradingscale 
                WHERE school_id = ? AND class_id = ? AND stream_id = ? ORDER BY lower_limit ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $school_id, $class_id, $stream_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!empty($result)) return $result;
    }
    
    if ($class_id && $class_id > 0) {
        $sql = "SELECT lower_limit, upper_limit, grade, points, remarks FROM tblgradingscale 
                WHERE school_id = ? AND class_id = ? AND (stream_id IS NULL OR stream_id = 0) ORDER BY lower_limit ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $school_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!empty($result)) return $result;
    }
    
    $sql = "SELECT lower_limit, upper_limit, grade, points, remarks FROM tblgradingscale 
            WHERE school_id = ? AND (class_id IS NULL OR class_id = 0) ORDER BY lower_limit ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (!empty($result)) return $result;
    
    return getDefaultKenyanCBCGradingScale();
}

function getGradeFromScore($score, $grading_scale) {
    foreach ($grading_scale as $scale) {
        if ($score >= $scale['lower_limit'] && $score <= $scale['upper_limit']) {
            return ['grade' => $scale['grade'], 'points' => floatval($scale['points']), 'remarks' => $scale['remarks']];
        }
    }
    return ['grade' => 'N/A', 'points' => 0, 'remarks' => 'No grade assigned'];
}

function getPathwayRecommendation($subjects_data) {
    $stem_subjects = ['Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Integrated Science', 'Agriculture', 'PRETECHNICAL STUDIES'];
    $social_sciences = ['English', 'Kiswahili', 'History', 'Geography', 'CRE', 'IRE', 'HRE', 'Social Studies', 'Business Studies'];
    $arts_sports = ['Creative Arts', 'Music', 'Drama', 'Physical Education', 'Sports', 'Art & Design', 'CHRISTIAN RELIGIOUS EDUCATION'];
    
    $stem_points = 0;
    $social_points = 0;
    $arts_points = 0;
    $stem_count = 0;
    $social_count = 0;
    $arts_count = 0;
    
    foreach ($subjects_data as $subject) {
        $subject_name = $subject['name'];
        $points = $subject['points'];
        $subject_upper = strtoupper($subject_name);
        
        foreach ($stem_subjects as $stem) {
            if (stripos($subject_upper, strtoupper($stem)) !== false) {
                $stem_points += $points;
                $stem_count++;
                break;
            }
        }
        foreach ($social_sciences as $social) {
            if (stripos($subject_upper, strtoupper($social)) !== false) {
                $social_points += $points;
                $social_count++;
                break;
            }
        }
        foreach ($arts_sports as $arts) {
            if (stripos($subject_upper, strtoupper($arts)) !== false) {
                $arts_points += $points;
                $arts_count++;
                break;
            }
        }
    }
    
    $stem_avg = $stem_count > 0 ? $stem_points / $stem_count : 0;
    $social_avg = $social_count > 0 ? $social_points / $social_count : 0;
    $arts_avg = $arts_count > 0 ? $arts_points / $arts_count : 0;
    
    $max_avg = max($stem_avg, $social_avg, $arts_avg);
    if ($max_avg == $stem_avg && $stem_avg > 0) {
        return 'STEM (Science, Technology, Engineering, Mathematics)';
    } elseif ($max_avg == $social_avg && $social_avg > 0) {
        return 'Social Sciences (Humanities, Languages, Business)';
    } elseif ($arts_avg > 0) {
        return 'Arts & Sports (Creative Arts, Performing Arts, Sports Science)';
    }
    return 'General Pathway (Multiple Options Available)';
}

function getTeacherForSubject($conn, $subject_id, $class_id, $stream_id, $school_id) {
    $sql = "SELECT 
                CONCAT(COALESCE(t.title, ''), ' ', 
                       t.firstname, ' ', 
                       COALESCE(t.secondname, ''), ' ', 
                       t.lastname) as teacher_name
            FROM tbllessons l
            INNER JOIN tblteachers t ON t.id = l.teacher_id
            WHERE l.subject_id = ? 
                AND l.class_id = ?
                AND l.school_id = ?
                AND (l.stream_id = ? OR l.stream_id IS NULL OR l.stream_id = 0)
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $subject_id, $class_id, $school_id, $stream_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result && !empty($result['teacher_name'])) {
        return trim($result['teacher_name']);
    }
    return 'Staff';
}

function getStudentFullScores($conn, $student_id, $class_id, $stream_id, $exam_id, $school_id, $grading_scale, &$subject_ranks) {
    $sql = "SELECT 
                ts.subject_id,
                ts.score_value,
                ts.total_score,
                ts.percentage,
                sub.subject_name
            FROM tblscores ts
            INNER JOIN tblsubjects sub ON sub.id = ts.subject_id
            WHERE ts.student_id = ?
                AND ts.class_id = ?
                AND ts.exam_id = ?
                AND ts.school_id = ?
            ORDER BY sub.subject_name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $student_id, $class_id, $exam_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subjects = [];
    $total_raw = 0;
    $total_cbc_points = 0;
    
    while ($row = $result->fetch_assoc()) {
        $score = floatval($row['score_value']);
        $total = floatval($row['total_score'] > 0 ? $row['total_score'] : 100);
        $percentage = floatval($row['percentage'] > 0 ? $row['percentage'] : ($score / $total * 100));
        $grade_info = getGradeFromScore($percentage, $grading_scale);
        
        $subject_name = $row['subject_name'];
        $subject_rank = $subject_ranks[$student_id][$subject_name] ?? (count($subjects) + 1);
        $teacher_name = getTeacherForSubject($conn, $row['subject_id'], $class_id, $stream_id, $school_id);
        
        $subjects[] = [
            'name' => $subject_name,
            'score' => $score,
            'total' => $total,
            'percentage' => round($percentage, 2),
            'grade' => $grade_info['grade'],
            'points' => $grade_info['points'],
            'remarks' => $grade_info['remarks'],
            'teacher' => $teacher_name,
            'rank' => $subject_rank
        ];
        
        $total_raw += $score;
        $total_cbc_points += $grade_info['points'];
    }
    $stmt->close();
    
    return [$subjects, $total_raw, $total_cbc_points];
}

function getAllStudentsData($conn, $class_id, $stream_id, $exam_id, $school_id, $grading_scale) {
    $stream_condition = "";
    if ($stream_id > 0) {
        $stream_condition = " AND ts.StreamId = " . intval($stream_id);
    }
    
    // Get subject rankings first
    $rank_sql = "SELECT 
                    ts.student_id,
                    ts.subject_id,
                    ts.score_value,
                    sub.subject_name
                FROM tblscores ts
                INNER JOIN tblsubjects sub ON sub.id = ts.subject_id
                WHERE ts.class_id = ? 
                    AND ts.school_id = ? 
                    AND ts.exam_id = ?
                    $stream_condition
                ORDER BY sub.subject_name, ts.score_value DESC";
    
    $rank_stmt = $conn->prepare($rank_sql);
    $rank_stmt->bind_param("iii", $class_id, $school_id, $exam_id);
    $rank_stmt->execute();
    $rank_result = $rank_stmt->get_result();
    
    $subject_scores = [];
    $all_students = [];
    
    while ($row = $rank_result->fetch_assoc()) {
        $subject_name = $row['subject_name'];
        $student_id = $row['student_id'];
        $score = floatval($row['score_value']);
        
        if (!isset($subject_scores[$subject_name])) {
            $subject_scores[$subject_name] = [];
        }
        $subject_scores[$subject_name][] = ['student_id' => $student_id, 'score' => $score];
        
        if (!in_array($student_id, $all_students)) {
            $all_students[] = $student_id;
        }
    }
    $rank_stmt->close();
    
    // Calculate subject ranks
    $subject_ranks = [];
    foreach ($subject_scores as $subject_name => &$scores_list) {
        usort($scores_list, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        $rank = 1;
        $prev_score = null;
        $same_rank_count = 0;
        foreach ($scores_list as $index => $item) {
            if ($prev_score !== null && $item['score'] == $prev_score) {
                $same_rank_count++;
                $subject_ranks[$item['student_id']][$subject_name] = $rank - $same_rank_count;
            } else {
                $rank = $index + 1;
                $same_rank_count = 0;
                $subject_ranks[$item['student_id']][$subject_name] = $rank;
            }
            $prev_score = $item['score'];
        }
    }
    
    // Get class rankings based on TOTAL MARKS
    $total_sql = "SELECT 
                    student_id, 
                    SUM(score_value) as total_marks,
                    AVG(score_value) as mean_percentage,
                    COUNT(*) as subject_count
                  FROM tblscores 
                  WHERE class_id = ? AND school_id = ? AND exam_id = ?
                  GROUP BY student_id
                  ORDER BY total_marks DESC";
    
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->bind_param("iii", $class_id, $school_id, $exam_id);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    
    $student_totals = [];
    while ($row = $total_result->fetch_assoc()) {
        $student_totals[] = [
            'student_id' => $row['student_id'],
            'total_marks' => round($row['total_marks'], 2),
            'mean_percentage' => round($row['mean_percentage'], 2),
            'subject_count' => $row['subject_count']
        ];
    }
    $total_stmt->close();
    
    usort($student_totals, function($a, $b) {
        return $b['total_marks'] - $a['total_marks'];
    });
    
    $class_rankings = [];
    $rank = 1;
    $prev_total = null;
    $same_rank_count = 0;
    
    foreach ($student_totals as $index => $student) {
        if ($prev_total !== null && $student['total_marks'] == $prev_total) {
            $same_rank_count++;
            $current_rank = $rank - $same_rank_count;
        } else {
            $rank = $index + 1;
            $same_rank_count = 0;
            $current_rank = $rank;
        }
        $class_rankings[$student['student_id']] = $current_rank;
        $prev_total = $student['total_marks'];
    }
    
    // Get student basic info
    $student_ids = array_column($student_totals, 'student_id');
    if (empty($student_ids)) return [];
    
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $student_sql = "SELECT 
                        id, 
                        AdmNo as admission_no, 
                        assessment_no as upi_no,
                        CONCAT(TRIM(FirstName), ' ', 
                               COALESCE(NULLIF(TRIM(SecondName), ''), ''), 
                               CASE WHEN TRIM(LastName) IS NOT NULL AND TRIM(LastName) != '' 
                                    THEN CONCAT(' ', TRIM(LastName)) 
                                    ELSE '' 
                               END) as full_name 
                    FROM tblstudents 
                    WHERE id IN ($placeholders) AND school_id = ?";
    
    $student_stmt = $conn->prepare($student_sql);
    $types = str_repeat('i', count($student_ids)) . 'i';
    $params = array_merge($student_ids, [$school_id]);
    $student_stmt->bind_param($types, ...$params);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    
    $students = [];
    while ($row = $student_result->fetch_assoc()) {
        $students[$row['id']] = $row;
    }
    $student_stmt->close();
    
    // Build complete student data
    $students_data = [];
    foreach ($student_ids as $student_id) {
        if (!isset($students[$student_id])) continue;
        
        $student = $students[$student_id];
        list($subjects, $total_raw, $total_cbc_points) = getStudentFullScores(
            $conn, $student_id, $class_id, $stream_id, $exam_id, $school_id, $grading_scale, $subject_ranks
        );
        
        if (empty($subjects)) continue;
        
        $subject_count = count($subjects);
        $mean_percentage = $subject_count > 0 ? round(($total_raw / ($subject_count * 100)) * 100, 2) : 0;
        $mean_cbc_points = $subject_count > 0 ? round($total_cbc_points / $subject_count, 2) : 0;
        $class_rank = $class_rankings[$student_id] ?? null;
        $overall_grade_info = getGradeFromScore($mean_percentage, $grading_scale);
        $overall_grade = $overall_grade_info['grade'];
        
        $pathway_recommendation = '';
        $class_level = strtolower($class_info['class_level'] ?? '');
        if (strpos($class_level, 'grade 9') !== false || strpos($class_level, 'grade 8') !== false || strpos($class_level, 'junior') !== false) {
            $pathway_recommendation = getPathwayRecommendation($subjects);
        }
        
        $students_data[] = [
            'student' => $student,
            'subjects' => $subjects,
            'total_marks' => round($total_raw, 2),
            'mean_percentage' => $mean_percentage,
            'mean_points' => $mean_cbc_points,
            'class_rank' => $class_rank,
            'overall_grade' => $overall_grade,
            'pathway_recommendation' => $pathway_recommendation
        ];
    }
    
    return $students_data;
}

function getSchoolInfo($conn, $school_id) {
    $sql = "SELECT id, school_name, school_address, school_motto, school_email, school_phone FROM tblschoolinfo WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ?: ['id' => $school_id, 'school_name' => 'School', 'school_address' => '', 'school_email' => '', 'school_phone' => '', 'school_motto' => ''];
}

function getClassInfo($conn, $class_id) {
    $sql = "SELECT id, class_level FROM tblclasses WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ?: ['id' => (string)$class_id, 'class_level' => 'Class'];
}

function getStreamName($conn, $stream_id) {
    if ($stream_id <= 0) return '';
    $sql = "SELECT stream_name FROM tblstreams WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $stream_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['stream_name'] ?? '';
}

function getTermInfo($conn, $term_id) {
    $sql = "SELECT id, term_name as name FROM tblterms WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $term_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ?: ['id' => $term_id, 'name' => 'Term'];
}

function getExamInfo($conn, $exam_id) {
    $sql = "SELECT id, examname as name FROM tblexam WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ?: ['id' => $exam_id, 'name' => 'Exam'];
}

function saveBulkReportRecord($conn, $school_id, $class_id, $stream_id, $exam_id, $term_id, $academic_year, $pdf_url, $total_students, $total_pages) {
    $check_table = $conn->query("SHOW TABLES LIKE 'bulk_reports'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS `bulk_reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `school_id` int(11) NOT NULL,
            `class_id` varchar(255) NOT NULL,
            `stream_id` int(11) DEFAULT NULL,
            `exam_id` int(11) NOT NULL,
            `term_id` int(11) DEFAULT NULL,
            `academic_year` varchar(20) DEFAULT NULL,
            `pdf_url` varchar(255) DEFAULT NULL,
            `total_students` int(11) DEFAULT 0,
            `total_pages` int(11) DEFAULT 0,
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");
    }
    
    $stmt = $conn->prepare("INSERT INTO bulk_reports 
        (school_id, class_id, stream_id, exam_id, term_id, academic_year, pdf_url, total_students, total_pages, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $class_id_str = (string)$class_id;
    $stmt->bind_param("isiisssii", $school_id, $class_id_str, $stream_id, $exam_id, $term_id, $academic_year, $pdf_url, $total_students, $total_pages);
    $stmt->execute();
    $stmt->close();
}

// ==================== MAIN PROCESSING ====================
try {
    $school_info = getSchoolInfo($conn, $school_id);
    $class_info = getClassInfo($conn, $class_id);
    $stream_name = getStreamName($conn, $stream_id);
    $term_info = getTermInfo($conn, $term_id);
    $exam_info = getExamInfo($conn, $exam_id);
    $grading_scale = getGradingScale($conn, $school_id, $class_id, $stream_id);
    
    // Get ALL students data
    $students_data = getAllStudentsData($conn, $class_id, $stream_id, $exam_id, $school_id, $grading_scale);
    
    if (empty($students_data)) {
        echo json_encode(['success' => false, 'message' => 'No student data found for the selected class and exam']);
        exit();
    }
    
    $class_total = count($students_data);
    $total_mean = array_sum(array_column($students_data, 'mean_percentage'));
    $class_mean = $class_total > 0 ? round($total_mean / $class_total, 2) : 0;
    
    // Generate PDF with all students
    $safe_class_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $class_info['class_level']);
    $safe_exam_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $exam_info['name']);
    $filename = 'bulk_reports_' . $safe_class_name . '_' . $safe_exam_name . '_' . time() . '.pdf';
    $filepath = $bulk_reports_dir . '/' . $filename;
    $web_url = $web_path . '/' . $filename;
    
    $pdf = new IndividualReportCardPDF($school_info, $class_info, $term_info, $exam_info, $stream_name, $grading_scale);
    
    foreach ($students_data as $data) {
        $pdf->AddStudentPage([
            'student_adm' => $data['student']['admission_no'],
            'student_name' => $data['student']['full_name'],
            'class_name' => $class_info['class_level'] ?? 'Class',
            'stream_name' => $stream_name,
            'academic_year' => $academic_year,
            'exam_name' => $exam_info['name'] ?? 'Exam',
            'upi_no' => $data['student']['upi_no'] ?? 'N/A',
            'class_total' => $class_total,
            'subjects' => $data['subjects'],
            'total_raw_score' => $data['total_marks'],
            'mean_percentage' => $data['mean_percentage'],
            'mean_points' => $data['mean_points'],
            'overall_grade' => $data['overall_grade'],
            'class_rank' => $data['class_rank'],
            'pathway_recommendation' => $data['pathway_recommendation']
        ]);
    }
    
    $total_pages = $pdf->page_count;
    $pdf->Output('F', $filepath);
    
    if (!file_exists($filepath) || filesize($filepath) < 1000) {
        throw new Exception("PDF generation failed");
    }
    
    // Save record
    saveBulkReportRecord($conn, $school_id, $class_id, $stream_id, $exam_id, $term_id, $academic_year, $web_url, $class_total, $total_pages);
    
    echo json_encode([
        'success' => true,
        'pdf_url' => $web_url,
        'filename' => $filename,
        'total_pages' => $total_pages,
        'total_students' => $class_total,
        'class_mean' => $class_mean,
        'message' => "Bulk report generated successfully for $class_total student(s) ($total_pages page(s))"
    ]);
    
} catch (Exception $e) {
    error_log("Error generating bulk report: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>