<?php

function hasPermission($permission_key, $db) {

    if (!isset($_SESSION['teacher_id'])) return false;

    $stmt = $db->prepare("
        SELECT rp.permission_key
        FROM tblteachers t
        JOIN role_permissions rp ON t.role_id = rp.role_id
        WHERE t.id = :teacher_id
        AND rp.permission_key = :permission_key
    ");

    $stmt->execute([
        ':teacher_id' => $_SESSION['teacher_id'],
        ':permission_key' => $permission_key
    ]);

    return $stmt->rowCount() > 0;
}