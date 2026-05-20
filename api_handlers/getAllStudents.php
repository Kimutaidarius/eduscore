<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // =======================================
    // ✅ Ensure the user is logged in
    // =======================================
    if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
        throw new Exception("Unauthorized access: school ID not found in session.");
    }

    $school_id = intval($_SESSION['school_id']);

    // =======================================
    // ✅ Fetch all active students for that school
    // =======================================
    $sql = "SELECT 
                id,
                AdmNo,
                FirstName,
                SecondName,
                LastName,
                Gender,
                GuardianName,
                GuardianRelationship,
                GuardianPhone,
                Class,
                StreamId
            FROM tblstudents
            WHERE school_id = :school_id
              AND Status = 'Active'
            ORDER BY FirstName ASC, SecondName ASC";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$students) {
        $response['message'] = "No students found.";
    } else {
        $response['success'] = true;
        $response['data'] = $students;
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
