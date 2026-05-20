<?php
/**
 * API: Generate Other Income Receipt PDF
 * Endpoint: /feesystem/api/feesystem/generate_other_income_pdf.php
 * Method: POST
 */

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
    echo json_encode(['success' => false, 'message' => 'FPDF library not found. Please install FPDF.']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

// Validate required fields
$required_fields = ['payer_name', 'payment_date', 'payment_mode', 'items', 'total_amount', 'receipt_number'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Get database connection
$database = Database::getInstance();
$db = $database->getConnection();

// Get school info
$school_query = "SELECT school_name, school_address, school_phone, school_email, school_logo 
                 FROM tblschoolinfo WHERE id = :school_id";
$school_stmt = $db->prepare($school_query);
$school_stmt->execute([':school_id' => $_SESSION['school_id']]);
$school_info = $school_stmt->fetch(PDO::FETCH_ASSOC);

// Helper function to convert number to words
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

// Custom PDF Class for Other Income Receipt
class OtherIncomePDF extends FPDF
{
    public $school_info;
    public $receipt_data;
    public $items;

    function Header()
    {
        // Black and white theme
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
        
        // School Logo (Left)
        $logo_path = null;
        if (!empty($this->school_info['school_logo'])) {
            $logo_path = '../../../' . ltrim($this->school_info['school_logo'], './');
            if (!file_exists($logo_path)) {
                $logo_path = null;
            }
        }
        
        if (!$logo_path && !empty($this->school_info['school_logo_url'])) {
            $logo_path = '../../../' . ltrim($this->school_info['school_logo_url'], './');
            if (!file_exists($logo_path)) {
                $logo_path = null;
            }
        }
        
        if ($logo_path && file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 25, 25);
        } else {
            $this->SetFont('Arial', 'B', 10);
            $this->SetXY(20, 18);
            $this->Cell(20, 8, 'SCHOOL', 0, 0, 'C');
        }
        
        // School Name and Info (Centered)
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
        if (!empty($this->school_info['school_phone'])) {
            $contact .= "Phone: " . $this->school_info['school_phone'];
        }
        if (!empty($this->school_info['school_email'])) {
            $contact .= " | Email: " . $this->school_info['school_email'];
        }
        
        $this->SetX(50);
        $this->Cell(110, 5, $contact, 0, 1, 'C');
        
        $this->Ln(3);
        
        // Title
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'OFFICIAL RECEIPT', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'OTHER INCOME RECEIPT', 0, 1, 'C');
        
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
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' - Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    }
    
    function ReceiptBody()
    {
        // Receipt Information Section
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'RECEIPT INFORMATION', 0, 1, 'L');
        $this->Ln(2);
        
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(245, 245, 245);
        
        // Two column layout for receipt info
        $this->Cell(45, 7, 'Receipt No:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(80, 7, $this->receipt_data['receipt_number'], 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(25, 7, 'Date:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 7, date('d/m/Y', strtotime($this->receipt_data['payment_date'])), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(45, 7, 'Received From:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(80, 7, $this->receipt_data['payer_name'], 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(25, 7, 'Payer Type:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $payer_type_text = ucfirst($this->receipt_data['payer_type']);
        $this->Cell(40, 7, $payer_type_text, 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(45, 7, 'Payment Mode:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $payment_mode_text = ucfirst($this->receipt_data['payment_mode']);
        $this->Cell(80, 7, $payment_mode_text, 0, 0, 'L');
        if (!empty($this->receipt_data['payment_code'])) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(25, 7, 'Transaction ID:', 0, 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 7, $this->receipt_data['payment_code'], 0, 1, 'L');
        } else {
            $this->Ln(7);
        }
        
        $this->Ln(5);
        
        // Items Table - Without Vote Head column, only Particulars and Amount
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'RECEIPT ITEMS', 0, 1, 'L');
        $this->Ln(2);
        
        // Table Header - Only 2 columns
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(200, 200, 200);
        $this->Cell(130, 8, 'Particulars', 1, 0, 'L', true);
        $this->Cell(50, 8, 'Amount (KES)', 1, 1, 'R', true);
        
        // Table Body
        $this->SetFont('Arial', '', 9);
        $total = 0;
        $row_count = 0;
        $max_rows = 12;
        
        foreach ($this->items as $item) {
            $amount = floatval($item['amount']);
            $total += $amount;
            $description = isset($item['description']) ? $item['description'] : '';
            
            // Check if we need a new page
            if ($row_count >= $max_rows && $this->GetY() > 230) {
                $this->AddPage();
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(0, 8, 'RECEIPT ITEMS (Continued)', 0, 1, 'L');
                $this->Ln(2);
                $this->SetFont('Arial', 'B', 9);
                $this->SetFillColor(200, 200, 200);
                $this->Cell(130, 8, 'Particulars', 1, 0, 'L', true);
                $this->Cell(50, 8, 'Amount (KES)', 1, 1, 'R', true);
                $this->SetFont('Arial', '', 9);
                $row_count = 0;
            }
            
            // Simple cell drawing with proper alignment
            $y_before = $this->GetY();
            
            // Draw description cell with possible multi-line
            $x = $this->GetX();
            $this->MultiCell(130, 6, $description, 1, 'L');
            $desc_height = $this->GetY() - $y_before;
            
            // Move to the same Y position for amount cell
            $this->SetY($y_before);
            $this->SetX($x + 130);
            
            // Draw amount cell with same height
            $this->Cell(50, $desc_height, number_format($amount, 2), 1, 1, 'R');
            
            $row_count++;
        }
        
        // Add empty rows to reach 12 rows
        $empty_rows = $max_rows - $row_count;
        for ($i = 0; $i < $empty_rows; $i++) {
            if ($this->GetY() > 250) {
                $this->AddPage();
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(0, 8, 'RECEIPT ITEMS (Continued)', 0, 1, 'L');
                $this->Ln(2);
                $this->SetFont('Arial', 'B', 9);
                $this->SetFillColor(200, 200, 200);
                $this->Cell(130, 8, 'Particulars', 1, 0, 'L', true);
                $this->Cell(50, 8, 'Amount (KES)', 1, 1, 'R', true);
                $this->SetFont('Arial', '', 9);
            }
            $this->Cell(130, 7, '', 1, 0, 'L');
            $this->Cell(50, 7, '', 1, 1, 'R');
        }
        
        // Total Row
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(220, 220, 220);
        $this->Cell(130, 8, 'TOTAL', 1, 0, 'R', true);
        $this->Cell(50, 8, number_format($total, 2), 1, 1, 'R', true);
        
        $this->Ln(5);
        
        // Amount in words
        $this->SetFont('Arial', '', 10);
        $this->Cell(40, 7, 'Amount in words:', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 7, numberToWords(floor($total)) . ' Shillings Only.', 0, 1, 'L');
        
        $this->Ln(5);
        
        // Notes if any
        if (!empty($this->receipt_data['notes'])) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 7, 'Notes:', 0, 1, 'L');
            $this->SetFont('Arial', 'I', 9);
            $this->MultiCell(0, 5, $this->receipt_data['notes']);
            $this->Ln(5);
        }
        
        // Signature Section
        $this->Ln(10);
        $this->SetFont('Arial', '', 10);
        $this->Cell(80, 7, '_________________________', 0, 0, 'C');
        $this->Cell(40, 7, '', 0, 0, 'C');
        $this->Cell(70, 7, '_________________________', 0, 1, 'C');
        $this->Cell(80, 5, 'Finance Officer Signature', 0, 0, 'C');
        $this->Cell(40, 5, '', 0, 0, 'C');
        $this->Cell(70, 5, 'Date', 0, 1, 'C');
        
        $this->Ln(5);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'This is a computer-generated receipt and requires no signature.', 0, 1, 'C');
    }
}

// Create PDF
$pdf = new OtherIncomePDF();
$pdf->school_info = $school_info;
$pdf->receipt_data = [
    'receipt_number' => $data['receipt_number'],
    'payer_name' => $data['payer_name'],
    'payer_type' => $data['payer_type'],
    'payment_date' => $data['payment_date'],
    'payment_mode' => $data['payment_mode'],
    'payment_code' => $data['payment_code'] ?? '',
    'notes' => $data['notes'] ?? '',
    'total_amount' => $data['total_amount']
];
$pdf->items = $data['items'];
$pdf->AddPage();
$pdf->ReceiptBody();

// Output PDF
$pdf_filename = 'receipt_' . $data['receipt_number'] . '_' . date('Ymd_His') . '.pdf';
$pdf_output = $pdf->Output('S');

// Save to file
$upload_dir = '../../uploads/other_income_receipts/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_path = $upload_dir . $pdf_filename;
file_put_contents($file_path, $pdf_output);

echo json_encode([
    'success' => true,
    'pdf_url' => '/feesystem/uploads/other_income_receipts/' . $pdf_filename,
    'filename' => $pdf_filename,
    'message' => 'PDF generated successfully'
]);
?>