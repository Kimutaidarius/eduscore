<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id'];

// Get the raw POST data
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON data received.';
    echo json_encode($response);
    exit();
}

$termsData = $data['terms'] ?? [];
$receivedSchoolId = $data['school_id'] ?? null;
$currentTermNumber = $data['currentTermNumber'] ?? null;

if (empty($termsData) || $receivedSchoolId === null || $receivedSchoolId != $schoolId || $currentTermNumber === null) {
    $response['message'] = 'Invalid data provided or school ID mismatch.';
    echo json_encode($response);
    exit();
}

if (isset($dbh) && $dbh instanceof PDO) {
    try {
        $dbh->beginTransaction();

        // Get the academic year from the first term (assuming all terms are for the same year)
        $academicYearToUpdate = $termsData[0]['academicYear'];

        // 1. Set is_current to 0 for all existing terms of this school
        $sqlResetCurrent = "UPDATE tblterms SET is_current = 0 WHERE school_id = :school_id";
        $stmtReset = $dbh->prepare($sqlResetCurrent);
        $stmtReset->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmtReset->execute();

        foreach ($termsData as $term) {
            $termNumber = $term['termNumber'];
            $termName = $term['termName']; // e.g., "Term 1"
            $academicYear = $term['academicYear'];
            $startDate = $term['startDate'];
            $endDate = $term['endDate'];

            // Validate dates
            if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate)) {
                $dbh->rollBack();
                $response['message'] = "Invalid date format for Term " . $termNumber . ". Dates must be in YYYY-MM-DD format.";
                echo json_encode($response);
                exit();
            }

            // Check if term already exists for this school and academic year
            $sqlCheck = "SELECT id FROM tblterms WHERE school_id = :school_id AND academic_year = :academic_year AND term_number = :term_number";
            $stmtCheck = $dbh->prepare($sqlCheck);
            $stmtCheck->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
            $stmtCheck->bindParam(':academic_year', $academicYear, PDO::PARAM_INT);
            $stmtCheck->bindParam(':term_number', $termNumber, PDO::PARAM_INT);
            $stmtCheck->execute();
            $existingTerm = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingTerm) {
                // Update existing term
                $sqlUpdate = "UPDATE tblterms SET 
                                term_name = :term_name, 
                                start_date = :start_date, 
                                end_date = :end_date
                              WHERE id = :id";
                $stmtUpdate = $dbh->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':term_name', $termName, PDO::PARAM_STR);
                $stmtUpdate->bindParam(':start_date', $startDate, PDO::PARAM_STR);
                $stmtUpdate->bindParam(':end_date', $endDate, PDO::PARAM_STR);
                $stmtUpdate->bindParam(':id', $existingTerm['id'], PDO::PARAM_INT);
                $stmtUpdate->execute();
            } else {
                // Insert new term
                $sqlInsert = "INSERT INTO tblterms (school_id, term_name, term_number, academic_year, start_date, end_date) 
                              VALUES (:school_id, :term_name, :term_number, :academic_year, :start_date, :end_date)";
                $stmtInsert = $dbh->prepare($sqlInsert);
                $stmtInsert->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
                $stmtInsert->bindParam(':term_name', $termName, PDO::PARAM_STR);
                $stmtInsert->bindParam(':term_number', $termNumber, PDO::PARAM_INT);
                $stmtInsert->bindParam(':academic_year', $academicYear, PDO::PARAM_INT);
                $stmtInsert->bindParam(':start_date', $startDate, PDO::PARAM_STR);
                $stmtInsert->bindParam(':end_date', $endDate, PDO::PARAM_STR);
                $stmtInsert->execute();
            }
        }
        
        // 2. Set the is_current flag for the selected term
        $sqlSetCurrent = "UPDATE tblterms SET is_current = 1 WHERE school_id = :school_id AND academic_year = :academic_year AND term_number = :current_term_number";
        $stmtSetCurrent = $dbh->prepare($sqlSetCurrent);
        $stmtSetCurrent->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmtSetCurrent->bindParam(':academic_year', $academicYearToUpdate, PDO::PARAM_INT);
        $stmtSetCurrent->bindParam(':current_term_number', $currentTermNumber, PDO::PARAM_INT);
        $stmtSetCurrent->execute();

        $dbh->commit();
        $response['success'] = true;
        $response['message'] = 'Term details and current term updated successfully!';

    } catch (PDOException $e) {
        $dbh->rollBack();
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("Database error in manage_terms.php: " . $e->getMessage());
    }
} else {
    $response['message'] = 'Database connection not available.';
}

echo json_encode($response);
?>