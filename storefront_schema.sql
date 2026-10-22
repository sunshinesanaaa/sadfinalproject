-- Schema additions for customer-confirmed orders.
-- Run once in phpMyAdmin with the salevision database selected.
-- Existing accounts, products, and order history are preserved.

USE salevision;

SET @shipping_address_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'shipping_address'
);
SET @shipping_address_sql = IF(
  @shipping_address_exists = 0,
  'ALTER TABLE orders ADD COLUMN shipping_address VARCHAR(500) NULL AFTER customer_id',
  'SELECT 1'
);
PREPARE shipping_address_statement FROM @shipping_address_sql;
EXECUTE shipping_address_statement;
DEALLOCATE PREPARE shipping_address_statement;

SET @contact_phone_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'contact_phone'
);
SET @contact_phone_sql = IF(
  @contact_phone_exists = 0,
  'ALTER TABLE orders ADD COLUMN contact_phone VARCHAR(30) NULL AFTER shipping_address',
  'SELECT 1'
);
PREPARE contact_phone_statement FROM @contact_phone_sql;
EXECUTE contact_phone_statement;
DEALLOCATE PREPARE contact_phone_statement;
