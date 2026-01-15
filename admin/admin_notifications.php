<?php
/**
 * Admin Notification Functions
 * Handles creating and managing admin notifications
 */

require_once __DIR__ . '/../connectMySql.php';

class AdminNotifications {
    private $db;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Create a new admin notification
     */
    public function createNotification($type, $title, $message, $user_id = null, $priority = 'medium') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO admin_notifications (type, title, message, user_id, priority) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([$type, $title, $message, $user_id, $priority]);
        } catch (Exception $e) {
            error_log("Admin Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification for subscription changes
     */
    public function createSubscriptionNotification($user_id, $action, $plan, $old_plan = null) {
        // Get user information
        $user_info = $this->getUserInfo($user_id);
        $username = $user_info ? $user_info['username'] : 'Unknown User';
        $email = $user_info ? $user_info['email'] : 'unknown@email.com';
        
        // Generate notification based on action
        switch ($action) {
            case 'created':
                $title = "New Subscription Created";
                $message = "User '{$username}' ({$email}) has subscribed to the {$plan} plan.";
                $priority = 'high';
                break;
                
            case 'updated':
                $old_plan_text = $old_plan ? " from {$old_plan}" : "";
                $title = "Subscription Updated";
                $message = "User '{$username}' ({$email}) has upgraded{$old_plan_text} to the {$plan} plan.";
                $priority = 'medium';
                break;
                
            case 'cancelled':
                $title = "Subscription Cancelled";
                $message = "User '{$username}' ({$email}) has cancelled their {$plan} subscription.";
                $priority = 'medium';
                break;
                
            case 'expired':
                $title = "Subscription Expired";
                $message = "User '{$username}' ({$email}) subscription has expired. Previous plan: {$plan}.";
                $priority = 'high';
                break;
                
            default:
                $title = "Subscription Activity";
                $message = "User '{$username}' ({$email}) subscription activity: {$action}";
                $priority = 'medium';
        }
        
        // Create the notification
        $this->createNotification('subscription', $title, $message, $user_id, $priority);
        
        // Log the subscription change
        $this->logSubscriptionChange($user_id, $old_plan, $plan, $action);
    }
    
    /**
     * Log subscription changes
     */
    private function logSubscriptionChange($user_id, $old_plan, $new_plan, $action) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO subscription_logs (user_id, old_plan, new_plan, action, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt->execute([$user_id, $old_plan, $new_plan, $action, $ip_address, $user_agent]);
        } catch (Exception $e) {
            error_log("Subscription Log Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get user information
     */
    private function getUserInfo($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT username, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get User Info Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all notifications
     */
    public function getNotifications($limit = 50, $offset = 0, $filter = 'all') {
        try {
            $where_clause = "";
            $params = [];
            
            if ($filter !== 'all') {
                if ($filter === 'unread') {
                    $where_clause = "WHERE is_read = 0";
                } elseif (in_array($filter, ['subscription', 'user_activity', 'system', 'security'])) {
                    $where_clause = "WHERE type = ?";
                    $params[] = $filter;
                }
            }
            
            $stmt = $this->db->prepare("
                SELECT n.*, u.username, u.email 
                FROM admin_notifications n 
                LEFT JOIN users u ON n.user_id = u.id 
                {$where_clause}
                ORDER BY n.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get Notifications Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get notification count
     */
    public function getNotificationCount($filter = 'all') {
        try {
            $where_clause = "";
            $params = [];
            
            if ($filter !== 'all') {
                if ($filter === 'unread') {
                    $where_clause = "WHERE is_read = 0";
                } elseif (in_array($filter, ['subscription', 'user_activity', 'system', 'security'])) {
                    $where_clause = "WHERE type = ?";
                    $params[] = $filter;
                }
            }
            
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM admin_notifications {$where_clause}");
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Get Notification Count Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE admin_notifications 
                SET is_read = 1, read_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            return $stmt->execute([$notification_id]);
        } catch (Exception $e) {
            error_log("Mark as Read Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead() {
        try {
            $stmt = $this->db->prepare("
                UPDATE admin_notifications 
                SET is_read = 1, read_at = CURRENT_TIMESTAMP 
                WHERE is_read = 0
            ");
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Mark All as Read Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete notification
     */
    public function deleteNotification($notification_id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM admin_notifications WHERE id = ?");
            return $stmt->execute([$notification_id]);
        } catch (Exception $e) {
            error_log("Delete Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get subscription statistics
     */
    public function getSubscriptionStats() {
        try {
            $stats = [];
            
            // Total subscriptions
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_subscriptions");
            $stmt->execute();
            $stats['total_subscriptions'] = $stmt->fetchColumn();
            
            // Active subscriptions by plan
            $stmt = $this->db->prepare("
                SELECT plan, COUNT(*) as count 
                FROM user_subscriptions 
                WHERE status = 'active' 
                GROUP BY plan
            ");
            $stmt->execute();
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['free_users'] = 0;
            $stats['premium_users'] = 0;
            
            foreach ($plans as $plan) {
                if ($plan['plan'] === 'free') {
                    $stats['free_users'] = $plan['count'];
                } elseif ($plan['plan'] === 'premium') {
                    $stats['premium_users'] = $plan['count'];
                }
            }
            
            // Recent subscription changes (last 7 days)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM subscription_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $stats['recent_changes'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (Exception $e) {
            error_log("Get Subscription Stats Error: " . $e->getMessage());
            return [];
        }
    }
}

// Initialize the notification system
$adminNotifications = new AdminNotifications();
?>
