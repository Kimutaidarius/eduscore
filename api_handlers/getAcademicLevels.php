<?php
require_once '../includes/config.php'; // This file (config.php) already defines sendResponse()

// Define $schoolId locally to avoid confusion
$schoolId = $_SESSION['school_id'] ?? null;

if (!$schoolId) {
    // Call the sendResponse function defined in config.php
    sendResponse([], 'error', 'Authentication required or session expired.');
}

try {
    $stmt = $dbh->prepare("SELECT DISTINCT academic_level FROM tblclasses WHERE school_id = :school_id ORDER BY academic_level ASC");
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();

    $academicLevels = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $academicLevels[] = [
            'id' => $row['academic_level'],
            'name' => $row['academic_level']
        ];
    }

    // Call the sendResponse function defined in config.php
    sendResponse($academicLevels, 'success', 'Academic levels fetched successfully.');

} catch (PDOException $e) {
    error_log("Error fetching academic levels: " . $e->getMessage());
    // Call the sendResponse function defined in config.php
    sendResponse([], 'error', 'Failed to fetch academic levels.');
}