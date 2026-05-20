<?php
// Set appropriate headers based on request type
if (isset($input['print_mode']) && $input['print_mode'] === true) {
    // For print mode, we'll output PDF directly
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="merit_list_print.pdf"');
} else {
    // For download mode
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
    if (isset($input['print_mode']) && $input['print_mode'] === true) {
        // For print mode, we can't return JSON
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug log
error_log("Received in generate_meritlist_pdf.php: " . print_r($input, true));

// Extract parameters
$school_id = $_SESSION['school_id'];
$teacher_id = $_SESSION['teacher_id'];
$merit_list = isset($input['merit_list']) ? $input['merit_list'] : [];
$subjects = isset($input['subjects']) ? $input['subjects'] : [];
$selections = isset($input['selections']) ? $input['selections'] : [];
$school_info = isset($input['school_info']) ? $input['school_info'] : [];
$print_mode = isset($input['print_mode']) && $input['print_mode'] === true;
$summary = isset($input['summary']) ? $input['summary'] : null;

// Validate that we have data
if (empty($merit_list)) {
    if ($print_mode) {
        // For print mode, we need to output PDF error
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

try {
    // If we don't have summary data, calculate it
    if (!$summary && !empty($merit_list)) {
        $summary = calculateSummaryStatistics($merit_list);
    }
    
    // Generate PDF using FPDF
    require_once('../assets/fpdf/fpdf.php');
    
    class MeritListPDF extends FPDF {
        // Page header
        function Header() {
            global $school_info, $selections, $print_mode;
            
            // School Logo (if exists)
            if (!empty($school_info['school_logo']) && file_exists('../' . $school_info['school_logo'])) {
                $this->Image('../' . $school_info['school_logo'], 10, 8, 30);
            }
            
            // School Name
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(30, 58, 138); // Primary blue
            $this->Cell(0, 8, $school_info['school_name'] ?? 'School Name', 0, 1, 'C');
            
            // School Motto
            $this->SetFont('Arial', 'I', 10);
            $this->SetTextColor(107, 114, 128); // Text light
            $this->Cell(0, 5, $school_info['school_motto'] ?? 'Excellence in Education', 0, 1, 'C');
            
            // Merit List Title
            $this->Ln(5);
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(30, 58, 138);
            
            $title = 'MERIT LIST';
            if (!empty($selections['class_name'])) {
                $title .= ' - ' . $selections['class_name'];
                if (!empty($selections['stream_name'])) {
                    $title .= ' (' . $selections['stream_name'] . ')';
                }
            }
            $this->Cell(0, 8, $title, 0, 1, 'C');
            
            // Exam Details
            $this->SetFont('Arial', '', 11);
            $this->SetTextColor(0, 0, 0);
            $details = '';
            if (!empty($selections['exam_name'])) $details .= $selections['exam_name'] . ' - ';
            if (!empty($selections['term_name'])) $details .= $selections['term_name'] . ' - ';
            if (!empty($selections['year'])) $details .= $selections['year'];
            $this->Cell(0, 6, $details, 0, 1, 'C');
            
            // Academic Level
            if (!empty($selections['academic_level'])) {
                $this->SetFont('Arial', 'I', 9);
                $this->SetTextColor(107, 114, 128);
                $this->Cell(0, 5, 'Academic Level: ' . ucwords(str_replace('_', ' ', $selections['academic_level'])), 0, 1, 'C');
            }
            
            // Generated Date
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(107, 114, 128);
            $this->Cell(0, 4, 'Generated on: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
            
            // Line break
            $this->Ln(8);
            
            // Print mode indicator (for print version)
            if ($print_mode) {
                $this->SetFont('Arial', 'I', 8);
                $this->SetTextColor(150, 150, 150);
                $this->Cell(0, 4, 'Print Version - Ready for printing', 0, 1, 'R');
                $this->Ln(2);
            }
        }

        // Page footer
        function Footer() {
            global $print_mode;
            
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            
            // Set font
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128, 128, 128);
            
            // Page number
            $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            
            // Generated by text
            $this->SetY(-10);
            $this->SetFont('Arial', 'I', 6);
            $this->Cell(0, 5, 'Generated by EduScore Merit List System', 0, 0, 'C');
            
            // Add print instructions for print mode
            if ($print_mode && $this->PageNo() == 1) {
                $this->SetY(-20);
                $this->SetFont('Arial', 'I', 7);
                $this->SetTextColor(100, 100, 100);
                $this->Cell(0, 5, 'Use browser print function (Ctrl+P) to print this document', 0, 0, 'C');
            }
        }

        // Grade Count Analysis Table - NEW TABLE
        function GradeCountAnalysisTable($summary, $selections, $teacher_name = 'Class Teacher') {
            if (!$summary) return;
            
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(30, 58, 138);
            $this->Cell(0, 8, 'OVERALL GRADE COUNT ANALYSIS', 0, 1, 'L');
            $this->Ln(2);
            
            // Set colors for header
            $this->SetFillColor(30, 58, 138); // Primary blue
            $this->SetTextColor(255, 255, 255); // White
            $this->SetFont('Arial', 'B', 9);
            
            // Table headers - CLS ENT EE ME AE AP BE X MEAN RUBRIC DEV TEACHER
            $headers = ['CLS', 'ENT', 'EE', 'ME', 'AE', 'AP', 'BE', 'X', 'MEAN', 'RUBRIC', 'DEV', 'TEACHER'];
            $widths = [30, 15, 12, 12, 12, 12, 12, 12, 25, 20, 15, 40];
            
            for ($i = 0; $i < count($headers); $i++) {
                $this->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            // Data row
            $this->SetFillColor(240, 248, 255); // Light blue background
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', '', 9);
            
            $className = $selections['class_name'] ?? 'Current Class';
            if (!empty($selections['stream_name'])) {
                $className .= ' - ' . $selections['stream_name'];
            }
            
            $data = [
                $className,
                $summary['studentCount'],
                $summary['meanGradeCounts']['EE'],
                $summary['meanGradeCounts']['ME'],
                $summary['meanGradeCounts']['AE'],
                $summary['meanGradeCounts']['AP'],
                $summary['meanGradeCounts']['BE'],
                $summary['subjectCount'],
                $summary['meanAbbreviation'],
                $summary['avgRubric'],
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

        // Summary Table (keeping for backward compatibility)
        function SummaryTable($summary, $selections, $teacher_name = 'Class Teacher') {
            if (!$summary) return;
            
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(30, 58, 138);
            $this->Cell(0, 8, 'CLASS PERFORMANCE SUMMARY', 0, 1, 'L');
            $this->Ln(2);
            
            // Set colors for header
            $this->SetFillColor(30, 58, 138); // Primary blue
            $this->SetTextColor(255, 255, 255); // White
            $this->SetFont('Arial', 'B', 9);
            
            // Table headers
            $headers = ['Class', 'Entry', 'EE', 'ME', 'AE', 'AP', 'BE', 'X', 'MEAN', 'Rubric', 'DEV', 'Teacher'];
            $widths = [30, 15, 12, 12, 12, 12, 12, 12, 25, 20, 15, 40];
            
            for ($i = 0; $i < count($headers); $i++) {
                $this->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            // Data row
            $this->SetFillColor(240, 248, 255); // Light blue background
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', '', 9);
            
            $className = $selections['class_name'] ?? 'Current Class';
            if (!empty($selections['stream_name'])) {
                $className .= ' - ' . $selections['stream_name'];
            }
            
            $data = [
                $className,
                $summary['studentCount'],
                $summary['meanGradeCounts']['EE'],
                $summary['meanGradeCounts']['ME'],
                $summary['meanGradeCounts']['AE'],
                $summary['meanGradeCounts']['AP'],
                $summary['meanGradeCounts']['BE'],
                $summary['subjectCount'],
                $summary['meanAbbreviation'] . "\n(" . $summary['avgRubric'] . ")",
                $summary['avgRubric'],
                $summary['stdDev'],
                $teacher_name
            ];
            
            $this->SetFont('Arial', '', 8);
            $y = $this->GetY();
            $x = $this->GetX();
            
            for ($i = 0; $i < count($data); $i++) {
                $this->MultiCell($widths[$i], 4, $data[$i], 1, 'C');
                $x += $widths[$i];
                $this->SetXY($x, $y);
            }
            $this->Ln();
        }
    }

    // Create PDF instance
    $pdf = new MeritListPDF('L', 'mm', 'A4'); // Landscape orientation for better table display
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 25);

    // Add the new Grade Count Analysis Table first
    if ($summary) {
        $pdf->GradeCountAnalysisTable($summary, $selections, $teacher_name ?? 'Class Teacher');
        $pdf->Ln(5);
    }

    // Keep the original summary table as well (optional - you can remove this if you only want the new one)
    if ($summary) {
        $pdf->SummaryTable($summary, $selections, $teacher_name ?? 'Class Teacher');
        $pdf->Ln(5);
    }

    // Merit List Table
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(30, 58, 138);
    $pdf->Cell(0, 8, 'STUDENT PERFORMANCE DETAILS', 0, 1, 'L');
    $pdf->Ln(2);

    // Calculate column widths
    $pageWidth = 277; // A4 landscape width in mm (297 - margins)
    $colWidths = [
        'sn' => 8,       // S/N
        'adm' => 18,     // Admission No
        'name' => 35,    // Student Name
        'tm' => 12,      // Total Marks
        'tr' => 12,      // Total Rubric
        'mean' => 18,    // Mean Grade
        'crank' => 12,   // Class Rank (C.P)
        'rank' => 12     // Overall Rank
    ];
    
    // Calculate remaining width for subjects
    $subjectColWidth = 14; // Width per subject column
    $totalSubjectWidth = count($subjects) * $subjectColWidth;
    
    // Adjust if too wide
    if ($totalSubjectWidth + array_sum($colWidths) > $pageWidth) {
        $subjectColWidth = 12;
        $totalSubjectWidth = count($subjects) * $subjectColWidth;
    }

    // Table Header
    $pdf->SetFillColor(30, 58, 138);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8);

    // S/N
    $pdf->Cell($colWidths['sn'], 12, '#', 1, 0, 'C', true);
    // Admission No
    $pdf->Cell($colWidths['adm'], 12, 'Adm No', 1, 0, 'C', true);
    // Student Name
    $pdf->Cell($colWidths['name'], 12, 'Student Name', 1, 0, 'C', true);
    
    // Subject columns
    foreach ($subjects as $subject) {
        $subjectName = isset($subject['subject_name']) ? 
            (strlen($subject['subject_name']) > 8 ? substr($subject['subject_name'], 0, 6) . '..' : $subject['subject_name']) : 
            'Subj';
        $pdf->Cell($subjectColWidth, 12, $subjectName, 1, 0, 'C', true);
    }
    
    // Total Marks
    $pdf->Cell($colWidths['tm'], 12, 'TM', 1, 0, 'C', true);
    // Total Rubric
    $pdf->Cell($colWidths['tr'], 12, 'TR', 1, 0, 'C', true);
    // Mean Grade
    $pdf->Cell($colWidths['mean'], 12, 'Mean Grade', 1, 0, 'C', true);
    // Class Position (C.P)
    $pdf->Cell($colWidths['crank'], 12, 'C.P', 1, 0, 'C', true);
    // Overall Rank
    $pdf->Cell($colWidths['rank'], 12, 'Rank', 1, 1, 'C', true);

    // Student data rows
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 8);
    
    $fill = false;
    $totalTM = 0;
    $totalTR = 0;
    $subjectTotals = array_fill(0, count($subjects), 0);
    
    // Calculate class positions (within the class/stream)
    $classRanks = [];
    foreach ($merit_list as $index => $student) {
        $classRanks[] = [
            'index' => $index,
            'total_marks' => floatval($student['total_marks'] ?? 0)
        ];
    }
    
    // Sort by total marks descending for class rank
    usort($classRanks, function($a, $b) {
        return $b['total_marks'] <=> $a['total_marks'];
    });
    
    // Assign class ranks
    $classRankMap = [];
    foreach ($classRanks as $pos => $item) {
        $classRankMap[$item['index']] = $pos + 1;
    }
    
    foreach ($merit_list as $index => $student) {
        $fill = !$fill;
        
        // S/N
        $pdf->Cell($colWidths['sn'], 8, $index + 1, 1, 0, 'C', $fill);
        // Admission No
        $pdf->Cell($colWidths['adm'], 8, $student['admission_no'] ?? 'N/A', 1, 0, 'C', $fill);
        // Student Name
        $pdf->Cell($colWidths['name'], 8, $student['full_name'] ?? 'Unknown', 1, 0, 'L', $fill);
        
        // Subject scores
        if (!empty($subjects)) {
            foreach ($subjects as $subIdx => $subject) {
                $subjectScore = isset($student['subject_scores'][$subject['id']]) ? $student['subject_scores'][$subject['id']] : [];
                $score = isset($subjectScore['score']) ? $subjectScore['score'] : 0;
                $achievement = isset($subjectScore['achievement_abbreviation']) ? $subjectScore['achievement_abbreviation'] : 'BE';
                
                // Set color based on achievement
                $pdf->SetTextColor(
                    $achievement == 'EE' ? 22 : ($achievement == 'ME' ? 37 : ($achievement == 'AE' ? 217 : 220)),
                    $achievement == 'EE' ? 163 : ($achievement == 'ME' ? 99 : ($achievement == 'AE' ? 119 : 38)),
                    $achievement == 'EE' ? 74 : ($achievement == 'ME' ? 235 : ($achievement == 'AE' ? 6 : 38))
                );
                
                $pdf->Cell($subjectColWidth, 8, $score . "\n(" . $achievement . ")", 1, 0, 'C', $fill);
                
                // Reset text color
                $pdf->SetTextColor(0, 0, 0);
                
                // Add to subject total
                $subjectTotals[$subIdx] += floatval($score);
            }
        }
        
        // Total Marks
        $tm = floatval($student['total_marks'] ?? 0);
        $pdf->Cell($colWidths['tm'], 8, number_format($tm, 1), 1, 0, 'C', $fill);
        $totalTM += $tm;
        
        // Total Rubric
        $tr = floatval($student['total_rubric_points'] ?? $student['total_points'] ?? 0);
        $pdf->Cell($colWidths['tr'], 8, number_format($tr, 1), 1, 0, 'C', $fill);
        $totalTR += $tr;
        
        // Mean Grade
        $meanScore = floatval($student['mean_score'] ?? 0);
        $meanGrade = $student['most_common_grade'] ?? $student['mean_grade'] ?? '';
        
        if (empty($meanGrade)) {
            if ($meanScore >= 3.5) $meanGrade = 'EE';
            else if ($meanScore >= 2.5) $meanGrade = 'ME';
            else if ($meanScore >= 1.5) $meanGrade = 'AE';
            else if ($meanScore >= 1.0) $meanGrade = 'AP';
            else $meanGrade = 'BE';
        }
        
        // Set color based on mean grade
        $pdf->SetTextColor(
            $meanGrade == 'EE' ? 22 : ($meanGrade == 'ME' ? 37 : ($meanGrade == 'AE' ? 217 : 220)),
            $meanGrade == 'EE' ? 163 : ($meanGrade == 'ME' ? 99 : ($meanGrade == 'AE' ? 119 : 38)),
            $meanGrade == 'EE' ? 74 : ($meanGrade == 'ME' ? 235 : ($meanGrade == 'AE' ? 6 : 38))
        );
        
        $pdf->Cell($colWidths['mean'], 8, $meanGrade . "\n(" . number_format($meanScore, 2) . ")", 1, 0, 'C', $fill);
        
        // Reset text color
        $pdf->SetTextColor(0, 0, 0);
        
        // Class Position (C.P) - just the number without suffix
        $classPos = $classRankMap[$index] ?? ($index + 1);
        $pdf->Cell($colWidths['crank'], 8, $classPos, 1, 0, 'C', $fill);
        
        // Overall Rank - just the number without suffix
        $rank = intval($student['rank'] ?? ($index + 1));
        $pdf->Cell($colWidths['rank'], 8, $rank, 1, 1, 'C', $fill);
    }

    // Totals row
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Cell($colWidths['sn'] + $colWidths['adm'] + $colWidths['name'], 8, 'TOTALS', 1, 0, 'L', true);
    
    // Subject totals
    foreach ($subjectTotals as $total) {
        $pdf->Cell($subjectColWidth, 8, number_format($total, 1), 1, 0, 'C', true);
    }
    
    // TM Total
    $pdf->Cell($colWidths['tm'], 8, number_format($totalTM, 1), 1, 0, 'C', true);
    // TR Total
    $pdf->Cell($colWidths['tr'], 8, number_format($totalTR, 1), 1, 0, 'C', true);
    // Empty cells for mean, C.P, and rank
    $pdf->Cell($colWidths['mean'], 8, '', 1, 0, 'C', true);
    $pdf->Cell($colWidths['crank'], 8, '', 1, 0, 'C', true);
    $pdf->Cell($colWidths['rank'], 8, '', 1, 1, 'C', true);

    // Averages row
    $studentCount = count($merit_list);
    $pdf->Cell($colWidths['sn'] + $colWidths['adm'] + $colWidths['name'], 8, 'AVERAGES', 1, 0, 'L', true);
    
    // Subject averages
    foreach ($subjectTotals as $total) {
        $avg = $studentCount > 0 ? $total / $studentCount : 0;
        $pdf->Cell($subjectColWidth, 8, number_format($avg, 1), 1, 0, 'C', true);
    }
    
    // TM Average
    $pdf->Cell($colWidths['tm'], 8, number_format($totalTM / $studentCount, 1), 1, 0, 'C', true);
    // TR Average
    $pdf->Cell($colWidths['tr'], 8, number_format($totalTR / $studentCount, 2), 1, 0, 'C', true);
    // Empty cells for mean, C.P, and rank
    $pdf->Cell($colWidths['mean'], 8, '', 1, 0, 'C', true);
    $pdf->Cell($colWidths['crank'], 8, '', 1, 0, 'C', true);
    $pdf->Cell($colWidths['rank'], 8, '', 1, 1, 'C', true);

    // Legend
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Cell(0, 4, 'TM = Total Marks | TR = Total Rubric | C.P = Class Position (within class/stream) | EE = Exceeding Expectations | ME = Meeting Expectations', 0, 1, 'L');
    $pdf->Cell(0, 4, 'AE = Approaching Expectations | BE = Below Expectations | AP = Approaching (Alternative) | Numbers in brackets show rubric points / mean score', 0, 1, 'L');

    // Add print instructions for print mode
    if ($print_mode) {
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, 'This document is optimized for printing. Use Ctrl+P to print.', 0, 1, 'C');
        
        // Add JavaScript to auto-print in browser (only works in some PDF viewers)
        $pdf->IncludeJS('print(true);');
    }

    // Output PDF
    if ($print_mode) {
        // For print mode: output to browser for viewing/printing
        $pdf->Output('I', 'merit_list_print.pdf');
    } else {
        // For download mode: output as downloadable file
        $pdf->Output('D', 'merit_list_' . date('Ymd_His') . '.pdf');
    }

} catch (Exception $e) {
    error_log('Error in generate_meritlist_pdf.php: ' . $e->getMessage());
    
    if ($print_mode) {
        header('Content-Type: text/html');
        echo '<html><body>';
        echo '<h3>Error Generating PDF</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>Please try again or contact support.</p>';
        echo '</body></html>';
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error generating PDF: ' . $e->getMessage()
        ]);
    }
}

$conn->close();

// Helper function to calculate summary statistics
function calculateSummaryStatistics($meritList) {
    if (empty($meritList)) return null;
    
    $studentCount = count($meritList);
    
    $meanGradeCounts = [
        'EE' => 0, 'ME' => 0, 'AE' => 0, 'AP' => 0, 'BE' => 0
    ];
    
    $subjectSet = [];
    $totalScoreSum = 0;
    $totalRubricSum = 0;
    
    foreach ($meritList as $student) {
        $totalScoreSum += floatval($student['total_marks'] ?? 0);
        $totalRubricSum += floatval($student['total_rubric_points'] ?? $student['total_points'] ?? 0);
        
        // Get student's mean grade
        $studentMeanGrade = $student['mean_grade'] ?? $student['most_common_grade'] ?? $student['overall_grade'] ?? '';
        
        if (empty($studentMeanGrade)) {
            $meanScore = floatval($student['mean_score'] ?? 0);
            if ($meanScore >= 3.5) $studentMeanGrade = 'EE';
            else if ($meanScore >= 2.5) $studentMeanGrade = 'ME';
            else if ($meanScore >= 1.5) $studentMeanGrade = 'AE';
            else if ($meanScore >= 1.0) $studentMeanGrade = 'AP';
            else $studentMeanGrade = 'BE';
        }
        
        $grade = strtoupper(trim($studentMeanGrade));
        if (isset($meanGradeCounts[$grade])) {
            $meanGradeCounts[$grade]++;
        } else {
            $meanGradeCounts['BE']++; // Default to BE for unknown grades
        }
        
        // Collect subjects
        if (!empty($student['subject_scores'])) {
            foreach (array_keys($student['subject_scores']) as $subjectId) {
                $subjectSet[$subjectId] = true;
            }
        }
    }
    
    $avgScore = $studentCount > 0 ? round($totalScoreSum / $studentCount, 1) : 0;
    $avgRubric = $studentCount > 0 ? round($totalRubricSum / $studentCount, 2) : 0;
    
    // Determine overall mean grade
    if ($avgRubric >= 3.5) $meanAbbreviation = 'EE';
    else if ($avgRubric >= 2.5) $meanAbbreviation = 'ME';
    else if ($avgRubric >= 1.5) $meanAbbreviation = 'AE';
    else $meanAbbreviation = 'BE';
    
    // Calculate standard deviation
    $scores = array_map(function($s) { return floatval($s['total_marks'] ?? 0); }, $meritList);
    $mean = $totalScoreSum / $studentCount;
    $variance = array_reduce($scores, function($carry, $score) use ($mean) {
        return $carry + pow($score - $mean, 2);
    }, 0) / $studentCount;
    $stdDev = round(sqrt($variance), 2);
    
    return [
        'studentCount' => $studentCount,
        'meanGradeCounts' => $meanGradeCounts,
        'avgScore' => $avgScore,
        'avgRubric' => $avgRubric,
        'meanAbbreviation' => $meanAbbreviation,
        'stdDev' => $stdDev,
        'subjectCount' => count($subjectSet)
    ];
}
?>