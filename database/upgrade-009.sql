-- CSH Atelier production upgrade 009
-- Member profiles, ratings, blocking/reporting, and listing audience controls.
ALTER TABLE users
  ADD COLUMN profile_visibility ENUM('public','members') NOT NULL DEFAULT 'public';

ALTER TABLE listings
  ADD COLUMN visibility ENUM('public','members') NOT NULL DEFAULT 'public',
  ADD INDEX idx_listings_visibility (visibility);

CREATE TABLE IF NOT EXISTS member_blocks (
  blocker_id INT NOT NULL,
  blocked_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (blocker_id, blocked_id),
  FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT NOT NULL,
  reported_id INT NOT NULL,
  listing_id INT NULL,
  reason ENUM('fraud','harassment','inappropriate','spam','other') NOT NULL DEFAULT 'other',
  details VARCHAR(1000) NOT NULL,
  status ENUM('open','reviewed','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  INDEX idx_reports_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rater_id INT NOT NULL,
  rated_id INT NOT NULL,
  listing_id INT NULL,
  order_id INT NULL,
  communication TINYINT NOT NULL,
  trust TINYINT NOT NULL,
  comment VARCHAR(600) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_rating (rater_id, rated_id, listing_id),
  FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (rated_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  INDEX idx_rated_member (rated_id)
) ENGINE=InnoDB;
