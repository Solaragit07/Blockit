-- phpMyAdmin SQL Dump
-- BlockIT Complete Database Schema
-- Generated on: July 23, 2025
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- 
-- Create and use database
-- 
DROP DATABASE IF EXISTS `blockit`;
CREATE DATABASE `blockit` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `blockit`;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` varchar(11) NOT NULL DEFAULT 'ACTIVE',
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`user_id`, `email`, `password`, `name`, `status`, `image`) VALUES
(1, 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'ACTIVE', 'images.png');

-- --------------------------------------------------------

--
-- Table structure for table `application_blocks`
--

CREATE TABLE `application_blocks` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `application_name` varchar(100) NOT NULL,
  `application_category` varchar(50) DEFAULT NULL,
  `domains` text DEFAULT NULL,
  `ports` text DEFAULT NULL,
  `protocols` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `blocked_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `unblocked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_categories`
--

CREATE TABLE `application_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#007bff',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_categories`
--

INSERT INTO `application_categories` (`id`, `name`, `description`, `icon`, `color`, `is_active`) VALUES
(1, 'Gaming', 'Gaming applications and platforms', 'fas fa-gamepad', '#28a745', 1),
(2, 'Social Media', 'Social networking platforms', 'fas fa-users', '#17a2b8', 1),
(3, 'Entertainment', 'Video streaming and entertainment', 'fas fa-play-circle', '#ffc107', 1),
(4, 'Communication', 'Messaging and communication apps', 'fas fa-comments', '#6f42c1', 1),
(5, 'Adult Content', 'Adult and mature content sites', 'fas fa-exclamation-triangle', '#dc3545', 1),
(6, 'Gambling', 'Online gambling and betting sites', 'fas fa-dice', '#fd7e14', 1),
(7, 'Educational', 'Educational platforms and resources', 'fas fa-graduation-cap', '#20c997', 1),
(8, 'News & Media', 'News websites and media outlets', 'fas fa-newspaper', '#6c757d', 1),
(9, 'Health & Wellness', 'Health and wellness resources', 'fas fa-heartbeat', '#e83e8c', 1),
(10, 'Shopping', 'E-commerce and shopping sites', 'fas fa-shopping-cart', '#fd7e14', 1);

-- --------------------------------------------------------

--
-- Table structure for table `blocking_log`
--

CREATE TABLE `blocking_log` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `blocked_domain` varchar(255) DEFAULT NULL,
  `block_reason` varchar(255) DEFAULT NULL,
  `blocked_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocklist`
--

CREATE TABLE `blocklist` (
  `id` int(11) NOT NULL,
  `website` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `blocking_method` enum('drop','redirect') DEFAULT 'drop',
  `added_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `blocklist`
--

INSERT INTO `blocklist` (`id`, `website`, `category`, `reason`, `blocking_method`, `is_active`) VALUES
(1, 'facebook.com', 'Social Media', 'Time management', 'drop', 1),
(2, 'instagram.com', 'Social Media', 'Time management', 'drop', 1),
(3, 'tiktok.com', 'Social Media', 'Time management', 'drop', 1),
(4, 'youtube.com', 'Entertainment', 'Time management', 'redirect', 1),
(5, 'twitter.com', 'Social Media', 'Time management', 'drop', 1);

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `id` int(11) NOT NULL,
  `category_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#007bff',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`id`, `category_name`, `description`, `icon`, `color`, `is_active`) VALUES
(1, 'Social Media', 'Social networking and communication platforms', 'fas fa-users', '#3b5998', 1),
(2, 'Entertainment', 'Video streaming and entertainment content', 'fas fa-play', '#ff0000', 1),
(3, 'Gaming', 'Online games and gaming platforms', 'fas fa-gamepad', '#00ff00', 1),
(4, 'Adult Content', 'Adult and mature content websites', 'fas fa-exclamation-triangle', '#dc3545', 1),
(5, 'Gambling', 'Online gambling and betting sites', 'fas fa-dice', '#ffc107', 1),
(6, 'Shopping', 'E-commerce and shopping websites', 'fas fa-shopping-cart', '#17a2b8', 1),
(7, 'News', 'News and media websites', 'fas fa-newspaper', '#6c757d', 1),
(8, 'Educational', 'Educational and learning platforms', 'fas fa-graduation-cap', '#28a745', 1),
(9, 'Health', 'Health and medical websites', 'fas fa-heartbeat', '#e83e8c', 1),
(10, 'Communication', 'Messaging and communication apps', 'fas fa-comments', '#6f42c1', 1),
(11, 'Productivity', 'Work and productivity tools', 'fas fa-briefcase', '#fd7e14', 1),
(12, 'Other', 'Miscellaneous websites', 'fas fa-globe', '#6c757d', 1);

-- --------------------------------------------------------

--
-- Table structure for table `device`
--

CREATE TABLE `device` (
  `id` int(11) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `timelimit` int(11) DEFAULT 24,
  `blocked_until` datetime DEFAULT NULL,
  `bandwidth` int(11) DEFAULT 1,
  `internet` varchar(10) DEFAULT 'No',
  `last_seen` timestamp DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `device`
--

INSERT INTO `device` (`id`, `device_name`, `mac_address`, `ip_address`, `device_type`, `status`, `timelimit`, `bandwidth`, `internet`) VALUES
(1, 'Test Device', '00:11:22:33:44:55', '192.168.1.100', 'laptop', 'active', 8, 2, 'No');

-- --------------------------------------------------------

--
-- Table structure for table `device_sessions`
--

CREATE TABLE `device_sessions` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `session_start` timestamp DEFAULT CURRENT_TIMESTAMP,
  `session_end` timestamp NULL DEFAULT NULL,
  `status` enum('active','ended','blocked') DEFAULT 'active',
  `data_usage` bigint(20) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_block`
--

CREATE TABLE `group_block` (
  `id` int(11) NOT NULL,
  `category` text DEFAULT NULL,
  `website` text DEFAULT NULL,
  `from_age` varchar(255) DEFAULT NULL,
  `to_age` varchar(255) DEFAULT NULL,
  `block_type` enum('permanent','scheduled','time_based') DEFAULT 'permanent',
  `schedule_start` time DEFAULT NULL,
  `schedule_end` time DEFAULT NULL,
  `blocking_method` enum('drop','redirect') DEFAULT 'drop',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `group_block`
--

INSERT INTO `group_block` (`id`, `category`, `website`, `from_age`, `to_age`, `blocking_method`, `is_active`) VALUES
(1, 'Adult Content', 'pornhub.com', '1', '17', 'drop', 1),
(2, 'Adult Content', 'xvideos.com', '1', '17', 'drop', 1),
(3, 'Adult Content', 'xnxx.com', '1', '17', 'drop', 1),
(4, 'Adult Content', 'redtube.com', '1', '17', 'drop', 1),
(5, 'Adult Content', 'youporn.com', '1', '17', 'drop', 1),
(6, 'Adult Content', 'brazzers.com', '1', '17', 'drop', 1),
(7, 'Adult Content', 'onlyfans.com', '1', '17', 'drop', 1),
(8, 'Adult Content', 'chaturbate.com', '1', '17', 'drop', 1),
(9, 'Gambling', 'bet365.com', '1', '17', 'drop', 1),
(10, 'Gambling', '1xbet.com', '1', '17', 'drop', 1),
(11, 'Gambling', 'pinnacle.com', '1', '17', 'drop', 1),
(12, 'Gambling', 'draftkings.com', '1', '17', 'drop', 1),
(13, 'Gambling', 'fanduel.com', '1', '17', 'drop', 1),
(14, 'Gambling', '888casino.com', '1', '17', 'drop', 1),
(15, 'Gambling', 'betfair.com', '1', '17', 'drop', 1);

-- --------------------------------------------------------

--
-- Table structure for table `group_whitelist`
--

CREATE TABLE `group_whitelist` (
  `id` int(11) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `website` text DEFAULT NULL,
  `from_age` varchar(255) DEFAULT NULL,
  `to_age` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `group_whitelist`
--

INSERT INTO `group_whitelist` (`id`, `category`, `website`, `from_age`, `to_age`, `reason`, `is_active`) VALUES
(1, 'Educational', 'khanacademy.org', '5', '18', 'Free educational content', 1),
(2, 'Educational', 'coursera.org', '13', '30', 'Online courses', 1),
(3, 'Educational', 'edx.org', '13', '30', 'University courses', 1),
(4, 'Educational', 'udemy.com', '13', '30', 'Skill development', 1),
(5, 'Educational', 'w3schools.com', '10', '30', 'Programming tutorials', 1),
(6, 'Educational', 'codecademy.com', '10', '30', 'Coding education', 1),
(7, 'Educational', 'duolingo.com', '8', '30', 'Language learning', 1),
(8, 'News & Media', 'bbc.com', '12', '30', 'Reliable news source', 1),
(9, 'News & Media', 'cnn.com', '12', '30', 'International news', 1),
(10, 'Health & Wellness', 'webmd.com', '16', '30', 'Health information', 1);

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `action` enum('blocked','allowed','redirected') DEFAULT 'blocked',
  `reason` varchar(255) DEFAULT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `predefined_applications`
--

CREATE TABLE `predefined_applications` (
    `id` int(11) NOT NULL,
    `name` varchar(100) NOT NULL,
    `category_id` int(11) NOT NULL,
    `domains` text NOT NULL,
    `ports` text,
    `protocols` text,
    `description` text,
    `icon` varchar(100),
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `predefined_applications`
--

INSERT INTO `predefined_applications` (`id`, `name`, `category_id`, `domains`, `ports`, `protocols`, `description`, `icon`) VALUES
-- Gaming Applications
(1, 'Fortnite', 1, 'fortnite.com,epicgames.com,unrealengine.com', '80,443,5222,5223', 'fortnite', 'Epic Games Battle Royale', 'fas fa-gamepad'),
(2, 'PUBG', 1, 'pubg.com,krafton.com,battlegrounds.com', '7000-7999,8000-8999', 'pubg', 'PlayerUnknown\'s Battlegrounds', 'fas fa-gamepad'),
(3, 'Minecraft', 1, 'minecraft.net,mojang.com', '25565,25575', 'minecraft', 'Sandbox building game', 'fas fa-cube'),
(4, 'Roblox', 1, 'roblox.com,rbxcdn.com', '53,80,443', 'roblox', 'Online gaming platform', 'fas fa-gamepad'),
(5, 'Steam', 1, 'steampowered.com,steamcommunity.com,steamstatic.com', '27000-27100', 'steam', 'Gaming platform', 'fas fa-steam'),

-- Social Media Applications
(6, 'Facebook', 2, 'facebook.com,fb.com,fbcdn.net', '80,443', 'facebook', 'Social networking platform', 'fab fa-facebook'),
(7, 'Instagram', 2, 'instagram.com,cdninstagram.com,fbcdn.net', '80,443', 'instagram', 'Photo and video sharing', 'fab fa-instagram'),
(8, 'TikTok', 2, 'tiktok.com,musically.com,musical.ly,tiktokcdn.com', '80,443', 'tiktok', 'Short video platform', 'fab fa-tiktok'),
(9, 'Twitter', 2, 'twitter.com,t.co,twimg.com,x.com', '80,443', 'twitter', 'Social networking and microblogging', 'fab fa-twitter'),
(10, 'Snapchat', 2, 'snapchat.com,sc-cdn.net', '80,443', 'snapchat', 'Multimedia messaging', 'fab fa-snapchat'),

-- Entertainment Applications
(11, 'YouTube', 3, 'youtube.com,youtu.be,googlevideo.com,ytimg.com', '80,443', 'youtube', 'Video sharing platform', 'fab fa-youtube'),
(12, 'Netflix', 3, 'netflix.com,nflxso.net,nflxext.com,nflximg.net', '80,443', 'netflix', 'Video streaming service', 'fas fa-film'),
(13, 'Spotify', 3, 'spotify.com,scdn.co,spoti.fi', '80,443,57621', 'spotify', 'Music streaming service', 'fab fa-spotify'),
(14, 'Twitch', 3, 'twitch.tv,twitchcdn.net,jtvnw.net', '80,443', 'twitch', 'Live streaming platform', 'fab fa-twitch'),
(15, 'Disney+', 3, 'disneyplus.com,disney.com,bamgrid.com', '80,443', 'disney', 'Disney streaming service', 'fas fa-film'),

-- Communication Applications
(16, 'WhatsApp', 4, 'whatsapp.com,whatsapp.net', '443,4244,5222', 'whatsapp', 'Messaging application', 'fab fa-whatsapp'),
(17, 'Telegram', 4, 'telegram.org,t.me,telegra.ph', '80,443', 'telegram', 'Cloud messaging app', 'fab fa-telegram'),
(18, 'Discord', 4, 'discord.com,discordapp.com,discord.gg', '80,443,50000-65535', 'discord', 'Gaming communication platform', 'fab fa-discord'),
(19, 'Zoom', 4, 'zoom.us,zoom.com', '80,443,8801,8802', 'zoom', 'Video conferencing', 'fas fa-video'),
(20, 'Skype', 4, 'skype.com,live.com', '80,443,1024-65535', 'skype', 'Video calling service', 'fab fa-skype');

-- --------------------------------------------------------

--
-- Table structure for table `whitelist`
--

CREATE TABLE `whitelist` (
  `id` int(11) NOT NULL,
  `website` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `whitelist`
--

INSERT INTO `whitelist` (`id`, `website`, `category`, `reason`, `is_active`) VALUES
(1, 'fast.com', 'Utility', 'Internet speed testing', 1),
(2, 'google.com', 'Search Engine', 'Primary search engine', 1),
(3, 'wikipedia.org', 'Educational', 'Educational resource', 1),
(4, 'github.com', 'Development', 'Code repository', 1),
(5, 'stackoverflow.com', 'Development', 'Programming help', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`user_id`) USING BTREE,
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- Indexes for table `application_blocks`
--
ALTER TABLE `application_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `status` (`status`),
  ADD KEY `application_category` (`application_category`),
  ADD KEY `idx_blocked_at` (`blocked_at`);

--
-- Indexes for table `application_categories`
--
ALTER TABLE `application_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `blocking_log`
--
ALTER TABLE `blocking_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `blocked_at` (`blocked_at`),
  ADD KEY `idx_blocked_domain` (`blocked_domain`);

--
-- Indexes for table `blocklist`
--
ALTER TABLE `blocklist`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `category` (`category`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_blocking_method` (`blocking_method`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `is_active` (`is_active`),
  ADD UNIQUE KEY `unique_category_name` (`category_name`);

--
-- Indexes for table `device`
--
ALTER TABLE `device`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `mac_address` (`mac_address`),
  ADD KEY `status` (`status`),
  ADD KEY `last_seen` (`last_seen`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Indexes for table `device_sessions`
--
ALTER TABLE `device_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mac_address` (`mac_address`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `session_start` (`session_start`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `group_block`
--
ALTER TABLE `group_block`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `category` (`category`(100)),
  ADD KEY `from_age` (`from_age`),
  ADD KEY `to_age` (`to_age`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_blocking_method` (`blocking_method`);

--
-- Indexes for table `group_whitelist`
--
ALTER TABLE `group_whitelist`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `category` (`category`),
  ADD KEY `from_age` (`from_age`),
  ADD KEY `to_age` (`to_age`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `device_id` (`device_id`),
  ADD KEY `action` (`action`),
  ADD KEY `date` (`date`),
  ADD KEY `idx_domain` (`domain`);

--
-- Indexes for table `predefined_applications`
--
ALTER TABLE `predefined_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD UNIQUE KEY `unique_app_name` (`name`);

--
-- Indexes for table `whitelist`
--
ALTER TABLE `whitelist`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `category` (`category`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `application_blocks`
--
ALTER TABLE `application_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_categories`
--
ALTER TABLE `application_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `blocking_log`
--
ALTER TABLE `blocking_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocklist`
--
ALTER TABLE `blocklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `device`
--
ALTER TABLE `device`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `device_sessions`
--
ALTER TABLE `device_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_block`
--
ALTER TABLE `group_block`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `group_whitelist`
--
ALTER TABLE `group_whitelist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `predefined_applications`
--
ALTER TABLE `predefined_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `whitelist`
--
ALTER TABLE `whitelist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `application_blocks`
--
ALTER TABLE `application_blocks`
  ADD CONSTRAINT `fk_application_blocks_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blocking_log`
--
ALTER TABLE `blocking_log`
  ADD CONSTRAINT `fk_blocking_log_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `device_sessions`
--
ALTER TABLE `device_sessions`
  ADD CONSTRAINT `fk_device_sessions_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `predefined_applications`
--
ALTER TABLE `predefined_applications`
  ADD CONSTRAINT `fk_predefined_apps_category` FOREIGN KEY (`category_id`) REFERENCES `application_categories` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
