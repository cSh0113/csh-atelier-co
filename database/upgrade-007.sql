-- CSH Atelier production upgrade 007
-- Fixes admin product creation by adding the product message field.
ALTER TABLE products
  ADD COLUMN message TEXT NULL AFTER description;
