-- Application Blocks Table
CREATE TABLE IF NOT EXISTS `application_blocks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
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
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `device_id` (`device_id`),
    KEY `status` (`status`),
    KEY `application_category` (`application_category`),
    FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Application Categories Table
CREATE TABLE IF NOT EXISTS `application_categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `description` text,
    `icon` varchar(50) DEFAULT 'fas fa-desktop',
    `color` varchar(20) DEFAULT '#6c757d',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default application categories
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

-- Predefined Applications Table
CREATE TABLE IF NOT EXISTS `predefined_applications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `category_id` int(11) NOT NULL,
    `domains` text NOT NULL,
    `ports` text,
    `protocols` text,
    `description` text,
    `icon` varchar(100),
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `category_id` (`category_id`),
    FOREIGN KEY (`category_id`) REFERENCES `application_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert predefined applications
INSERT INTO `predefined_applications` (`name`, `category_id`, `domains`, `ports`, `protocols`, `description`, `icon`) VALUES
-- Gaming
('Fortnite', 1, 'fortnite.com,epicgames.com,unrealengine.com', '80,443,5222,5223', 'fortnite', 'Epic Games Battle Royale', 'https://cdn2.unrealengine.com/fortnite-logo-1920x1080-044eb15a968c.jpg'),
('PUBG', 1, 'pubg.com,krafton.com,battlegrounds.com', '7000-7999,8000-8999', 'pubg', 'PlayerUnknown\'s Battlegrounds', ''),
('Minecraft', 1, 'minecraft.net,mojang.com', '25565,25575', 'minecraft', 'Sandbox building game', ''),
('Roblox', 1, 'roblox.com,rbxcdn.com', '53,80,443', 'roblox', 'Online gaming platform', ''),
('Steam', 1, 'steampowered.com,steamcommunity.com,steamstatic.com', '27000-27100', 'steam', 'Gaming platform', ''),

-- Social Media
('Facebook', 2, 'facebook.com,fb.com,fbcdn.net,instagram.com', '80,443', 'facebook', 'Social networking platform', ''),
('Instagram', 2, 'instagram.com,cdninstagram.com,fbcdn.net', '80,443', 'instagram', 'Photo and video sharing', ''),
('TikTok', 2, 'tiktok.com,musically.com,musical.ly,tiktokcdn.com', '80,443', 'tiktok', 'Short video platform', ''),
('Twitter', 2, 'twitter.com,t.co,twimg.com,x.com', '80,443', 'twitter', 'Social networking and microblogging', ''),
('Snapchat', 2, 'snapchat.com,sc-cdn.net', '80,443', 'snapchat', 'Multimedia messaging', ''),

-- Entertainment
('YouTube', 3, 'youtube.com,youtu.be,googlevideo.com,ytimg.com', '80,443', 'youtube', 'Video sharing platform', ''),
('Netflix', 3, 'netflix.com,nflxso.net,nflxext.com,nflximg.net', '80,443', 'netflix', 'Video streaming service', ''),
('Spotify', 3, 'spotify.com,scdn.co,spoti.fi', '80,443,57621', 'spotify', 'Music streaming service', ''),
('Twitch', 3, 'twitch.tv,twitchcdn.net,jtvnw.net', '80,443', 'twitch', 'Live streaming platform', ''),
('Disney+', 3, 'disneyplus.com,disney.com,bamgrid.com', '80,443', 'disney', 'Disney streaming service', ''),

-- Communication
('WhatsApp', 4, 'whatsapp.com,whatsapp.net', '443,4244,5222', 'whatsapp', 'Messaging application', ''),
('Telegram', 4, 'telegram.org,t.me,telegra.ph', '80,443', 'telegram', 'Cloud messaging app', ''),
('Discord', 4, 'discord.com,discordapp.com,discord.gg', '80,443,50000-65535', 'discord', 'Gaming communication platform', ''),
('Zoom', 4, 'zoom.us,zoom.com', '80,443,8801,8802', 'zoom', 'Video conferencing', ''),
('Skype', 4, 'skype.com,live.com', '80,443,1024-65535', 'skype', 'Video calling service', ''),

-- E-commerce
('Amazon', 5, 'amazon.com,amazonwebservices.com,ssl-images-amazon.com', '80,443', 'amazon', 'Online shopping platform', ''),
('eBay', 5, 'ebay.com,ebayimg.com,ebaycdn.net', '80,443', 'ebay', 'Online auction platform', ''),
('Shopee', 5, 'shopee.com,shopee.ph,shp.ee', '80,443', 'shopee', 'E-commerce platform', ''),
('Lazada', 5, 'lazada.com,lazada.ph,lzd.co', '80,443', 'lazada', 'Online shopping platform', '');
