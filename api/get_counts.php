<?php
// Include your database connection and sendResponse function
require_once('../includes/config.php'); // Adjust path as necessary

// Ensure the response type is JSON
header('Content-Type: application/json');

$counts = [
    'schools' => 0,
    'teachers' => 0,
    'students' => 0
];

try {
    // Prepare and execute statement for Schools
    // CHANGED TABLE NAME TO tblschoolinfo
    $stmt = $dbh->prepare("SELECT COUNT(*) AS total_schools FROM tblschoolinfo");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $counts['schools'] = (int)$result['total_schools'];
    }

    // Prepare and execute statement for Teachers
    $stmt = $dbh->prepare("SELECT COUNT(*) AS total_teachers FROM tblteachers"); // Assuming table name is tblteachers
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $counts['teachers'] = (int)$result['total_teachers'];
    }

    // Prepare and execute statement for Students
    $stmt = $dbh->prepare("SELECT COUNT(*) AS total_students FROM tblstudents"); // Assuming table name is tblstudents
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $counts['students'] = (int)$result['total_students'];
    }

    // Send a success response using your defined function
    sendResponse($counts, 'success', 'Counts fetched successfully.');

} catch (PDOException $e) {
    // Log the error
    error_log("Error fetching counts: " . $e->getMessage());
    // Send an error response using your defined function
    sendResponse([], 'error', 'Error fetching counts: ' . $e->getMessage());
}
?>