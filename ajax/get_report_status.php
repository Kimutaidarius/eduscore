<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $reportId = $_GET['id'] ?? 0;
    
    $query = "SELECT batch_status as status, status_message 
              FROM tblreportconfigurations 
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $reportId);
    $stmt->execute();
    
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($report) {
        echo json_encode([
            'success' => true,
            'status' => $report['status'],
            'message' => $report['status_message']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
    }
    
} catch(Exception $e) {
    error_log("Get report status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error']);
}
?>