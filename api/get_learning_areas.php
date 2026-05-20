<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('config.php');

header('Content-Type: application/json');

$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($classId === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid class ID.']);
    exit();
}

$learningAreas = array();
try {
    // Assuming 'class_id' column exists in 'subjects' table or you have a linking table
    // Adjust this query based on your actual database schema
    $sql = "SELECT id as subject_id, subject_name FROM tblsubjects WHERE class_id = :class_id ORDER BY subject_name ASC";
    $query = $dbh->prepare($sql);
    $query->bindParam(':class_id', $classId, PDO::PARAM_INT);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);

    if ($query->rowCount() > 0) {
        foreach ($results as $result) {
            $learningAreas[] = array(
                'subject_id' => $result->subject_id,
                'subject_name' => $result->subject_name
            );
        }
        echo json_encode(['success' => true, 'learning_areas' => $learningAreas]);
    } else {
        // Fallback: if no specific subjects for class, perhaps list all subjects or just return empty
        $sql = "SELECT id as subject_id, subject_name FROM tblsubjects ORDER BY subject_name ASC"; // Example: get all subjects if no class-specific subjects
        $query = $dbh->prepare($sql);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_OBJ);
         if ($query->rowCount() > 0) {
            foreach ($results as $result) {
                $learningAreas[] = array(
                    'subject_id' => $result->subject_id,
                    'subject_name' => $result->subject_name
                );
            }
            echo json_encode(['success' => true, 'learning_areas' => $learningAreas]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No learning areas found for this class.']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>