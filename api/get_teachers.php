<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');
header('Content-Type: application/json');

$response = ["status" => "error", "message" => "", "teachers" => []];

// --- Session Validation ---
if (!isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response["message"] = "Unauthorized access. School ID not found in session.";
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id'];
$searchTerm = $_GET['search'] ?? '';

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Please contact support.";
    error_log("DB connection failed in get_teachers.php");
    echo json_encode($response);
    exit();
}

try {
    // ✅ Join tblteachers with tblroles (same school only)
    $sql = "
        SELECT 
            t.id,
            t.firstname,
            t.secondname,
            t.lastname,
            t.gender,
            t.email,
            t.phonenumber,
            r.role_name AS role
        FROM tblteachers t
        LEFT JOIN roles r 
            ON t.role_id = r.id 
            AND r.school_id = t.school_id
        WHERE t.school_id = :school_id
    ";

    $params = [':school_id' => $schoolId];

    // 🔍 Optional search filter
    if (!empty($searchTerm)) {
        $sql .= " AND (
            t.firstname LIKE :search_term OR 
            t.secondname LIKE :search_term OR
            t.lastname LIKE :search_term OR 
            t.email LIKE :search_term OR 
            t.phonenumber LIKE :search_term OR
            r.role_name LIKE :search_term
        )";
        $params[':search_term'] = '%' . $searchTerm . '%';
    }

    $sql .= " ORDER BY t.firstname ASC, t.lastname ASC";

    $query = $dbh->prepare($sql);
    $query->execute($params);
    $teachers = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($teachers && count($teachers) > 0) {
        $response["status"] = "success";
        $response["teachers"] = $teachers;
        $response["message"] = "Teachers fetched successfully.";
    } else {
        $response["message"] = "No teachers found for this school.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in get_teachers.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "Unexpected error: " . $e->getMessage();
    error_log("General Error in get_teachers.php: " . $e->getMessage());
}

echo json_encode($response);
exit();
