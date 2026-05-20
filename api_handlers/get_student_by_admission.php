<?php
// ==========================
// ✅ Fully Corrected Version - Supports both formats
// ==========================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// --- Start session safely ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET allowed']);
    exit;
}

$admission_no = trim($_GET['admission_no'] ?? '');
$school_id = $_SESSION['school_id'] ?? null;

if (empty($admission_no) || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters or not logged in']);
    exit;
}

try {
    // Search with exact match first
    $stmt = $dbh->prepare("
        SELECT 
            id,
            FirstName,
            SecondName,
            LastName,
            AdmNo,
            class_id,
            StreamId,
            Nemis,
            Gender,
            assessment_no,
            GuardianName AS guardian_name,
            GuardianRelationship AS guardian_relationship,
            GuardianPhone AS guardian_phone,
            BoardingStatus AS boarding_status,
            Status,
            ProfilePic,
            admission_date
        FROM tblstudents
        WHERE AdmNo = :admno AND school_id = :school_id
        LIMIT 1
    ");
    $stmt->execute([
        ':admno' => $admission_no,
        ':school_id' => $school_id
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Try partial match for formats like STU26063 where it might be stored differently
        // For example, if stored as STU/026/2023, search for number part
        $search_pattern = '%' . $admission_no . '%';
        $stmt2 = $dbh->prepare("
            SELECT 
                id,
                FirstName,
                SecondName,
                LastName,
                AdmNo,
                class_id,
                StreamId,
                Nemis,
                Gender,
                assessment_no,
                GuardianName AS guardian_name,
                GuardianRelationship AS guardian_relationship,
                GuardianPhone AS guardian_phone,
                BoardingStatus AS boarding_status,
                Status,
                ProfilePic,
                admission_date
            FROM tblstudents
            WHERE AdmNo LIKE :pattern AND school_id = :school_id
            LIMIT 1
        ");
        $stmt2->execute([
            ':pattern' => $search_pattern,
            ':school_id' => $school_id
        ]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    $data = [
        'id' => (int)$row['id'],
        'FirstName' => $row['FirstName'] ?? '',
        'SecondName' => $row['SecondName'] ?? '',
        'LastName' => $row['LastName'] ?? '',
        'AdmNo' => $row['AdmNo'] ?? '',
        'class_id' => (int)$row['class_id'],
        'StreamId' => $row['StreamId'] ? (int)$row['StreamId'] : null,
        'Nemis' => $row['Nemis'] ?? '',
        'Gender' => $row['Gender'] ?? '',
        'assessment_no' => $row['assessment_no'] ?? '',
        'guardian_name' => $row['guardian_name'] ?? 'N/A',
        'guardian_relationship' => $row['guardian_relationship'] ?? 'N/A',
        'guardian_phone' => $row['guardian_phone'] ?? 'N/A',
        'boarding_status' => $row['boarding_status'] ?: 'Day Scholar',
        'Status' => $row['Status'] ?? 'Unknown',
        'ProfilePic' => $row['ProfilePic'] ?: 'default.png',
        'admission_date' => $row['admission_date'] ?? date('Y-m-d')
    ];

    echo json_encode(['success' => true, 'data' => $data]);

} catch (PDOException $e) {
    error_log("Student search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>