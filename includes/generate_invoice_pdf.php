<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use ReportLab\Platypus\SimpleDocTemplate;
use ReportLab\Platypus\Paragraph;
use ReportLab\Lib\Styles;

$invoice_id = (int)($_GET['invoice_id'] ?? 0);

$stmt = $dbh->prepare("
    SELECT i.*, s.school_name, s.school_email
    FROM tbl_invoices i
    JOIN tblschoolinfo s ON s.id = i.school_id
    WHERE i.id = :id
");
$stmt->execute([':id' => $invoice_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) die('Invalid invoice');

$file = __DIR__ . "/../storage/invoices/invoice_{$inv['id']}.pdf";

$doc = new SimpleDocTemplate($file);
$styles = Styles::getSampleStyleSheet();
$content = [];

$content[] = new Paragraph("<b>EDUSCORE SYSTEM</b>", $styles['Title']);
$content[] = new Paragraph("Invoice #: {$inv['id']}", $styles['Normal']);
$content[] = new Paragraph("School: {$inv['school_name']}", $styles['Normal']);
$content[] = new Paragraph("Email: {$inv['school_email']}", $styles['Normal']);
$content[] = new Paragraph("Amount: KSh " . number_format($inv['amount']), $styles['Normal']);
$content[] = new Paragraph("Status: {$inv['status']}", $styles['Normal']);
$content[] = new Paragraph("Date: {$inv['created_at']}", $styles['Normal']);

$doc->build($content);

header('Content-Type: application/pdf');
readfile($file);
exit;
