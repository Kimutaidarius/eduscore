<?php
// ajax/export_students_pdf.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';

// Include FPDF library
require_once '../assets/fpdf/fpdf.php';

// Check authentication
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$school_id = (int) $_SESSION['school_id'];
$teacher_id = (int) $_SESSION['teacher_id'];

// Get school info including logo
$stmt = $db->prepare("SELECT school_name, school_address, school_phone, school_logo FROM tblschoolinfo WHERE id = :school_id");
$stmt->execute([':school_id' => $school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

// Get filter parameters
$class_id = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
$stream_id = isset($_GET['stream_id']) && $_GET['stream_id'] !== '' ? (int)$_GET['stream_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query to fetch students
$sql = "
    SELECT 
        s.id,
        CONCAT(s.FirstName, ' ', s.SecondName, ' ', s.LastName) as full_name,
        s.FirstName,
        s.LastName,
        s.AdmNo,
        s.Gender,
        s.GuardianName,
        s.GuardianPhone,
        s.admission_date,
        c.class_level,
        st.stream_name
    FROM tblstudents s
    LEFT JOIN tblclasses c ON c.id = s.class_id
    LEFT JOIN tblstreams st ON st.id = s.StreamId
    WHERE s.school_id = :school_id
    AND s.Status = 'Active'
";

$params = [':school_id' => $school_id];

if ($class_id) {
    $sql .= " AND s.class_id = :class_id";
    $params[':class_id'] = $class_id;
}

if ($stream_id) {
    $sql .= " AND s.StreamId = :stream_id";
    $params[':stream_id'] = $stream_id;
}

if ($search !== '') {
    $sql .= " AND (
        s.FirstName LIKE :search OR
        s.LastName LIKE :search OR
        s.AdmNo LIKE :search OR
        CONCAT(s.FirstName, ' ', s.LastName) LIKE :search
    )";
    $params[':search'] = "%{$search}%";
}

$sql .= " ORDER BY c.class_level, st.stream_name, s.FirstName, s.LastName";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No students found']);
        exit;
    }
    
    // Get class and stream info for report title
    $class_info = '';
    $stream_info = '';
    
    if ($class_id) {
        $stmt = $db->prepare("SELECT class_level, academic_level FROM tblclasses WHERE id = :class_id");
        $stmt->execute([':class_id' => $class_id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        $class_info = $class ? $class['class_level'] : '';
    }
    
    if ($stream_id) {
        $stmt = $db->prepare("SELECT stream_name FROM tblstreams WHERE id = :stream_id");
        $stmt->execute([':stream_id' => $stream_id]);
        $stream = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($stream) {
            $stream_info = $stream['stream_name'];
        }
    }
    
    // Generate PDF
    generateStudentListPDF($students, $school, $class_info, $stream_info);
    
} catch (PDOException $e) {
    error_log("PDF Generation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function generateStudentListPDF($students, $school, $class_info, $stream_info) {
    // Create PDF instance
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape orientation
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    
    // Set colors
    $headerColor = array(30, 58, 138); // Primary blue
    $accentColor = array(251, 191, 36); // Accent yellow
    $tableHeaderColor = array(59, 130, 246); // Lighter blue
    $blackText = array(0, 0, 0); // Black text for table
    
    // Calculate page width for centering
    $pageWidth = 297; // A4 landscape width in mm
    $leftMargin = 15;
    $rightMargin = 15;
    $contentWidth = $pageWidth - $leftMargin - $rightMargin;
    
    // School Logo - if exists
    $logoPath = '../uploads/schools/' . $school['school_logo'];
    $logoWidth = 30;
    $logoHeight = 30;
    
    // Centered Header with logo and school info
    if (file_exists($logoPath) && !empty($school['school_logo'])) {
        $logoX = ($pageWidth - $logoWidth) / 2;
        $pdf->Image($logoPath, $logoX, 10, $logoWidth);
        $pdf->Ln($logoHeight + 5);
    } else {
        $pdf->Ln(10);
    }
    
    // School name - centered
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->Cell(0, 10, $school['school_name'], 0, 1, 'C');
    
    // School address and contact - centered
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor($blackText[0], $blackText[1], $blackText[2]);
    
    if (!empty($school['school_address'])) {
        $pdf->Cell(0, 5, $school['school_address'], 0, 1, 'C');
    }
    
    if (!empty($school['school_phone'])) {
        $pdf->Cell(0, 5, 'Tel: ' . $school['school_phone'], 0, 1, 'C');
    }
    
    $pdf->Ln(10);
    
    // Report Title - centered
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->Cell(0, 10, 'STUDENT LIST REPORT', 0, 1, 'C');
    
    // Class and stream info - centered
    if (!empty($class_info) || !empty($stream_info)) {
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
        
        $classStreamInfo = '';
        if (!empty($class_info)) {
            $classStreamInfo = 'Class: ' . $class_info;
        }
        if (!empty($stream_info)) {
            if (!empty($classStreamInfo)) {
                $classStreamInfo .= '  |  ';
            }
            $classStreamInfo .= 'Stream: ' . $stream_info;
        }
        
        $pdf->Cell(0, 8, $classStreamInfo, 0, 1, 'C');
    }
    
    $pdf->Ln(15);
    
    // Calculate table position for centering
    $col_widths = [15, 25, 120, 30]; // #, Adm No, Student Name, Gender
    $tableWidth = array_sum($col_widths);
    $tableStartX = ($pageWidth - $tableWidth) / 2;
    
    // Set X position for table
    $pdf->SetX($tableStartX);
    
    // Table Header
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor($tableHeaderColor[0], $tableHeaderColor[1], $tableHeaderColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    
    $headers = ['#', 'Adm No', 'Student Name', 'Gender'];
    
    // Print headers with borders
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($col_widths[$i], 10, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Table Data - white background with black text
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor($blackText[0], $blackText[1], $blackText[2]);
    
    $counter = 1;
    $totalStudents = count($students);
    
    foreach ($students as $student) {
        // Set X position for each row (keep centered)
        $pdf->SetX($tableStartX);
        
        // White background for all rows
        $pdf->SetFillColor(255, 255, 255); // White background
        
        // Student Number
        $pdf->Cell($col_widths[0], 8, $counter, 1, 0, 'C', true);
        
        // Admission Number
        $pdf->Cell($col_widths[1], 8, $student['AdmNo'], 1, 0, 'C', true);
        
        // Student Name (full name)
        $pdf->Cell($col_widths[2], 8, $student['full_name'], 1, 0, 'L', true);
        
        // Gender
        $pdf->Cell($col_widths[3], 8, $student['Gender'], 1, 1, 'C', true);
        
        $counter++;
    }
    
    // Center the summary text
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->Cell(0, 8, 'Total Students: ' . $totalStudents, 0, 1, 'C');
    
    // Footer - centered
    $pdf->SetY(-25);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    
    // Centered footer text
    $footerText = 'Generated on: ' . date('F j, Y g:i A');
    $footerWidth = $pdf->GetStringWidth($footerText);
    $footerX = ($pageWidth - $footerWidth) / 2;
    $pdf->SetX($footerX);
    $pdf->Cell($footerWidth, 6, $footerText, 0, 1, 'C');
    
    // Page number - centered
    $pageText = 'Page ' . $pdf->PageNo();
    $pageWidthText = $pdf->GetStringWidth($pageText);
    $pageX = ($pageWidth - $pageWidthText) / 2;
    $pdf->SetX($pageX);
    $pdf->Cell($pageWidthText, 6, $pageText, 0, 0, 'C');
    
    // Output PDF
    $filename = 'Student_List_' . date('Y-m-d') . '.pdf';
    $pdf->Output('I', $filename); // 'I' sends to browser
    exit;
}
?>