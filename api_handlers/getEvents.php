<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['school_id'])) {
    echo json_encode([]);
    exit;
}

$schoolId = $_SESSION['school_id'];

try {
    $sql = "SELECT id, title, description, type, event_date, event_time 
            FROM tblevents 
            WHERE school_id = :school_id 
            ORDER BY event_date ASC, event_time ASC";
    $query = $dbh->prepare($sql);
    $query->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $query->execute();
    $events = $query->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($events as $ev) {
        $formatted[] = [
            'id'    => $ev['id'],
            'title' => $ev['title'],
            'description' => $ev['description'],
            'type'  => $ev['type'],
            'date'  => $ev['event_date'],
            'time'  => $ev['event_time'],
            // 👇 Only date (so calendar shows title under date, no time)
            'start' => $ev['event_date']
        ];
    }

    echo json_encode($formatted);
} catch (Exception $e) {
    echo json_encode([]);
}
