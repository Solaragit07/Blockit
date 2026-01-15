-- SQL script to create subscription table
-- Run this in phpMyAdmin or MySQL command line

CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan` enum('free','premium') NOT NULL DEFAULT 'free',
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_plan` (`plan`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default free plan for existing users
INSERT IGNORE INTO `user_subscriptions` (`user_id`, `plan`) 
SELECT `id`, 'free' FROM `users` WHERE `id` NOT IN (SELECT `user_id` FROM `user_subscriptions`);
