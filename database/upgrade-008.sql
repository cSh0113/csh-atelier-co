-- CSH Atelier production upgrade 008
-- Adds WhatsApp-style presence and message timestamps.
ALTER TABLE users
  ADD COLUMN last_seen_at DATETIME NULL AFTER notify_marketing;

ALTER TABLE messages
  ADD COLUMN read_at DATETIME NULL AFTER is_read,
  ADD INDEX idx_msg_thread (sender_id, receiver_id, created_at),
  ADD INDEX idx_msg_unread (receiver_id, is_read);
