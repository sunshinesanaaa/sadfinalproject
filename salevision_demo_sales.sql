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

DROP PROCEDURE IF EXISTS seed_salevision_demo_sales;

DELIMITER $$

CREATE PROCEDURE seed_salevision_demo_sales()
BEGIN
  DECLARE v_customer_number INT DEFAULT 1;
  DECLARE v_customer_index INT;
  DECLARE v_customer_id INT UNSIGNED;
  DECLARE v_order_id BIGINT UNSIGNED;
  DECLARE v_month_index INT DEFAULT 0;
  DECLARE v_product_id INT;
  DECLARE v_segment INT;
  DECLARE v_month_start DATE;
  DECLARE v_month_units INT;
  DECLARE v_base_units INT;
  DECLARE v_seasonal_units INT;
  DECLARE v_segment_units INT;
  DECLARE v_unit_price DECIMAL(10, 2);
  DECLARE v_subtotal DECIMAL(10, 2);
  DECLARE v_shipping_fee DECIMAL(10, 2);
  DECLARE v_order_number VARCHAR(40);
  DECLARE v_order_date DATETIME;
  DECLARE v_order_status VARCHAR(20);

  START TRANSACTION;

  WHILE v_customer_number <= 24 DO
    INSERT INTO users (full_name, email, role)
    VALUES (
      CONCAT('Demo Customer ', LPAD(v_customer_number, 2, '0')),
      CONCAT('demo', LPAD(v_customer_number, 2, '0'), '@example.test'),
      'customer'
    )
    ON DUPLICATE KEY UPDATE
      full_name = VALUES(full_name),
      role = VALUES(role);

    SET v_customer_number = v_customer_number + 1;
  END WHILE;

  WHILE v_month_index < 36 DO
    SET v_month_start = DATE_ADD('2023-10-01', INTERVAL v_month_index MONTH);
    SET v_product_id = 1;

    WHILE v_product_id <= 6 DO
      SET v_base_units = CASE v_product_id
        WHEN 1 THEN 28
        WHEN 2 THEN 20
        WHEN 3 THEN 18
        WHEN 4 THEN 22
        WHEN 5 THEN 14
        ELSE 24
      END;

      SET v_seasonal_units = CASE MONTH(v_month_start)
        WHEN 11 THEN 8
        WHEN 12 THEN 12
        WHEN 1 THEN 5
        WHEN 5 THEN 2
        ELSE 0
      END;

      -- A modest upward trend plus a deterministic seasonal and month-to-month
      -- variation gives forecasting code testable patterns without randomness.
      SET v_month_units = v_base_units
        + FLOOR(v_month_index / 6)
        + v_seasonal_units
        + MOD(v_month_index * 7 + v_product_id * 11, 7);

      SELECT price
        INTO v_unit_price
        FROM products
        WHERE id = v_product_id;

      SET v_segment = 1;
      WHILE v_segment <= 3 DO
        SET v_segment_units = FLOOR(v_month_units / 3)
          + IF(v_segment <= MOD(v_month_units, 3), 1, 0);
        SET v_subtotal = v_segment_units * v_unit_price;
        SET v_shipping_fee = IF(v_subtotal >= 2500.00, 0.00, 99.00);
        SET v_customer_index = MOD(
          v_month_index * 6 + v_product_id * 3 + v_segment - 1,
          24
        ) + 1;

        SELECT id
          INTO v_customer_id
          FROM users
          WHERE email = CONCAT('demo', LPAD(v_customer_index, 2, '0'), '@example.test');

        SET v_order_number = CONCAT(
          'SYN-',
          DATE_FORMAT(v_month_start, '%Y%m'),
          '-P',
          LPAD(v_product_id, 2, '0'),
          '-S',
          v_segment
        );
        SET v_order_date = DATE_ADD(
          v_month_start,
          INTERVAL ((v_segment - 1) * 9 + MOD(v_product_id * 3 + v_month_index, 6)) DAY
        );
        SET v_order_status = IF(
          MOD(v_month_index * 6 + v_product_id * 3 + v_segment, 31) = 0,
          'cancelled',
          'completed'
        );

        INSERT INTO orders (
          order_number, customer_id, order_date, status,
          subtotal, shipping_fee, total_amount, payment_method
        )
        VALUES (
          v_order_number,
          v_customer_id,
          v_order_date,
          v_order_status,
          v_subtotal,
          v_shipping_fee,
          v_subtotal + v_shipping_fee,
          'cash_on_delivery'
        )
        ON DUPLICATE KEY UPDATE
          id = LAST_INSERT_ID(id),
          customer_id = VALUES(customer_id),
          order_date = VALUES(order_date),
          status = VALUES(status),
          subtotal = VALUES(subtotal),
          shipping_fee = VALUES(shipping_fee),
          total_amount = VALUES(total_amount),
          payment_method = VALUES(payment_method);

        SET v_order_id = LAST_INSERT_ID();

        INSERT INTO order_items (order_id, product_id, quantity, unit_price)
        VALUES (v_order_id, v_product_id, v_segment_units, v_unit_price)
        ON DUPLICATE KEY UPDATE
          quantity = VALUES(quantity),
          unit_price = VALUES(unit_price);

        SET v_segment = v_segment + 1;
      END WHILE;

      SET v_product_id = v_product_id + 1;
    END WHILE;

    SET v_month_index = v_month_index + 1;
  END WHILE;

  COMMIT;
END$$

DELIMITER ;

CALL seed_salevision_demo_sales();
DROP PROCEDURE seed_salevision_demo_sales;

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
