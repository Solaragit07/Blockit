-- SQL Migration for Reports System
-- Run this in phpMyAdmin or MySQL command line to create the activity_log table

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocked_site` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_usage` decimal(10,2) DEFAULT 0.00,
  `session_duration` int(11) DEFAULT 0,
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'low',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_blocked_site` (`blocked_site`),
  CONSTRAINT `fk_activity_log_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for demonstration (optional)
INSERT IGNORE INTO `activity_log` (`device_id`, `action`, `blocked_site`, `category`, `data_usage`, `session_duration`, `severity`, `created_at`) VALUES
(1, 'blocked', 'facebook.com', 'Social Media', 15.50, 300, 'medium', NOW() - INTERVAL FLOOR(RAND() * 7) DAY - INTERVAL FLOOR(RAND() * 24) HOUR),
(1, 'blocked', 'youtube.com', 'Entertainment', 25.30, 450, 'low', NOW() - INTERVAL FLOOR(RAND() * 7) DAY - INTERVAL FLOOR(RAND() * 24) HOUR),
(2, 'blocked', 'tiktok.com', 'Social Media', 18.75, 600, 'high', NOW() - INTERVAL FLOOR(RAND() * 7) DAY - INTERVAL FLOOR(RAND() * 24) HOUR),
(2, 'blocked', 'instagram.com', 'Social Media', 12.90, 200, 'medium', NOW() - INTERVAL FLOOR(RAND() * 7) DAY - INTERVAL FLOOR(RAND() * 24) HOUR),
(3, 'blocked', 'twitter.com', 'Social Media', 8.45, 150, 'low', NOW() - INTERVAL FLOOR(RAND() * 7) DAY - INTERVAL FLOOR(RAND() * 24) HOUR),
(1, 'blocked', 'reddit.com', 'Forum', 22.10, 800, 'medium', NOW() - INTERVAL FLOOR(RAND() * 7) DAY - INTERVAL FLOOR(RAND() * 24) HOUR),
(3, 'blocked', 'twitch.tv', 'Gaming', 35.60, 1200, 'low', NOW() - INTERVAL FLOOR(RAND() * 7) DAY - INTERVAL FLOOR(RAND() * 24) HOUR),
(2, 'blocked', 'netflix.com', 'Entertainment', 45.20, 2400, 'low', NOW() - INTERVAL FLOOR(RAND() * 7) DAY - INTERVAL FLOOR(RAND() * 24) HOUR);

-- Create reports_config table for custom report settings
CREATE TABLE IF NOT EXISTS `reports_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `report_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `report_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filters` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_recipients` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_report_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes to existing tables for better report performance
ALTER TABLE `device` ADD INDEX `idx_internet_status` (`internet`);
ALTER TABLE `device` ADD INDEX `idx_device_type` (`device`);

-- Create a view for quick reporting queries
CREATE OR REPLACE VIEW `device_activity_summary` AS
SELECT 
    d.id as device_id,
    d.name as device_name,
    d.device as device_type,
    d.internet as blocking_status,
    COUNT(al.id) as total_activities,
    COUNT(CASE WHEN al.action = 'blocked' THEN 1 END) as blocked_attempts,
    SUM(al.data_usage) as total_data_usage,
    MAX(al.created_at) as last_activity,
    AVG(al.session_duration) as avg_session_duration
FROM device d
LEFT JOIN activity_log al ON d.id = al.device_id
GROUP BY d.id, d.name, d.device, d.internet;
