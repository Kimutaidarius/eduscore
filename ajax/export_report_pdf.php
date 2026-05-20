<?php
session_start();
require_once '../config/config.php';
require_once '../vendor/autoload.php'; // For TCPDF or similar library

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$report_id = $_GET['report_id'] ?? null;

if (!$report_id) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

try {
    // Get report configuration
    $query = "SELECT rc.*, c.class_level as class_name, s.stream_name, e.examname, t.term_name,
                     t.academic_year, rc.report_year as year, sc.school_name, sc.school_address,
                     sc.school_logo, sc.school_motto
              FROM tblreportconfigurations rc
              LEFT JOIN tblclasses c ON rc.class_id = c.id
              LEFT JOIN tblstreams s ON rc.stream_id = s.id
              LEFT JOIN tblexam e ON rc.exam_id = e.id
              LEFT JOIN tblterms t ON rc.term_id = t.id
              LEFT JOIN tblschools sc ON rc.school_id = sc.id
              WHERE rc.id = :report_id AND rc.school_id = :school_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':report_id', $report_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $report_config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report_config) {
        throw new Exception('Report not found');
    }
    
    // Get report cards
    $cards_query = "SELECT rc.*, s.firstname, s.lastname, s.admission_number, s.gender,
                           s.date_of_birth, s.parent_phone, s.parent_email
                    FROM report_cards rc
                    INNER JOIN tblstudents s ON rc.student_id = s.id
                    WHERE rc.report_configuration_id = :report_id 
                    AND rc.school_id = :school_id
                    ORDER BY rc.overall_position ASC, s.firstname ASC";
    
    $stmt = $db->prepare($cards_query);
    $stmt->bindParam(':report_id', $report_id, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $report_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('EduScore System');
    $pdf->SetAuthor($_SESSION['school_name']);
    $pdf->SetTitle($report_config['report_title']);
    $pdf->SetSubject('Student Report Card');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    // Add a page for each student
    foreach ($report_cards as $index => $card) {
        $pdf->AddPage();
        
        // Get subject data for this card
        $subject_query = "SELECT rcs.* FROM report_card_subjects rcs 
                         WHERE rcs.report_card_id = :card_id
                         ORDER BY rcs.subject_type DESC, rcs.subject_name ASC";
        $stmt = $db->prepare($subject_query);
        $stmt->bindParam(':card_id', $card['id'], PDO::PARAM_INT);
        $stmt->execute();
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate PDF content for this student
        generateReportCardPDF($pdf, $report_config, $card, $subjects, $index + 1, count($report_cards));
    }
    
    // Output PDF
    $filename = 'report_cards_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D'); // 'D' for download
    
} catch (Exception $e) {
    error_log("PDF export failed: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "Failed to generate PDF: " . $e->getMessage();
}

function generateReportCardPDF($pdf, $config, $card, $subjects, $current, $total) {
    // School header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $config['school_name'], 0, 1, 'C');
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, $config['school_motto'], 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, $config['school_address'], 0, 1, 'C');
    
    $pdf->Ln(10);
    
    // Report title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, $config['report_title'], 0, 1, 'C');
    
    // Student information
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Ln(5);
    
    $info = [
        ['Student Name', $card['firstname'] . ' ' . $card['lastname']],
        ['Admission No.', $card['admission_number']],
        ['Class', $config['class_name'] . ($config['stream_name'] ? ' - ' . $config['stream_name'] : '')],
        ['Exam', $config['examname']],
        ['Term', $config['term_name'] . ' ' . $config['year']],
        ['Position', $card['overall_position'] . ' out of ' . $card['total_students_in_class']]
    ];
    
    foreach ($info as $item) {
        $pdf->Cell(50, 7, $item[0] . ':', 0, 0);
        $pdf->Cell(0, 7, $item[1], 0, 1);
    }
    
    $pdf->Ln(10);
    
    // Performance summary
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Performance Summary', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    
    $summary = [
        ['Total Marks', number_format($card['total_marks'], 2)],
        ['Mean Score', number_format($card['mean_score'], 2)],
        ['Mean Grade', $card['mean_grade']]
    ];
    
    $pdf->Cell(60, 8, $summary[0][0] . ':', 1, 0, 'C');
    $pdf->Cell(60, 8, $summary[1][0] . ':', 1, 0, 'C');
    $pdf->Cell(60, 8, $summary[2][0] . ':', 1, 1, 'C');
    
    $pdf->Cell(60, 8, $summary[0][1], 1, 0, 'C');
    $pdf->Cell(60, 8, $summary[1][1], 1, 0, 'C');
    $pdf->Cell(60, 8, $summary[2][1], 1, 1, 'C');
    
    $pdf->Ln(10);
    
    // Subjects table
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Subject Performance', 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(80, 8, 'Subject', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Type', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Score', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Grade', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    $total_score = 0;
    $total_subjects = count($subjects);
    
    foreach ($subjects as $subject) {
        $total_score += $subject['score'];
        $pdf->Cell(80, 8, $subject['subject_name'], 1, 0);
        $pdf->Cell(30, 8, $subject['subject_type'], 1, 0, 'C');
        $pdf->Cell(30, 8, number_format($subject['score'], 2), 1, 0, 'C');
        $pdf->Cell(30, 8, $subject['grade'], 1, 1, 'C');
    }
    
    // Comments section
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Comments', 0, 1);
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 8, 'Teacher: ' . ($card['teacher_comment'] ?: 'No comment provided'), 1, 'L');
    $pdf->Ln(5);
    $pdf->MultiCell(0, 8, 'Principal: ' . ($card['principal_comment'] ?: 'No comment provided'), 1, 'L');
    
    // Footer
    $pdf->SetY(-25);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y, g:i a'), 0, 0, 'C');
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'Page ' . $current . ' of ' . $total, 0, 0, 'C');
}
?>