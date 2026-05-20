<?php
// ajax/delete_report.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Get the raw POST data
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Debug: Log received data
    error_log("Delete report request: " . print_r($data, true));
    
    if (!isset($data['report_id']) || empty($data['report_id'])) {
        echo json_encode(['success' => false, 'message' => 'Report ID is required']);
        exit;
    }
    
    // Check session
    if (!isset($_SESSION['school_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    $report_id = intval($data['report_id']);
    $school_id = $_SESSION['school_id'];
    
    error_log("Attempting to delete report ID: $report_id for school ID: $school_id");
    
    // Check if report exists and belongs to this school
    $checkStmt = $db->prepare("SELECT id FROM tblreportconfigurations WHERE id = :id AND school_id = :school_id");
    $checkStmt->bindParam(":id", $report_id, PDO::PARAM_INT);
    $checkStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Report not found or you do not have permission to delete it'
        ]);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // First, delete from report_cards if they exist
        $deleteCardsStmt = $db->prepare("DELETE FROM report_cards WHERE exam_id IN (SELECT exam_id FROM tblreportconfigurations WHERE id = :id)");
        $deleteCardsStmt->bindParam(":id", $report_id, PDO::PARAM_INT);
        $deleteCardsStmt->execute();
        
        // Then delete the report configuration
        $deleteStmt = $db->prepare("DELETE FROM tblreportconfigurations WHERE id = :id AND school_id = :school_id");
        $deleteStmt->bindParam(":id", $report_id, PDO::PARAM_INT);
        $deleteStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        
        if ($deleteStmt->execute()) {
            $db->commit();
            
            error_log("Report ID: $report_id deleted successfully");
            
            echo json_encode([
                'success' => true,
                'message' => 'Report deleted successfully'
            ]);
        } else {
            $db->rollBack();
            
            $errorInfo = $deleteStmt->errorInfo();
            error_log("Delete failed: " . print_r($errorInfo, true));
            
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete report. Database error.'
            ]);
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch(Exception $e) {
    error_log("Delete report error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage()
    ]);
}
?>