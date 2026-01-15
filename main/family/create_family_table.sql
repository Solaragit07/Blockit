-- SQL script to create family devices table
-- Run this in phpMyAdmin or MySQL command line

CREATE TABLE IF NOT EXISTS `family_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_name` varchar(255) NOT NULL,
  `device_type` enum('android','ios','windows','mac','other') NOT NULL DEFAULT 'other',
  `family_code` varchar(20) DEFAULT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_family_code` (`family_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add family_code column to users table if it doesn't exist
ALTER TABLE `users` ADD COLUMN `family_code` varchar(20) DEFAULT NULL AFTER `email`;

-- Create index on family_code
ALTER TABLE `users` ADD INDEX `idx_family_code` (`family_code`);
