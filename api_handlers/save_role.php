<?php
session_start();
include('../includes/config.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$roleName = trim($data['roleName']);
$permissions = $data['permissions']; // array of function => [view, create, edit, delete]

try {
    // ✅ Check if role already exists
    $checkStmt = $dbh->prepare("SELECT id FROM roles WHERE role_name = :role_name LIMIT 1");
    $checkStmt->execute([':role_name' => $roleName]);
    $existingRole = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingRole) {
        echo json_encode([
            "status" => "error",
            "message" => "Role '$roleName' already exists."
        ]);
        exit;
    }

    // ✅ Insert new role
    $stmt = $dbh->prepare("INSERT INTO roles (role_name) VALUES (:role_name)");
    $stmt->execute([':role_name' => $roleName]);
    $roleId = $dbh->lastInsertId();

    // ✅ Insert permissions
    $stmtPerm = $dbh->prepare("
        INSERT INTO role_permissions 
        (role_id, function_name, can_view, can_create, can_edit, can_delete) 
        VALUES (:role_id, :function_name, :can_view, :can_create, :can_edit, :can_delete)
    ");

    foreach ($permissions as $function => $perms) {
        $stmtPerm->execute([
            ':role_id' => $roleId,
            ':function_name' => $function,
            ':can_view'   => !empty($perms['view']) ? 1 : 0,
            ':can_create' => !empty($perms['create']) ? 1 : 0,
            ':can_edit'   => !empty($perms['edit']) ? 1 : 0,
            ':can_delete' => !empty($perms['delete']) ? 1 : 0,
        ]);
    }

    echo json_encode([
        "status" => "success",
        "message" => "Role '$roleName' saved successfully"
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
