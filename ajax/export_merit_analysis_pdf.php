<?php
require_once 'AnalyticsPDF.php';

class MeritAnalysisPDF extends AnalyticsPDF
{
    private $data;
    private $subject;
    private $colWidths;
    
    function __construct($schoolInfo, $selections, $data, $subject, $is_print_mode = false)
    {
        parent::__construct($schoolInfo, $selections, $is_print_mode);
        $this->data = $data;
        $this->subject = $subject;
        $this->calculateColumnWidths();
    }
    
    protected function getReportTitle() {
        return 'LEARNING AREA MERIT ANALYSIS - ' . strtoupper($this->subject);
    }
    
    private function calculateColumnWidths()
    {
        $margins = 20;
        $availableWidth = $this->pageWidth - $margins;
        
        $this->colWidths = [
            'no' => 15,
            'admn' => 25,
            'name' => 100,
            'score' => 50,
            'rubric' => 25
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
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'Subject: ' . $this->subject, 0, 1, 'L', false);
        $this->Ln(2);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($this->colWidths['no'], 8, 'NO', 1, 0, 'C', false);
        $this->Cell($this->colWidths['admn'], 8, 'ADMN', 1, 0, 'C', false);
        $this->Cell($this->colWidths['name'], 8, 'NAME', 1, 0, 'C', false);
        $this->Cell($this->colWidths['score'], 8, 'SCORE/GRADE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['rubric'], 8, 'RUBRIC', 1, 1, 'C', false);
        
        $this->SetFont('Arial', '', 9);
        $rowHeight = 7;
        
        foreach ($this->data as $index => $item) {
            $scoreDisplay = $item['score'] . ' ' . $item['grade'];
            
            $this->Cell($this->colWidths['no'], $rowHeight, $index + 1, 1, 0, 'C', false);
            $this->Cell($this->colWidths['admn'], $rowHeight, $this->truncateText($item['admission_no'] ?? '', $this->colWidths['admn'] - 2), 1, 0, 'C', false);
            $this->Cell($this->colWidths['name'], $rowHeight, $this->truncateText($item['student_name'] ?? '', $this->colWidths['name'] - 2), 1, 0, 'L', false);
            $this->Cell($this->colWidths['score'], $rowHeight, $scoreDisplay, 1, 0, 'C', false);
            $this->Cell($this->colWidths['rubric'], $rowHeight, $item['rubric'] ?? '0', 1, 1, 'C', false);
            
            if ($this->GetY() + $rowHeight > ($this->GetPageHeight() - 20)) {
                $this->AddPage('L');
                $this->SetFont('Arial', 'B', 9);
                $this->Cell($this->colWidths['no'], 8, 'NO', 1, 0, 'C', false);
                $this->Cell($this->colWidths['admn'], 8, 'ADMN', 1, 0, 'C', false);
                $this->Cell($this->colWidths['name'], 8, 'NAME', 1, 0, 'C', false);
                $this->Cell($this->colWidths['score'], 8, 'SCORE/GRADE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['rubric'], 8, 'RUBRIC', 1, 1, 'C', false);
                $this->SetFont('Arial', '', 9);
            }
        }
        
        // Summary
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 6, 'Total Students: ' . count($this->data), 0, 1, 'L', false);
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
    
    if (!isset($data['school_id']) || !isset($data['selections']) || !isset($data['data']) || !isset($data['subject'])) {
        throw new Exception('Missing required data');
    }
    
    $school_id = $data['school_id'];
    $selections = $data['selections'];
    $analytics_data = $data['data'];
    $subject = $data['subject'];
    $is_print_mode = isset($data['print_mode']) && $data['print_mode'] === true;
    
    // Get school info
    $school_info = getSchoolInfoForPDF($school_id);
    
    // Create PDF
    $pdf = new MeritAnalysisPDF($school_info, $selections, $analytics_data, $subject, $is_print_mode);
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->GenerateTable();
    
    // Generate filename
    $className = isset($selections['class_name']) ? preg_replace('/[^A-Za-z0-9]/', '', $selections['class_name']) : 'Class';
    $subjectClean = preg_replace('/[^A-Za-z0-9]/', '', $subject);
    $filename = strtolower($className) . '_' . strtolower($subjectClean) . '_merit_analysis.pdf';
    
    ob_end_clean();
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($is_print_mode ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $pdf->Output('I', $filename);
    exit();
    
} catch (Exception $e) {
    error_log("Merit Analysis PDF Error: " . $e->getMessage());
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>