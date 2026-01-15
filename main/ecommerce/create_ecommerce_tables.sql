-- SQL script to create e-commerce tables
-- Run this in phpMyAdmin or MySQL command line

CREATE TABLE IF NOT EXISTS `ecommerce_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `settings` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_platforms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `platform_name` varchar(255) NOT NULL,
  `platform_url` varchar(500) NOT NULL,
  `access_level` enum('browsing','full','blocked') NOT NULL DEFAULT 'browsing',
  `reason` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_access_level` (`access_level`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `platform_name` varchar(255) NOT NULL,
  `platform_url` varchar(500) NOT NULL,
  `action` enum('accessed','blocked','purchase_attempted','purchase_blocked') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some default popular e-commerce platforms
INSERT INTO `ecommerce_platforms` (`user_id`, `platform_name`, `platform_url`, `access_level`, `reason`) VALUES
(1, 'Amazon', 'amazon.com', 'browsing', 'Popular shopping platform'),
(1, 'eBay', 'ebay.com', 'browsing', 'Auction and marketplace'),
(1, 'Shopee', 'shopee.ph', 'browsing', 'Local e-commerce platform'),
(1, 'Lazada', 'lazada.com.ph', 'browsing', 'Southeast Asian e-commerce'),
(1, 'Zalora', 'zalora.com.ph', 'blocked', 'Fashion e-commerce platform');
