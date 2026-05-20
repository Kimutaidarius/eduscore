<?php
// F:\xampp\htdocs\school result PHP\srms\api_handlers\importCSV.php

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include DB config
require_once __DIR__ . '/../includes/config.php';

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse([], 'error', 'Invalid request method for student import.');
}

// --- Retrieve class_id and stream_id from POST data ---
$classFilterId = $_POST['class_id'] ?? null;
$streamFilterId = isset($_POST['stream_id']) && $_POST['stream_id'] !== '' ? $_POST['stream_id'] : null;

// Validate
if (empty($classFilterId)) {
    sendResponse([], 'error', 'Class must be selected from the main page filters before importing.');
}

// Check if school_id is set in session
$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId) {
    $schoolId = 1; // dev fallback, remove in prod
}

// Check for file upload
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    sendResponse([], 'error', 'No file uploaded or upload error. Error code: ' . ($_FILES['csv_file']['error'] ?? 'N/A'));
}

$fileTmpPath = $_FILES['csv_file']['tmp_name'];
$fileName = $_FILES['csv_file']['name'];
$fileType = $_FILES['csv_file']['type'];

// Validate file type
$allowedMimeTypes = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/plain'];
if (!in_array($fileType, $allowedMimeTypes) && pathinfo($fileName, PATHINFO_EXTENSION) !== 'csv') {
    sendResponse([], 'error', 'Invalid file type. Only CSV files are allowed.');
}

// Define expected CSV headers
$csvHeaders = [
    'First Name',
    'Second Name',
    'Last Name',
    'Admission Number',
    'Assessment Number',
    'Admission Date',
    'Gender',
    'NEMIS Number',
    'Contact Number'
];

$importedCount = 0;
$skippedCount = 0;
$errorDetails = [];

$dbh->beginTransaction();

try {
    // Prepare statement for student insertion
    $stmtInsertStudent = $dbh->prepare("
        INSERT INTO tblstudents (
            school_id, class_id, FirstName, SecondName, LastName, AdmNo,
            assessment_no, admission_date, Nemis, Gender, Status,
            StreamId, ContactNo
        )
        VALUES (
            :school_id, :class_id, :first_name, :second_name, :last_name, :admission_no,
            :assessment_no, :admission_date, :nemis, :gender, :status,
            :stream_id, :contact_no
        )
    ");

    if (($handle = fopen($fileTmpPath, "r")) === FALSE) {
        throw new Exception("Failed to open uploaded CSV file.");
    }

    // Read headers
    $headerRow = fgetcsv($handle);
    $headerRow = array_map('trim', $headerRow);

    if ($headerRow === FALSE || $headerRow !== $csvHeaders) {
        fclose($handle);
        throw new Exception("CSV header row does not match expected format. Expected: '" . implode("', '", $csvHeaders) . "'. Got: '" . implode("', '", $headerRow) . "'");
    }

    $rowNumber = 1;
    while (($data = fgetcsv($handle)) !== FALSE) {
        $rowNumber++;
        if (empty($data) || count(array_filter($data, 'strlen')) === 0) continue;
        if (count($data) !== count($csvHeaders)) {
            $errorDetails[] = ['row' => $rowNumber, 'error' => 'Incorrect number of columns', 'admission_no' => 'N/A'];
            $skippedCount++;
            continue;
        }

        $data = array_map('trim', $data);

        $firstName     = $data[0];
        $secondName    = $data[1];
        $lastName      = $data[2];
        $admissionNo   = $data[3];
        $assessmentNo  = $data[4];
        $admissionDate = $data[5];
        $gender        = $data[6];
        $nemis         = $data[7];
        $contactNo     = $data[8];

        // Validation
        $rowErrors = [];
        if (empty($admissionNo)) $rowErrors[] = 'Admission Number is required.';
        if (empty($firstName)) $rowErrors[] = 'First Name is required.';
        if (empty($secondName)) $rowErrors[] = 'Second Name is required.';
        if (empty($admissionDate)) $rowErrors[] = 'Admission Date is required.';
        if (empty($gender)) $rowErrors[] = 'Gender is required.';

        $validGenders = ['Male', 'Female', 'Other'];
        if (!in_array($gender, $validGenders, true)) {
            $rowErrors[] = 'Invalid Gender. Must be one of: ' . implode(', ', $validGenders);
        }

        if (!empty($rowErrors)) {
            $errorDetails[] = ['row' => $rowNumber, 'admission_no' => $admissionNo, 'error' => implode('; ', $rowErrors)];
            $skippedCount++;
            continue;
        }

        // Admission number uniqueness
        $stmt_check_admno = $dbh->prepare("SELECT id FROM tblstudents WHERE AdmNo = :admno AND school_id = :school_id LIMIT 1");
        $stmt_check_admno->execute([':admno' => $admissionNo, ':school_id' => $schoolId]);
        if ($stmt_check_admno->fetchColumn()) {
            $errorDetails[] = ['row' => $rowNumber, 'admission_no' => $admissionNo, 'error' => "Admission Number already exists."];
            $skippedCount++;
            continue;
        }

        // Admission date validation
        $admissionDateForDb = null;
        if (!empty($admissionDate)) {
            $d = DateTime::createFromFormat('Y-m-d', $admissionDate);
            if ($d && $d->format('Y-m-d') === $admissionDate) {
                $admissionDateForDb = $admissionDate;
            } else {
                $rowErrors[] = 'Invalid Admission Date format. Use YYYY-MM-DD.';
            }
        }

        if (!empty($rowErrors)) {
            $errorDetails[] = ['row' => $rowNumber, 'admission_no' => $admissionNo, 'error' => implode('; ', $rowErrors)];
            $skippedCount++;
            continue;
        }

        // Insert
        $success = $stmtInsertStudent->execute([
            ':school_id'     => $schoolId,
            ':class_id'      => $classFilterId,
            ':first_name'    => $firstName,
            ':second_name'   => $secondName,
            ':last_name'     => !empty($lastName) ? $lastName : null,
            ':admission_no'  => $admissionNo,
            ':assessment_no' => !empty($assessmentNo) ? $assessmentNo : null,
            ':admission_date'=> $admissionDateForDb,
            ':nemis'         => !empty($nemis) ? $nemis : null,
            ':gender'        => $gender,
            ':status'        => 'Active',
            ':stream_id'     => !empty($streamFilterId) ? $streamFilterId : null,
            ':contact_no'    => !empty($contactNo) ? $contactNo : null,
        ]);

        if (!$success) {
            $errorInfo = $stmtInsertStudent->errorInfo();
            $errorDetails[] = ['row' => $rowNumber, 'admission_no' => $admissionNo, 'error' => "DB insertion failed: " . ($errorInfo[2] ?? 'Unknown error')];
            $skippedCount++;
        } else {
            $importedCount++;
        }
    }

    fclose($handle);
    $dbh->commit();

    sendResponse([
        'imported_count' => $importedCount,
        'skipped_count'  => $skippedCount,
        'error_details'  => $errorDetails
    ], 'success', 'Student import process completed.');

} catch (Exception $e) {
    $dbh->rollBack();
    error_log("Student import error: " . $e->getMessage());
    sendResponse([], 'error', 'Import failed: ' . $e->getMessage());
}
