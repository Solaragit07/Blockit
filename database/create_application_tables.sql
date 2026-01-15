-- Create application_blocks table
CREATE TABLE IF NOT EXISTS `application_blocks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `device_id` int(11) DEFAULT NULL,
    `application_name` varchar(100) NOT NULL,
    `application_category` varchar(50) NOT NULL,
    `block_type` enum('complete','time_based','bandwidth_limit','content_filter') DEFAULT 'complete',
    `duration` int(11) DEFAULT 24 COMMENT 'Duration in hours, 0 for permanent',
    `reason` text DEFAULT NULL,
    `domains` text DEFAULT NULL,
    `ports` text DEFAULT NULL,
    `protocols` varchar(100) DEFAULT NULL,
    `status` enum('active','inactive','expired') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_device_id` (`device_id`),
    KEY `idx_application_name` (`application_name`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create application_categories table
CREATE TABLE IF NOT EXISTS `application_categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL UNIQUE,
    `description` text DEFAULT NULL,
    `icon` varchar(50) DEFAULT 'fas fa-cube',
    `color` varchar(20) DEFAULT '#6c757d',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default application categories
INSERT IGNORE INTO `application_categories` (`name`, `description`, `icon`, `color`) VALUES
('Gaming', 'Video games and gaming platforms', 'fas fa-gamepad', '#e74c3c'),
('Social Media', 'Social networking platforms', 'fas fa-users', '#3b5998'),
('Entertainment', 'Streaming and media platforms', 'fas fa-play', '#ff6b35'),
('Communication', 'Messaging and communication apps', 'fas fa-comments', '#1abc9c'),
('E-commerce', 'Online shopping platforms', 'fas fa-shopping-cart', '#f39c12'),
('Education', 'Educational platforms and tools', 'fas fa-graduation-cap', '#9b59b6'),
('News', 'News and media websites', 'fas fa-newspaper', '#34495e'),
('Adult Content', 'Adult and mature content sites', 'fas fa-ban', '#c0392b'),
('File Sharing', 'File sharing and torrenting sites', 'fas fa-share-alt', '#95a5a6');

-- Create device table if it doesn't exist
CREATE TABLE IF NOT EXISTS `device` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `device_name` varchar(100) DEFAULT NULL,
    `mac_address` varchar(17) DEFAULT NULL,
    `ip_address` varchar(15) DEFAULT NULL,
    `device_type` varchar(50) DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
