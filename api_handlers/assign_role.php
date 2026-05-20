<?php
session_start();
include('../includes/config.php');

if (empty($_SESSION['id']) || empty($_SESSION['login'])) {
    sendResponse(null, 'error', 'Unauthorized');
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$roleId = intval($data['roleId'] ?? 0);
$teacherIds = $data['userIds'] ?? [];

if (!$roleId || empty($teacherIds)) {
    sendResponse(null, 'error', 'Role and users must be selected.');
}

try {
    // Start transaction
    $dbh->beginTransaction();

    // Prepare statements
    $stmtDelete = $dbh->prepare("DELETE FROM user_roles WHERE role_id = :role_id AND teacher_id = :teacher_id");
    $stmtInsert = $dbh->prepare("INSERT INTO user_roles (teacher_id, role_id) VALUES (:teacher_id, :role_id)");

    foreach ($teacherIds as $teacherId) {
        $teacherId = intval($teacherId);

        // Remove previous assignment if exists
        $stmtDelete->execute([
            ':role_id' => $roleId,
            ':teacher_id' => $teacherId
        ]);

        // Assign role
        $stmtInsert->execute([
            ':teacher_id' => $teacherId,
            ':role_id' => $roleId
        ]);
    }

    $dbh->commit();
    sendResponse(null, 'success', 'Role assigned successfully!');
} catch (PDOException $e) {
    $dbh->rollBack();
    sendResponse(null, 'error', 'Error assigning role: ' . $e->getMessage());
}
