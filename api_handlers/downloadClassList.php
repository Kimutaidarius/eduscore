<?php
// api_handlers/downloadClassList.php

require_once 'fpdf.php'; // FPDF library

header('Content-Type: application/json'); // Default to JSON for errors

require_once '../includes/config.php'; // Database connection (provides $dbh)

function sendJsonResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// --- Input Validation ---
if (!isset($_GET['class_id'])) {
    sendJsonResponse(false, 'Class ID is required for PDF download.', [], 400);
}

$classId = $_GET['class_id'];
$streamId = isset($_GET['stream_id']) && $_GET['stream_id'] !== '' ? $_GET['stream_id'] : null;

// Sanitize inputs
$classId = filter_var($classId, FILTER_SANITIZE_NUMBER_INT);
if ($streamId !== null) {
    $streamId = filter_var($streamId, FILTER_SANITIZE_NUMBER_INT);
}

if (!is_numeric($classId)) {
    sendJsonResponse(false, 'Invalid Class ID provided.', [], 400);
}
if ($streamId !== null && !is_numeric($streamId)) {
    sendJsonResponse(false, 'Invalid Stream ID provided.', [], 400);
}

try {
    // Check if PDO connection $dbh is available
    if (!isset($dbh) || !$dbh) {
        throw new Exception("PDO Database connection not established in config.php.");
    }

    // --- Retrieve School ID ---
    $schoolId = 1; // Default fallback, adjust if using $_SESSION['school_id'] or other means
    if (isset($_SESSION['school_id'])) {
        $schoolId = $_SESSION['school_id'];
    }

    // --- Fetch School Details from tblschoolinfo ---
    $schoolName = 'Your School Name'; // Default fallback
    $schoolMotto = 'Your School Motto'; // Default fallback

    if ($schoolId) {
        $stmt = $dbh->prepare("SELECT school_name, school_motto FROM tblschoolinfo WHERE id = :school_id");
        $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt->execute();
        $schoolDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($schoolDetails) {
            $schoolName = $schoolDetails['school_name'];
            $schoolMotto = $schoolDetails['school_motto'];
        } else {
            error_log("School ID {$schoolId} not found in tblschoolinfo.");
        }
    }


    // --- Fetch Class Name and Academic Level Name for the PDF Title ---
    $levelName = '';
    $className = '';
    $streamName = 'All Streams';

    // Fetch Class Name AND Academic Level from tblclasses using the provided class_id
    $stmt = $dbh->prepare("SELECT class_level, academic_level FROM tblclasses WHERE id = :class_id");
    $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $className = $row['class_level'];
        $levelName = $row['academic_level'];
    } else {
        sendJsonResponse(false, 'Class ID not found in database.', [], 404);
    }

    // Fetch Stream Name if provided
    if ($streamId !== null) {
        $stmt = $dbh->prepare("SELECT stream_name FROM tblstreams WHERE id = :stream_id");
        $stmt->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $streamName = $row['stream_name'];
        }
    }

    // --- Fetch Student Data ---
    // SQL query remains the same as no new data is fetched for a blank column
    $sql = "
        SELECT
            s.AdmNo,             -- Required for Admission Number
            s.FirstName,         -- Required for Full Name
            s.SecondName,        -- Required for Full Name
            s.LastName           -- Required for Full Name
        FROM
            tblstudents s  -- Using tblstudents
        WHERE
            s.class_id = :class_id --
    ";
    $params = [':class_id' => $classId];

    if ($streamId !== null) {
        $sql .= " AND s.StreamId = :stream_id"; // Use s.StreamId as per screenshot
        $params[':stream_id'] = $streamId;
    }

    $sql .= " ORDER BY s.FirstName, s.SecondName, s.LastName ASC";

    $stmt = $dbh->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . implode(" ", $dbh->errorInfo()));
    }
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        sendJsonResponse(false, 'No students found for the selected criteria. No PDF generated.', [], 404);
    }

    // --- FPDF Generation ---
    class PDF extends FPDF
    {
        private $schoolName;
        private $schoolMotto;
        private $classAndStreamInfo;
        private $generationDateInfo;

        function setHeaderInfo($schoolName, $schoolMotto, $classAndStreamInfo, $generationDateInfo) {
            $this->schoolName = $schoolName;
            $this->schoolMotto = $schoolMotto;
            $this->classAndStreamInfo = $classAndStreamInfo;
            $this->generationDateInfo = $generationDateInfo;
        }

        function Header()
        {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, $this->schoolName, 0, 1, 'C');

            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 6, $this->schoolMotto, 0, 1, 'C');

            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, $this->classAndStreamInfo, 0, 1, 'C');

            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 6, $this->generationDateInfo, 0, 1, 'C');

            $this->Ln(10);
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            $this->SetY(-10);
            $this->Cell(0, 5, '© ' . date('Y') . ' Your School Name. All rights reserved.', 0, 0, 'C');
        }

        function BasicTable($header, $data)
        {
            $this->SetFillColor(230, 230, 230);
            $this->SetFont('Arial', 'B', 9);
            // MODIFIED: Added width for the new blank column
            // S/N (15), Adm No (40), Full Name (100), Blank Column (20) - adjusted Full Name width for total 190mm
            $columnWidths = [15, 40, 100, 20];
            foreach ($header as $i => $col) {
                $this->Cell($columnWidths[$i], 7, $col, 1, 0, 'C', true);
            }
            $this->Ln();

            $this->SetFont('Arial', '', 8);
            foreach ($data as $row) {
                $this->Cell($columnWidths[0], 6, $row['sn'], 1);
                $this->Cell($columnWidths[1], 6, $row['AdmNo'], 1);
                $this->Cell($columnWidths[2], 6, $row['FullName'], 1);
                $this->Cell($columnWidths[3], 6, '', 1); // NEW: Blank cell for the extra column
                $this->Ln();
            }
        }
    }

    // Instantiate and configure PDF
    $pdf = new PDF();
    $pdf->AliasNbPages();

    $currentDate = date('Y-m-d H:i:s');

    $classAndStreamInfo = "Class: {$className}";
    if ($streamId !== null) {
        $classAndStreamInfo .= " {$streamName}";
    }

    $pdf->setHeaderInfo(
        $schoolName,
        $schoolMotto,
        $classAndStreamInfo,
        "Generated On: {$currentDate}"
    );

    $pdf->AddPage();

    // MODIFIED: Added header for the new blank column (empty string for no label)
    $header = ['S/N', 'Adm No.', 'Full Name', '']; // Added an empty string for the new blank column header

    $tableData = [];
    $sn = 1;
    foreach ($students as $student) {
        $tableData[] = [
            'sn' => $sn++,
            'AdmNo' => htmlspecialchars($student['AdmNo']),
            'FullName' => htmlspecialchars("{$student['FirstName']} {$student['SecondName']} {$student['LastName']}"),
        ];
    }

    $pdf->BasicTable($header, $tableData);

    $filename = "Class_List_{$levelName}_{$className}";
    if ($streamId !== null) {
        $filename .= "_{$streamName}";
    }
    $filename .= "_" . date('Ymd_His') . ".pdf";

    if (ob_get_level()) {
        ob_end_clean();
    }

    $pdf->Output('D', $filename);
    exit();

} catch (Exception $e) {
    error_log("PDF Generation Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendJsonResponse(false, 'An unexpected error occurred during PDF generation: ' . $e->getMessage(), [], 500);
}

?>