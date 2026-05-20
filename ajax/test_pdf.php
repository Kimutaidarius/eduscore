<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    die('Unauthorized access');
}

// Try to load FPDF
$fpdf_loaded = false;
$fpdf_paths = [
    '../assets/fpdf/fpdf.php',
    '../fpdf/fpdf.php',
    '../vendor/setasign/fpdf/fpdf.php'
];

foreach ($fpdf_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $fpdf_loaded = true;
        echo "FPDF loaded from: $path\n";
        break;
    }
}

if (!$fpdf_loaded) {
    die('FPDF library not found. Checked paths: ' . implode(', ', $fpdf_paths));
}

// Create a simple PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(40, 10, 'Test PDF Working!');
$pdf->Output('D', 'test.pdf');
exit;