<?php
/**
 * generate_invoice.php - Enhanced version with professional layout
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
    
    // Get onboarding fee
    $stmt = $dbh->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'onboarding_fee'");
    $stmt->execute();
    $onboarding_fee = $stmt->fetchColumn() ?: 2000;
    
    // Generate invoice number
    $invoice_no = 'INV-' . date('Y') . '-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT);
    $is_paid = in_array(strtolower($payment['status'] ?? ''), ['completed', 'paid', 'success']);
    
    // Determine payment type
    $amount = floatval($payment['amount']);
    $description = 'Subscription Payment';
    if ($amount == $onboarding_fee) {
        $description = 'EDUSCORE ANALYSIS-One-time Onboarding Fee';
    } elseif ($amount > 0) {
        $description = 'Term Subscription Fee';
    }
    
    // Format date
    $payment_date = date('d M Y', strtotime($payment['created_at'] ?? 'now'));
    
    // Get reference
    $reference = $payment['reference'] ?? $payment['transaction_id'] ?? $payment['mpesa_receipt'] ?? 'N/A';
    
    // Payment method
    $method = isset($payment['reference']) ? 'M-PESA STK Push' : 'M-PESA Buy Goods';
    
    // Payment date
    $trans_date = date('d M Y', strtotime($payment['paid_at'] ?? $payment['created_at'] ?? 'now'));
    
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

// ============ CREATE PDF WITH ENHANCED LAYOUT ============
class PDF_Invoice extends FPDF
{
    // Colors
    protected $blue = [41, 128, 185];      // #2980b9 - Dark Blue
    protected $light_blue = [52, 152, 219]; // #3498db - Light Blue
    protected $yellow = [241, 196, 15];     // #f1c40f - Yellow
    protected $light_yellow = [254, 249, 207]; // #fef9cf - Light Yellow
    protected $gray = [236, 240, 241];      // #ecf0f1 - Light Gray
    protected $red = [231, 76, 60];         // #e74c3c - Red for stamp
    protected $orange = [243, 156, 18];     // #f39c12 - Orange for pending
    
    // Data properties
    protected $invoice_no;
    protected $payment_date;
    protected $school;
    protected $payment;
    protected $is_paid;
    protected $description;
    protected $amount;
    protected $reference;
    protected $method;
    protected $trans_date;
    
    function __construct($data) {
        parent::__construct();
        $this->invoice_no = $data['invoice_no'];
        $this->payment_date = $data['payment_date'];
        $this->school = $data['school'];
        $this->payment = $data['payment'];
        $this->is_paid = $data['is_paid'];
        $this->description = $data['description'];
        $this->amount = $data['amount'];
        $this->reference = $data['reference'];
        $this->method = $data['method'];
        $this->trans_date = $data['trans_date'];
    }
    
    function Header()
    {
        // Top colored bar (yellow)
        $this->SetFillColor($this->yellow[0], $this->yellow[1], $this->yellow[2]);
        $this->Rect(0, 0, 210, 15, 'F');
        
        // Logo area (blue bar)
        $this->SetFillColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->Rect(0, 15, 70, 25, 'F');
        
        // Company name (white on blue)
        $this->SetXY(5, 20);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(60, 10, 'EDUSCORE', 0, 1, 'C');
        
        // Tagline
        $this->SetFont('Arial', '', 8);
        $this->SetXY(5, 30);
        $this->Cell(60, 5, 'School Management System', 0, 1, 'C');
        
        // Invoice title (on white background)
        $this->SetTextColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->SetFont('Arial', 'B', 24);
        $this->SetXY(80, 22);
        $this->Cell(0, 10, 'INVOICE', 0, 1);
        
        // Invoice number and date in light yellow box
        $this->SetFillColor($this->light_yellow[0], $this->light_yellow[1], $this->light_yellow[2]);
        $this->SetXY(140, 20);
        $this->Cell(60, 20, '', 0, 1, 'C', true);
        
        $this->SetXY(145, 23);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(50, 4, 'INVOICE NO:', 0, 1);
        
        $this->SetXY(145, 27);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->Cell(50, 4, $this->invoice_no, 0, 1);
        
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
        // Position at 1.5 cm from bottom
        $this->SetY(-25);
        
        // Footer line (blue)
        $this->SetDrawColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        $this->SetY(-20);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        
        // Left side - company info
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
    
    function CompanyInfo()
    {
        // Bill To section
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->Cell(0, 8, 'BILL TO:', 0, 1);
        
        // Store current Y position
        $startY = $this->GetY();
        
        // Light gray background for company details
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
        $this->Cell(80, 5, 'Phone: ' . ($this->school['school_phone'] ?? 'N/A'), 0, 1);
        
        $this->SetX(15);
        $this->Cell(80, 5, 'Email: ' . ($this->school['school_email'] ?? 'N/A'), 0, 1);
        
        $this->SetX(15);
        if (!empty($this->school['county'])) {
            $this->Cell(80, 5, 'County: ' . $this->school['county'], 0, 1);
        }
        
        // Payment status in right box
        $this->SetXY(110, $startY);
        $this->SetFillColor($this->light_yellow[0], $this->light_yellow[1], $this->light_yellow[2]);
        $this->Cell(90, 30, '', 0, 0, 'L', true);
        
        $this->SetXY(115, $startY + 5);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(80, 6, 'PAYMENT STATUS:', 0, 1);
        
        $this->SetXY(115, $startY + 11);
        $status = ucfirst($this->payment['status'] ?? 'Pending');
        if ($this->is_paid) {
            $this->SetTextColor(46, 204, 113); // Green
            $status = 'PAID';
        } else {
            $this->SetTextColor(231, 76, 60); // Red
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(80, 8, $status, 0, 1);
        
        $this->SetXY(115, $startY + 19);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        
        $this->SetY($startY + 35);
    }
    
    function InvoiceItems()
    {
        // Table header with blue background
        $this->SetFillColor($this->blue[0], $this->blue[1], $this->blue[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        
        $this->Cell(90, 8, 'Description', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Quantity', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Unit Price', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Total', 1, 1, 'C', true);
        
        // Table content
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 10);
        
        // Item row
        $this->Cell(90, 8, $this->description, 1);
        $this->Cell(30, 8, '1', 1, 0, 'C');
        $this->Cell(35, 8, 'KES ' . number_format($this->amount, 2), 1, 0, 'R');
        $this->Cell(35, 8, 'KES ' . number_format($this->amount, 2), 1, 1, 'R');
        
        // Empty rows for style (like in the image)
        $this->SetFillColor($this->gray[0], $this->gray[1], $this->gray[2]);
        for ($i = 0; $i < 3; $i++) {
            $fill = ($i % 2 == 0);
            $this->Cell(90, 8, '', 1, 0, 'L', $fill);
            $this->Cell(30, 8, '', 1, 0, 'C', $fill);
            $this->Cell(35, 8, '', 1, 0, 'R', $fill);
            $this->Cell(35, 8, '', 1, 1, 'R', $fill);
        }
        
        $this->Ln(5);
    }
    
    function Totals()
    {
        // Subtotal
        $this->SetFont('Arial', '', 10);
        $this->SetX(120);
        $this->Cell(40, 6, 'Sub Total:', 0, 0, 'R');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(35, 6, 'KES ' . number_format($this->amount, 2), 0, 1, 'R');
        
        // Tax (0% for now)
        $this->SetFont('Arial', '', 10);
        $this->SetX(120);
        $this->Cell(40, 6, 'Tax (0%):', 0, 0, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell(35, 6, 'KES 0.00', 0, 1, 'R');
        
        // Total Due in yellow box
        $this->SetFillColor($this->yellow[0], $this->yellow[1], $this->yellow[2]);
        $this->SetX(120);
        $this->Cell(40, 8, 'TOTAL DUE:', 0, 0, 'R');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(35, 8, 'KES ' . number_format($this->amount, 2), 0, 1, 'R', true);
        
        $this->Ln(10);
    }
    
    function PaymentInfo()
    {
        // Payment Info and Thank You section
        $this->SetFillColor($this->light_blue[0], $this->light_blue[1], $this->light_blue[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(95, 7, 'PAYMENT INFORMATION', 1, 0, 'C', true);
        $this->Cell(95, 7, 'THANK YOU FOR YOUR BUSINESS!', 1, 1, 'C', true);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 9);
        
        // Store current Y position
        $startY = $this->GetY();
        
        // Left column - Payment details
        $this->SetFillColor($this->gray[0], $this->gray[1], $this->gray[2]);
        $this->Cell(95, 20, '', 1, 0, 'L', true);
        
        $this->SetXY(15, $startY + 2);
        $this->Cell(50, 5, 'Reference:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(40, 5, substr($this->reference, 0, 20), 0, 1);
        
        $this->SetX(15);
        $this->SetFont('Arial', '', 9);
        $this->Cell(50, 5, 'Payment Date:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(40, 5, $this->trans_date, 0, 1);
        
        $this->SetX(15);
        $this->SetFont('Arial', '', 9);
        $this->Cell(50, 5, 'Payment Method:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(40, 5, $this->method, 0, 1);
        
        // Right column - Thank you
        $this->SetXY(115, $startY);
        $this->SetFillColor(255, 255, 255);
        $this->Cell(85, 20, '', 1, 1, 'L', true);
        
        $this->SetXY(120, $startY + 4);
        $this->SetFont('Arial', 'I', 9);
        $this->MultiCell(75, 4, 'We appreciate your prompt payment. For any queries, please contact our support team.', 0, 'L');
        
        $this->SetY($startY + 25);
    }
    
function Signature()
{
    $this->Ln(5);
    
    // Left side - digital stamp or payment notice
    $this->SetFont('Arial', 'B', 8);
    $this->SetTextColor(100, 100, 100);
    
    if ($this->is_paid) {
        $this->Cell(80, 5, 'Digitally Generated Invoice - PAID', 0, 0, 'L');
    } else {
        $this->Cell(80, 5, 'Digitally Generated Invoice - PENDING', 0, 0, 'L');
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
            // PAID stamp - Red circle with PAID text
            $this->SetDrawColor($this->red[0], $this->red[1], $this->red[2]);
            $this->SetFillColor(255, 240, 240); // Very light red fill
            $this->SetLineWidth(1.5);
            
            // Draw circle
            $this->Circle($stampX + 15, $stampY + 15, 15);
            
            // Add text inside stamp
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor($this->red[0], $this->red[1], $this->red[2]);
            
            // First line of stamp
            $this->SetXY($stampX + 5, $stampY + 8);
            $this->Cell(20, 5, 'PAID', 0, 1, 'C');
            
            // Second line of stamp
            $this->SetX($stampX + 5);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(20, 4, date('d M Y'), 0, 1, 'C');
        } else {
            // UNPAID stamp - Orange circle with PENDING text
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
    
    function UnpaidWatermark()
    {
        if (!$this->is_paid) {
            $this->SetFont('Arial', 'B', 50);
            $this->SetTextColor(255, 220, 220);
            $this->RotatedText(50, 150, 'UNPAID', 45);
        }
    }
    
    function RotatedText($x, $y, $txt, $angle)
    {
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }
    
    var $angle = 0;
    
    function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1)
            $x = $this->x;
        if ($y == -1)
            $y = $this->y;
        if ($this->angle != 0)
            $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy));
        }
    }
}

// Prepare data for PDF class
$pdf_data = [
    'invoice_no' => $invoice_no,
    'payment_date' => $payment_date,
    'school' => $payment,
    'payment' => $payment,
    'is_paid' => $is_paid,
    'description' => $description,
    'amount' => $amount,
    'reference' => $reference,
    'method' => $method,
    'trans_date' => $trans_date
];

// Create PDF with enhanced layout
$pdf = new PDF_Invoice($pdf_data);
$pdf->AliasNbPages();
$pdf->AddPage();

// Add watermark for unpaid invoices
$pdf->UnpaidWatermark();

// Company/Bill To information
$pdf->CompanyInfo();

// Invoice items
$pdf->InvoiceItems();

// Totals
$pdf->Totals();

// Payment information and thank you note
$pdf->PaymentInfo();

// Signature and stamp
$pdf->Signature();

// Output PDF - 'I' sends inline to browser (for viewing in iframe)
$filename = 'Invoice_' . $invoice_no . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$pdf->Output('I', $filename);
exit;