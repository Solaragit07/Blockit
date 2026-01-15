-- E-Commerce filtering tables
CREATE TABLE IF NOT EXISTS ecommerce_settings (
  id TINYINT PRIMARY KEY DEFAULT 1,
  block_access TINYINT(1) NOT NULL DEFAULT 0,
  block_purchases TINYINT(1) NOT NULL DEFAULT 0,
  notifications TINYINT(1) NOT NULL DEFAULT 0,
  notification_methods JSON NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO ecommerce_settings (id)
SELECT 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM ecommerce_settings WHERE id = 1);

CREATE TABLE IF NOT EXISTS ecommerce_platforms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  url VARCHAR(255) NOT NULL,
  access ENUM('browsing','full','blocked') NOT NULL DEFAULT 'browsing',
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
