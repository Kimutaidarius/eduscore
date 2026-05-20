<?php
include('includes/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId = $_POST['id'];

    try {
        $sql = "DELETE FROM tblclasses WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $classId, PDO::PARAM_INT);
        $query->execute();

        $response = array('success' => true, 'message' => 'Class deleted successfully');
        echo json_encode($response);
    } catch (PDOException $e) {
        $response = array('success' => false, 'error' => 'Error deleting class: ' . $e->getMessage());
        echo json_encode($response);
    }
} else {
    $response = array('success' => false, 'error' => 'Invalid request method');
    echo json_encode($response);
}
?>