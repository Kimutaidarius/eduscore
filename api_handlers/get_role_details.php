<?php
include('../includes/config.php');
header('Content-Type: application/json');

$roleId = intval($_GET['id'] ?? 0);
if (!$roleId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid role ID']);
    exit;
}

$stmt = $dbh->prepare("SELECT role_name FROM roles WHERE id = ?");
$stmt->execute([$roleId]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    echo json_encode(['status' => 'error', 'message' => 'Role not found']);
    exit;
}

// Permissions
$stmt = $dbh->prepare("SELECT function_name, can_view, can_create, can_edit, can_delete 
                       FROM role_permissions WHERE role_id = ?");
$stmt->execute([$roleId]);
$perms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => [
        'id' => $roleId,
        'role_name' => $role['role_name'],
        'permissions' => $perms
    ]
]);




