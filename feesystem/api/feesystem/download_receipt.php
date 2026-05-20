<?php
/**
 * PDF Receipt Generator for Other Income
 * Endpoint: /feesystem/api/feesystem/download_receipt.php?id={receipt_id}
 * Method: GET
 */

session_start();
require_once('../../includes/config.php');

// Verify authentication
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../../login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid receipt ID');
}

$receipt_id = intval($_GET['id']);
$school_id = $_SESSION['school_id'];

// Get receipt data
$sql = "SELECT r.*, u.username as created_by_name 
        FROM other_income_receipts r
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.id = ? AND r.school_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $receipt_id, $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    die('Receipt not found');
}

$receipt = mysqli_fetch_assoc($result);

// Get receipt items
$items_sql = "SELECT ri.*, vh.name as vote_head_name, vh.alias 
              FROM other_income_receipt_items ri
              LEFT JOIN vote_heads vh ON ri.vote_head_id = vh.id
              WHERE ri.receipt_id = ?";
$items_stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($items_stmt, 'i', $receipt_id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

$items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $items[] = $item;
}

// Get school info
$school_sql = "SELECT school_name, school_address, school_phone, school_email, principal_name, school_logo 
               FROM tblschoolinfo WHERE id = ?";
$school_stmt = mysqli_prepare($conn, $school_sql);
mysqli_stmt_bind_param($school_stmt, 'i', $school_id);
mysqli_stmt_execute($school_stmt);
$school_result = mysqli_stmt_get_result($school_stmt);
$school_info = mysqli_fetch_assoc($school_result);

// Load HTML2PDF library (you need to install this via composer or include manually)
// For this example, we'll generate HTML that can be printed to PDF
require_once('../../vendor/autoload.php'); // Adjust path as needed

use Spipu\Html2Pdf\Html2Pdf;

$html = generateReceiptHTML($receipt, $items, $school_info);

$html2pdf = new Html2Pdf('P', 'A4', 'en', true, 'UTF-8', array(10, 10, 10, 10));
$html2pdf->writeHTML($html);
$html2pdf->output("Receipt_{$receipt['receipt_number']}.pdf", 'D');

function generateReceiptHTML($receipt, $items, $school_info) {
    $logo_html = '';
    if (!empty($school_info['school_logo']) && file_exists('../../' . $school_info['school_logo'])) {
        $logo_html = '<img src="../../' . $school_info['school_logo'] . '" style="max-height: 80px; max-width: 150px;" alt="School Logo">';
    }
    
    $school_name = htmlspecialchars($school_info['school_name'] ?? 'School Name');
    $school_address = htmlspecialchars($school_info['school_address'] ?? '');
    $school_phone = htmlspecialchars($school_info['school_phone'] ?? '');
    $principal_name = htmlspecialchars($school_info['principal_name'] ?? '');
    
    $status_class = $receipt['status'] === 'void' ? 'color: #dc3545;' : 'color: #28a745;';
    $status_text = $receipt['status'] === 'void' ? 'VOIDED' : 'OFFICIAL RECEIPT';
    
    $items_html = '';
    foreach ($items as $item) {
        $vote_head = htmlspecialchars($item['vote_head_name'] ?? 'N/A');
        $description = htmlspecialchars($item['description']);
        $amount = number_format($item['amount'], 2);
        $items_html .= "
            <tr>
                <td style=\"padding: 8px; border: 1px solid #ddd;\">{$vote_head}</td>
                <td style=\"padding: 8px; border: 1px solid #ddd;\">{$description}</td>
                <td style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\">KES {$amount}</td>
            </tr>
        ";
    }
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - {$receipt['receipt_number']}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .receipt-title { font-size: 24px; font-weight: bold; margin: 10px 0; }
        .receipt-status { font-size: 18px; font-weight: bold; {$status_class} }
        .school-name { font-size: 18px; font-weight: bold; color: #1e3a8a; }
        .school-details { font-size: 12px; color: #666; margin-top: 5px; }
        .receipt-details { margin: 20px 0; }
        .details-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .details-table td { padding: 5px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th { background-color: #f3f4f6; padding: 10px; border: 1px solid #ddd; text-align: left; }
        .items-table td { padding: 8px; border: 1px solid #ddd; }
        .total-section { margin-top: 20px; text-align: right; }
        .total-row { font-size: 16px; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
        .signature { margin-top: 30px; display: flex; justify-content: space-between; }
        .signature-line { width: 200px; border-top: 1px solid #000; margin-top: 30px; text-align: center; font-size: 12px; }
        .void-stamp { 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%) rotate(-45deg); 
            font-size: 72px; 
            font-weight: bold; 
            color: rgba(220, 53, 69, 0.3); 
            pointer-events: none;
            z-index: 1000;
        }
        .receipt-container { position: relative; }
    </style>
</head>
<body>
<div class="receipt-container">
    {$logo_html}
    <div class="header">
        <div class="school-name">{$school_name}</div>
        <div class="school-details">{$school_address}</div>
        <div class="school-details">Tel: {$school_phone}</div>
        <div class="receipt-title">{$status_text}</div>
        <div class="receipt-status">Receipt No: {$receipt['receipt_number']}</div>
    </div>
    
    <div class="receipt-details">
        <table class="details-table">
            <tr>
                <td width="50%"><strong>Received From:</strong> {$receipt['payer_name']}</td>
                <td width="50%"><strong>Date:</strong> {$receipt['payment_date']}</td>
            </tr>
            <tr>
                <td><strong>Payer Type:</strong> " . ucfirst({$receipt['payer_type']}) . "</td>
                <td><strong>Payment Mode:</strong> " . ucfirst({$receipt['payment_mode']}) . "</td>
            </tr>
            " . ($receipt['payment_code'] ? "<tr><td><strong>Transaction ID:</strong> {$receipt['payment_code']}</td><td></td></tr>" : "") . "
        </table>
    </div>
    
    <table class="items-table">
        <thead>
            <tr>
                <th>Vote Head</th>
                <th>Particulars</th>
                <th style=\"text-align: right;\">Amount (KES)</th>
            </tr>
        </thead>
        <tbody>
            {$items_html}
        </tbody>
        <tfoot>
            <tr style=\"background-color: #fef2f2;\">
                <td colspan=\"2\" style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\"><strong>Subtotal:</strong></td>
                <td style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\">KES " . number_format($receipt['subtotal'], 2) . "</td>
            </tr>
            <tr style=\"background-color: #fef2f2;\">
                <td colspan=\"2\" style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\"><strong>VAT (16%):</strong></td>
                <td style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\">KES " . number_format($receipt['tax_amount'], 2) . "</td>
            </tr>
            <tr style=\"background-color: #e0e7ff;\">
                <td colspan=\"2\" style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\"><strong>TOTAL:</strong></td>
                <td style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\"><strong>KES " . number_format($receipt['total_amount'], 2) . "</strong></td>
            </tr>
        </tfoot>
    </table>
    
    " . ($receipt['notes'] ? "<div style=\"margin: 15px 0; padding: 10px; background-color: #f9fafb; border-left: 3px solid #10b981;\"><strong>Notes:</strong> " . htmlspecialchars($receipt['notes']) . "</div>" : "") . "
    
    <div class="signature">
        <div class="signature-line">
            <div>_________________</div>
            <div>Finance Officer</div>
        </div>
        <div class="signature-line">
            <div>_________________</div>
            <div>Principal / Head of School</div>
        </div>
    </div>
    
    <div class="footer">
        <p>This is a computer generated receipt - No signature required</p>
        <p>Generated on: " . date('Y-m-d H:i:s') . "</p>
        " . ($receipt['status'] === 'void' ? "<p style=\"color: #dc3545;\"><strong>*** RECEIPT VOIDED ***</strong></p>" : "") . "
    </div>
    
    " . ($receipt['status'] === 'void' ? '<div class="void-stamp">VOID</div>' : "") . "
</div>
</body>
</html>
HTML;
}
?>