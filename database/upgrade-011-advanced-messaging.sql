-- CSH Atelier Co. upgrade 011: advanced messaging
-- Run once after the base schema and prior upgrades in phpMyAdmin.
-- This migration assumes the base messages and member_reports tables already exist.

ALTER TABLE messages
  ADD COLUMN kind ENUM('text','sticker','gif','image','listing','system') NOT NULL DEFAULT 'text' AFTER body,
  ADD COLUMN media_ref VARCHAR(120) NULL AFTER kind,
  ADD COLUMN reply_to INT NULL AFTER media_ref,
  ADD COLUMN edited_at DATETIME NULL AFTER read_at,
  ADD COLUMN delivered_at DATETIME NULL AFTER read_at,
  ADD COLUMN starred_by_sender TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN starred_by_receiver TINYINT(1) NOT NULL DEFAULT 0,
  ADD CONSTRAINT fk_msg_reply FOREIGN KEY (reply_to) REFERENCES messages(id) ON DELETE SET NULL,
  ADD INDEX idx_msg_pair (sender_id, receiver_id, created_at);

CREATE TABLE conversation_state (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  partner_id INT NOT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  is_muted TINYINT(1) NOT NULL DEFAULT 0,
  cleared_at DATETIME NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_convo (owner_id, partner_id),
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE message_reactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  user_id INT NOT NULL,
  emoji VARCHAR(16) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_react (message_id, user_id),
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
  ADD COLUMN typing_to INT NULL,
  ADD COLUMN typing_at DATETIME NULL;

ALTER TABLE member_reports
  ADD COLUMN message_id INT NULL AFTER listing_id,
  ADD CONSTRAINT fk_member_report_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL;
