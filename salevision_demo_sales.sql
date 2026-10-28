-- Salevision demo database and synthetic sales history.
-- This creates a database named `salevision` and generates 36 complete months
-- of test orders: October 2023 through September 2026. These are not real sales.
-- Run once in phpMyAdmin or the MySQL/MariaDB command-line client.

CREATE DATABASE IF NOT EXISTS salevision
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE salevision;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(80) NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(40) NOT NULL,
  customer_id INT UNSIGNED NULL,
  order_date DATETIME NOT NULL,
  status ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded')
    NOT NULL DEFAULT 'pending',
  subtotal DECIMAL(10, 2) NOT NULL,
  shipping_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(10, 2) NOT NULL,
  payment_method VARCHAR(40) NOT NULL DEFAULT 'cash_on_delivery',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_order_number (order_number),
  KEY idx_orders_date_status (order_date, status),
  KEY idx_orders_customer (customer_id),
  CONSTRAINT fk_orders_customer
    FOREIGN KEY (customer_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_items_order_product (order_id, product_id),
  KEY idx_order_items_product (product_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- IDs make the generated data reproducible. Prices match the current shop cards;
-- the cream's current sale price (₱649) is used, not its former ₱749 price.
INSERT INTO products (id, name, category, price, stock_quantity, is_active) VALUES
  (1, 'Radiance Cleansing Foam', 'Cleanser', 349.00, 100, 1),
  (2, 'Brightening Facial Toner', 'Toner', 399.00, 100, 1),
  (3, 'Golden Glow Repair Serum', 'Serum', 899.00, 100, 1),
  (4, 'Even-Tone Day Lotion', 'Lotion', 459.00, 100, 1),
  (5, 'Overnight Repair Cream', 'Cream', 649.00, 100, 1),
  (6, 'Gentle Exfoliating Bar', 'Bar soap', 279.00, 100, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  category = VALUES(category),
  price = VALUES(price),
  is_active = VALUES(is_active);

-- Example historical rankings for September 2026. Change these values as needed.
SET @report_year = 2026;
SET @report_month = 9;

SELECT
  p.id,
  p.name,
  SUM(oi.quantity) AS units_sold
FROM orders AS o
JOIN order_items AS oi ON oi.order_id = o.id
JOIN products AS p ON p.id = oi.product_id
WHERE o.status = 'completed'
  AND YEAR(o.order_date) = @report_year
  AND MONTH(o.order_date) = @report_month
GROUP BY p.id, p.name
ORDER BY units_sold DESC, p.name ASC;

SELECT
  p.id,
  p.name,
  SUM(oi.quantity * oi.unit_price) AS sales_revenue
FROM orders AS o
JOIN order_items AS oi ON oi.order_id = o.id
JOIN products AS p ON p.id = oi.product_id
WHERE o.status = 'completed'
  AND YEAR(o.order_date) = @report_year
  AND MONTH(o.order_date) = @report_month
GROUP BY p.id, p.name
ORDER BY sales_revenue DESC, p.name ASC;
