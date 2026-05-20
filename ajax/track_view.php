<?php
// ajax/track_view.php - SIMPLIFIED VERSION
session_start();

// Enable error display for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once __DIR__ . '/../includes/db.php';

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST.'
        ]);
        exit();
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No data received'
        ]);
        exit();
    }
    
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['blog_id']) || !is_numeric($data['blog_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing or invalid blog_id parameter'
        ]);
        exit();
    }
    
    $blogId = (int)$data['blog_id'];
    $userIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Check if blogs table exists and has views column
    try {
        // First, check if blogs table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'blogs'");
        if ($checkTable->rowCount() === 0) {
            throw new Exception('Blogs table does not exist');
        }
        
        // Check if views column exists
        $checkColumn = $pdo->query("SHOW COLUMNS FROM blogs LIKE 'views'");
        if ($checkColumn->rowCount() === 0) {
            // Add views column if it doesn't exist
            $pdo->exec("ALTER TABLE blogs ADD COLUMN views INT DEFAULT 0");
        }
        
    } catch (Exception $e) {
        error_log('Table/column check error: ' . $e->getMessage());
        // Continue anyway - we'll try to update
    }
    
    // Check if blog exists
    $checkStmt = $pdo->prepare("SELECT id, title FROM blogs WHERE id = ?");
    $checkStmt->execute([$blogId]);
    
    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Blog post not found'
        ]);
        exit();
    }
    
    // Simple view counting - update views directly
    // This prevents needing the blog_views table initially
    $updateStmt = $pdo->prepare("UPDATE blogs SET views = COALESCE(views, 0) + 1 WHERE id = ?");
    $updateStmt->execute([$blogId]);
    
    // Get updated view count
    $countStmt = $pdo->prepare("SELECT COALESCE(views, 0) as views FROM blogs WHERE id = ?");
    $countStmt->execute([$blogId]);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $views = (int)($result['views'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'message' => 'View tracked successfully',
        'views' => $views,
        'blog_id' => $blogId
    ]);
    
} catch (Throwable $e) {
    error_log('VIEW TRACK ERROR: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to track view',
        'error' => $e->getMessage(), // Show error for debugging
        'trace' => $e->getTraceAsString() // Add trace for debugging
    ]);
    exit;
}