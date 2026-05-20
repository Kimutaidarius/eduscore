<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!isset($_SESSION['school_id'])) {
        echo json_encode([]);
        exit;
    }
    
    $class_id = $_POST['class_id'] ?? 0;
    $school_id = $_SESSION['school_id'];
    
    $query = "SELECT id, subject_name FROM tblsubjects 
              WHERE school_id = :school_id 
              AND class_id = :class_id 
              AND subject_type = 'Compulsory'
              ORDER BY subject_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $school_id);
    $stmt->bindParam(":class_id", $class_id);
    $stmt->execute();
    
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($subjects);
    
} catch(Exception $e) {
    error_log("Get compulsory subjects error: " . $e->getMessage());
    echo json_encode([]);
}
?>