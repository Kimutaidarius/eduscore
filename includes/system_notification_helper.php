<?php
// includes/system_notification_helper.php

class SystemNotificationHelper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create a system-wide notification
     */
    public function createSystemNotification($title, $message, $type = 'system', $targetType = 'all', $priority = 'medium', $endDate = null) {
        $sql = "INSERT INTO system_notifications 
                (title, message, notification_type, target_type, priority, start_date, end_date, is_active) 
                VALUES (:title, :message, :type, :target_type, :priority, NOW(), :end_date, 1)";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':title' => $title,
            ':message' => $message,
            ':type' => $type,
            ':target_type' => $targetType,
            ':priority' => $priority,
            ':end_date' => $endDate
        ]);
    }
    
    /**
     * Get active system notifications for a user
     */
    public function getActiveNotifications($userId) {
        $sql = "SELECT n.*, 
                       (SELECT COUNT(*) FROM user_notification_views 
                        WHERE notification_id = n.id AND user_id = :user_id) as viewed
                FROM system_notifications n
                WHERE n.is_active = 1 
                AND n.start_date <= NOW()
                AND (n.end_date IS NULL OR n.end_date >= NOW())
                AND (n.target_type = 'all' OR 
                     (n.target_type = 'inactive_users' AND :user_id IN (
                         SELECT user_id FROM (
                             SELECT teacher_id as user_id, MAX(login_time) as last_login
                             FROM login_logs 
                             WHERE status = 'success'
                             GROUP BY teacher_id
                             HAVING last_login < DATE_SUB(NOW(), INTERVAL 7 DAY)
                         ) as inactive
                     ))
                )
                ORDER BY n.priority DESC, n.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark notification as viewed
     */
    public function markAsViewed($userId, $notificationId) {
        $sql = "INSERT INTO user_notification_views (user_id, notification_id, viewed_at) 
                VALUES (:user_id, :notification_id, NOW())
                ON DUPLICATE KEY UPDATE viewed_at = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':notification_id' => $notificationId
        ]);
    }
    
    /**
     * Dismiss notification
     */
    public function dismissNotification($userId, $notificationId) {
        $sql = "INSERT INTO user_notification_views (user_id, notification_id, viewed_at, dismissed) 
                VALUES (:user_id, :notification_id, NOW(), 1)
                ON DUPLICATE KEY UPDATE dismissed = 1, viewed_at = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':notification_id' => $notificationId
        ]);
    }
    
    /**
     * Get unread system notification count
     */
    public function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) as count
                FROM system_notifications n
                WHERE n.is_active = 1 
                AND n.start_date <= NOW()
                AND (n.end_date IS NULL OR n.end_date >= NOW())
                AND (n.target_type = 'all' OR 
                     (n.target_type = 'inactive_users' AND :user_id IN (
                         SELECT user_id FROM (
                             SELECT teacher_id as user_id, MAX(login_time) as last_login
                             FROM login_logs 
                             WHERE status = 'success'
                             GROUP BY teacher_id
                             HAVING last_login < DATE_SUB(NOW(), INTERVAL 7 DAY)
                         ) as inactive
                     ))
                )
                AND NOT EXISTS (
                    SELECT 1 FROM user_notification_views v
                    WHERE v.notification_id = n.id AND v.user_id = :user_id
                )";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] ?? 0;
    }
    
    /**
     * Get system notifications with view status
     */
    public function getUserNotifications($userId, $limit = 10) {
        $sql = "SELECT n.*, 
                       CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END as viewed,
                       v.viewed_at,
                       v.dismissed
                FROM system_notifications n
                LEFT JOIN user_notification_views v ON n.id = v.notification_id AND v.user_id = :user_id
                WHERE n.is_active = 1 
                AND n.start_date <= NOW()
                AND (n.end_date IS NULL OR n.end_date >= NOW())
                AND (n.target_type = 'all' OR 
                     (n.target_type = 'inactive_users' AND :user_id IN (
                         SELECT user_id FROM (
                             SELECT teacher_id as user_id, MAX(login_time) as last_login
                             FROM login_logs 
                             WHERE status = 'success'
                             GROUP BY teacher_id
                             HAVING last_login < DATE_SUB(NOW(), INTERVAL 7 DAY)
                         ) as inactive
                     ))
                )
                ORDER BY n.priority DESC, n.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}