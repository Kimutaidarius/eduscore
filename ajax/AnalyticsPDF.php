<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', '../logs/analytics_pdf_errors.log');

// ============ LOAD FPDF FIRST ============
$fpdf_loaded = false;
$fpdf_paths = [
    '../assets/fpdf/fpdf.php',
    '../assets/fpdf/fpdf/fpdf.php',
    '../fpdf/fpdf.php',
    '../fpdf181/fpdf.php'
];

foreach ($fpdf_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $fpdf_loaded = true;
        error_log("FPDF loaded from: " . $path);
        break;
    }
}

if (!$fpdf_loaded) {
    die(json_encode(['success' => false, 'message' => 'FPDF library not found. Please install FPDF in assets/fpdf/']));
}

// Helper function to get school info
function getSchoolInfoForPDF($school_id) {
    require_once '../includes/config.php';
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception('Database connection failed: ' . $conn->connect_error);
        }
        
        $query = "SELECT school_name, school_motto, school_address, school_logo, school_phone, school_email FROM tblschoolinfo WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Determine the logo path
            $logo_path = 'uploads/logos/default.png';
            
            if (!empty($row['school_logo']) && $row['school_logo'] !== 'default.png') {
                $full_logo_path = '../uploads/logos/' . $row['school_logo'];
                if (file_exists($full_logo_path)) {
                    $logo_path = 'uploads/logos/' . $row['school_logo'];
                } else {
                    error_log("Logo file not found: " . $full_logo_path);
                }
            }
            
            $school_info = [
                'school_name' => $row['school_name'] ?? 'School Name',
                'school_motto' => $row['school_motto'] ?? 'Excellence in Education',
                'school_address' => $row['school_address'] ?? '',
                'school_logo' => $logo_path,
                'phone_number' => $row['school_phone'] ?? '',
                'email' => $row['school_email'] ?? ''
            ];
            
            $stmt->close();
            $conn->close();
            
            return $school_info;
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        error_log("School info database error: " . $e->getMessage());
    }
    
    return [
        'school_name' => 'School Name',
        'school_motto' => 'Excellence in Education',
        'school_address' => '',
        'school_logo' => 'uploads/logos/default.png',
        'phone_number' => '',
        'email' => ''
    ];
}

// Base Analytics PDF Class - now FPDF is loaded
class AnalyticsPDF extends FPDF
{
    protected $schoolInfo;
    protected $selections;
    protected $is_print_mode;
    protected $pageWidth = 297; // A4 landscape
    
    function __construct($schoolInfo, $selections, $is_print_mode = false)
    {
        parent::__construct('L', 'mm', 'A4');
        $this->schoolInfo = $schoolInfo;
        $this->selections = $selections;
        $this->is_print_mode = $is_print_mode;
    }
    
    // Header with school logo and info
    function Header()
    {
        $schoolName = isset($this->schoolInfo['school_name']) ? strtoupper($this->schoolInfo['school_name']) : 'SCHOOL NAME';
        
        // Get logo path
        $logoPath = isset($this->schoolInfo['school_logo']) && !empty($this->schoolInfo['school_logo']) 
            ? '../' . $this->schoolInfo['school_logo'] 
            : null;
        
        // Add logo on left side if exists
        if ($logoPath && file_exists($logoPath)) {
            try {
                $this->Image($logoPath, 10, 8, 25);
            } catch (Exception $e) {
                error_log("Logo image error: " . $e->getMessage());
            }
        }
        
        // School name centered
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, $schoolName, 0, 1, 'C', false);
        
        // Contact info centered
        $contactInfo = '';
        if (!empty($this->schoolInfo['phone_number'])) {
            $contactInfo .= 'Phone: ' . $this->schoolInfo['phone_number'];
        }
        if (!empty($this->schoolInfo['email'])) {
            if (!empty($contactInfo)) $contactInfo .= ' | ';
            $contactInfo .= 'Email: ' . $this->schoolInfo['email'];
        }
        
        if (!empty($contactInfo)) {
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 5, $contactInfo, 0, 1, 'C', false);
        }
        
        $this->Ln(2);
        
        // Build header line
        $examName = !empty($this->selections['exam_name']) ? strtoupper($this->selections['exam_name']) : '';
        $className = !empty($this->selections['class_name']) ? strtoupper($this->selections['class_name']) : '';
        $streamName = !empty($this->selections['stream_name']) ? strtoupper($this->selections['stream_name']) : '';
        $termName = !empty($this->selections['term_name']) ? strtoupper($this->selections['term_name']) : '';
        $year = !empty($this->selections['year']) ? $this->selections['year'] : '';
        
        $headerLine = $this->getReportTitle() . ' - ' . trim("$examName $className $streamName $termName $year");
        $headerLine = preg_replace('/\s+/', ' ', $headerLine);
        
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, $headerLine, 0, 1, 'C', false);

        // Line separator
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->Ln(5);
    }
    
    // Footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);

        // Draw line
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());

        $this->Ln(3);

        // Motto
        if (!empty($this->schoolInfo['school_motto'])) {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 4, 'Motto: ' . $this->schoolInfo['school_motto'], 0, 0, 'L', false);
        }

        // Page number
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
    
    // To be overridden by child classes
    protected function getReportTitle() {
        return 'ANALYTICS REPORT';
    }
    
    // Truncate text to fit column
    protected function truncateText($text, $maxWidth, $fontSize = 8)
    {
        if (empty($text)) return '';
        
        $this->SetFont('Arial', '', $fontSize);
        $textWidth = $this->GetStringWidth($text);
        
        if ($textWidth <= $maxWidth) {
            return $text;
        }
        
        $truncated = $text;
        while ($this->GetStringWidth($truncated . '...') > $maxWidth && strlen($truncated) > 3) {
            $truncated = substr($truncated, 0, -1);
        }
        
        return $truncated . '...';
    }
}
?>