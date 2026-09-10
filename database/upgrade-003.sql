-- CSH Atelier production upgrade 003
-- Run once on an existing database.
ALTER TABLE users
  ADD COLUMN newsletter_subscribed TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN notify_orders TINYINT(1) NOT NULL DEFAULT 1 AFTER newsletter_subscribed,
  ADD COLUMN notify_messages TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_orders,
  ADD COLUMN notify_marketing TINYINT(1) NOT NULL DEFAULT 0 AFTER notify_messages;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  user_id INT NULL,
  status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
