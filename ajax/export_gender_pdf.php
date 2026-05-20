<?php
require_once 'AnalyticsPDF.php';

class GenderPDF extends AnalyticsPDF
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
        return 'GENDER ANALYSIS';
    }
    
    private function calculateColumnWidths()
    {
        $margins = 20;
        $availableWidth = $this->pageWidth - $margins;
        
        $this->colWidths = [
            'class' => 35,
            'entry' => 15,
            'ee' => 12,
            'me' => 12,
            'ae' => 12,
            'ap' => 12,
            'be' => 12,
            'x' => 12,
            'm_rb' => 15,
            'm_mark' => 15,
            'grade' => 15
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
        
        $this->Cell($this->colWidths['class'], 8, 'CLASS', 1, 0, 'C', false);
        $this->Cell($this->colWidths['entry'], 8, 'ENTRY', 1, 0, 'C', false);
        $this->Cell($this->colWidths['ee'], 8, 'EE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['me'], 8, 'ME', 1, 0, 'C', false);
        $this->Cell($this->colWidths['ae'], 8, 'AE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['ap'], 8, 'AP', 1, 0, 'C', false);
        $this->Cell($this->colWidths['be'], 8, 'BE', 1, 0, 'C', false);
        $this->Cell($this->colWidths['x'], 8, 'X', 1, 0, 'C', false);
        $this->Cell($this->colWidths['m_rb'], 8, 'M RB', 1, 0, 'C', false);
        $this->Cell($this->colWidths['m_mark'], 8, 'M.MARK', 1, 0, 'C', false);
        $this->Cell($this->colWidths['grade'], 8, 'GRADE', 1, 1, 'C', false);
        
        $this->SetFont('Arial', '', 8);
        $rowHeight = 7;
        
        foreach ($this->data as $item) {
            $this->Cell($this->colWidths['class'], $rowHeight, $this->truncateText($item['class_display'] ?? '', $this->colWidths['class'] - 2), 1, 0, 'L', false);
            $this->Cell($this->colWidths['entry'], $rowHeight, $item['entry_count'] ?? 0, 1, 0, 'C', false);
            $this->Cell($this->colWidths['ee'], $rowHeight, $item['ee_count'] ?? 0, 1, 0, 'C', false);
            $this->Cell($this->colWidths['me'], $rowHeight, $item['me_count'] ?? 0, 1, 0, 'C', false);
            $this->Cell($this->colWidths['ae'], $rowHeight, $item['ae_count'] ?? 0, 1, 0, 'C', false);
            $this->Cell($this->colWidths['ap'], $rowHeight, $item['ap_count'] ?? 0, 1, 0, 'C', false);
            $this->Cell($this->colWidths['be'], $rowHeight, $item['be_count'] ?? 0, 1, 0, 'C', false);
            $this->Cell($this->colWidths['x'], $rowHeight, $item['x_count'] ?? 0, 1, 0, 'C', false);
            $this->Cell($this->colWidths['m_rb'], $rowHeight, number_format($item['mean_rubric'] ?? 0, 1), 1, 0, 'C', false);
            $this->Cell($this->colWidths['m_mark'], $rowHeight, number_format($item['mean_mark'] ?? 0, 1), 1, 0, 'C', false);
            $this->Cell($this->colWidths['grade'], $rowHeight, $item['grade'] ?? 'N/A', 1, 1, 'C', false);
            
            if ($this->GetY() + $rowHeight > ($this->GetPageHeight() - 20)) {
                $this->AddPage('L');
                $this->SetFont('Arial', 'B', 8);
                $this->Cell($this->colWidths['class'], 8, 'CLASS', 1, 0, 'C', false);
                $this->Cell($this->colWidths['entry'], 8, 'ENTRY', 1, 0, 'C', false);
                $this->Cell($this->colWidths['ee'], 8, 'EE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['me'], 8, 'ME', 1, 0, 'C', false);
                $this->Cell($this->colWidths['ae'], 8, 'AE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['ap'], 8, 'AP', 1, 0, 'C', false);
                $this->Cell($this->colWidths['be'], 8, 'BE', 1, 0, 'C', false);
                $this->Cell($this->colWidths['x'], 8, 'X', 1, 0, 'C', false);
                $this->Cell($this->colWidths['m_rb'], 8, 'M RB', 1, 0, 'C', false);
                $this->Cell($this->colWidths['m_mark'], 8, 'M.MARK', 1, 0, 'C', false);
                $this->Cell($this->colWidths['grade'], 8, 'GRADE', 1, 1, 'C', false);
                $this->SetFont('Arial', '', 8);
            }
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
    $pdf = new GenderPDF($school_info, $selections, $analytics_data, $is_print_mode);
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->GenerateTable();
    
    // Generate filename
    $className = isset($selections['class_name']) ? preg_replace('/[^A-Za-z0-9]/', '', $selections['class_name']) : 'Class';
    $filename = strtolower($className) . '_gender_analysis.pdf';
    
    ob_end_clean();
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($is_print_mode ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $pdf->Output('I', $filename);
    exit();
    
} catch (Exception $e) {
    error_log("Gender PDF Error: " . $e->getMessage());
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>