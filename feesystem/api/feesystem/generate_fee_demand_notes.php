<?php
/**
 * API: Generate Fee Demand Notes PDF
 * Endpoint: /feesystem/api/feesystem/generate_fee_demand_notes.php
 * Method: POST
 */

session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance privileges required.']);
    exit;
}

require_once('../../includes/config.php');

// Find FPDF library - More robust path checking
$fpdf_paths = [
    '../../vendor/setasign/fpdf/fpdf.php',
    '../../fpdf/fpdf.php',
    '../../fpdf181/fpdf.php',
    '../../assets/fpdf/fpdf.php',
    '../../lib/fpdf/fpdf.php'
];

$fpdf_loaded = false;
foreach ($fpdf_paths as $path) {
    $full_path = __DIR__ . '/' . $path;
    if (file_exists($full_path)) {
        require_once($full_path);
        $fpdf_loaded = true;
        break;
    }
}

if (!$fpdf_loaded) {
    error_log("FPDF not found. Checked paths: " . implode(', ', $fpdf_paths));
    echo json_encode(['success' => false, 'message' => 'PDF library not found. Please contact administrator.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$students = $input['students'] ?? [];
$school_id = $input['school_id'] ?? $_SESSION['school_id'] ?? null;
$year = $input['year'] ?? date('Y');
$term = $input['term'] ?? 1;

if (empty($students)) {
    echo json_encode(['success' => false, 'message' => 'No students selected']);
    exit;
}

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

try {
    // Get database connection
    $database = Database::getInstance();
    $db = $database->getConnection();

    // Get school info
    $school_query = "SELECT school_name, school_address, school_phone, school_email, school_logo 
                     FROM tblschoolinfo WHERE id = :school_id";
    $school_stmt = $db->prepare($school_query);
    $school_stmt->execute([':school_id' => $school_id]);
    $school_info = $school_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$school_info) {
        $school_info = [
            'school_name' => 'SCHOOL NAME',
            'school_address' => '',
            'school_phone' => '',
            'school_email' => ''
        ];
    }

    $termText = "Term $term";
    $date = date('d M Y');

    // Custom PDF Class for Demand Notes
    class DemandNotePDF extends FPDF
    {
        public $school_info;
        public $date;
        public $termText;
        public $year;
        
        function __construct($orientation='P', $unit='mm', $size='A4')
        {
            parent::__construct($orientation, $unit, $size);
        }
        
        function Header()
        {
            // No header
        }
        
        function Footer()
        {
            // No footer
        }
        function replaceTags($message, $student, $school_info, $term, $year) {
    $tags = [
        '[student_name]' => $student['full_name'],
        '[admission_no]' => $student['admission_no'],
        '[balance]' => 'KES ' . number_format(abs($student['balance']), 2),
        '[next]' => 'KES ' . number_format($student['next_term_fee'] ?? 0, 2),
        '[total]' => 'KES ' . number_format(($student['balance'] + ($student['next_term_fee'] ?? 0)), 2),
        '[class]' => $student['class_name'] ?? 'N/A',
        '[stream]' => $student['stream_name'] ?? '',
        '[term]' => $term,
        '[year]' => $year,
        '[school_name]' => $school_info['school_name'] ?? 'School'
    ];
    
    foreach ($tags as $tag => $value) {
        $message = str_replace($tag, $value, $message);
    }
    
    return $message;
}
        function DemandNote($student, $index, $total)
        {
            $school_name = isset($this->school_info['school_name']) ? strtoupper($this->school_info['school_name']) : 'SCHOOL NAME';
            $school_phone = $this->school_info['school_phone'] ?? '';
            $school_email = $this->school_info['school_email'] ?? '';
            $balance = abs($student['balance']);
            $balance_display = number_format($balance, 2);
            
            // Start Y position - alternate between top and middle of page
            if ($index % 2 == 0) {
                $this->SetY(15);
            } else {
                $this->SetY(145);
            }
            
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 8, $school_name, 0, 1, 'C');
            
            $this->SetFont('Arial', '', 10);
            $contact = "";
            if (!empty($school_phone)) $contact .= "Phone: $school_phone";
            if (!empty($school_email)) $contact .= " | Email: $school_email";
            if (!empty($contact)) {
                $this->Cell(0, 5, $contact, 0, 1, 'C');
            }
            
            $this->Ln(3);
            
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 7, 'FEE DEMAND NOTE', 0, 1, 'C');
            
            $this->Ln(3);
            
            $this->SetFont('Arial', '', 10);
            $this->Cell(45, 7, "Student:", 0, 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(100, 7, $student['full_name'], 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(25, 7, "Date:", 0, 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 7, $this->date, 0, 1, 'L');
            
            $this->SetFont('Arial', '', 10);
            $this->Cell(45, 7, "Adm No:", 0, 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(100, 7, $student['admission_no'], 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(25, 7, "Class:", 0, 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $class_display = $student['class_name'] ?? 'N/A';
            if (!empty($student['stream_name'])) $class_display .= " " . $student['stream_name'];
            $this->Cell(0, 7, $class_display, 0, 1, 'L');
            
            $this->Ln(5);
            
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 7, "Dear Parent / Guardian,", 0, 1, 'L');
            
            $this->Ln(3);
            
            $message = "This is to notify you that your outstanding fee balance is KES $balance_display. "
                      . "Please make arrangements to clear the balance soonest possible to avoid wasting student's learning time. "
                      . "Thank You.";
            
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(0, 6, $message);
            
            $this->Ln(5);
            
            $this->Cell(0, 7, "Yours sincerely,", 0, 1, 'L');
            $this->Ln(3);
            $this->Cell(0, 7, "Accounts Office", 0, 1, 'L');
            
            // Cut line (dotted line for cutting)
            $this->Ln(5);
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            
            if ($index % 2 == 0 && $index < $total - 1) {
                $this->SetY(135);
                if (method_exists($this, 'SetDash')) {
                    $this->SetDash(3, 3);
                    $this->Line(15, $this->GetY(), 195, $this->GetY());
                    $this->SetDash();
                } else {
                    $this->Line(15, $this->GetY(), 195, $this->GetY());
                }
                
                $this->SetFont('Arial', 'I', 8);
                $this->SetTextColor(150, 150, 150);
                $this->SetY(137);
                $this->Cell(0, 4, '--- Cut here ---', 0, 1, 'C');
                $this->SetTextColor(0, 0, 0);
            } elseif ($index % 2 == 1 && $index < $total - 1) {
                $this->SetY(270);
                if (method_exists($this, 'SetDash')) {
                    $this->SetDash(3, 3);
                    $this->Line(15, $this->GetY(), 195, $this->GetY());
                    $this->SetDash();
                } else {
                    $this->Line(15, $this->GetY(), 195, $this->GetY());
                }
                
                $this->SetFont('Arial', 'I', 8);
                $this->SetTextColor(150, 150, 150);
                $this->SetY(272);
                $this->Cell(0, 4, '--- Cut here ---', 0, 1, 'C');
                $this->SetTextColor(0, 0, 0);
            }
            
            // Add new page after every 2 notes (except last)
            if (($index + 1) % 2 == 0 && $index < $total - 1) {
                $this->AddPage();
            }
        }
    }

    // Create PDF
    $pdf = new DemandNotePDF();
    $pdf->school_info = $school_info;
    $pdf->date = date('d M Y');
    $pdf->termText = "Term $term";
    $pdf->year = $year;
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(15, 15, 15);

    $total = count($students);
    foreach ($students as $index => $student) {
        $pdf->DemandNote($student, $index, $total);
    }

    // Output PDF
    $pdf_filename = 'fee_demand_notes_' . date('Ymd_His') . '.pdf';
    $pdf_output = $pdf->Output('S');

    // Save to file
    $upload_dir = __DIR__ . '/../../uploads/fee_demand_notes/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_path = $upload_dir . $pdf_filename;
    $bytes_written = file_put_contents($file_path, $pdf_output);
    
    if ($bytes_written === false) {
        throw new Exception('Failed to save PDF file');
    }

    echo json_encode([
        'success' => true,
        'pdf_url' => '/feesystem/uploads/fee_demand_notes/' . $pdf_filename,
        'filename' => $pdf_filename,
        'message' => 'PDF generated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("PDF Generation Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to generate PDF: ' . $e->getMessage()
    ]);
}
?>