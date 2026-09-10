-- CSH Atelier production upgrade 010
-- Removes optional location storage and adds WhatsApp-style delete-for-me flags.
ALTER TABLE users
  DROP COLUMN location_lat,
  DROP COLUMN location_lng;

ALTER TABLE messages
  ADD COLUMN deleted_for_sender TINYINT(1) NOT NULL DEFAULT 0 AFTER read_at,
  ADD COLUMN deleted_for_receiver TINYINT(1) NOT NULL DEFAULT 0 AFTER deleted_for_sender,
  ADD INDEX idx_msg_sender_deleted (sender_id, deleted_for_sender),
  ADD INDEX idx_msg_receiver_deleted (receiver_id, deleted_for_receiver);
