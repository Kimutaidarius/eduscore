<?php
session_start();
include('../includes/config.php');
header('Content-Type: application/json');

// --- TEMPORARY DEBUGGING CODE START ---
error_log("Session data: " . print_r($_SESSION, true));
error_log("Incoming POST data: " . print_r(json_decode(file_get_contents("php://input"), true), true));
// --- TEMPORARY DEBUGGING CODE END ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    $categoryId = $input['category_id'] ?? null;
    $schoolId = $_SESSION['school_id'] ?? null; // THIS IS THE VARIABLE WE'RE CHECKING

    error_log("Category ID from input: " . ($categoryId ?? 'null'));
    error_log("School ID from session: " . ($schoolId ?? 'null'));

    if (!$categoryId || !$schoolId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing category or school ID.']);
        exit;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // 1. Delete all subjects under the category
        $stmtSubjects = $conn->prepare("DELETE FROM tblsubjects WHERE category_id = ? AND school_id = ?");
        $stmtSubjects->bind_param("ii", $categoryId, $schoolId);
        $stmtSubjects->execute();

        // 2. Delete the category itself
        $stmtCategory = $conn->prepare("DELETE FROM tblsubjectcombination WHERE id = ? AND school_id = ?");
        $stmtCategory->bind_param("ii", $categoryId, $schoolId);
        $stmtCategory->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Category and associated learning areas deleted.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Deletion failed. Try again.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
