<?php
// /feesystem/api/feesystem/generate_fee_statement_pdf.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'finance') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Finance privileges required.']);
    exit;
}

require_once('../../includes/config.php');

// Try to find FPDF library
$fpdf_paths = [
    '../../assets/fpdf/fpdf.php',
    '../../assets/fpdf/fpdf/fpdf.php',
    '../../fpdf/fpdf.php',
    '../../fpdf181/fpdf.php',
    '../../vendor/setasign/fpdf/fpdf.php',
    '../../lib/fpdf/fpdf.php'
];

$fpdf_loaded = false;
foreach ($fpdf_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $fpdf_loaded = true;
        break;
    }
}

if (!$fpdf_loaded) {
    echo json_encode(['success' => false, 'message' => 'PDF library not found. Please install FPDF.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$student_id = $data['student_id'] ?? 0;
$year = $data['year'] ?? date('Y');

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

$database = Database::getInstance();
$db = $database->getConnection();

// Get student details
$student_query = "SELECT 
                    s.id, 
                    s.AdmNo as admission_no, 
                    s.FirstName as first_name,
                    s.SecondName as middle_name,
                    s.LastName as last_name,
                    s.Gender as gender,
                    s.Class as class_name
                  FROM tblstudents s
                  WHERE s.id = :student_id AND s.school_id = :school_id";
$student_stmt = $db->prepare($student_query);
$student_stmt->execute([
    ':student_id' => $student_id,
    ':school_id' => $_SESSION['school_id']
]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
}

// Get transactions
$trans_query = "SELECT 
                  ft.id,
                  ft.amount,
                  ft.description,
                  ft.transaction_type,
                  ft.created_at,
                  ft.academic_year
                FROM fee_transactions ft
                WHERE ft.student_id = :student_id 
                  AND ft.school_id = :school_id
                  AND ft.academic_year = :year
                ORDER BY ft.created_at ASC";
$trans_stmt = $db->prepare($trans_query);
$trans_stmt->execute([
    ':student_id' => $student_id,
    ':school_id' => $_SESSION['school_id'],
    ':year' => $year
]);
$transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get school info
$school_query = "SELECT school_name, school_address, school_phone, school_email FROM tblschoolinfo WHERE id = :school_id";
$school_stmt = $db->prepare($school_query);
$school_stmt->execute([':school_id' => $_SESSION['school_id']]);
$school_info = $school_stmt->fetch(PDO::FETCH_ASSOC);

// Custom PDF Class
class PDF extends FPDF
{
    public $school_info;
    public $student;
    public $year;
    public $transactions;
    
    function Header()
    {
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
        
        // School Name
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, strtoupper($this->school_info['school_name'] ?? 'SCHOOL NAME'), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, $this->school_info['school_address'] ?? 'P.O BOX 000 - CITY', 0, 1, 'C');
        
        $contact = "";
        if (!empty($this->school_info['school_phone'])) {
            $contact .= "Phone: " . $this->school_info['school_phone'];
        }
        if (!empty($this->school_info['school_email'])) {
            $contact .= " | Email: " . $this->school_info['school_email'];
        }
        $this->Cell(0, 5, $contact, 0, 1, 'C');
        
        $this->Ln(3);
        
        // Title
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'FEE STATEMENT', 0, 1, 'C');
        
        $student_name = trim($this->student['first_name'] . ' ' . ($this->student['middle_name'] ?? '') . ' ' . $this->student['last_name']);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, $student_name . ' (' . $this->student['admission_no'] . ') - ' . $this->student['class_name'], 0, 1, 'C');
        $this->Cell(0, 5, 'Academic Year: ' . $this->year, 0, 1, 'C');
        
        $this->Ln(3);
        
        // Separator Line
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(5);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' - Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    }
    
    function GenerateStatement()
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'TRANSACTION HISTORY', 0, 1, 'L');
        $this->Ln(2);
        
        // Table Header
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(25, 8, 'Date', 1, 0, 'L');
        $this->Cell(35, 8, 'Receipt No', 1, 0, 'L');
        $this->Cell(65, 8, 'Description', 1, 0, 'L');
        $this->Cell(30, 8, 'Debit (KES)', 1, 0, 'R');
        $this->Cell(30, 8, 'Credit (KES)', 1, 0, 'R');
        $this->Cell(30, 8, 'Balance (KES)', 1, 1, 'R');
        
        // Table Body
        $this->SetFont('Arial', '', 9);
        $running_balance = 0;
        $total_debit = 0;
        $total_credit = 0;
        
        if (empty($this->transactions)) {
            $this->Cell(215, 8, 'No transactions found', 1, 1, 'C');
        } else {
            foreach ($this->transactions as $trans) {
                $date = date('d M Y', strtotime($trans['created_at']));
                $description = $trans['description'];
                $debit = $trans['transaction_type'] == 'debit' ? $trans['amount'] : 0;
                $credit = $trans['transaction_type'] == 'payment' ? $trans['amount'] : 0;
                
                $running_balance += $debit - $credit;
                $total_debit += $debit;
                $total_credit += $credit;
                
                $this->Cell(25, 7, $date, 1, 0, 'L');
                $this->Cell(35, 7, $trans['transaction_type'] == 'payment' ? 'Receipt' : '-', 1, 0, 'L');
                $this->Cell(65, 7, substr($description, 0, 40), 1, 0, 'L');
                $this->Cell(30, 7, $debit > 0 ? number_format($debit, 2) : '-', 1, 0, 'R');
                $this->Cell(30, 7, $credit > 0 ? number_format($credit, 2) : '-', 1, 0, 'R');
                $this->Cell(30, 7, number_format($running_balance, 2), 1, 1, 'R');
            }
        }
        
        // Totals Row
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(125, 8, 'TOTAL', 1, 0, 'R');
        $this->Cell(30, 8, number_format($total_debit, 2), 1, 0, 'R');
        $this->Cell(30, 8, number_format($total_credit, 2), 1, 0, 'R');
        $this->Cell(30, 8, number_format($running_balance, 2), 1, 1, 'R');
        
        $this->Ln(10);
        
        // Footer Notes
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'This is an official fee statement. Please contact the finance office for any queries.', 0, 1, 'C');
        $this->Cell(0, 5, 'Payment deadlines and penalties apply as per school policy.', 0, 1, 'C');
    }
}

// Create PDF
$pdf = new PDF();
$pdf->school_info = $school_info;
$pdf->student = $student;
$pdf->year = $year;
$pdf->transactions = $transactions;
$pdf->AddPage();
$pdf->GenerateStatement();

// Output PDF
$pdf_filename = 'fee_statement_' . $student['admission_no'] . '_' . $year . '.pdf';
$pdf_output = $pdf->Output('S');

// Save to file
$upload_dir = '../../uploads/fee_statements/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_path = $upload_dir . $pdf_filename;
file_put_contents($file_path, $pdf_output);

echo json_encode([
    'success' => true,
    'pdf_url' => '/feesystem/uploads/fee_statements/' . $pdf_filename,
    'filename' => $pdf_filename
]);
?>