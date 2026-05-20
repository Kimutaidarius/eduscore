<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| AUTH CHECK
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['school_id']) ||
    !isset($_SESSION['authenticated']) ||
    $_SESSION['authenticated'] !== true
) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$school_id = (int) $_SESSION['school_id'];

require_once __DIR__ . '/../includes/config.php';

/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/
try {
    if (!isset($dbh)) {
        throw new Exception('Database handle not found');
    }
    $dbh->query('SELECT 1');
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database connection failed';
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH TEACHERS
|--------------------------------------------------------------------------
*/
$query = "
    SELECT 
        t.teacher_number,
        t.title,
        t.firstname,
        t.middle_name,
        t.secondname,
        t.lastname,
        t.gender,
        t.email,
        t.phonenumber,
        t.status,
        r.role_name,
        GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', ') AS subjects
    FROM tblteachers t
    LEFT JOIN roles r ON t.role_id = r.id
    LEFT JOIN teacher_subjects ts ON t.id = ts.teacher_id
    LEFT JOIN tblsubjects s ON ts.subject_id = s.id
    WHERE t.school_id = :school_id
      AND (t.is_deleted = 0 OR t.is_deleted IS NULL)
    GROUP BY t.id
    ORDER BY t.firstname, t.lastname
";

$stmt = $dbh->prepare($query);
$stmt->bindValue(':school_id', $school_id, PDO::PARAM_INT);
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| FETCH SCHOOL INFO
|--------------------------------------------------------------------------
*/
$schoolStmt = $dbh->prepare("
    SELECT school_name, school_address, school_phone
    FROM tblschoolinfo
    WHERE id = :school_id
");
$schoolStmt->bindValue(':school_id', $school_id, PDO::PARAM_INT);
$schoolStmt->execute();
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TRY TCPDF
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| TRY TCPDF (CLASSIC — INFINITYFREE SAFE)
|--------------------------------------------------------------------------
*/
$tcpdfPath = __DIR__ . '/../assets/tcpdf/tcpdf.php';

if (file_exists($tcpdfPath)) {
    require_once $tcpdfPath;

    if (!class_exists('TCPDF')) {
        die('TCPDF class not found. Check installation.');
    }

    generatePDF($school, $teachers);
    exit;
}


/*
|--------------------------------------------------------------------------
| FALLBACK TO HTML (PRINT TO PDF)
|--------------------------------------------------------------------------
*/
generateHTML($school, $teachers);
exit;


/* ========================================================================
   PDF FUNCTIONS
   ======================================================================== */

function generatePDF(array $school, array $teachers): void
{
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // CRITICAL FIXES
    $pdf->setFontSubsetting(false);
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetCreator('EduScore');
    $pdf->SetAuthor('EduScore');
    $pdf->SetTitle('Teacher Directory');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    // Header
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 10, 'TEACHER DIRECTORY', 0, 1, 'C');

    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(0, 6, $school['school_name'] ?? '', 0, 1, 'C');
    $pdf->Cell(0, 6, $school['school_address'] ?? '', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Phone: ' . ($school['school_phone'] ?? ''), 0, 1, 'C');
    $pdf->Ln(6);

    // Table Header
    $pdf->SetFont('dejavusans', 'B', 9);
    $headers = ['#', 'Teacher ID', 'Name', 'Gender', 'Email', 'Phone', 'Role', 'Subjects', 'Status'];
    $widths  = [8, 25, 45, 18, 50, 28, 25, 45, 20];

    foreach ($headers as $i => $h) {
        $pdf->Cell($widths[$i], 7, $h, 1, 0, 'C');
    }
    $pdf->Ln();

    // Rows
    $pdf->SetFont('dejavusans', '', 9);
    $i = 1;

    foreach ($teachers as $t) {
        $name = trim(
            ($t['title'] ?? '') . ' ' .
            ($t['firstname'] ?? '') . ' ' .
            ($t['middle_name'] ?? '') . ' ' .
            ($t['secondname'] ?? '') . ' ' .
            ($t['lastname'] ?? '')
        );

        $pdf->Cell($widths[0], 6, $i++, 1);
        $pdf->Cell($widths[1], 6, $t['teacher_number'], 1);
        $pdf->Cell($widths[2], 6, mb_substr($name, 0, 35), 1);
        $pdf->Cell($widths[3], 6, $t['gender'], 1);
        $pdf->Cell($widths[4], 6, mb_substr($t['email'], 0, 35), 1);
        $pdf->Cell($widths[5], 6, $t['phonenumber'], 1);
        $pdf->Cell($widths[6], 6, $t['role_name'] ?? 'N/A', 1);
        $pdf->Cell($widths[7], 6, mb_substr($t['subjects'] ?? '—', 0, 40), 1);
        $pdf->Cell($widths[8], 6, $t['status'], 1);
        $pdf->Ln();
    }

    $pdf->Output('Teachers_' . date('Y-m-d') . '.pdf', 'I');
}

/* ========================================================================
   HTML FALLBACK
   ======================================================================== */

function generateHTML(array $school, array $teachers): void
{
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>
<html>
<head>
<title>Teacher Directory</title>
<style>
body{font-family:Arial;margin:20px}
h1{color:#1e3a8a;text-align:center}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{border:1px solid #ccc;padding:8px;font-size:12px}
th{background:#1e3a8a;color:#fff}
</style>
</head>
<body>
<h1>Teacher Directory</h1>
<p style="text-align:center">' . htmlspecialchars($school['school_name'] ?? '') . '</p>

<table>
<tr>
<th>#</th><th>ID</th><th>Name</th><th>Gender</th>
<th>Email</th><th>Phone</th><th>Role</th><th>Subjects</th><th>Status</th>
</tr>';

    $i = 1;
    foreach ($teachers as $t) {
        $name = htmlspecialchars(trim(
            ($t['title'] ?? '') . ' ' .
            ($t['firstname'] ?? '') . ' ' .
            ($t['middle_name'] ?? '') . ' ' .
            ($t['secondname'] ?? '') . ' ' .
            ($t['lastname'] ?? '')
        ));

        echo '<tr>
<td>' . $i++ . '</td>
<td>' . htmlspecialchars($t['teacher_number']) . '</td>
<td>' . $name . '</td>
<td>' . htmlspecialchars($t['gender']) . '</td>
<td>' . htmlspecialchars($t['email']) . '</td>
<td>' . htmlspecialchars($t['phonenumber']) . '</td>
<td>' . htmlspecialchars($t['role_name'] ?? 'N/A') . '</td>
<td>' . htmlspecialchars($t['subjects'] ?? '-') . '</td>
<td>' . htmlspecialchars($t['status']) . '</td>
</tr>';
    }

    echo '</table>
<script>window.print()</script>
</body></html>';
}
$pdf->setFontSubsetting(false);
