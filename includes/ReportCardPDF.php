<?php
// includes/ReportCardPDF.php - COMPLETELY REDESIGNED FOR MULTI-EXAM SUPPORT

require_once(__DIR__ . '/../assets/fpdf/fpdf.php');

class PersonalReportCardPDF extends FPDF {

    protected $school_info;
    protected $report_data;

    public function __construct($school_info, $report_data) {
        parent::__construct('P', 'mm', 'A4');
        $this->school_info = $school_info;
        $this->report_data = $report_data;

        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 20);
        
        // Alias for total pages
        $this->AliasNbPages();
    }

    /* =========================================================
       HEADER
    ==========================================================*/
    function Header() {
        /* ---------------- SCHOOL LOGO (LEFT) ---------------- */
        $schoolLogo = $this->school_info['logo_path'] ?? null;
        $schoolLogoPath = '../' . ($schoolLogo && file_exists('../' . $schoolLogo) ? $schoolLogo : 'uploads/logos/default.png');

        $logoWidth = 25;
        $logoHeight = 25;
        
        if (file_exists($schoolLogoPath)) {
            $this->Image($schoolLogoPath, 15, 15, $logoWidth, $logoHeight);
        }

        /* ---------------- STUDENT PHOTO (RIGHT) ---------------- */
        $photo = $this->report_data['profile_pic'] ?? null;
        $photoPath = '../' . ($photo && file_exists('../' . $photo) ? $photo : 'images/default-avatar.png');

        $x = 170;
        $y = 15;
        $r = 15;

        // Draw circular border
        $this->SetLineWidth(0.5);
        $this->Circle($x + $r, $y + $r, $r, 'D');

        // Place image inside circle
        if (file_exists($photoPath)) {
            $this->Image($photoPath, $x, $y, $r * 2, $r * 2);
        }

        /* ---------------- SCHOOL INFO (CENTERED) ---------------- */
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(45, 15);
        $this->Cell(120, 7, strtoupper($this->school_info['school_name'] ?? 'SCHOOL NAME'), 0, 1, 'C');

        $this->SetFont('Arial', '', 10);
        $this->SetX(45);
        $this->Cell(120, 5, $this->school_info['school_address'] ?? 'P.O BOX 000 - CITY', 0, 1, 'C');

        $contact = "";
        if (!empty($this->school_info['school_phone'])) {
            $contact .= "Phone: " . $this->school_info['school_phone'];
        }
        if (!empty($this->school_info['email'])) {
            $contact .= " | Email: " . $this->school_info['email'];
        }

        $this->SetX(45);
        $this->Cell(120, 5, $contact, 0, 1, 'C');

        $this->Ln(3);

        /* ---------------- TITLE ---------------- */
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'STUDENT REPORT FORM', 0, 1, 'C');

        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(5);
    }

    /* =========================================================
       STUDENT DETAILS BOX
    ==========================================================*/
    function StudentDetailsBox() {
        $data = $this->report_data;

        $this->SetFont('Arial', '', 9);

        // Row 1
        $this->Cell(60, 6, 'ADM NO: ' . ($data['student_adm'] ?? 'N/A'), 1);
        $this->Cell(60, 6, 'CLASS: ' . ($data['class_name'] ?? 'N/A'), 1);
        $this->Cell(60, 6, 'ENTRY: N/A', 1);
        $this->Ln();

        // Row 2
        $this->Cell(60, 6, 'NAME: ' . ($data['student_name'] ?? 'N/A'), 1);
        $this->Cell(60, 6, 'STREAM: ' . ($data['stream_name'] ?? 'N/A'), 1);
        $this->Cell(60, 6, 'TERM: ' . ($data['term_name'] ?? 'N/A'), 1);
        $this->Ln();

        // Row 3
        $this->Cell(60, 6, 'UPI NO: ' . ($data['upi_no'] ?? 'N/A'), 1);
        $this->Cell(60, 6, 'YEAR: ' . ($data['academic_year'] ?? date('Y')), 1);
        $this->Cell(60, 6, 'EXAM PERIOD: ' . ($data['exam_name'] ?? 'Term'), 1);
        $this->Ln(8);
    }

    /* =========================================================
       SUBJECTS TABLE - REDESIGNED FOR MID/END/AVG
    ==========================================================*/
    function SubjectsTable() {
        $subjects = $this->report_data['subjects'] ?? [];

        if (empty($subjects)) {
            $this->Cell(0, 10, "No subject data available", 0, 1, 'C');
            return;
        }

        /* ===== COLUMN WIDTHS (TOTAL = 180) ===== */
        $w = [30, 15, 15, 18, 18, 18, 36, 30];

        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(200, 200, 200);

        $headers = ['SUBJECT', 'MID', 'END', 'AVG', 'GRADE', 'RUBRIC', 'REMARK', 'TEACHER'];

        foreach ($headers as $i => $h) {
            $this->Cell($w[$i], 7, $h, 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('Arial', '', 8);

        foreach ($subjects as $subject) {
            $row = [
                $subject['name'] ?? '',
                $subject['mid_term'] ?? '-',
                $subject['end_term'] ?? '-',
                round($subject['average'] ?? 0, 1),
                $subject['grade'] ?? '',
                $subject['points'] ?? '',
                $subject['remarks'] ?? '',
                $subject['teacher'] ?? ''
            ];

            $this->Row($row, $w);
        }

        // Render summary after subjects
        $this->RenderSummary();
    }

    /* =========================================================
       SUMMARY SECTION - WITH RUBRIC AND POSITIONS
    ==========================================================*/
    function RenderSummary() {
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(230, 230, 230);

        $totalMarks = $this->report_data['total_marks'] ?? 0;
        $avgRubric = round($this->report_data['avg_rubric'] ?? 0, 2);
        $lastTerm = round($this->report_data['last_term_avg_rubric'] ?? 0, 2);

        // Get overall grade from grading scale based on avg rubric
        $gradingScale = $this->report_data['grading_scale'] ?? [];
        $overallGrade = 'N/A';
        
        foreach ($gradingScale as $scale) {
            if ($avgRubric >= $scale['lower_limit'] && $avgRubric <= $scale['upper_limit']) {
                $overallGrade = $scale['grade'];
                break;
            }
        }

        /* ===== ROW 1: 5 columns ===== */
        $w = 180 / 5;

        $this->Cell($w, 7, "Total Marks: $totalMarks", 1, 0, 'L', true);
        $this->Cell($w, 7, "Grade: $overallGrade", 1, 0, 'L', true);
        $this->Cell($w, 7, "Avg Rubric: $avgRubric", 1, 0, 'L', true);
        $this->Cell($w, 7, "Last Term Avg: $lastTerm", 1, 0, 'L', true);
        $this->Cell($w, 7, "", 1, 1, 'L', true);

        /* ===== ROW 2: 2 columns for positions ===== */
        $w2 = 180 / 2;

        $streamPos = $this->report_data['stream_position'] ?? 'N/A';
        $streamTotal = $this->report_data['stream_total'] ?? 0;
        $classPos = $this->report_data['class_position'] ?? 'N/A';
        $classTotal = $this->report_data['class_total'] ?? 0;

        $streamDisplay = ($streamPos !== 'N/A' && $streamTotal > 0) 
            ? "Stream Position: $streamPos / $streamTotal" 
            : "Stream Position: N/A";
        
        $classDisplay = ($classPos !== 'N/A' && $classTotal > 0) 
            ? "Class Position: $classPos / $classTotal" 
            : "Class Position: N/A";

        $this->Cell($w2, 7, $streamDisplay, 1, 0, 'L', true);
        $this->Cell($w2, 7, $classDisplay, 1, 1, 'L', true);
    }

    /* =========================================================
       PERFORMANCE TREND (TERM COMPARISON)
    ==========================================================*/
    function PerformanceTrend() {
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'PERFORMANCE TREND', 0, 1, 'L');
        $this->Ln(2);

        $previousGrade = $this->report_data['previous']['grade'] ?? 'N/A';
        $previousRank = $this->report_data['previous']['class_rank'] ?? 'N/A';
        $currentRank = $this->report_data['class_position'] ?? 'N/A';
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(40, 6, 'Previous Term Grade:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 6, $previousGrade, 0, 0);

        $this->SetFont('Arial', '', 9);
        $this->Cell(40, 6, 'Previous Class Rank:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 6, $previousRank, 0, 1);

        // Calculate improvement
        if (is_numeric($previousRank) && is_numeric($currentRank)) {
            $change = $previousRank - $currentRank;
            $trend = ($change > 0) ? "↑ Improved by $change positions" : (($change < 0) ? "↓ Dropped by " . abs($change) . " positions" : "→ Maintained position");
            
            $this->Ln(3);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 5, "Trend: $trend", 0, 1);
        }

        $this->Ln(5);
    }

    /* =========================================================
       REMARKS AND SIGNATURES
    ==========================================================*/
    function RemarksAndSignatures() {
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'REMARKS AND SIGNATURES', 0, 1, 'L');
        $this->Ln(3);

        /* ===============================
           TABLE 1 – REMARKS (2 ROWS)
        ===============================*/
        $this->SetFont('Arial', 'B', 9);

        // Row 1 - Class Teacher Remarks
        $this->Cell(180, 10, "CLASS TEACHER'S REMARKS:", 1, 1, 'L');
        $this->Cell(180, 15, "", 1, 1); // Empty space for remarks

        // Row 2 - Principal Remarks
        $this->Cell(180, 10, "PRINCIPAL'S REMARKS:", 1, 1, 'L');
        $this->Cell(180, 15, "", 1, 1);

        $this->Ln(5);

        /* ===============================
           TABLE 2 – SIGNATURE & FINANCE
        ===============================*/
        $col = 60; // 180 / 3

        $this->SetFont('Arial', '', 9);

        /* Row 1 */
        $this->Cell($col, 12, "Teacher:\nClosing:", 1, 0, 'L');
        $this->Cell($col, 12, "Signature:\nRe-opens:", 1, 0, 'L');
        $this->Cell($col, 12, "Date:", 1, 1, 'L');

        /* Row 2 */
        $this->Cell($col, 8, "Balance:", 1, 0, 'L');
        $this->Cell($col, 8, "Next Fees:", 1, 0, 'L');
        $this->Cell($col, 8, "Total:", 1, 1, 'L');

        /* Row 3 - Parent Signature */
        $this->Cell($col * 2, 10, "Parent Signature:", 1, 0, 'L');
        $this->Cell($col, 10, "Date:", 1, 1, 'L');

        $this->Ln(5);
    }

    /* =========================================================
       SAFE ROW FUNCTION (PREVENTS TEXT OVERFLOW)
    ==========================================================*/
    function Row($data, $widths) {
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
        }

        $h = 5 * $nb;
        $this->CheckPageBreak($h);

        for ($i = 0; $i < count($data); $i++) {
            $w = $widths[$i];
            $x = $this->GetX();
            $y = $this->GetY();

            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 5, $data[$i], 0, 'L');
            $this->SetXY($x + $w, $y);
        }

        $this->Ln($h);
    }

    function CheckPageBreak($h) {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage();
        }
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ')
                $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j)
                        $i++;
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else
                $i++;
        }
        return $nl;
    }

    /* =========================================================
       CIRCLE AND ELLIPSE FUNCTIONS
    ==========================================================*/
    function Circle($x, $y, $r, $style = 'D') {
        $this->Ellipse($x - $r, $y - $r, $r * 2, $r * 2, $style);
    }

    function Ellipse($x, $y, $rx, $ry, $style = 'D') {
        $k = $this->k;
        $hp = $this->h;
        
        // Simplified ellipse using bezier curves
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $rx) * $k,
            ($hp - $y) * $k,
            ($x + $rx) * $k,
            ($hp - ($y - $ry * 0.5523)) * $k,
            ($x + $rx * 0.5523) * $k,
            ($hp - ($y - $ry)) * $k
        ));
    }

    /* =========================================================
       FOOTER
    ==========================================================*/
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);

        $motto = $this->school_info['school_motto'] ?? 'Your CBC Partner';
        $this->Cell(0, 10, 'Motto: ' . $motto, 0, 0, 'C');

        $this->SetY(-15);
        $this->SetX(-30);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

/* =========================================================
   USAGE EXAMPLE WITH NEW DATA STRUCTURE
=========================================================*/

/*
// How to use the updated renderer:

$report_data = [
    'student_adm' => '12345',
    'student_name' => 'John Doe',
    'class_name' => 'Grade 7',
    'stream_name' => 'A',
    'term_name' => 'Term 1',
    'academic_year' => '2024',
    'exam_name' => 'End of Term',
    'upi_no' => 'UPI123456',
    'profile_pic' => 'uploads/students/john.jpg',
    'grading_scale' => $grading_scale,
    'class_total' => 50,
    'stream_total' => 25,
    'total_marks' => 540,
    'avg_rubric' => 5.8,
    'last_term_avg_rubric' => 5.2,
    'class_position' => 3,
    'stream_position' => 1,
    'previous' => [
        'grade' => 'ME',
        'class_rank' => 5
    ],
    'subjects' => [
        [
            'name' => 'Mathematics',
            'mid_term' => 65,
            'end_term' => 72,
            'average' => 68.5,
            'grade' => 'ME1',
            'points' => 6,
            'remarks' => 'Good performance',
            'teacher' => 'Mr. John'
        ],
        [
            'name' => 'English',
            'mid_term' => 70,
            'end_term' => 75,
            'average' => 72.5,
            'grade' => 'ME',
            'points' => 7,
            'remarks' => 'Excellent',
            'teacher' => 'Mrs. Jane'
        ],
        // ... more subjects
    ]
];

$pdf = new PersonalReportCardPDF($school_info, $report_data);
$pdf->AddPage();
$pdf->StudentDetailsBox();
$pdf->SubjectsTable();
$pdf->PerformanceTrend();
$pdf->RemarksAndSignatures();
$pdf->Output('I', 'report_card.pdf');
*/
?>