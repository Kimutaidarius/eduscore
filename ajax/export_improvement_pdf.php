<?php
require_once 'AnalyticsPDF.php';

class ImprovementPDF extends AnalyticsPDF
{
    private $data;
    private $colWidths;
    
    function __construct($schoolInfo, $selections, $data, $is_print_mode = false)
    {
        parent::__construct($schoolInfo, $selections, $is_print_mode);
        $this->data = $data;
        $this->calculateColumnWidths();
    }
    
    protected function getReportTitle() {
        return 'IMPROVEMENT ANALYSIS';
    }
    
    private function calculateColumnWidths()
    {
        $margins = 20;
        $availableWidth = $this->pageWidth - $margins;
        
        $this->colWidths = [
            'student' => 50,
            'adm' => 25,
            'class' => 25,
            'first' => 25,
            'current' => 25,
            'improve' => 25,
            'percent' => 25
        ];
        
        $totalWidth = array_sum($this->colWidths);
        if ($totalWidth > $availableWidth) {
            $scale = $availableWidth / $totalWidth;
            foreach ($this->colWidths as $key => $value) {
                $this->colWidths[$key] = round($value * $scale, 1);
            }
        }
    }
    
    function GenerateTable()
    {
        $this->SetFont('Arial', 'B', 9);
        
        // Get first and current exam names
        $firstExam = $this->selections['first_exam_name'] ?? 'First Exam';
        $currentExam = $this->selections['current_exam_name'] ?? 'Current Exam';
        
        $this->Cell(0, 6, 'Comparing: ' . $firstExam . ' vs ' . $currentExam, 0, 1, 'L', false);
        $this->Ln(2);
        
        $this->Cell($this->colWidths['student'], 8, 'STUDENT', 1, 0, 'C', false);
        $this->Cell($this->colWidths['adm'], 8, 'ADMISSION', 1, 0, 'C', false);
        $this->Cell($this->colWidths['class'], 8, 'CLASS/STREAM', 1, 0, 'C', false);
        $this->Cell($this->colWidths['first'], 8, 'FIRST SCORE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['current'], 8, 'CURRENT SCORE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['improve'], 8, 'IMPROVEMENT', 1, 0, 'C', false);
        $this->Cell($this->colWidths['percent'], 8, '% CHANGE', 1, 1, 'C', false);
        
        $this->SetFont('Arial', '', 9);
        $rowHeight = 7;
        
        foreach ($this->data as $item) {
            $changeClass = ($item['improvement'] ?? 0) > 0 ? '' : ''; // Styling handled later
            
            $this->Cell($this->colWidths['student'], $rowHeight, $this->truncateText($item['student_name'] ?? '', $this->colWidths['student'] - 2), 1, 0, 'L', false);
            $this->Cell($this->colWidths['adm'], $rowHeight, $this->truncateText($item['admission_no'] ?? '', $this->colWidths['adm'] - 2), 1, 0, 'C', false);
            $this->Cell($this->colWidths['class'], $rowHeight, $this->truncateText(($item['class_name'] ?? '') . ' ' . ($item['stream_name'] ?? ''), $this->colWidths['class'] - 2), 1, 0, 'C', false);
            $this->Cell($this->colWidths['first'], $rowHeight, number_format($item['first_score'] ?? 0, 1), 1, 0, 'C', false);
            $this->Cell($this->colWidths['current'], $rowHeight, number_format($item['current_score'] ?? 0, 1), 1, 0, 'C', false);
            
            $improvement = $item['improvement'] ?? 0;
            $improveDisplay = ($improvement > 0 ? '+' : '') . number_format($improvement, 1);
            $this->Cell($this->colWidths['improve'], $rowHeight, $improveDisplay, 1, 0, 'C', false);
            
            $percent = $item['percentage_change'] ?? 0;
            $percentDisplay = ($percent > 0 ? '+' : '') . number_format($percent, 1) . '%';
            $this->Cell($this->colWidths['percent'], $rowHeight, $percentDisplay, 1, 1, 'C', false);
            
            if ($this->GetY() + $rowHeight > ($this->GetPageHeight() - 20)) {
                $this->AddPage('L');
                $this->SetFont('Arial', 'B', 9);
                $this->Cell($this->colWidths['student'], 8, 'STUDENT', 1, 0, 'C', false);
                $this->Cell($this->colWidths['adm'], 8, 'ADMISSION', 1, 0, 'C', false);
                $this->Cell($this->colWidths['class'], 8, 'CLASS/STREAM', 1, 0, 'C', false);
                $this->Cell($this->colWidths['first'], 8, 'FIRST SCORE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['current'], 8, 'CURRENT SCORE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['improve'], 8, 'IMPROVEMENT', 1, 0, 'C', false);
                $this->Cell($this->colWidths['percent'], 8, '% CHANGE', 1, 1, 'C', false);
                $this->SetFont('Arial', '', 9);
            }
        }
        
        // Summary statistics
        if (count($this->data) > 0) {
            $totalImprovement = 0;
            $improved = 0;
            $declined = 0;
            
            foreach ($this->data as $item) {
                $imp = $item['improvement'] ?? 0;
                $totalImprovement += $imp;
                if ($imp > 0) $improved++;
                else if ($imp < 0) $declined++;
            }
            
            $avgImprovement = $totalImprovement / count($this->data);
            
            $this->Ln(5);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(0, 6, 'Summary:', 0, 1, 'L', false);
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 6, 'Total Students Compared: ' . count($this->data), 0, 1, 'L', false);
            $this->Cell(0, 6, 'Students Improved: ' . $improved, 0, 1, 'L', false);
            $this->Cell(0, 6, 'Students Declined: ' . $declined, 0, 1, 'L', false);
            $this->Cell(0, 6, 'Average Improvement: ' . ($avgImprovement > 0 ? '+' : '') . number_format($avgImprovement, 2), 0, 1, 'L', false);
        }
    }
}

// Main execution
try {
    $json_input = file_get_contents('php://input');
    if (empty($json_input)) {
        throw new Exception('No data received');
    }
    
    $data = json_decode($json_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($data['school_id']) || !isset($data['selections']) || !isset($data['data'])) {
        throw new Exception('Missing required data');
    }
    
    $school_id = $data['school_id'];
    $selections = $data['selections'];
    $analytics_data = $data['data'];
    $is_print_mode = isset($data['print_mode']) && $data['print_mode'] === true;
    
    // Get school info
    $school_info = getSchoolInfoForPDF($school_id);
    
    // Create PDF
    $pdf = new ImprovementPDF($school_info, $selections, $analytics_data, $is_print_mode);
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->GenerateTable();
    
    // Generate filename
    $className = isset($selections['class_name']) ? preg_replace('/[^A-Za-z0-9]/', '', $selections['class_name']) : 'Class';
    $filename = strtolower($className) . '_improvement_analysis.pdf';
    
    ob_end_clean();
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($is_print_mode ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $pdf->Output('I', $filename);
    exit();
    
} catch (Exception $e) {
    error_log("Improvement PDF Error: " . $e->getMessage());
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>