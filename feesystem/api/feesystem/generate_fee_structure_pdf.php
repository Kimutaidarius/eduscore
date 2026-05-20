<?php
// /feesystem/api/feesystem/generate_fee_structure_pdf.php
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
$class_id = $data['class_id'] ?? '';
$year = $data['year'] ?? date('Y');
$term = $data['term'] ?? 1;

if (empty($class_id)) {
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit;
}

// Get school info
$database = Database::getInstance();
$db = $database->getConnection();

// Get school details
$school_query = "SELECT * FROM tblschoolinfo WHERE id = :school_id";
$school_stmt = $db->prepare($school_query);
$school_stmt->execute([':school_id' => $_SESSION['school_id']]);
$school_info = $school_stmt->fetch(PDO::FETCH_ASSOC);

// Get fee structure
$fee_query = "SELECT 
                fs.id,
                fs.class_level,
                fs.academic_year,
                fs.term,
                fs.vote_head_id,
                fs.amount,
                fs.term1,
                fs.term2,
                fs.term3,
                fs.is_optional,
                vh.name as vote_head_name,
                vh.alias as vote_head_alias
              FROM fee_structures fs
              LEFT JOIN vote_heads vh ON fs.vote_head_id = vh.id
              WHERE fs.school_id = :school_id 
                AND fs.class_level = :class_level
                AND fs.academic_year = :academic_year
                AND fs.status = 'active'
              ORDER BY vh.priority ASC";

$fee_stmt = $db->prepare($fee_query);
$fee_stmt->execute([
    ':school_id' => $_SESSION['school_id'],
    ':class_level' => $class_id,
    ':academic_year' => $year
]);
$fee_items = $fee_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($fee_items)) {
    echo json_encode(['success' => false, 'message' => 'No fee structure found for this class']);
    exit;
}

// Calculate total per term
$term_totals = [1 => 0, 2 => 0, 3 => 0];
foreach ($fee_items as $item) {
    $term_totals[1] += $item['term1'] > 0 ? $item['term1'] : $item['amount'];
    $term_totals[2] += $item['term2'] > 0 ? $item['term2'] : $item['amount'];
    $term_totals[3] += $item['term3'] > 0 ? $item['term3'] : $item['amount'];
}

// Custom PDF Class
class PDF extends FPDF
{
    public $school_info;
    public $className;
    public $year;
    public $term;

    function Header()
    {
        // Black and white theme - no colors
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
        
        // School Logo (Left) - Check multiple possible paths
        $logo_path = null;
        
        // Try school_logo field first
        if (!empty($this->school_info['school_logo'])) {
            $logo_path = '../../../' . ltrim($this->school_info['school_logo'], './');
            if (!file_exists($logo_path)) {
                $logo_path = null;
            }
        }
        
        // Try school_logo_url field
        if (!$logo_path && !empty($this->school_info['school_logo_url'])) {
            $logo_path = '../../../' . ltrim($this->school_info['school_logo_url'], './');
            if (!file_exists($logo_path)) {
                $logo_path = null;
            }
        }
        
        // Try default logo
        if (!$logo_path) {
            $default_logo = '../../../uploads/logos/default.png';
            if (file_exists($default_logo)) {
                $logo_path = $default_logo;
            }
        }
        
        // Display logo if found
        if ($logo_path && file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 25, 25);
        } else {
            // Fallback: simple text placeholder
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
        $this->Cell(0, 7, 'FEE STRUCTURE', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $term_text = $this->term ? "Term " . $this->term : "All Terms";
        $this->Cell(0, 5, $this->className . ' - ' . $this->year . ' | ' . $term_text, 0, 1, 'C');
        
        // Separator Line (Black only)
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
}

// Create PDF
$pdf = new PDF();
$pdf->school_info = $school_info;
$pdf->className = $class_id;
$pdf->year = $year;
$pdf->term = $term;
$pdf->AddPage();

// Set font for content
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);

// Fee Structure Table
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'FEE ITEMS BREAKDOWN', 0, 1, 'L');
$pdf->Ln(2);

// Table Header
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(60, 8, 'Vote Head', 1, 0, 'L');
$pdf->Cell(50, 8, 'Alias', 1, 0, 'L');
$pdf->Cell(35, 8, 'Amount (KES)', 1, 0, 'R');
$pdf->Cell(35, 8, 'Type', 1, 1, 'L');

// Table Body
$pdf->SetFont('Arial', '', 9);
$total = 0;

foreach ($fee_items as $item) {
    // Get amount based on selected term
    if ($term == 1 && $item['term1'] > 0) {
        $amount = $item['term1'];
    } elseif ($term == 2 && $item['term2'] > 0) {
        $amount = $item['term2'];
    } elseif ($term == 3 && $item['term3'] > 0) {
        $amount = $item['term3'];
    } else {
        $amount = $item['amount'];
    }
    
    $total += $amount;
    
    $pdf->Cell(60, 7, substr($item['vote_head_name'], 0, 40), 1, 0, 'L');
    $pdf->Cell(50, 7, $item['vote_head_alias'], 1, 0, 'L');
    $pdf->Cell(35, 7, number_format($amount, 2), 1, 0, 'R');
    $pdf->Cell(35, 7, $item['is_optional'] ? 'Optional' : 'Compulsory', 1, 1, 'L');
}

// Total Row
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(110, 8, 'TOTAL', 1, 0, 'R');
$pdf->Cell(35, 8, number_format($total, 2), 1, 0, 'R');
$pdf->Cell(35, 8, '', 1, 1, 'L');

$pdf->Ln(5);

// Summary by Term
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'SUMMARY BY TERM', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(80, 8, 'Term', 1, 0, 'L');
$pdf->Cell(80, 8, 'Total Amount (KES)', 1, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(80, 7, 'Term 1', 1, 0, 'L');
$pdf->Cell(80, 7, number_format($term_totals[1], 2), 1, 1, 'R');
$pdf->Cell(80, 7, 'Term 2', 1, 0, 'L');
$pdf->Cell(80, 7, number_format($term_totals[2], 2), 1, 1, 'R');
$pdf->Cell(80, 7, 'Term 3', 1, 0, 'L');
$pdf->Cell(80, 7, number_format($term_totals[3], 2), 1, 1, 'R');

// Annual Total
$pdf->SetFont('Arial', 'B', 9);
$annual_total = $term_totals[1] + $term_totals[2] + $term_totals[3];
$pdf->Cell(80, 7, 'ANNUAL TOTAL', 1, 0, 'L');
$pdf->Cell(80, 7, number_format($annual_total, 2), 1, 1, 'R');

$pdf->Ln(10);

// Footer Notes
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, 'Note: This fee structure is subject to change. Please contact the finance office for any queries.', 0, 1, 'C');
$pdf->Cell(0, 5, 'Payment deadlines and penalties apply as per school policy.', 0, 1, 'C');

// Output PDF
$pdf_filename = 'fee_structure_' . $class_id . '_' . $year . '.pdf';
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
    'filename' => $pdf_filename,
    'message' => 'PDF generated successfully'
]);
?>