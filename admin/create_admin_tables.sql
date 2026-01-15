-- SQL script to create admin notification tables
-- Run this in phpMyAdmin or MySQL command line

-- Create admin_notifications table
CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('subscription','user_activity','system','security') NOT NULL DEFAULT 'subscription',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_read` boolean NOT NULL DEFAULT FALSE,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admin_settings table
CREATE TABLE IF NOT EXISTS `admin_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin settings
INSERT IGNORE INTO `admin_settings` (`setting_key`, `setting_value`, `description`) VALUES
('notifications_enabled', '1', 'Enable/disable admin notifications'),
('email_notifications', '1', 'Enable/disable email notifications'),
('subscription_notifications', '1', 'Enable/disable subscription notifications'),
('admin_email', 'admin@blockit.local', 'Admin email for notifications');

-- Create subscription_logs table for tracking subscription changes
CREATE TABLE IF NOT EXISTS `subscription_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `old_plan` enum('free','premium') DEFAULT NULL,
  `new_plan` enum('free','premium') NOT NULL,
  `action` enum('created','updated','cancelled','expired') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
