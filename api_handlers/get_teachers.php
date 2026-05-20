<?php
session_start();
include('../includes/config.php');

header('Content-Type: application/json');

try {
    $sql = "SELECT id, CONCAT(firstname, ' ', secondname, ' ', lastname) AS full_name 
            FROM tblteachers 
            ORDER BY full_name ASC";
    $stmt = $dbh->query($sql);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $teachers
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
