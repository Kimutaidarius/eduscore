<?php
// notification_helper.php

class NotificationHelper {
    private $db;
    private $school_id;
    
    public function __construct($db, $school_id) {
        $this->db = $db;
        $this->school_id = $school_id;
    }
    
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getNotifications($user_id, $limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }
    
    // NEW: Get maintenance notifications specifically
    public function getMaintenanceNotifications() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    'maintenance' as notification_type,
                    CONCAT('🔧 ', title) as message,
                    start_date as created_at,
                    CASE 
                        WHEN status = 'scheduled' AND start_date <= NOW() AND end_date >= NOW() THEN 'in_progress'
                        ELSE status 
                    END as maintenance_status,
                    start_date,
                    end_date,
                    impact
                FROM maintenance_schedule 
                WHERE status IN ('scheduled', 'in_progress')
                AND start_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY start_date ASC
                LIMIT 5
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting maintenance notifications: " . $e->getMessage());
            return [];
        }
    }
    
    // NEW: Get active maintenance (for urgent notification)
    public function getActiveMaintenance() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM maintenance_schedule 
                WHERE status = 'scheduled' 
                AND start_date <= NOW() 
                AND end_date >= NOW()
                LIMIT 1
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting active maintenance: " . $e->getMessage());
            return null;
        }
    }
}
?>