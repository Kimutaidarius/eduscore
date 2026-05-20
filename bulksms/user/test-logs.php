<?php
// user/test-logs.php - Simplified version for testing
require_once '../config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Simple query without filters
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sms_messages WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT * FROM sms_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test SMS Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>
    
    <div class="main-content" style="margin-left:250px; margin-top:60px; padding:20px;">
        <h2>Test SMS Logs</h2>
        <p>Total Messages: <?php echo $count; ?></p>
        
        <?php if (empty($messages)): ?>
            <div class="alert alert-info">No messages found</div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Recipient</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?php echo $msg['id']; ?></td>
                            <td><?php echo htmlspecialchars($msg['recipient']); ?></td>
                            <td><?php echo htmlspecialchars(substr($msg['message'], 0, 30)); ?>...</td>
                            <td><?php echo $msg['status']; ?></td>
                            <td><?php echo $msg['created_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>