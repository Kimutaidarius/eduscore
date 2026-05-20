<?php
/**
 * generate_receipt.php - Professional receipt layout matching invoice style
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];

// Get parameters
$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'payment';

if (!$payment_id) {
    die('Invalid payment ID');
}

// ============ LOAD FPDF ============
$fpdf_loaded = false;
$fpdf_paths = [
    __DIR__ . '/../assets/fpdf/fpdf.php',
    __DIR__ . '/../fpdf/fpdf.php',
    __DIR__ . '/../vendor/setasign/fpdf/fpdf.php'
];

foreach ($fpdf_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $fpdf_loaded = true;
        break;
    }
}

if (!$fpdf_loaded) {
    die('FPDF library not found. Please install FPDF.');
}

// Fetch payment details
try {
    if ($type === 'payment') {
        $stmt = $dbh->prepare("
            SELECT p.*, s.school_name, s.school_email, s.school_phone, s.school_address,
                   s.county, s.principal_name
            FROM tblpayments p
            LEFT JOIN tblschoolinfo s ON p.school_id = s.id
            WHERE p.id = ? AND p.school_id = ?
        ");
    } else {
        $stmt = $dbh->prepare("
            SELECT t.*, s.school_name, s.school_email, s.school_phone, s.school_address,
                   s.county, s.principal_name
            FROM tbltransactions t
            LEFT JOIN tblschoolinfo s ON t.school_id = s.id
            WHERE t.id = ? AND t.school_id = ?
        ");
    }
    
    $stmt->execute([$payment_id, $school_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        die('Payment not found');
    }
    
    // Get onboarding fee from settings
    $stmt = $dbh->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'onboarding_fee'");
    $stmt->execute();
    $onboarding_fee = $stmt->fetchColumn() ?: 2000;
    
    // Get extra student fee from settings
    $stmt = $dbh->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'extra_student_fee'");
    $stmt->execute();
    $extra_student_fee = $stmt->fetchColumn() ?: 15;
    
    // Get school's total students
    $stmt = $dbh->prepare("SELECT total_students FROM tblschoolinfo WHERE id = ?");
    $stmt->execute([$school_id]);
    $total_students = $stmt->fetchColumn() ?: 0;
    
    // Get active students count
    $stmt = $dbh->prepare("SELECT COUNT(*) as active_count FROM tblstudents WHERE school_id = ? AND Status = 'Active'");
    $stmt->execute([$school_id]);
    $active_students = $stmt->fetchColumn() ?: 0;
    
    // Check if onboarding fee has been paid before
    $stmt = $dbh->prepare("
        SELECT COUNT(*) FROM payments 
        WHERE school_id = ? AND amount = ? AND status IN ('completed', 'paid', 'success')
        UNION ALL
        SELECT COUNT(*) FROM tbltransactions 
        WHERE school_id = ? AND amount = ? AND status IN ('completed', 'paid', 'success')
    ");
    $stmt->execute([$school_id, $onboarding_fee, $school_id, $onboarding_fee]);
    $onboarding_paid_count = array_sum($stmt->fetchAll(PDO::FETCH_COLUMN));
    $onboarding_already_paid = ($onboarding_paid_count > 0);
    
    // Calculate invoice totals based on payment type
    $amount = floatval($payment['amount']);
    $is_onboarding_payment = ($amount == $onboarding_fee);
    
    // For onboarding fee: Total is the onboarding fee, Balance Due is 0
    // For subscription: Total is based on student count, Balance Due depends on partial payments
    if ($is_onboarding_payment) {
        // Onboarding fee payment
        $invoice_total = $onboarding_fee;
        $total_paid = $amount;
        $balance_due = 0; // Onboarding fee is one-time, so once paid, balance is 0
        $description = 'EDUSCORE ANALYSIS - One-time Onboarding Fee';
    } else {
        // Subscription payment - calculate based on student count
        // Assuming term fee is KES 15 per student
        $term_fee_per_student = 15;
        $invoice_total = $active_students * $term_fee_per_student;
        $total_paid = $amount;
        $balance_due = max(0, $invoice_total - $total_paid);
        $description = 'EDUSCORE ANALYSIS - Term Subscription Fee';
    }
    
    // Generate receipt number
    $receipt_no = 'RCT-' . date('Y') . '-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT);
    $is_paid = in_array(strtolower($payment['status'] ?? ''), ['completed', 'paid', 'success']);
    
    // Format date
    $payment_date = date('d M Y', strtotime($payment['created_at'] ?? 'now'));
    
    // Get reference
    $reference = $payment['reference'] ?? $payment['transaction_id'] ?? $payment['mpesa_receipt'] ?? 'N/A';
    
    // Payment method
    $method = isset($payment['reference']) ? 'M-PESA ' : 'M-PESA Buy Goods';
    
    // Payment date
    $trans_date = date('d M Y', strtotime($payment['paid_at'] ?? $payment['created_at'] ?? 'now'));
    
    // Get phone number
    $phone = $payment['phone'] ?? $payment['phone_number'] ?? 'N/A';
    
    // Generate invoice reference
    $invoice_ref = 'INV-' . date('Y') . '-' . str_pad($payment['id'], 4, '0', STR_PAD_LEFT);
    
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

// ============ CREATE PDF WITH INVOICE STYLING ============
class PDF_Receipt extends FPDF
{
    // Colors matching invoice
    protected $blue = [41, 128, 185];      // #2980b9 - Dark Blue
    protected $light_blue = [52, 152, 219]; // #3498db - Light Blue
    protected $yellow = [241, 196, 15];     // #f1c40f - Yellow
    protected $light_yellow = [254, 249, 207]; // #fef9cf - Light Yellow
    protected $gray = [236, 240, 241];      // #ecf0f1 - Light Gray
    protected $red = [231, 76, 60];         // #e74c3c - Red for stamp
    protected $orange = [243, 156, 18];     // #f39c12 - Orange for pending
    protected $green = [46, 204, 113];      // #2ecc71 - Green for paid
    
    // Data properties
    protected $receipt_no;
    protected $payment_date;
    protected $school;
    protected $payment;
    protected $is_paid;
    protected $description;
    protected $amount;
    protected $reference;
    protected $method;
    protected $trans_date;
    protected $phone;
    protected $invoice_ref;
    protected $invoice_total;
    protected $total_paid;
    protected $balance_due;
    protected $is_onboarding_payment;
    
    function __construct($data) {
        parent::__construct();
        $this->receipt_no = $data['receipt_no'];
        $this->payment_date = $data['payment_date'];
        $this->school = $data['school'];
        $this->payment = $data['payment'];
        $this->is_paid = $data['is_paid'];
        $this->description = $data['description'];
        $this->amount = $data['amount'];
        $this->reference = $data['reference'];
        $this->method = $data['method'];
        $this->trans_date = $data['trans_date'];
        $this->phone = $data['phone'];
        $this->invoice_ref = $data['invoice_ref'];
        $this->invoice_total = $data['invoice_total'];
        $this->total_paid = $data['total_paid'];
        $this->balance_due = $data['balance_due'];
        $this->is_onboarding_payment = $data['is_onboarding_payment'];
    }
    
    function Header()
    {
        // Top colored bar (yellow) - matching invoice
        $this->SetFillColor($this->yellow[0], $this->yellow[1], $this->yellow[2]);
        $this->Rect(0, 0, 210, 15, 'F');
        
        // Logo area (blue bar) - matching invoice
        $this->SetFillColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->Rect(0, 15, 70, 25, 'F');
        
        // Company name (white on blue) - matching invoice
        $this->SetXY(5, 20);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(60, 10, 'EDUSCORE', 0, 1, 'C');
        
        // Tagline - matching invoice
        $this->SetFont('Arial', '', 8);
        $this->SetXY(5, 30);
        $this->Cell(60, 5, 'School Management System', 0, 1, 'C');
        
        // Receipt title (on white background) - matching invoice style
        $this->SetTextColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->SetFont('Arial', 'B', 24);
        $this->SetXY(80, 22);
        $this->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1);
        
        // Receipt number and date in light yellow box - matching invoice
        $this->SetFillColor($this->light_yellow[0], $this->light_yellow[1], $this->light_yellow[2]);
        $this->SetXY(140, 20);
        $this->Cell(60, 20, '', 0, 1, 'C', true);
        
        $this->SetXY(145, 23);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(50, 4, 'RECEIPT NO:', 0, 1);
        
        $this->SetXY(145, 27);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->Cell(50, 4, $this->receipt_no, 0, 1);
        
        $this->SetXY(145, 33);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(50, 4, 'DATE:', 0, 1);
        
        $this->SetXY(145, 37);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(50, 4, $this->payment_date, 0, 1);
        
        $this->Ln(25);
    }
    
    function Footer()
    {
        // Position at 1.5 cm from bottom - matching invoice
        $this->SetY(-25);
        
        // Footer line (blue) - matching invoice
        $this->SetDrawColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        $this->SetY(-20);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        
        // Left side - company info - matching invoice
        $this->Cell(60, 4, 'EduScore Systems Ltd', 0, 0, 'L');
        $this->Cell(70, 4, '', 0, 0);
        $this->Cell(60, 4, 'Page ' . $this->PageNo() . '/{nb}', 0, 1, 'R');
        
        $this->SetY(-16);
        $this->SetFont('Arial', '', 7);
        $this->Cell(60, 4, 'kymtechnologiesltd@gmail.com | +254 799 115 282', 0, 0, 'L');
        $this->Cell(70, 4, '', 0, 0);
        $this->Cell(60, 4, 'Generated: ' . date('Y-m-d H:i'), 0, 1, 'R');
        
        $this->SetY(-12);
        $this->Cell(60, 4, 'www.edu-score.app', 0, 0, 'L');
    }
    
    function ReceiptContent()
    {
        // Received From section with gray background - matching invoice style
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->Cell(0, 8, 'RECEIVED FROM:', 0, 1);
        
        // Store current Y position
        $startY = $this->GetY();
        
        // Light gray background for payer details - matching invoice
        $this->SetFillColor($this->gray[0], $this->gray[1], $this->gray[2]);
        $this->SetX(10);
        $this->Cell(90, 30, '', 0, 0, 'L', true);
        
        $this->SetXY(15, $startY + 2);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(80, 6, $this->school['school_name'] ?? 'School Name', 0, 1);
        
        $this->SetX(15);
        $this->SetFont('Arial', '', 9);
        $this->Cell(80, 5, $this->school['school_address'] ?? 'Address not provided', 0, 1);
        
        $this->SetX(15);
        $this->Cell(80, 5, 'Phone: ' . $this->phone, 0, 1);
        
        $this->SetX(15);
        $this->Cell(80, 5, 'Email: ' . ($this->school['school_email'] ?? 'N/A'), 0, 1);
        
        $this->SetX(15);
        if (!empty($this->school['county'])) {
            $this->Cell(80, 5, 'County: ' . $this->school['county'], 0, 1);
        }
        
        // Payment status in right box with light yellow background - matching invoice
        $this->SetXY(110, $startY);
        $this->SetFillColor($this->light_yellow[0], $this->light_yellow[1], $this->light_yellow[2]);
        $this->Cell(90, 30, '', 0, 0, 'L', true);
        
        $this->SetXY(115, $startY + 5);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(80, 6, 'PAYMENT STATUS:', 0, 1);
        
        $this->SetXY(115, $startY + 11);
        if ($this->is_paid) {
            $this->SetTextColor($this->green[0], $this->green[1], $this->green[2]); // Green for paid
            $status = 'PAID';
        } else {
            $this->SetTextColor($this->red[0], $this->red[1], $this->red[2]); // Red for pending
            $status = 'PENDING';
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(80, 8, $status, 0, 1);
        
        $this->SetXY(115, $startY + 19);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(80, 5, 'Receipt ID: ' . $this->payment['id'], 0, 1);
        
        $this->SetY($startY + 35);
        $this->Ln(5);
        
        // Amount (large blue text) - matching invoice
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->Cell(40, 8, 'Amount:', 0, 0);
        $this->Cell(0, 8, 'KSH ' . number_format($this->amount, 2), 0, 1);
        $this->Ln(5);
        
        // Payment Mode and Reference
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(45, 6, 'Payment Mode:', 0, 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(45, 6, $this->method, 0, 0);
        
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(40, 6, 'Reference No:', 0, 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, substr($this->reference, 0, 15), 0, 1);
        $this->Ln(8);
        
        // Amount in Words
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(45, 6, 'Amount in Words:', 0, 0);
        $this->SetFont('Arial', '', 11);
        $words = $this->numberToWords($this->amount);
        $this->MultiCell(0, 6, $words . ' Shillings only', 0, 'L');
        $this->Ln(5);
        
        // Payment For
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(40, 6, 'Payment For:', 0, 0);
        $this->SetFont('Arial', '', 11);
        $this->MultiCell(0, 6, $this->description . ' [INV-' . date('Y') . '-' . str_pad($this->payment['id'], 4, '0', STR_PAD_LEFT) . ']', 0, 'L');
        $this->Ln(8);
        
        // Invoice Reference Line with gray background - matching invoice style
        $this->SetFillColor($this->gray[0], $this->gray[1], $this->gray[2]);
        $this->SetFont('Arial', '', 10);
        
        // First row - Invoice Ref and Invoice Total
        $this->Cell(50, 8, 'Invoice Ref:', 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(45, 8, $this->invoice_ref, 0, 0);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(40, 8, 'Invoice Total:', 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'KSH ' . number_format($this->invoice_total, 2), 0, 1);
        
        // Second row - Total Paid and Balance Due
        $this->SetFont('Arial', '', 10);
        $this->Cell(50, 8, '', 0, 0); // Empty cell for alignment
        $this->Cell(45, 8, '', 0, 0);
        $this->Cell(40, 8, 'Total Paid:', 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'KSH ' . number_format($this->total_paid, 2), 0, 1);
        
        // Balance Due (in red if > 0)
        $this->SetFont('Arial', '', 10);
        $this->Cell(50, 8, '', 0, 0);
        $this->Cell(45, 8, '', 0, 0);
        $this->Cell(40, 8, 'Balance Due:', 0, 0);
        
        if ($this->balance_due > 0) {
            $this->SetTextColor(255, 0, 0); // Red for balance due
        } else {
            $this->SetTextColor($this->green[0], $this->green[1], $this->green[2]); // Green for zero balance
        }
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'KSH ' . number_format($this->balance_due, 2), 0, 1);
        
        $this->SetTextColor(0, 0, 0); // Reset text color
        $this->Ln(15);
        
        // Signature section - matching invoice
        $this->Signature();
        
        // Thank you message in blue - matching invoice
        $this->Ln(5);
        $this->SetFont('Arial', 'I', 11);
        $this->SetTextColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->Cell(0, 6, 'Thank you for your payment!', 0, 1, 'C');
    }
    
    function Signature()
    {
        $this->Ln(5);
        
        // Left side - digital stamp or payment notice
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(100, 100, 100);
        
        if ($this->is_paid) {
            $this->Cell(80, 5, 'Digitally Generated Receipt - PAID', 0, 0, 'L');
        } else {
            $this->Cell(80, 5, 'Digitally Generated Receipt - PENDING', 0, 0, 'L');
        }
        
        // Right side - signature area
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 5, '', 0, 0);
        
        // Check if signature image exists
        $signature_path = __DIR__ . '/../images/signature.png';
        if (file_exists($signature_path)) {
            // Add signature image
            $this->Image($signature_path, 150, $this->GetY() - 2, 40, 10);
            $this->Ln(12);
        } else {
            // Fallback to text signature line
            $this->Cell(0, 5, '_________________________________', 0, 1, 'R');
            $this->Ln(7);
        }
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(80, 6, '', 0, 0);
        $this->Cell(30, 6, '', 0, 0);
        $this->Cell(0, 6, 'Authorized Signature', 0, 1, 'R');
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(80, 4, '', 0, 0);
        $this->Cell(30, 4, '', 0, 0);
        $this->Cell(0, 4, 'For EduScore Systems Ltd', 0, 1, 'R');
        
        // Add stamp based on payment status
        $this->AddStatusStamp();
    }
    
    function AddStatusStamp()
    {
        // Position for the stamp (bottom left area)
        $stampX = 20;
        $stampY = $this->GetY() - 20;
        
        if ($this->is_paid) {
            // PAID stamp - Green circle with PAID text
            $this->SetDrawColor($this->green[0], $this->green[1], $this->green[2]);
            $this->SetFillColor(230, 255, 230); // Very light green fill
            $this->SetLineWidth(1.5);
            
            // Draw circle
            $this->Circle($stampX + 15, $stampY + 15, 15);
            
            // Add text inside stamp
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor($this->green[0], $this->green[1], $this->green[2]);
            
            // First line of stamp
            $this->SetXY($stampX + 5, $stampY + 8);
            $this->Cell(20, 5, 'PAID', 0, 1, 'C');
            
            // Second line of stamp
            $this->SetX($stampX + 5);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(20, 4, date('d M Y'), 0, 1, 'C');
        } else {
            // PENDING stamp - Orange circle with PENDING text
            $this->SetDrawColor($this->orange[0], $this->orange[1], $this->orange[2]);
            $this->SetFillColor(255, 245, 230); // Very light orange fill
            $this->SetLineWidth(1.5);
            
            // Draw circle
            $this->Circle($stampX + 15, $stampY + 15, 15);
            
            // Add text inside stamp
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor($this->orange[0], $this->orange[1], $this->orange[2]);
            
            // First line of stamp
            $this->SetXY($stampX + 2, $stampY + 6);
            $this->Cell(26, 5, 'PENDING', 0, 1, 'C');
            
            // Second line of stamp
            $this->SetX($stampX + 2);
            $this->SetFont('Arial', 'B', 7);
            $this->Cell(26, 4, 'NOT PAID', 0, 1, 'C');
            
            // Third line with date
            $this->SetX($stampX + 2);
            $this->SetFont('Arial', 'B', 6);
            $this->Cell(26, 4, date('d M Y'), 0, 1, 'C');
        }
        
        // Reset colors
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        
        $this->Ln(5);
    }
    
    // Helper function to convert numbers to words
    function numberToWords($number) {
        $words = array(
            0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 
            14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 
            18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty', 
            40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 
            80 => 'Eighty', 90 => 'Ninety', 100 => 'Hundred', 1000 => 'Thousand'
        );
        
        $number = floor($number);
        if ($number < 21) {
            return $words[$number];
        } elseif ($number < 100) {
            $tens = floor($number / 10) * 10;
            $units = $number % 10;
            if ($units > 0) {
                return $words[$tens] . ' ' . $words[$units];
            }
            return $words[$tens];
        } elseif ($number < 1000) {
            $hundreds = floor($number / 100);
            $remainder = $number % 100;
            if ($remainder > 0) {
                return $words[$hundreds] . ' Hundred and ' . $this->numberToWords($remainder);
            }
            return $words[$hundreds] . ' Hundred';
        } elseif ($number < 1000000) {
            $thousands = floor($number / 1000);
            $remainder = $number % 1000;
            if ($remainder > 0) {
                return $this->numberToWords($thousands) . ' Thousand ' . $this->numberToWords($remainder);
            }
            return $this->numberToWords($thousands) . ' Thousand';
        }
        return 'Number too large';
    }
    
    // Helper function to draw a circle
    function Circle($x, $y, $r)
    {
        $this->Ellipse($x, $y, $r, $r);
    }
    
    function Ellipse($x, $y, $rx, $ry)
    {
        $k = 0.552284749831;
        $xo = $rx * $k;
        $yo = $ry * $k;
        
        $this->_out(sprintf('%.2F %.2F m', ($x+$rx), $y));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$rx), ($y-$yo),
            ($x+$xo), ($y-$ry),
            $x, ($y-$ry)));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$xo), ($y-$ry),
            ($x-$rx), ($y-$yo),
            ($x-$rx), $y));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$rx), ($y+$yo),
            ($x-$xo), ($y+$ry),
            $x, ($y+$ry)));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$xo), ($y+$ry),
            ($x+$rx), ($y+$yo),
            ($x+$rx), $y));
        $this->_out('f');
    }
}

// Prepare data for PDF class
$pdf_data = [
    'receipt_no' => $receipt_no,
    'payment_date' => $payment_date,
    'school' => $payment,
    'payment' => $payment,
    'is_paid' => $is_paid,
    'description' => $description,
    'amount' => $amount,
    'reference' => $reference,
    'method' => $method,
    'trans_date' => $trans_date,
    'phone' => $phone,
    'invoice_ref' => $invoice_ref,
    'invoice_total' => $invoice_total,
    'total_paid' => $total_paid,
    'balance_due' => $balance_due,
    'is_onboarding_payment' => $is_onboarding_payment
];

// Create PDF with invoice styling
$pdf = new PDF_Receipt($pdf_data);
$pdf->AliasNbPages();
$pdf->AddPage();

// Generate receipt content
$pdf->ReceiptContent();

// Output PDF - 'I' sends inline to browser (for viewing in iframe)
$filename = 'Receipt_' . $receipt_no . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$pdf->Output('I', $filename);
exit;