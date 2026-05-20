<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['school_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$schoolId = $_SESSION['school_id'];

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['title']) || empty($data['event_date']) || empty($data['event_time'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

try {
    $sql = "INSERT INTO tblevents (school_id, title, description, type, event_date, event_time)
            VALUES (:school_id, :title, :description, :type, :event_date, :event_time)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->bindParam(':title', $data['title'], PDO::PARAM_STR);
    $query->bindParam(':description', $data['description'], PDO::PARAM_STR);
    $query->bindParam(':type', $data['type'], PDO::PARAM_STR);
    $query->bindParam(':event_date', $data['event_date'], PDO::PARAM_STR);
    $query->bindParam(':event_time', $data['event_time'], PDO::PARAM_STR);
    $query->execute();

    echo json_encode(['status' => 'success', 'message' => 'Event saved successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
