<?php
header("Content-Type: application/json");
include("../includes/config.php");

$roleId = $_GET['id'] ?? null;
if (!$roleId) {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

$roleId = intval($roleId);

try {
    $dbh->beginTransaction();

    $dbh->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
    $dbh->prepare("DELETE FROM user_roles WHERE role_id = ?")->execute([$roleId]);
    $dbh->prepare("DELETE FROM roles WHERE id = ?")->execute([$roleId]);

    $dbh->commit();
    echo json_encode(["status" => "success", "message" => "Role deleted successfully."]);
} catch (Exception $e) {
    $dbh->rollBack();
    echo json_encode(["status" => "error", "message" => "Failed to delete role."]);
}
