<?php
session_start();
header('Content-Type: application/json');

include('../includes/config.php'); // adjust path if needed

try {
    // Fetch only teachers who have at least one assigned role
    $stmt = $dbh->prepare("
        SELECT 
            t.id, 
            CONCAT(t.firstname, ' ', t.secondname, ' ', t.lastname) AS full_name,
            t.email,
            GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') AS role_name
        FROM tblteachers t
        INNER JOIN user_roles ur ON t.id = ur.teacher_id
        INNER JOIN roles r ON ur.role_id = r.id
        GROUP BY t.id, t.firstname, t.secondname, t.lastname, t.email
        ORDER BY t.firstname, t.secondname, t.lastname ASC
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'message' => 'Assigned teachers fetched successfully.',
        'data' => $teachers
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching assigned teachers: ' . $e->getMessage(),
        'data' => null
    ]);
}
?>
