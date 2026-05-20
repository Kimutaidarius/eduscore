<?php
session_start();
require_once('../includes/config.php');
header('Content-Type: application/json');

if (empty($_SESSION['school_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Authentication required."
    ]);
    exit;
}

$schoolId = intval($_SESSION['school_id']);

try {
    $sql = "SELECT 
                r.id, 
                r.role_name, 
                COUNT(rp.id) AS permission_count
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            WHERE r.school_id = :school_id OR r.school_id = 0
            GROUP BY r.id, r.role_name
            ORDER BY r.role_name ASC";

    $stmt = $dbh->prepare($sql);
    $stmt->execute([':school_id' => $schoolId]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $roles
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
