<?php
// ajax/fetchblog.php - UPDATED VERSION WITH LIKES AND VIEWS
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';

define('BLOG_IMAGE_BASE_URL', 'https://admineduscore.rf.gd/');

try {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = max(1, (int)($_GET['limit'] ?? 6));
    $category = trim($_GET['category'] ?? '');
    $search   = trim($_GET['search'] ?? '');

    $offset = ($page - 1) * $limit;

    // Check if likes and views columns exist, if not use defaults
    $columnCheck = $pdo->query("SHOW COLUMNS FROM blogs LIKE 'likes'");
    $hasLikesColumn = $columnCheck->rowCount() > 0;
    
    $columnCheck = $pdo->query("SHOW COLUMNS FROM blogs LIKE 'views'");
    $hasViewsColumn = $columnCheck->rowCount() > 0;

    $sql = "
        SELECT 
            id,
            title,
            description,
            content,
            author,
            category,
            tags,
            image,
            created_at" . 
            ($hasLikesColumn ? ", COALESCE(likes, 0) as likes" : ", 0 as likes") .
            ($hasViewsColumn ? ", COALESCE(views, 0) as views" : ", 0 as views") . "
        FROM blogs
        WHERE 1=1
    ";

    $params = [];

    if ($category !== '') {
        $sql .= " AND category = :category";
        $params['category'] = $category;
    }

    if ($search !== '') {
        $sql .= " AND (
            title LIKE :search
            OR description LIKE :search
            OR content LIKE :search
            OR tags LIKE :search
        )";
        $params['search'] = "%{$search}%";
    }

    $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value);
    }

    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $blogs = $stmt->fetchAll();

    // Check if user is logged in to get like status
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $userIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    foreach ($blogs as &$blog) {
        // Fix image URL
        if (!empty($blog['image']) && !preg_match('#^https?://#', $blog['image'])) {
            $blog['image'] = BLOG_IMAGE_BASE_URL . ltrim($blog['image'], '/');
        }
        
        // Check if user has liked this blog
        $blog['is_liked'] = false;
        if ($userId > 0) {
            $likeStmt = $pdo->prepare("SELECT id FROM blog_likes WHERE blog_id = ? AND user_id = ?");
            $likeStmt->execute([$blog['id'], $userId]);
            $blog['is_liked'] = $likeStmt->rowCount() > 0;
        } else {
            $likeStmt = $pdo->prepare("SELECT id FROM blog_likes_ip WHERE blog_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $likeStmt->execute([$blog['id'], $userIp]);
            $blog['is_liked'] = $likeStmt->rowCount() > 0;
        }
        
        // Ensure numeric values
        $blog['likes'] = (int)$blog['likes'];
        $blog['views'] = (int)$blog['views'];
    }
    unset($blog);

    // Count query
    $countSql = "SELECT COUNT(*) FROM blogs WHERE 1=1";

    if ($category !== '') {
        $countSql .= " AND category = :category";
    }

    if ($search !== '') {
        $countSql .= " AND (
            title LIKE :search
            OR description LIKE :search
            OR content LIKE :search
            OR tags LIKE :search
        )";
    }

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue(":{$key}", $value);
    }

    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'blogs'   => $blogs,
        'total'   => $total,
        'page'    => $page,
        'limit'   => $limit,
        'hasMore' => ($offset + count($blogs)) < $total
    ]);
    exit;

} catch (Throwable $e) {
    error_log('BLOG FETCH ERROR: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch blogs',
        'blogs'   => []
    ]);
    exit;
}