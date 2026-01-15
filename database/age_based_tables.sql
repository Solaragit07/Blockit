-- Age-Based Filtering Tables
CREATE TABLE IF NOT EXISTS `age_based_blacklist` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `domain` varchar(255) NOT NULL,
    `category` varchar(100) DEFAULT NULL,
    `min_age` int(3) NOT NULL,
    `max_age` int(3) DEFAULT 0 COMMENT '0 means no upper limit',
    `reason` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_domain` (`domain`),
    KEY `idx_age_range` (`min_age`, `max_age`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `age_based_whitelist` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `domain` varchar(255) NOT NULL,
    `category` varchar(100) DEFAULT NULL,
    `min_age` int(3) NOT NULL,
    `reason` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_domain` (`domain`),
    KEY `idx_min_age` (`min_age`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
