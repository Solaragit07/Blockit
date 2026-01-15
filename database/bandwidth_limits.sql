-- Create bandwidth_limits table for storing bandwidth limit settings
CREATE TABLE IF NOT EXISTS `bandwidth_limits` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `mac_address` varchar(17) NOT NULL,
    `device_name` varchar(255) DEFAULT NULL,
    `upload_limit` bigint(20) NOT NULL COMMENT 'Upload limit in bytes per second',
    `download_limit` bigint(20) NOT NULL COMMENT 'Download limit in bytes per second',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status` enum('active','inactive','deleted') DEFAULT 'active',
    PRIMARY KEY (`id`),
    INDEX `idx_mac_address` (`mac_address`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
