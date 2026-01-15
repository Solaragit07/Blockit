-- Add created_at column to admin table if it doesn't exist
ALTER TABLE `admin` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
