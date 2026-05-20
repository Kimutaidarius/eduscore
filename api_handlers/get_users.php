<?php
session_start();
include('../includes/config.php');

header('Content-Type: application/json');

try {
    $sql = "SELECT t.id, 
                   CONCAT(t.firstname, ' ', t.secondname, ' ', t.lastname) AS full_name,
                   t.email, 
                   r.role_name
            FROM tblteachers t
            LEFT JOIN roles r ON t.role_id = r.id
            ORDER BY full_name ASC";

    $stmt = $dbh->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($users);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
