<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', '../logs/analytics_pdf_errors.log');

// Include the AnalyticsPDF class
require_once 'AnalyticsPDF.php';

class LearningAreaPDF extends AnalyticsPDF
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
        return 'LEARNING AREA ANALYSIS';
    }
    
    private function calculateColumnWidths()
    {
        $margins = 20;
        $availableWidth = $this->pageWidth - $margins;
        
        $this->colWidths = [
            'subject' => 50,
            'class' => 20,
            'ee' => 12,
            'me' => 12,
            'ae' => 12,
            'ap' => 12,
            'be' => 12,
            'x' => 12,
            'mean' => 15,
            'rubric' => 15,
            'avg_rubric' => 15,
            'teacher' => 60
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
        $this->SetFont('Arial', 'B', 8);
        
        // Table header
        $this->Cell($this->colWidths['subject'], 8, 'SUBJECT', 1, 0, 'C', false);
        $this->Cell($this->colWidths['class'], 8, 'CLASS', 1, 0, 'C', false);
        $this->Cell($this->colWidths['ee'], 8, 'EE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['me'], 8, 'ME', 1, 0, 'C', false);
        $this->Cell($this->colWidths['ae'], 8, 'AE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['ap'], 8, 'AP', 1, 0, 'C', false);
        $this->Cell($this->colWidths['be'], 8, 'BE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['x'], 8, 'X', 1, 0, 'C', false);
        $this->Cell($this->colWidths['mean'], 8, 'MEAN', 1, 0, 'C', false);
        $this->Cell($this->colWidths['rubric'], 8, 'RUBRIC', 1, 0, 'C', false);
        $this->Cell($this->colWidths['avg_rubric'], 8, 'AVG RB', 1, 0, 'C', false);
        $this->Cell($this->colWidths['teacher'], 8, 'TEACHER', 1, 1, 'C', false);
        
        $this->SetFont('Arial', '', 8);
        $rowHeight = 6;
        
        foreach ($this->data as $item) {
            // Use the field names that match what we're sending from JavaScript
            $subject = $item['subject_name'] ?? 'N/A';
            $classDisplay = $item['class_display'] ?? 'All';
            
            // For count fields, use the _count versions
            $ee = $item['ee_count'] ?? 0;
            $me = $item['me_count'] ?? 0;
            $ae = $item['ae_count'] ?? 0;
            $ap = $item['ap_count'] ?? 0;
            $be = $item['be_count'] ?? 0;
            $x = $item['x_count'] ?? 0;
            
            $mean = floatval($item['mean'] ?? 0);
            $rubric = floatval($item['rubric'] ?? 0);
            $avg_rubric = floatval($item['avg_rubric'] ?? $rubric);
            $teacher = $item['teacher_name'] ?? 'Not Assigned';
            
            $this->Cell($this->colWidths['subject'], $rowHeight, $this->truncateText($subject, $this->colWidths['subject'] - 2), 1, 0, 'L', false);
            $this->Cell($this->colWidths['class'], $rowHeight, $this->truncateText($classDisplay, $this->colWidths['class'] - 2), 1, 0, 'C', false);
            $this->Cell($this->colWidths['ee'], $rowHeight, $ee, 1, 0, 'C', false);
            $this->Cell($this->colWidths['me'], $rowHeight, $me, 1, 0, 'C', false);
            $this->Cell($this->colWidths['ae'], $rowHeight, $ae, 1, 0, 'C', false);
            $this->Cell($this->colWidths['ap'], $rowHeight, $ap, 1, 0, 'C', false);
            $this->Cell($this->colWidths['be'], $rowHeight, $be, 1, 0, 'C', false);
            $this->Cell($this->colWidths['x'], $rowHeight, $x, 1, 0, 'C', false);
            $this->Cell($this->colWidths['mean'], $rowHeight, number_format($mean, 1), 1, 0, 'C', false);
            $this->Cell($this->colWidths['rubric'], $rowHeight, number_format($rubric, 1), 1, 0, 'C', false);
            $this->Cell($this->colWidths['avg_rubric'], $rowHeight, number_format($avg_rubric, 1), 1, 0, 'C', false);
            $this->Cell($this->colWidths['teacher'], $rowHeight, $this->truncateText($teacher, $this->colWidths['teacher'] - 2), 1, 1, 'L', false);
            
            if ($this->GetY() + $rowHeight > ($this->GetPageHeight() - 20)) {
                $this->AddPage('L');
                $this->SetFont('Arial', 'B', 8);
                $this->Cell($this->colWidths['subject'], 8, 'SUBJECT', 1, 0, 'C', false);
                $this->Cell($this->colWidths['class'], 8, 'CLASS', 1, 0, 'C', false);
                $this->Cell($this->colWidths['ee'], 8, 'EE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['me'], 8, 'ME', 1, 0, 'C', false);
                $this->Cell($this->colWidths['ae'], 8, 'AE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['ap'], 8, 'AP', 1, 0, 'C', false);
                $this->Cell($this->colWidths['be'], 8, 'BE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['x'], 8, 'X', 1, 0, 'C', false);
                $this->Cell($this->colWidths['mean'], 8, 'MEAN', 1, 0, 'C', false);
                $this->Cell($this->colWidths['rubric'], 8, 'RUBRIC', 1, 0, 'C', false);
                $this->Cell($this->colWidths['avg_rubric'], 8, 'AVG RB', 1, 0, 'C', false);
                $this->Cell($this->colWidths['teacher'], 8, 'TEACHER', 1, 1, 'C', false);
                $this->SetFont('Arial', '', 8);
            }
        }
    }
}

// Main execution
try {
    // Get POST data from form fields
    $school_id = $_POST['school_id'] ?? 0;
    $selections_json = $_POST['selections'] ?? '{}';
    $data_json = $_POST['data'] ?? '[]';
    $is_print_mode = isset($_POST['print_mode']) && $_POST['print_mode'] === 'true';
    
    if (!$school_id) {
        throw new Exception('School ID missing');
    }
    
    $selections = json_decode($selections_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid selections JSON: ' . json_last_error_msg());
    }
    
    $analytics_data = json_decode($data_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid data JSON: ' . json_last_error_msg());
    }
    
    if (empty($analytics_data)) {
        throw new Exception('No data to export');
    }
    
    // Get school info
    $school_info = getSchoolInfoForPDF($school_id);
    
    // Create PDF
    $pdf = new LearningAreaPDF($school_info, $selections, $analytics_data, $is_print_mode);
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->GenerateTable();
    
    // Generate filename
    $className = isset($selections['class_name']) ? preg_replace('/[^A-Za-z0-9]/', '', $selections['class_name']) : 'Class';
    $examName = isset($selections['exam_name']) ? preg_replace('/[^A-Za-z0-9]/', '', $selections['exam_name']) : '';
    $filename = strtolower($className);
    if (!empty($examName)) {
        $filename .= '_' . strtolower($examName);
    }
    $filename .= '_learning_area_analysis.pdf';
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($is_print_mode ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $pdf->Output('I', $filename);
    exit();
    
} catch (Exception $e) {
    error_log("Learning Area PDF Error: " . $e->getMessage());
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>