<?php
// includes/SystemNotificationHelper.php

class SystemNotificationHelper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create a system-wide notification
     */
    public function createNotification($title, $message, $type = 'system', $targetType = 'all', $priority = 'medium', $endDate = null, $targetUsers = null) {
        $sql = "INSERT INTO system_notifications 
                (title, message, notification_type, target_type, target_users, priority, start_date, end_date, is_active) 
                VALUES (:title, :message, :type, :target_type, :target_users, :priority, NOW(), :end_date, 1)";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':title' => $title,
            ':message' => $message,
            ':type' => $type,
            ':target_type' => $targetType,
            ':target_users' => $targetUsers,
            ':priority' => $priority,
            ':end_date' => $endDate
        ]);
    }
    
    /**
     * Create maintenance notification
     */
    public function createMaintenanceNotification($title, $message, $maintenanceStart, $maintenanceEnd, $priority = 'urgent') {
        // Check if there's already an active maintenance notification
        $this->deactivateMaintenanceNotifications();
        
        return $this->createNotification(
            $title,
            $message . "\n\nMaintenance Window: " . date('F j, Y g:i A', strtotime($maintenanceStart)) . 
            " to " . date('F j, Y g:i A', strtotime($maintenanceEnd)),
            'maintenance',
            'all',
            $priority,
            $maintenanceEnd
        );
    }
    
    /**
     * Deactivate old maintenance notifications
     */
    private function deactivateMaintenanceNotifications() {
        $sql = "UPDATE system_notifications 
                SET is_active = 0 
                WHERE notification_type = 'maintenance' 
                AND is_active = 1";
        $this->conn->exec($sql);
    }
    
    /**
     * Check if maintenance mode is active
     */
    public function isMaintenanceModeActive() {
        $sql = "SELECT * FROM system_notifications 
                WHERE notification_type = 'maintenance' 
                AND is_active = 1 
                AND start_date <= NOW() 
                AND (end_date IS NULL OR end_date >= NOW())
                LIMIT 1";
        
        $stmt = $this->conn->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get active system notifications for a user
     */
    public function getActiveNotifications($userId, $userRole = null, $schoolId = null, $isTrial = false) {
        $sql = "SELECT n.*, 
                       CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END as viewed,
                       v.dismissed
                FROM system_notifications n
                LEFT JOIN user_notification_views v ON n.id = v.notification_id AND v.user_id = :user_id
                WHERE n.is_active = 1 
                AND n.start_date <= NOW()
                AND (n.end_date IS NULL OR n.end_date >= NOW())
                AND (n.target_type = 'all' 
                    OR (n.target_type = 'trial_users' AND :is_trial = 1)
                    OR (n.target_type = 'inactive_users' AND :user_id IN (
                        SELECT user_id FROM (
                            SELECT teacher_id as user_id, MAX(login_time) as last_login
                            FROM login_logs 
                            WHERE status = 'success'
                            GROUP BY teacher_id
                            HAVING last_login < DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ) as inactive
                    ))
                    OR (n.target_type = 'specific_users' AND JSON_CONTAINS(n.target_users, CAST(:user_id AS JSON)))
                )
                ORDER BY 
                    CASE n.priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    n.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':is_trial' => $isTrial ? 1 : 0
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark notification as viewed
     */
    public function markAsViewed($userId, $notificationId) {
        $sql = "INSERT INTO user_notification_views (user_id, notification_id, viewed_at) 
                VALUES (:user_id, :notification_id, NOW())
                ON DUPLICATE KEY UPDATE viewed_at = NOW(), dismissed = 0";
        
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
     * Get unread notification count
     */
    public function getUnreadCount($userId, $isTrial = false) {
        $sql = "SELECT COUNT(*) as count
                FROM system_notifications n
                WHERE n.is_active = 1 
                AND n.start_date <= NOW()
                AND (n.end_date IS NULL OR n.end_date >= NOW())
                AND (n.target_type = 'all' 
                    OR (n.target_type = 'trial_users' AND :is_trial = 1))
                AND NOT EXISTS (
                    SELECT 1 FROM user_notification_views v
                    WHERE v.notification_id = n.id AND v.user_id = :user_id
                )";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':is_trial' => $isTrial ? 1 : 0
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] ?? 0;
    }
    
    /**
     * Get user notifications with limit
     */
    public function getUserNotifications($userId, $limit = 10, $isTrial = false) {
        $sql = "SELECT n.*, 
                       CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END as viewed,
                       v.viewed_at,
                       v.dismissed
                FROM system_notifications n
                LEFT JOIN user_notification_views v ON n.id = v.notification_id AND v.user_id = :user_id
                WHERE n.is_active = 1 
                AND n.start_date <= NOW()
                AND (n.end_date IS NULL OR n.end_date >= NOW())
                AND (n.target_type = 'all' 
                    OR (n.target_type = 'trial_users' AND :is_trial = 1))
                ORDER BY 
                    CASE n.priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    n.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':is_trial', $isTrial ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get notification icon based on type
     */
    public static function getNotificationIcon($type) {
        $icons = [
            'system' => 'fas fa-bell text-primary',
            'trial_reminder' => 'fas fa-hourglass-half text-warning',
            'feature' => 'fas fa-star text-success',
            'maintenance' => 'fas fa-tools text-danger',
            'promotional' => 'fas fa-gift text-info'
        ];
        return $icons[$type] ?? 'fas fa-bell text-muted';
    }
    
    /**
     * Get notification badge color based on priority
     */
    public static function getPriorityBadge($priority) {
        $badges = [
            'urgent' => 'bg-danger',
            'high' => 'bg-warning',
            'medium' => 'bg-info',
            'low' => 'bg-secondary'
        ];
        return $badges[$priority] ?? 'bg-primary';
    }
}