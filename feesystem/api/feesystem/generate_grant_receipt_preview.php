<?php
// /feesystem/api/feesystem/generate_grant_receipt_preview.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    echo json_encode(['success' => false, 'message' => 'FPDF library not found']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Get school info
$database = Database::getInstance();
$db = $database->getConnection();

$school_query = "SELECT school_name, school_address, school_phone, school_email, school_logo 
                 FROM tblschoolinfo WHERE id = :school_id";
$school_stmt = $db->prepare($school_query);
$school_stmt->execute([':school_id' => $_SESSION['school_id']]);
$school_info = $school_stmt->fetch(PDO::FETCH_ASSOC);

// Generate unique receipt number
function generateReceiptNumber() {
    $date = new DateTime();
    return 'GRT-' . $date->format('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function numberToWords($num) {
    if ($num === 0) return 'Zero';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    
    function convert($n, $ones, $tens, $teens) {
        if ($n < 10) return $ones[$n];
        if ($n < 20) return $teens[$n - 10];
        if ($n < 100) return $tens[floor($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        if ($n < 1000) return $ones[floor($n / 100)] . ' Hundred' . ($n % 100 ? ' ' . convert($n % 100, $ones, $tens, $teens) : '');
        if ($n < 1000000) return convert(floor($n / 1000), $ones, $tens, $teens) . ' Thousand' . ($n % 1000 ? ' ' . convert($n % 1000, $ones, $tens, $teens) : '');
        return convert(floor($n / 1000000), $ones, $tens, $teens) . ' Million' . ($n % 1000000 ? ' ' . convert($n % 1000000, $ones, $tens, $teens) : '');
    }
    return convert($num, $ones, $tens, $teens);
}

class PDF extends FPDF
{
    public $school_info;
    public $grant_data;
    public $distributions;

    function Header()
    {
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
        
        $logo_path = null;
        if (!empty($this->school_info['school_logo'])) {
            $logo_path = '../../../' . ltrim($this->school_info['school_logo'], './');
            if (!file_exists($logo_path)) $logo_path = null;
        }
        
        if ($logo_path && file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 25, 25);
        } else {
            $this->SetFont('Arial', 'B', 10);
            $this->SetXY(20, 18);
            $this->Cell(20, 8, 'SCHOOL', 0, 0, 'C');
        }
        
        $this->SetY(10);
        $this->SetFont('Arial', 'B', 14);
        $this->SetX(50);
        $school_name = isset($this->school_info['school_name']) ? strtoupper($this->school_info['school_name']) : 'SCHOOL NAME';
        $this->Cell(110, 7, $school_name, 0, 1, 'C');
        
        $this->SetFont('Arial', '', 9);
        $this->SetX(50);
        $address = isset($this->school_info['school_address']) ? $this->school_info['school_address'] : 'P.O BOX 000 - CITY';
        $this->Cell(110, 5, $address, 0, 1, 'C');
        
        $contact = "";
        if (!empty($this->school_info['school_phone'])) $contact .= "Phone: " . $this->school_info['school_phone'];
        if (!empty($this->school_info['school_email'])) $contact .= " | Email: " . $this->school_info['school_email'];
        
        $this->SetX(50);
        $this->Cell(110, 5, $contact, 0, 1, 'C');
        
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'GRANT RECEIPT (PREVIEW)', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'This is a preview - Grant has not been saved yet', 0, 1, 'C');
        
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, 'PREVIEW - Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    }
    
    function ReceiptBody()
    {
        $total = array_sum(array_column($this->distributions, 'amount'));
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'GRANT INFORMATION', 0, 1, 'L');
        $this->Ln(2);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(45, 7, 'Receipt No:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(80, 7, generateReceiptNumber(), 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(25, 7, 'Date:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 7, date('d/m/Y', strtotime($this->grant_data['receipt_date'])), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(45, 7, 'Grant Name:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(80, 7, $this->grant_data['name'], 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(25, 7, 'Source:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 7, $this->grant_data['source'], 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(45, 7, 'Payment Mode:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(80, 7, ucfirst($this->grant_data['payment_mode']), 0, 0, 'L');
        if (!empty($this->grant_data['reference_no'])) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(25, 7, 'Reference:', 0, 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 7, $this->grant_data['reference_no'], 0, 1, 'L');
        } else {
            $this->Ln(7);
        }
        
        $this->Ln(5);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'DISTRIBUTION BREAKDOWN', 0, 1, 'L');
        $this->Ln(2);
        
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(200, 200, 200);
        $this->Cell(100, 8, 'Vote Head', 1, 0, 'L', true);
        $this->Cell(50, 8, 'Alias', 1, 0, 'L', true);
        $this->Cell(30, 8, 'Amount (KES)', 1, 1, 'R', true);
        
        $this->SetFont('Arial', '', 9);
        foreach ($this->distributions as $dist) {
            $this->Cell(100, 7, substr($dist['vote_head_name'], 0, 45), 1, 0, 'L');
            $this->Cell(50, 7, $dist['vote_head_name'], 1, 0, 'L');
            $this->Cell(30, 7, number_format($dist['amount'], 2), 1, 1, 'R');
        }
        
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(220, 220, 220);
        $this->Cell(150, 8, 'TOTAL', 1, 0, 'R', true);
        $this->Cell(30, 8, number_format($total, 2), 1, 1, 'R', true);
        
        $this->Ln(5);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(40, 7, 'Amount in words:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 7, numberToWords(floor($total)) . ' Shillings Only.', 0, 1, 'L');
        
        if (!empty($this->grant_data['notes'])) {
            $this->Ln(5);
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 7, 'Notes:', 0, 1, 'L');
            $this->SetFont('Arial', 'I', 9);
            $this->MultiCell(0, 5, $this->grant_data['notes']);
        }
        
        $this->Ln(10);
        $this->SetFont('Arial', '', 10);
        $this->Cell(80, 7, '_________________________', 0, 0, 'C');
        $this->Cell(40, 7, '', 0, 0, 'C');
        $this->Cell(70, 7, '_________________________', 0, 1, 'C');
        $this->Cell(80, 5, 'Finance Officer Signature', 0, 0, 'C');
        $this->Cell(40, 5, '', 0, 0, 'C');
        $this->Cell(70, 5, 'Date', 0, 1, 'C');
    }
}

$pdf = new PDF();
$pdf->school_info = $school_info;
$pdf->grant_data = $data;
$pdf->distributions = $data['distributions'];
$pdf->AddPage();
$pdf->ReceiptBody();

$pdf_filename = 'grant_receipt_preview_' . date('Ymd_His') . '.pdf';
$pdf_output = $pdf->Output('S');

$upload_dir = '../../uploads/grant_receipts/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

$file_path = $upload_dir . $pdf_filename;
file_put_contents($file_path, $pdf_output);

echo json_encode([
    'success' => true,
    'pdf_url' => '/feesystem/uploads/grant_receipts/' . $pdf_filename,
    'filename' => $pdf_filename
]);
?>