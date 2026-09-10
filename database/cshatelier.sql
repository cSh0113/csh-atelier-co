-- ============================================================
--  CSH ATELIER CO., Circular Fashion Platform
--  B2C (brand sells) + C2C (users sell) + C2B (users donate)
--  MySQL / MariaDB schema
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS cshatelier
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cshatelier;

-- ------------------------------------------------------------
-- USERS  (buyers, sellers, admins, one table, role column)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(120)  NOT NULL,
  email           VARCHAR(190)  NOT NULL UNIQUE,
  password_hash   VARCHAR(255)  NOT NULL,
  role            ENUM('member','admin') NOT NULL DEFAULT 'member',
  phone           VARCHAR(30)   DEFAULT NULL,
  bio             VARCHAR(400)  DEFAULT NULL,
  avatar_url      VARCHAR(255)  DEFAULT NULL,
  city            VARCHAR(80)   DEFAULT NULL,
  province        VARCHAR(80)   DEFAULT NULL,
  credits         INT           NOT NULL DEFAULT 0,   -- store credit balance
  items_diverted  INT           NOT NULL DEFAULT 0,   -- impact counter
  status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
  newsletter_subscribed TINYINT(1) NOT NULL DEFAULT 0,
  notify_orders   TINYINT(1) NOT NULL DEFAULT 1,
  notify_messages TINYINT(1) NOT NULL DEFAULT 1,
  notify_marketing TINYINT(1) NOT NULL DEFAULT 0,
  last_seen_at   DATETIME       DEFAULT NULL,
  typing_to      INT            DEFAULT NULL,
  typing_at      DATETIME       DEFAULT NULL,
  profile_visibility ENUM('public','members') NOT NULL DEFAULT 'public',
  created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE newsletter_subscribers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  user_id INT NULL,
  status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- CAUSES  (the "message" each garment carries)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS causes;
CREATE TABLE causes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL UNIQUE,
  tagline     VARCHAR(180) DEFAULT NULL,
  description TEXT,
  icon        VARCHAR(40)  DEFAULT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CATEGORIES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  name   VARCHAR(80) NOT NULL,
  slug   VARCHAR(80) NOT NULL UNIQUE,
  kind   ENUM('clothing','accessory','other') NOT NULL DEFAULT 'clothing'
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PRODUCTS  (B2C, the brand's own stock, incl. REMADE items)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS products;
CREATE TABLE products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(180)   NOT NULL,
  slug         VARCHAR(190)   NOT NULL UNIQUE,
  description  TEXT,
  message      TEXT,
  price        DECIMAL(10,2)  NOT NULL,
  stock        INT            NOT NULL DEFAULT 0,
  category_id  INT            DEFAULT NULL,
  cause_id     INT            DEFAULT NULL,
  image_url    VARCHAR(255)   DEFAULT NULL,
  image_hover  VARCHAR(255)   DEFAULT NULL,
  -- circular attributes
  is_remade    TINYINT(1)     NOT NULL DEFAULT 0,  -- made from donated stock
  material     VARCHAR(160)   DEFAULT NULL,        -- e.g. "100% recycled cotton"
  recycled_pct INT            NOT NULL DEFAULT 0,  -- % recycled content
  featured     TINYINT(1)     NOT NULL DEFAULT 0,
  status       ENUM('active','hidden') NOT NULL DEFAULT 'active',
  external_url VARCHAR(500) DEFAULT NULL,
  external_source VARCHAR(120) DEFAULT NULL,
  created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (cause_id)    REFERENCES causes(id)     ON DELETE SET NULL,
  INDEX idx_products_status (status),
  INDEX idx_products_external (external_source),
  FULLTEXT KEY ft_products (name, description)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- LISTINGS  (C2C, items listed for sale BY users)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS listings;
CREATE TABLE listings (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  seller_id     INT            NOT NULL,
  title         VARCHAR(180)   NOT NULL,
  slug          VARCHAR(190)   NOT NULL UNIQUE,
  description   TEXT,
  price         DECIMAL(10,2)  NOT NULL,
  category_id   INT            DEFAULT NULL,
  cause_id      INT            DEFAULT NULL,
  item_size     VARCHAR(20)    DEFAULT NULL,
  item_condition ENUM('new','like_new','good','well_loved') NOT NULL DEFAULT 'good',
  brand         VARCHAR(80)    DEFAULT NULL,
  colour        VARCHAR(40)    DEFAULT NULL,
  image_url     VARCHAR(255)   DEFAULT NULL,
  image_2       VARCHAR(255)   DEFAULT NULL,
  image_3       VARCHAR(255)   DEFAULT NULL,
  -- moderation + lifecycle
  status        ENUM('pending','active','sold','removed') NOT NULL DEFAULT 'pending',
  reject_reason VARCHAR(255)   DEFAULT NULL,
  visibility    ENUM('public','members') NOT NULL DEFAULT 'public',
  views         INT            NOT NULL DEFAULT 0,
  created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (seller_id)   REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (cause_id)    REFERENCES causes(id)     ON DELETE SET NULL,
  INDEX idx_listings_status (status),
  INDEX idx_listings_seller (seller_id),
  FULLTEXT KEY ft_listings (title, description)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- DONATIONS  (C2B, users send clothing back to the brand)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS donations;
CREATE TABLE donations (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT           NOT NULL,
  reference      VARCHAR(30)   NOT NULL UNIQUE,
  item_count     INT           NOT NULL DEFAULT 1,
  description    TEXT,
  photo_url      VARCHAR(255)  DEFAULT NULL,
  dropoff_method ENUM('courier','dropoff') NOT NULL DEFAULT 'dropoff',
  address        VARCHAR(255)  DEFAULT NULL,
  -- what the brand did with it
  outcome        ENUM('pending','remade','resold','recycled','declined')
                 NOT NULL DEFAULT 'pending',
  credits_awarded INT          NOT NULL DEFAULT 0,
  admin_note     VARCHAR(255)  DEFAULT NULL,
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  processed_at   DATETIME      DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_donations_outcome (outcome)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CREDIT LEDGER  (every credit movement, for transparency)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS credit_transactions;
CREATE TABLE credit_transactions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  amount      INT          NOT NULL,          -- +earn / -spend
  balance_after INT        NOT NULL,
  reason      ENUM('donation','sale','purchase','refund','admin_adjust','signup_bonus')
              NOT NULL,
  reference   VARCHAR(60)  DEFAULT NULL,      -- e.g. DON-1042 / ORD-2051
  note        VARCHAR(200) DEFAULT NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_credit_user (user_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CART  (holds BOTH brand products and user listings)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS cart_items;
CREATE TABLE cart_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          DEFAULT NULL,
  session_id  VARCHAR(120) DEFAULT NULL,   -- guest carts
  item_type   ENUM('product','listing') NOT NULL,
  item_id     INT          NOT NULL,
  qty         INT          NOT NULL DEFAULT 1,
  added_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_cart (user_id, session_id, item_type, item_id),
  INDEX idx_cart_session (session_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ORDERS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  order_no       VARCHAR(30)   NOT NULL UNIQUE,
  user_id        INT           DEFAULT NULL,
  full_name      VARCHAR(120)  NOT NULL,
  email          VARCHAR(190)  NOT NULL,
  phone          VARCHAR(30)   DEFAULT NULL,
  address        VARCHAR(255)  NOT NULL,
  city           VARCHAR(80)   NOT NULL,
  province       VARCHAR(80)   DEFAULT NULL,
  postal_code    VARCHAR(12)   DEFAULT NULL,
  subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping       DECIMAL(10,2) NOT NULL DEFAULT 0,
  credits_used   INT           NOT NULL DEFAULT 0,
  credit_value   DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_method ENUM('shipping','collection') NOT NULL DEFAULT 'shipping',
  payment_method ENUM('card','eft','credits') NOT NULL DEFAULT 'card',
  status         ENUM('pending','paid','shipped','delivered','cancelled')
                 NOT NULL DEFAULT 'pending',
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_status (status)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  order_id    INT           NOT NULL,
  item_type   ENUM('product','listing') NOT NULL,
  item_id     INT           NOT NULL,
  seller_id   INT           DEFAULT NULL,   -- NULL = sold by the brand
  title       VARCHAR(180)  NOT NULL,       -- snapshot
  unit_price  DECIMAL(10,2) NOT NULL,
  qty         INT           NOT NULL DEFAULT 1,
  line_total  DECIMAL(10,2) NOT NULL,
  payout_done TINYINT(1)    NOT NULL DEFAULT 0,  -- seller credited?
  FOREIGN KEY (order_id)  REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id)  ON DELETE SET NULL,
  INDEX idx_oi_seller (seller_id)
) ENGINE=InnoDB;

ALTER TABLE order_items
  ADD COLUMN seller_status ENUM('new','contacted','ready','collected') NOT NULL DEFAULT 'new' AFTER payout_done,
  ADD COLUMN seller_note VARCHAR(255) NULL AFTER seller_status;

CREATE TABLE wishlists (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  item_type ENUM('product','listing') NOT NULL, item_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wishlist (user_id,item_type,item_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, order_item_id INT NOT NULL,
  reviewer_id INT NOT NULL, item_type ENUM('product','listing') NOT NULL, item_id INT NOT NULL,
  rating TINYINT NOT NULL, body VARCHAR(600) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (order_item_id,reviewer_id), CHECK (rating BETWEEN 1 AND 5),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_blocks (
  blocker_id INT NOT NULL, blocked_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (blocker_id,blocked_id),
  FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_reports (
  id INT AUTO_INCREMENT PRIMARY KEY, reporter_id INT NOT NULL, reported_id INT NOT NULL,
  listing_id INT DEFAULT NULL, message_id INT DEFAULT NULL, reason ENUM('fraud','harassment','inappropriate','spam','other') NOT NULL DEFAULT 'other',
  details VARCHAR(1000) NOT NULL, status ENUM('open','reviewed','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  INDEX idx_reports_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_ratings (
  id INT AUTO_INCREMENT PRIMARY KEY, rater_id INT NOT NULL, rated_id INT NOT NULL,
  listing_id INT DEFAULT NULL, order_id INT DEFAULT NULL, communication TINYINT NOT NULL,
  trust TINYINT NOT NULL, comment VARCHAR(600) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_rating (rater_id,rated_id,listing_id),
  FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (rated_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  INDEX idx_rated_member (rated_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NOT NULL, entity_id INT NULL, details VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- MESSAGES  (buyer <-> seller about a listing)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS messages;
CREATE TABLE messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT          DEFAULT NULL,
  sender_id   INT          NOT NULL,
  receiver_id INT          NOT NULL,
  body        TEXT         NOT NULL,
  kind        ENUM('text','sticker','gif','image','listing','system') NOT NULL DEFAULT 'text',
  media_ref   VARCHAR(120) DEFAULT NULL,
  reply_to    INT          DEFAULT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  read_at     DATETIME     DEFAULT NULL,
  edited_at   DATETIME     DEFAULT NULL,
  delivered_at DATETIME    DEFAULT NULL,
  starred_by_sender TINYINT(1) NOT NULL DEFAULT 0,
  starred_by_receiver TINYINT(1) NOT NULL DEFAULT 0,
  deleted_for_sender TINYINT(1) NOT NULL DEFAULT 0,
  deleted_for_receiver TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id)  REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id)   REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (reply_to) REFERENCES messages(id) ON DELETE SET NULL,
  INDEX idx_msg_receiver (receiver_id),
  INDEX idx_msg_thread (sender_id, receiver_id, created_at),
  INDEX idx_msg_unread (receiver_id, is_read),
  INDEX idx_msg_sender_deleted (sender_id, deleted_for_sender),
  INDEX idx_msg_receiver_deleted (receiver_id, deleted_for_receiver)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- WISHLIST
-- ------------------------------------------------------------
DROP TABLE IF EXISTS wishlist;
CREATE TABLE wishlist (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id   INT NOT NULL,
  item_type ENUM('product','listing') NOT NULL,
  item_id   INT NOT NULL,
  UNIQUE KEY uq_wish (user_id, item_type, item_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- SETTINGS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  skey   VARCHAR(60) PRIMARY KEY,
  svalue TEXT
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  SEED DATA
-- ============================================================

INSERT INTO causes (name, slug, tagline, description, icon) VALUES
('GBV Awareness','gbv','Speak up. Stand up.','Garments that carry the message against gender-based violence, and open a conversation every time they are worn.','shield'),
('Mental Health','mental-health','It''s okay to not be okay.','Pieces designed to normalise the conversation around mental wellbeing.','heart'),
('Environment','environment','Wear the change.','Made from recycled and reclaimed material, proof that fashion can tread lighter.','leaf'),
('Empowerment','empowerment','Built to rise.','Celebrating self-worth, opportunity and community upliftment.','star');

INSERT INTO categories (name, slug, kind) VALUES
('T-Shirts','t-shirts','clothing'),
('Hoodies','hoodies','clothing'),
('Jackets','jackets','clothing'),
('Denim','denim','clothing'),
('Dresses','dresses','clothing'),
('Knitwear','knitwear','clothing'),
('Bags','bags','accessory'),
('Caps & Hats','caps-hats','accessory'),
('Jewellery','jewellery','accessory'),
('Shoes','shoes','clothing');

-- Admin account, password is: Admin@1234
INSERT INTO users (name, email, password_hash, role, city, province, credits) VALUES
('CSH Admin','admin@cshatelier.co','$2y$12$YKJk1ezvlnY/Dwkd7wR2WeFK.WlaK.mCKfDefkjgq1sSen7Gth46a','admin','Johannesburg','Gauteng',0);

-- Demo members (password for all: Member@123)
INSERT INTO users (name, email, password_hash, role, city, province, credits, items_diverted) VALUES
('Naledi S','naledi@example.com','$2y$12$vQPrWMkEixW4RhQkmf0KE.pcHITEPI2diy6JP/ZVrUdoVNu47vXmK','member','Pretoria','Gauteng',450,6),
('Thabane C','thabane@example.com','$2y$12$vQPrWMkEixW4RhQkmf0KE.pcHITEPI2diy6JP/ZVrUdoVNu47vXmK','member','Soweto','Gauteng',120,2),
('Florence T','florence@example.com','$2y$12$vQPrWMkEixW4RhQkmf0KE.pcHITEPI2diy6JP/ZVrUdoVNu47vXmK','member','Polokwane','Limpopo',780,11);

-- Brand products (B2C), including REMADE pieces from donated stock
INSERT INTO products (name, slug, description, price, stock, category_id, cause_id, is_remade, material, recycled_pct, featured) VALUES
('Speak Up Tee','speak-up-tee','A statement tee for the conversation that matters. Screen-printed on 100% organic cotton.',349.00,40,1,1,0,'100% organic cotton',0,1),
('Not Okay Hoodie','not-okay-hoodie','Heavyweight hoodie carrying a simple, honest message about mental health.',749.00,25,2,2,0,'80% cotton / 20% recycled polyester',20,1),
('Remade Denim Jacket','remade-denim-jacket','One of one. Rebuilt from donated denim, patched and reprinted in our studio.',899.00,6,3,3,1,'100% reclaimed denim',100,1),
('Wear The Change Tee','wear-the-change-tee','Printed on fabric spun from recycled bottles. Proof that waste can be worn.',399.00,35,1,3,0,'Recycled PET blend',65,1),
('Built To Rise Cap','built-to-rise-cap','Embroidered cap for the ones building something.',249.00,50,8,4,0,'Recycled cotton twill',40,0),
('Reclaimed Patch Hoodie','reclaimed-patch-hoodie','Remade from three donated hoodies. No two are alike.',829.00,4,2,3,1,'100% reclaimed cotton',100,1),
('Stand Up Tote','stand-up-tote','Sturdy tote sewn from offcuts that would have been discarded.',199.00,60,7,1,1,'Reclaimed offcut canvas',100,0),
('Quiet Strength Knit','quiet-strength-knit','Soft knit in a muted palette, made to be kept for years.',699.00,18,6,2,0,'Recycled wool blend',55,0);

-- C2C listings by members
INSERT INTO listings (seller_id, title, slug, description, price, category_id, cause_id, item_size, item_condition, brand, colour, status) VALUES
(2,'Vintage Denim Jacket','vintage-denim-jacket','Worn twice, just not my size anymore. Classic mid-wash.',420.00,3,3,'M','like_new','Levi''s','Blue','active'),
(2,'Cream Knit Jumper','cream-knit-jumper','Cosy knit, no pilling. Selling to make space.',180.00,6,3,'L','good','Woolworths','Cream','active'),
(3,'Black Cargo Pants','black-cargo-pants','Great condition, barely worn.',260.00,4,4,'32','good','Factorie','Black','active'),
(4,'Beaded Statement Necklace','beaded-statement-necklace','Handmade, bought at a market in Limpopo.',150.00,9,4,'One size','like_new','Handmade','Multi','active'),
(4,'Linen Summer Dress','linen-summer-dress','Light and breezy, perfect for summer.',320.00,5,3,'S','good','Mr Price','Sage','active'),
(3,'Canvas Sneakers','canvas-sneakers','Cleaned and ready for a new owner.',210.00,10,3,'8','well_loved','Converse','White','pending');

INSERT INTO donations (user_id, reference, item_count, description, dropoff_method, outcome, credits_awarded, processed_at) VALUES
(2,'DON-1001',4,'Two tees, a hoodie and a pair of jeans.','dropoff','remade',200,NOW()),
(4,'DON-1002',7,'Bag of assorted knitwear.','courier','resold',350,NOW()),
(3,'DON-1003',2,'Old jackets, quite worn.','dropoff','recycled',100,NOW());

INSERT INTO credit_transactions (user_id, amount, balance_after, reason, reference, note) VALUES
(2,200,200,'donation','DON-1001','4 items received, remade'),
(2,250,450,'sale','ORD-2001','Listing sold: Vintage tee'),
(4,350,350,'donation','DON-1002','7 items received, resold'),
(4,430,780,'sale','ORD-2004','Listing sold: Knit jumper'),
(3,100,100,'donation','DON-1003','2 items received, recycled'),
(3,20,120,'signup_bonus',NULL,'Welcome bonus');

INSERT INTO settings (skey, svalue) VALUES
('store_name','CSH Atelier Co.'),
('tagline','Clothing that carries a message. And a second life.'),
('credit_rate','10'),          -- 10 credits = R1
('credits_per_item','50'),     -- default credits awarded per donated item
('shipping_flat','150'),
('announcement','Donate your old clothing → earn credits → shop the marketplace.');

-- ------------------------------------------------------------
-- ADVANCED MESSAGING
-- ------------------------------------------------------------
CREATE TABLE conversation_state (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  partner_id INT NOT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  is_muted TINYINT(1) NOT NULL DEFAULT 0,
  cleared_at DATETIME DEFAULT NULL,
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
