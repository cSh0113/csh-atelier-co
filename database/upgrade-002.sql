-- ============================================================
-- CSH ATELIER CO., upgrade 002
-- Run this ONCE in phpMyAdmin (SQL tab) on an existing install.
-- A fresh import of cshatelier.sql already includes all of this.
-- ============================================================
USE cshatelier;

-- 1. Products can now carry their own custom written message
ALTER TABLE products
  ADD COLUMN message TEXT NULL AFTER description;

-- Give the seeded pieces a message
UPDATE products SET message = 'Silence protects the abuser, never the abused. Wearing this is choosing a side.'
  WHERE slug LIKE '%speak-up%';
UPDATE products SET message = 'It is okay to not be okay. This says what you cannot always say out loud.'
  WHERE slug LIKE '%not-okay%' OR slug LIKE '%mental%';
UPDATE products SET message = 'This piece had a life before you. Now it has another. That is the whole point.'
  WHERE is_remade = 1;
UPDATE products SET message = 'Landfill is not a plan. Wear the change.'
  WHERE message IS NULL;

-- 2. Listings can carry a message too (sellers explain why it matters)
ALTER TABLE listings
  ADD COLUMN message VARCHAR(300) NULL AFTER description;

-- 3. More categories, including accessories
INSERT INTO categories (name, slug, kind) VALUES
  ('Accessories','accessories','accessory'),
  ('Scarves & Wraps','scarves-wraps','accessory'),
  ('Belts','belts','accessory'),
  ('Bags & Backpacks','bags-backpacks','accessory'),
  ('Activewear','activewear','clothing'),
  ('Sleepwear','sleepwear','clothing'),
  ('Formal','formal','clothing'),
  ('Kids','kids','clothing'),
  ('Vintage','vintage','clothing'),
  ('Sunglasses','sunglasses','accessory')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 4. Shipping / fulfilment fields on orders
ALTER TABLE orders
  ADD COLUMN delivery_method ENUM('shipping','collection') NOT NULL DEFAULT 'shipping' AFTER total,
  ADD COLUMN courier        VARCHAR(60)  NULL AFTER status,
  ADD COLUMN tracking_no    VARCHAR(80)  NULL AFTER courier,
  ADD COLUMN shipped_at     DATETIME     NULL AFTER tracking_no,
  ADD COLUMN delivered_at   DATETIME     NULL AFTER shipped_at,
  ADD COLUMN admin_note     VARCHAR(255) NULL AFTER delivered_at;

-- 5. Payment records, so cash flow is auditable
CREATE TABLE IF NOT EXISTS payments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT           NOT NULL,
  gateway      ENUM('payfast','yoco','paypal','eft','credits','manual') NOT NULL DEFAULT 'manual',
  gateway_ref  VARCHAR(120)  DEFAULT NULL,
  amount       DECIMAL(10,2) NOT NULL,
  fee          DECIMAL(10,2) NOT NULL DEFAULT 0,
  status       ENUM('initiated','paid','failed','refunded') NOT NULL DEFAULT 'initiated',
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_pay_status (status)
) ENGINE=InnoDB;

-- 6. Demo accounts (password for all: Admin@1234)
--    Used only for the walkthrough / marking demo.
INSERT INTO users (name, email, password_hash, role, city, province, credits) VALUES
  ('Sibusiso',   'sibusiso@example.com',   '$2y$12$YKJk1ezvlnY/Dwkd7wR2WeFK.WlaK.mCKfDefkjgq1sSen7Gth46a','member','Soweto','Gauteng',420),
  ('Thabane',    'thabane@example.com',    '$2y$12$YKJk1ezvlnY/Dwkd7wR2WeFK.WlaK.mCKfDefkjgq1sSen7Gth46a','member','Pretoria','Gauteng',180),
  ('Cinderella', 'cinderella@example.com', '$2y$12$YKJk1ezvlnY/Dwkd7wR2WeFK.WlaK.mCKfDefkjgq1sSen7Gth46a','member','Polokwane','Limpopo',95)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 7. Settings used by the new admin screens
INSERT INTO settings (skey, svalue) VALUES
  ('shipping_flat','150'),
  ('free_shipping_over','900'),
  ('payment_gateway','payfast'),
  ('payfast_merchant_id',''),
  ('payfast_merchant_key',''),
  ('payfast_passphrase',''),
  ('payfast_sandbox','1'),
  ('yoco_public_key',''),
  ('yoco_secret_key',''),
  ('bank_name','FNB'),
  ('bank_account','') 
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);
