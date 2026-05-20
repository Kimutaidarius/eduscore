<?php
// F:\xampp\htdocs\school result PHP\srms\api_handlers\getClassesByAcademicLevel.php

// Include the config file to get $dbh (database connection) and sendResponse() function
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['school_id'])) {
    sendResponse([], 'error', 'Authentication required. School ID not found in session.');
}
$schoolId = $_SESSION['school_id'];

// --- FIX THIS LINE ---
// Change from 'academic_level_value' to 'academic_level_id' to match JavaScript
if (!isset($_GET['academic_level_id']) || empty($_GET['academic_level_id'])) {
    sendResponse([], 'error', 'Invalid academic level specified.');
}
$academicLevelValue = $_GET['academic_level_id']; // <-- Also update variable assignment

try {
    if (!isset($dbh) || !$dbh instanceof PDO) {
        error_log("Database handle (dbh) not properly established in getClassesByAcademicLevel.php");
        sendResponse([], 'error', 'Server configuration error: Database connection not available.');
    }

    // Your SQL query seems correct with 'academic_level' and 'class_level'
    $stmt = $dbh->prepare("SELECT id, class_level FROM tblclasses WHERE academic_level = :academic_level AND school_id = :school_id ORDER BY class_level ASC");

    $stmt->bindParam(':academic_level', $academicLevelValue, PDO::PARAM_STR);
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $rawClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $classes = [];
    foreach ($rawClasses as $class) {
        $classes[] = [
            'id' => $class['id'],
            'name' => $class['class_level']
        ];
    }
    sendResponse($classes, 'success', 'Classes fetched successfully.');

} catch (PDOException $e) {
    error_log("PDO Error fetching classes by academic level: " . $e->getMessage());
    sendResponse([], 'error', 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("General Error fetching classes by academic level: " . $e->getMessage());
    sendResponse([], 'error', 'An unexpected server error occurred: ' . $e->getMessage());
}
?>