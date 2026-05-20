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

$roleId = intval($_GET['id'] ?? 0);

if ($roleId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid role ID."
    ]);
    exit;
}

try {
    $sql = "SELECT function_name, can_view, can_create, can_edit, can_delete 
            FROM role_permissions 
            WHERE role_id = :role_id";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([":role_id" => $roleId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "permissions" => $permissions
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
