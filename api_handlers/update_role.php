<?php
header("Content-Type: application/json");
include("../includes/config.php");

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || empty($input['roleId']) || empty($input['roleName'])) {
    sendResponse(null, "error", "Invalid data.");
}

$roleId = intval($input['roleId']);
$roleName = trim($input['roleName']);
$permissions = $input['permissions'] ?? [];

try {
    // 1. Update role name
    $stmt = $dbh->prepare("UPDATE roles SET role_name = :name WHERE id = :id");
    $stmt->execute([":name" => $roleName, ":id" => $roleId]);

    // 2. Remove old permissions
    $stmt = $dbh->prepare("DELETE FROM role_permissions WHERE role_id = :id");
    $stmt->execute([":id" => $roleId]);

    // 3. Insert new permissions
    if (!empty($permissions)) {
        $stmt = $dbh->prepare("
            INSERT INTO role_permissions
            (role_id, function_name, can_view, can_create, can_edit, can_delete)
            VALUES (:role_id, :function_name, :view, :create, :edit, :delete)
        ");
        foreach ($permissions as $function => $p) {
            $stmt->execute([
                ":role_id"      => $roleId,
                ":function_name"=> $function,
                ":view"         => $p['view'] ?? 0,
                ":create"       => $p['create'] ?? 0,
                ":edit"         => $p['edit'] ?? 0,
                ":delete"       => $p['delete'] ?? 0
            ]);
        }
    }

    sendResponse(null, "success", "Role updated successfully.");
} catch (Exception $e) {
    sendResponse(null, "error", "DB error: " . $e->getMessage());
}
