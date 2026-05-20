<?php
// /feesystem/api/payments/generate_voucher_pdf.php
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
$voucher_id = $data['voucher_id'] ?? 0;

if ($voucher_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Voucher ID is required']);
    exit;
}

$database = Database::getInstance();
$db = $database->getConnection();
$school_id = $_SESSION['school_id'];

// Get voucher details
$voucher_query = "SELECT pv.*, 
                         s.name as supplier_name,
                         u.firstname as created_by_name
                  FROM payment_vouchers pv
                  LEFT JOIN suppliers s ON pv.supplier_id = s.id
                  LEFT JOIN tblteachers u ON pv.created_by = u.id
                  WHERE pv.id = :voucher_id AND pv.school_id = :school_id";
$voucher_stmt = $db->prepare($voucher_query);
$voucher_stmt->execute([
    ':voucher_id' => $voucher_id,
    ':school_id' => $school_id
]);
$voucher = $voucher_stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) {
    echo json_encode(['success' => false, 'message' => 'Voucher not found']);
    exit;
}

// Get voucher items
$items_query = "SELECT pvi.*, vh.name as vote_head_name
                FROM payment_voucher_items pvi
                LEFT JOIN vote_heads vh ON pvi.vote_head_id = vh.id
                WHERE pvi.voucher_id = :voucher_id";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([':voucher_id' => $voucher_id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get school info
$school_query = "SELECT school_name, school_address, school_phone, school_email, school_logo FROM tblschoolinfo WHERE id = :school_id";
$school_stmt = $db->prepare($school_query);
$school_stmt->execute([':school_id' => $school_id]);
$school_info = $school_stmt->fetch(PDO::FETCH_ASSOC);

// Custom PDF Class for Payment Voucher
class PaymentVoucherPDF extends FPDF
{
    public $school_info;
    public $voucher;
    public $items;
    
    function Header()
    {
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
        
        // School Logo (if exists)
        if (!empty($this->school_info['school_logo']) && file_exists('../../' . $this->school_info['school_logo'])) {
            $this->Image('../../' . $this->school_info['school_logo'], 15, 8, 25);
        }
        
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
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'PAYMENT VOUCHER', 0, 1, 'C');
        
        // Voucher Number
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 7, 'Voucher No: ' . ($this->voucher['voucher_no'] ?? 'AUTO-GENERATED'), 0, 1, 'C');
        
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
    
    function GenerateVoucher()
    {
        // Voucher Details Section
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, 'VOUCHER DETAILS', 0, 1, 'L');
        $this->Ln(2);
        
        $this->SetFont('Arial', '', 10);
        
        // Two column layout for details
        $left_width = 40;
        $right_width = 120;
        
        // Payee Name
        $this->SetX(15);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($left_width, 7, 'Payee Name:', 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell($right_width, 7, ucwords($this->voucher['payee_name']), 0, 1, 'L');
        
        // Payment Date
        $this->SetX(15);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($left_width, 7, 'Payment Date:', 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell($right_width, 7, date('d F Y', strtotime($this->voucher['payment_date'])), 0, 1, 'L');
        
        // Payment Mode
        $this->SetX(15);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($left_width, 7, 'Payment Mode:', 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $mode = ucfirst($this->voucher['payment_mode']);
        $this->Cell($right_width, 7, $mode, 0, 1, 'L');
        
        // Reference/Cheque No
        if (!empty($this->voucher['reference'])) {
            $this->SetX(15);
            $this->SetFont('Arial', 'B', 10);
            $ref_label = $this->voucher['payment_mode'] == 'cheque' ? 'Cheque No:' : 'Reference No:';
            $this->Cell($left_width, 7, $ref_label, 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell($right_width, 7, $this->voucher['reference'], 0, 1, 'L');
        }
        
        // ID/PS Number
        if (!empty($this->voucher['id_number'])) {
            $this->SetX(15);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($left_width, 7, 'ID/PS Number:', 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell($right_width, 7, $this->voucher['id_number'], 0, 1, 'L');
        }
        
        // Supplier (if any)
        if (!empty($this->voucher['supplier_name'])) {
            $this->SetX(15);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($left_width, 7, 'Supplier:', 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell($right_width, 7, $this->voucher['supplier_name'], 0, 1, 'L');
        }
        
        // LPO Details (if detailed voucher)
        if ($this->voucher['type'] == 'detailed') {
            if (!empty($this->voucher['lpo_number'])) {
                $this->SetX(15);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell($left_width, 7, 'LPO/LSO Number:', 0, 0, 'L');
                $this->SetFont('Arial', '', 10);
                $this->Cell($right_width, 7, $this->voucher['lpo_number'], 0, 1, 'L');
            }
            if (!empty($this->voucher['delivery_note_no'])) {
                $this->SetX(15);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell($left_width, 7, 'Delivery Note No:', 0, 0, 'L');
                $this->SetFont('Arial', '', 10);
                $this->Cell($right_width, 7, $this->voucher['delivery_note_no'], 0, 1, 'L');
            }
        }
        
        $this->Ln(5);
        
        // Separator Line
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(5);
        
        // Expense Items Table
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, 'EXPENSE ITEMS', 0, 1, 'L');
        $this->Ln(2);
        
        // Table Header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(240, 240, 240);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(70, 8, 'Vote Head', 1, 0, 'L', true);
        $this->Cell(70, 8, 'Particulars', 1, 0, 'L', true);
        $this->Cell(40, 8, 'Amount (KES)', 1, 1, 'R', true);
        
        // Table Body
        $this->SetFont('Arial', '', 9);
        $total = 0;
        $row_num = 1;
        
        if (empty($this->items)) {
            $this->Cell(190, 8, 'No items found', 1, 1, 'C');
        } else {
            foreach ($this->items as $item) {
                $vote_head = $item['vote_head_name'] ?? 'N/A';
                $particulars = $item['particulars'] ?? '-';
                $amount = floatval($item['amount']);
                $total += $amount;
                
                $this->Cell(10, 7, $row_num, 1, 0, 'C');
                $this->Cell(70, 7, substr($vote_head, 0, 35), 1, 0, 'L');
                $this->Cell(70, 7, substr($particulars, 0, 35), 1, 0, 'L');
                $this->Cell(40, 7, number_format($amount, 2), 1, 1, 'R');
                $row_num++;
            }
        }
        
        // Total Row
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(255, 245, 245);
        $this->Cell(150, 8, 'TOTAL', 1, 0, 'R', true);
        $this->Cell(40, 8, number_format($total, 2), 1, 1, 'R', true);
        
        $this->Ln(8);
        
        // Notes (if any)
        if (!empty($this->voucher['notes'])) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 7, 'Notes:', 0, 1, 'L');
            $this->SetFont('Arial', '', 9);
            $this->MultiCell(0, 6, $this->voucher['notes']);
            $this->Ln(3);
        }
        
        // Amount in words
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 7, 'Amount in Words:', 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, ucwords($this->convertNumberToWords($total)) . ' Shillings Only', 0, 1, 'L');
        
        $this->Ln(10);
        
        // Signatures
        $this->SetFont('Arial', 'B', 10);
        
        $signature_width = 60;
        $spacing = 15;
        $start_x = (210 - ($signature_width * 3 + $spacing * 2)) / 2;
        
        // Prepared By
        $this->SetX($start_x);
        $this->Cell($signature_width, 10, 'Prepared By', 0, 0, 'C');
        
        // Approved By
        $this->SetX($start_x + $signature_width + $spacing);
        $this->Cell($signature_width, 10, 'Approved By', 0, 0, 'C');
        
        // Received By
        $this->SetX($start_x + ($signature_width + $spacing) * 2);
        $this->Cell($signature_width, 10, 'Received By', 0, 1, 'C');
        
        // Signature Lines
        $this->SetY($this->GetY() + 5);
        $this->SetX($start_x);
        $this->Cell($signature_width, 10, '_________________', 0, 0, 'C');
        $this->SetX($start_x + $signature_width + $spacing);
        $this->Cell($signature_width, 10, '_________________', 0, 0, 'C');
        $this->SetX($start_x + ($signature_width + $spacing) * 2);
        $this->Cell($signature_width, 10, '_________________', 0, 1, 'C');
        
        // Names under signatures
        $this->SetFont('Arial', '', 8);
        $this->SetY($this->GetY() + 2);
        $this->SetX($start_x);
        $this->Cell($signature_width, 5, '(' . ($this->voucher['created_by_name'] ?? 'Accountant') . ')', 0, 0, 'C');
        $this->SetX($start_x + $signature_width + $spacing);
        $this->Cell($signature_width, 5, '(Finance Officer)', 0, 0, 'C');
        $this->SetX($start_x + ($signature_width + $spacing) * 2);
        $this->Cell($signature_width, 5, '(' . ucwords($this->voucher['payee_name']) . ')', 0, 1, 'C');
        
        $this->Ln(5);
        
        // Footer Note
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'This is a computer-generated payment voucher and does not require a signature.', 0, 1, 'C');
    }
    
    function convertNumberToWords($number) {
        $number = round($number, 2);
        $shillings = floor($number);
        $cents = round(($number - $shillings) * 100);
        
        $words = $this->convertNumberToWordsHelper($shillings);
        
        if ($cents > 0) {
            $words .= ' and ' . $this->convertNumberToWordsHelper($cents) . ' Cents';
        }
        
        return $words;
    }
    
    function convertNumberToWordsHelper($number) {
        $words = array(
            0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 
            14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 
            18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty', 
            40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 
            80 => 'Eighty', 90 => 'Ninety'
        );
        
        if ($number < 21) {
            return $words[$number];
        } elseif ($number < 100) {
            $tens = floor($number / 10) * 10;
            $units = $number % 10;
            return $words[$tens] . ($units ? ' ' . $words[$units] : '');
        } elseif ($number < 1000) {
            $hundreds = floor($number / 100);
            $remainder = $number % 100;
            return $words[$hundreds] . ' Hundred' . ($remainder ? ' and ' . $this->convertNumberToWordsHelper($remainder) : '');
        } elseif ($number < 1000000) {
            $thousands = floor($number / 1000);
            $remainder = $number % 1000;
            return $this->convertNumberToWordsHelper($thousands) . ' Thousand' . ($remainder ? ' ' . $this->convertNumberToWordsHelper($remainder) : '');
        } elseif ($number < 1000000000) {
            $millions = floor($number / 1000000);
            $remainder = $number % 1000000;
            return $this->convertNumberToWordsHelper($millions) . ' Million' . ($remainder ? ' ' . $this->convertNumberToWordsHelper($remainder) : '');
        }
        
        return 'Amount too large';
    }
}

// Create PDF
$pdf = new PaymentVoucherPDF();
$pdf->school_info = $school_info;
$pdf->voucher = $voucher;
$pdf->items = $items;
$pdf->AddPage();
$pdf->GenerateVoucher();

// Output PDF
$pdf_filename = 'payment_voucher_' . $voucher['voucher_no'] . '_' . date('Ymd') . '.pdf';
$pdf_output = $pdf->Output('S');

// Save to file
$upload_dir = '../../uploads/payment_vouchers/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_path = $upload_dir . $pdf_filename;
file_put_contents($file_path, $pdf_output);

echo json_encode([
    'success' => true,
    'pdf_url' => '/feesystem/uploads/payment_vouchers/' . $pdf_filename,
    'filename' => $pdf_filename
]);
?>