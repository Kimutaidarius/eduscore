<?php
session_start();
require_once 'config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get database connection from config
    if (!isset($db)) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    $class_id = $_POST['class_id'] ?? '';

    if (empty($class_id)) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        exit;
    }

    // Check if class exists and belongs to this school
    $check_query = "SELECT id FROM tblclasses WHERE id = :class_id AND school_id = :school_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
    $check_stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $check_stmt->execute();

    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit;
    }

    // Check if class has students
    $students_check = "SELECT COUNT(*) as student_count FROM tblstudents WHERE class_id = :class_id AND school_id = :school_id";
    $students_stmt = $db->prepare($students_check);
    $students_stmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
    $students_stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $students_stmt->execute();
    $student_count = $students_stmt->fetch()['student_count'];

    if ($student_count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete class with students. Please reassign or remove students first.']);
        exit;
    }

    // Delete class
    $delete_query = "DELETE FROM tblclasses WHERE id = :class_id AND school_id = :school_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
    $delete_stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);

    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Class deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete class']);
    }

} catch (PDOException $e) {
    error_log("Delete class error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>