<?php
// Always return JSON
header('Content-Type: application/json');
session_start();

// Prevent HTML redirect from breaking JSON
if (!isset($_SESSION['login']) || !isset($_SESSION['school_id'])) {
    echo json_encode([
        "success" => false,
        "students" => [],
        "message" => "Not authenticated."
    ]);
    exit;
}

require('../includes/config.php'); // MUST NOT output any HTML

$schoolId = $_SESSION['school_id'];
$query = $_GET['q'] ?? ($_GET['query'] ?? '');
$query = trim($query);

// If no query, return empty
if ($query === '') {
    echo json_encode([
        "success" => true,
        "students" => []
    ]);
    exit;
}

// Ensure DB is valid
if (!isset($dbh) || !$dbh instanceof PDO) {
    echo json_encode([
        "success" => false,
        "students" => [],
        "message" => "Database connection failed."
    ]);
    exit;
}

try {
    $stmt = $dbh->prepare("
        SELECT 
            s.id,
            s.FirstName,
            s.SecondName,
            s.LastName,
            s.AdmNo AS AdmNo,
            CONCAT_WS(' ', s.FirstName, s.SecondName, s.LastName) AS full_name,
            c.class_level AS class_name
        FROM tblstudents s
        LEFT JOIN tblclasses c ON s.class_id = c.id
        WHERE s.school_id = :school_id
          AND (
                s.FirstName LIKE :q
                OR s.SecondName LIKE :q
                OR s.LastName LIKE :q
                OR s.AdmNo LIKE :q
              )
        LIMIT 10
    ");

    $searchTerm = "%$query%";

    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->bindParam(':q', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "students" => $results
    ]);
    exit;

} catch (Exception $e) {

    // If any PHP error occurs, return JSON (not HTML error page)
    echo json_encode([
        "success" => false,
        "students" => [],
        "message" => "Server error: " . $e->getMessage()
    ]);
    exit;
}
