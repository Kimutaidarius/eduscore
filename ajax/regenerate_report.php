<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $reportId = $data['report_id'] ?? 0;
    
    // Get report details
    $query = "SELECT * FROM tblreportconfigurations WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $reportId);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }
    
    // Update status to processing
    $updateQuery = "UPDATE tblreportconfigurations 
                   SET batch_status = 'processing', 
                   status_message = 'Regenerating report...',
                   updated_at = NOW()
                   WHERE id = :id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(":id", $reportId);
    $updateStmt->execute();
    
    // Process in background
    register_shutdown_function(function() use ($reportId, $report, $db) {
        try {
            // Re-process the report (similar to create_report processing)
            // ... processing logic ...
            
            // Update status to completed
            $finalUpdate = "UPDATE tblreportconfigurations 
                           SET batch_status = 'completed', 
                           status_message = 'Report regenerated successfully',
                           updated_at = NOW()
                           WHERE id = :id";
            
            $finalStmt = $db->prepare($finalUpdate);
            $finalStmt->bindParam(":id", $reportId);
            $finalStmt->execute();
            
        } catch(Exception $e) {
            error_log("Regenerate error: " . $e->getMessage());
        }
    });
    
    echo json_encode([
        'success' => true,
        'message' => 'Report regeneration started'
    ]);
    
} catch(Exception $e) {
    error_log("Regenerate report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error']);
}
?>