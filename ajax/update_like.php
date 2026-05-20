<?php
// ajax/update_like.php - SIMPLIFIED VERSION
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }
    
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit();
    }
    
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['blog_id']) || !isset($data['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit();
    }
    
    $blogId = (int)$data['blog_id'];
    $action = trim($data['action']);
    $userIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Validate action
    if (!in_array($action, ['like', 'unlike'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }
    
    // Check if blog exists
    $checkStmt = $pdo->prepare("SELECT id FROM blogs WHERE id = ?");
    $checkStmt->execute([$blogId]);
    
    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Blog not found']);
        exit();
    }
    
    // Simple like handling using IP
    if ($action === 'like') {
        // Check if already liked by this IP today
        $checkLike = $pdo->prepare("
            SELECT id FROM blog_likes_ip 
            WHERE blog_id = ? AND ip_address = ? 
            AND DATE(created_at) = CURDATE()
        ");
        $checkLike->execute([$blogId, $userIp]);
        
        if ($checkLike->rowCount() === 0) {
            // Add like
            $insertStmt = $pdo->prepare("
                INSERT INTO blog_likes_ip (blog_id, ip_address, created_at) 
                VALUES (?, ?, NOW())
            ");
            $insertStmt->execute([$blogId, $userIp]);
            
            // Update likes count
            $updateStmt = $pdo->prepare("UPDATE blogs SET likes = COALESCE(likes, 0) + 1 WHERE id = ?");
            $updateStmt->execute([$blogId]);
            
            $liked = true;
            $message = 'Blog liked successfully';
        } else {
            $liked = true;
            $message = 'Already liked today';
        }
    } else {
        // Unlike action
        $deleteStmt = $pdo->prepare("
            DELETE FROM blog_likes_ip 
            WHERE blog_id = ? AND ip_address = ?
        ");
        $deleteStmt->execute([$blogId, $userIp]);
        
        if ($deleteStmt->rowCount() > 0) {
            // Update likes count (don't go below 0)
            $updateStmt = $pdo->prepare("
                UPDATE blogs 
                SET likes = GREATEST(COALESCE(likes, 0) - 1, 0) 
                WHERE id = ?
            ");
            $updateStmt->execute([$blogId]);
        }
        
        $liked = false;
        $message = 'Blog unliked successfully';
    }
    
    // Get updated likes count
    $countStmt = $pdo->prepare("SELECT COALESCE(likes, 0) as likes FROM blogs WHERE id = ?");
    $countStmt->execute([$blogId]);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $likes = (int)($result['likes'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'likes' => $likes,
        'liked' => $liked,
        'blog_id' => $blogId
    ]);
    
} catch (Throwable $e) {
    error_log('LIKE ERROR: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update like',
        'error' => $e->getMessage()
    ]);
    exit;
}