-- phpMyAdmin SQL Dump
-- BlockIt Complete Database Schema
-- Created: July 23, 2025
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
-- Database: `blockit`
--

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
(1, 'admin@gmail.com', '123', 'admins', 'ACTIVE', 'images.png');

-- --------------------------------------------------------

--
-- Table structure for table `application_blocks`
--

CREATE TABLE `application_blocks` (
    `id` int(11) NOT NULL,
    `device_id` int(11) DEFAULT NULL,
    `application_name` varchar(100) NOT NULL,
    `application_category` varchar(50) NOT NULL,
    `block_type` enum('complete','time_based','bandwidth_limit','content_filter') DEFAULT 'complete',
    `duration` int(11) DEFAULT 24,
    `reason` text,
    `status` enum('active','inactive') DEFAULT 'active',
    `domains` text,
    `ports` text,
    `protocols` text,
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
    `description` text,
    `icon` varchar(50) DEFAULT 'fas fa-desktop',
    `color` varchar(20) DEFAULT '#6c757d',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_categories`
--

INSERT INTO `application_categories` (`name`, `description`, `icon`, `color`) VALUES
('Gaming', 'Video games and gaming platforms', 'fas fa-gamepad', '#dc3545'),
('Social Media', 'Social networking platforms', 'fas fa-users', '#17a2b8'),
('Entertainment', 'Streaming and media platforms', 'fas fa-play-circle', '#6f42c1'),
('Communication', 'Messaging and video calling apps', 'fas fa-comments', '#28a745'),
('E-commerce', 'Online shopping platforms', 'fas fa-shopping-cart', '#fd7e14'),
('Education', 'Learning and educational platforms', 'fas fa-graduation-cap', '#20c997'),
('News', 'News and information websites', 'fas fa-newspaper', '#6c757d'),
('Adult Content', 'Adult and inappropriate content', 'fas fa-exclamation-triangle', '#dc3545'),
('File Sharing', 'File sharing and torrent sites', 'fas fa-download', '#ffc107'),
('Malware', 'Malicious and security threats', 'fas fa-virus', '#dc3545');

-- --------------------------------------------------------

--
-- Table structure for table `blocking_log`
--

CREATE TABLE `blocking_log` (
  `id` int(11) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL,
  `block_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocklist`
--

CREATE TABLE `blocklist` (
  `id` int(11) NOT NULL,
  `website` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `blocklist`
--

INSERT INTO `blocklist` (`id`, `website`, `category`) VALUES
(1, 'pornhub.com', 'Adult Content'),
(2, 'xvideos.com', 'Adult Content'),
(3, 'xnxx.com', 'Adult Content'),
(4, 'bet365.com', 'Gambling'),
(5, 'facebook.com', 'Social Media');

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6c757d',
  `icon` varchar(50) DEFAULT 'fas fa-folder',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`id`, `name`, `description`, `color`, `icon`) VALUES
(1, 'Educational', 'Educational and learning websites', '#28a745', 'fas fa-graduation-cap'),
(2, 'News & Media', 'News and media websites', '#17a2b8', 'fas fa-newspaper'),
(3, 'Health & Wellness', 'Health and wellness related sites', '#20c997', 'fas fa-heartbeat'),
(4, 'Government & Public Service', 'Government and public service sites', '#6c757d', 'fas fa-landmark'),
(5, 'E-commerce', 'Online shopping platforms', '#fd7e14', 'fas fa-shopping-cart'),
(6, 'Banking & Finance', 'Banking and financial services', '#007bff', 'fas fa-university'),
(7, 'Productivity', 'Productivity and work tools', '#6f42c1', 'fas fa-briefcase'),
(8, 'Job & Career', 'Job search and career sites', '#e83e8c', 'fas fa-user-tie'),
(9, 'Travel & Maps', 'Travel and navigation sites', '#20c997', 'fas fa-map-marked-alt'),
(10, 'Adult Content', 'Adult and inappropriate content', '#dc3545', 'fas fa-exclamation-triangle'),
(11, 'Gambling', 'Gambling and betting sites', '#dc3545', 'fas fa-dice'),
(12, 'Gaming', 'Gaming platforms and sites', '#6f42c1', 'fas fa-gamepad');

-- --------------------------------------------------------

--
-- Table structure for table `device`
--

CREATE TABLE `device` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `device` varchar(255) DEFAULT NULL,
  `mac_address` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `bandwidth` int(11) NOT NULL DEFAULT 0,
  `internet` varchar(50) NOT NULL DEFAULT 'Yes',
  `timelimit` int(11) NOT NULL DEFAULT 24,
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `last_seen` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `device`
--

INSERT INTO `device` (`id`, `name`, `device`, `mac_address`, `age`, `image`, `bandwidth`, `internet`, `timelimit`) VALUES
(1, 'jeann', 'PC', '1c:ab:48:be:0f:4f', 5, '360_F_243123463_zTooub557xEWABDLk0jJklDyLSGl2jrr.jpg', 2, 'No', 1);

-- --------------------------------------------------------

--
-- Table structure for table `device_sessions`
--

CREATE TABLE `device_sessions` (
  `id` int(11) NOT NULL,
  `mac_address` varchar(17) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `session_start` datetime NOT NULL,
  `session_end` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `data_usage_mb` decimal(10,2) DEFAULT 0.00,
  `websites_visited` int(11) DEFAULT 0,
  `blocked_attempts` int(11) DEFAULT 0,
  `status` enum('active','ended') DEFAULT 'active',
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
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `group_block`
--

INSERT INTO `group_block` (`id`, `category`, `website`, `from_age`, `to_age`) VALUES
(1, 'Adult Content', 'pornhub.com', '1', '17'),
(2, 'Adult Content', 'xvideos.com', '1', '17'),
(3, 'Adult Content', 'xnxx.com', '1', '17'),
(4, 'Adult Content', 'redtube.com', '1', '17'),
(5, 'Adult Content', 'youporn.com', '1', '17'),
(6, 'Adult Content', 'brazzers.com', '1', '17'),
(7, 'Adult Content', 'onlyfans.com', '1', '17'),
(8, 'Adult Content', 'chaturbate.com', '1', '17'),
(9, 'Gambling', 'bet365.com', '1', '17'),
(10, 'Gambling', '1xbet.com', '1', '17'),
(11, 'Gambling', 'pinnacle.com', '1', '17'),
(12, 'Gambling', 'draftkings.com', '1', '17'),
(13, 'Gambling', 'fanduel.com', '1', '17'),
(14, 'Gambling', '888casino.com', '1', '17'),
(15, 'Gambling', 'betfair.com', '1', '17');

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
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `group_whitelist`
--

INSERT INTO `group_whitelist` (`id`, `category`, `website`, `from_age`, `to_age`, `reason`) VALUES
(1, 'Educational', 'khanacademy.org', '5', '18', 'Free educational content'),
(2, 'Educational', 'coursera.org', '13', '30', 'Online courses'),
(3, 'Educational', 'edx.org', '13', '30', 'University courses'),
(4, 'Educational', 'udemy.com', '13', '30', 'Skill development'),
(5, 'Educational', 'w3schools.com', '10', '30', 'Programming tutorials'),
(6, 'Educational', 'codecademy.com', '10', '30', 'Coding education'),
(7, 'Educational', 'duolingo.com', '8', '30', 'Language learning'),
(8, 'News & Media', 'bbc.com', '12', '30', 'Reliable news source'),
(9, 'News & Media', 'cnn.com', '12', '30', 'International news'),
(10, 'Health & Wellness', 'webmd.com', '16', '30', 'Health information');

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
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
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
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `predefined_applications`
--

INSERT INTO `predefined_applications` (`name`, `category_id`, `domains`, `ports`, `protocols`, `description`, `icon`) VALUES
-- Gaming Applications
('Fortnite', 1, 'fortnite.com,epicgames.com,unrealengine.com', '80,443,5222,5223', 'fortnite', 'Epic Games Battle Royale', 'fas fa-gamepad'),
('PUBG', 1, 'pubg.com,krafton.com,battlegrounds.com', '7000-7999,8000-8999', 'pubg', 'PlayerUnknown\'s Battlegrounds', 'fas fa-gamepad'),
('Minecraft', 1, 'minecraft.net,mojang.com', '25565,25575', 'minecraft', 'Sandbox building game', 'fas fa-cube'),
('Roblox', 1, 'roblox.com,rbxcdn.com', '53,80,443', 'roblox', 'Online gaming platform', 'fas fa-gamepad'),
('Steam', 1, 'steampowered.com,steamcommunity.com,steamstatic.com', '27000-27100', 'steam', 'Gaming platform', 'fas fa-steam'),

-- Social Media Applications
('Facebook', 2, 'facebook.com,fb.com,fbcdn.net', '80,443', 'facebook', 'Social networking platform', 'fab fa-facebook'),
('Instagram', 2, 'instagram.com,cdninstagram.com,fbcdn.net', '80,443', 'instagram', 'Photo and video sharing', 'fab fa-instagram'),
('TikTok', 2, 'tiktok.com,musically.com,musical.ly,tiktokcdn.com', '80,443', 'tiktok', 'Short video platform', 'fab fa-tiktok'),
('Twitter', 2, 'twitter.com,t.co,twimg.com,x.com', '80,443', 'twitter', 'Social networking and microblogging', 'fab fa-twitter'),
('Snapchat', 2, 'snapchat.com,sc-cdn.net', '80,443', 'snapchat', 'Multimedia messaging', 'fab fa-snapchat'),

-- Entertainment Applications
('YouTube', 3, 'youtube.com,youtu.be,googlevideo.com,ytimg.com', '80,443', 'youtube', 'Video sharing platform', 'fab fa-youtube'),
('Netflix', 3, 'netflix.com,nflxso.net,nflxext.com,nflximg.net', '80,443', 'netflix', 'Video streaming service', 'fas fa-film'),
('Spotify', 3, 'spotify.com,scdn.co,spoti.fi', '80,443,57621', 'spotify', 'Music streaming service', 'fab fa-spotify'),
('Twitch', 3, 'twitch.tv,twitchcdn.net,jtvnw.net', '80,443', 'twitch', 'Live streaming platform', 'fab fa-twitch'),
('Disney+', 3, 'disneyplus.com,disney.com,bamgrid.com', '80,443', 'disney', 'Disney streaming service', 'fas fa-film'),

-- Communication Applications
('WhatsApp', 4, 'whatsapp.com,whatsapp.net', '443,4244,5222', 'whatsapp', 'Messaging application', 'fab fa-whatsapp'),
('Telegram', 4, 'telegram.org,t.me,telegra.ph', '80,443', 'telegram', 'Cloud messaging app', 'fab fa-telegram'),
('Discord', 4, 'discord.com,discordapp.com,discord.gg', '80,443,50000-65535', 'discord', 'Gaming communication platform', 'fab fa-discord'),
('Zoom', 4, 'zoom.us,zoom.com', '80,443,8801,8802', 'zoom', 'Video conferencing', 'fas fa-video'),
('Skype', 4, 'skype.com,live.com', '80,443,1024-65535', 'skype', 'Video calling service', 'fab fa-skype');

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
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `whitelist`
--

INSERT INTO `whitelist` (`id`, `website`, `category`, `reason`) VALUES
(1, 'fast.com', 'Utility', 'Internet speed testing'),
(2, 'google.com', 'Search Engine', 'Primary search engine'),
(3, 'wikipedia.org', 'Educational', 'Educational resource'),
(4, 'github.com', 'Development', 'Code repository'),
(5, 'stackoverflow.com', 'Development', 'Programming help');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`user_id`) USING BTREE;

--
-- Indexes for table `application_blocks`
--
ALTER TABLE `application_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `status` (`status`),
  ADD KEY `application_category` (`application_category`);

--
-- Indexes for table `application_categories`
--
ALTER TABLE `application_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `blocking_log`
--
ALTER TABLE `blocking_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `blocked_at` (`blocked_at`);

--
-- Indexes for table `blocklist`
--
ALTER TABLE `blocklist`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `category` (`category`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `device`
--
ALTER TABLE `device`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `mac_address` (`mac_address`),
  ADD KEY `status` (`status`),
  ADD KEY `last_seen` (`last_seen`);

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
  ADD KEY `to_age` (`to_age`);

--
-- Indexes for table `group_whitelist`
--
ALTER TABLE `group_whitelist`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `category` (`category`),
  ADD KEY `from_age` (`from_age`),
  ADD KEY `to_age` (`to_age`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `device_id` (`device_id`),
  ADD KEY `action` (`action`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `predefined_applications`
--
ALTER TABLE `predefined_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `whitelist`
--
ALTER TABLE `whitelist`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `category` (`category`),
  ADD KEY `added_by` (`added_by`);

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
-- Constraints for table `predefined_applications`
--
ALTER TABLE `predefined_applications`
  ADD CONSTRAINT `fk_predefined_apps_category` FOREIGN KEY (`category_id`) REFERENCES `application_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `device_sessions`
--
ALTER TABLE `device_sessions`
  ADD CONSTRAINT `fk_device_sessions_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
