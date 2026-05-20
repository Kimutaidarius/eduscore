<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use ReportLab\Platypus\SimpleDocTemplate;
use ReportLab\Platypus\Paragraph;
use ReportLab\Lib\Styles;

$receipt_id = (int)($_GET['receipt_id'] ?? 0);

$stmt = $dbh->prepare("
    SELECT r.*, i.amount, s.school_name
    FROM tbl_receipts r
    JOIN tbl_invoices i ON i.id = r.invoice_id
    JOIN tblschoolinfo s ON s.id = i.school_id
    WHERE r.id = :id
");
$stmt->execute([':id' => $receipt_id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) die('Invalid receipt');

$file = __DIR__ . "/../storage/receipts/receipt_{$r['id']}.pdf";

$doc = new SimpleDocTemplate($file);
$styles = Styles::getSampleStyleSheet();
$content = [];

$content[] = new Paragraph("<b>PAYMENT RECEIPT</b>", $styles['Title']);
$content[] = new Paragraph("School: {$r['school_name']}", $styles['Normal']);
$content[] = new Paragraph("Amount Paid: KSh " . number_format($r['amount']), $styles['Normal']);
$content[] = new Paragraph("M-Pesa Ref: {$r['receipt_no']}", $styles['Normal']);
$content[] = new Paragraph("Paid At: {$r['created_at']}", $styles['Normal']);

$doc->build($content);

header('Content-Type: application/pdf');
readfile($file);
exit;
