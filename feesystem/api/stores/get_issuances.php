<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $data['school_id'] ?? 0;
$issuance_id = $data['issuance_id'] ?? null;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    // If specific issuance ID is requested
    if ($issuance_id) {
        $stmt = $db->prepare("
            SELECT i.*, 
                   d.name as department_name,
                   (SELECT COUNT(*) FROM issuance_items WHERE issuance_id = i.id) as item_count,
                   (SELECT SUM(quantity) FROM issuance_items WHERE issuance_id = i.id) as total_quantity,
                   (SELECT SUM(total) FROM issuance_items WHERE issuance_id = i.id) as total_value
            FROM issuances i
            LEFT JOIN departments d ON i.department_id = d.id
            WHERE i.id = ? AND i.school_id = ?
        ");
        $stmt->execute([$issuance_id, $school_id]);
        $issuance = $stmt->fetch();
        
        if ($issuance) {
            // Get issuance items
            $stmt2 = $db->prepare("
                SELECT ii.*, 
                       si.item_code, 
                       si.item_name, 
                       si.unit_of_measure
                FROM issuance_items ii
                JOIN store_items si ON ii.item_id = si.id
                WHERE ii.issuance_id = ?
                ORDER BY ii.id
            ");
            $stmt2->execute([$issuance_id]);
            $items = $stmt2->fetchAll();
            
            $issuance['items'] = $items;
            
            echo json_encode(['success' => true, 'issuance' => $issuance]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Issuance not found']);
        }
    } 
    // Get all issuances
    else {
        $stmt = $db->prepare("
            SELECT i.*, 
                   d.name as department_name,
                   (SELECT COUNT(*) FROM issuance_items WHERE issuance_id = i.id) as item_count,
                   (SELECT SUM(quantity) FROM issuance_items WHERE issuance_id = i.id) as total_quantity,
                   (SELECT SUM(total) FROM issuance_items WHERE issuance_id = i.id) as total_value
            FROM issuances i
            LEFT JOIN departments d ON i.department_id = d.id
            WHERE i.school_id = ?
            ORDER BY i.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$school_id]);
        $issuances = $stmt->fetchAll();
        
        // Calculate summary statistics
        $totalIssuances = count($issuances);
        $totalQuantity = 0;
        $totalValue = 0;
        
        foreach ($issuances as $issuance) {
            $totalQuantity += floatval($issuance['total_quantity'] ?? 0);
            $totalValue += floatval($issuance['total_value'] ?? 0);
        }
        
        echo json_encode([
            'success' => true, 
            'issuances' => $issuances,
            'summary' => [
                'total_issuances' => $totalIssuances,
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue
            ]
        ]);
    }
} catch (PDOException $e) {
    error_log("Error in get_issuances: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'issuances' => []]);
}
?>