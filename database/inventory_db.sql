-- =====================================================
--  ElectroStock Solutions — Apple Inventory System
--  database/inventory_db.sql
--  HOW TO RUN: Open phpMyAdmin > SQL tab > paste all > Go
--  Default login: owner@electrostock.com / Admin@123
-- =====================================================

-- Drop and recreate database for a clean install
DROP DATABASE IF EXISTS inventory_db;
CREATE DATABASE inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_db;

-- =====================================================
--  TABLE: users
--  Stores all system user accounts and their roles
-- =====================================================
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('store_owner','employee') NOT NULL DEFAULT 'employee',
  is_active     TINYINT(1)  NOT NULL DEFAULT 1,
  created_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
--  TABLE: products
--  Apple product catalogue with stock tracking
-- =====================================================
CREATE TABLE products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL UNIQUE,
  sku         VARCHAR(40)  NOT NULL UNIQUE,
  category    ENUM('iPhone','Mac','iPad','Watch','Accessory') NOT NULL,
  description TEXT,
  price       DECIMAL(10,2) NOT NULL,
  quantity    INT           NOT NULL DEFAULT 0,
  min_qty     INT           NOT NULL DEFAULT 5,
  supplier    VARCHAR(100)  DEFAULT 'Apple Inc.',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  added_by    INT,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
--  TABLE: stock_movements
--  Complete audit trail of every stock change
-- =====================================================
CREATE TABLE stock_movements (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT  NOT NULL,
  type       ENUM('IN','OUT','ADJUSTMENT') NOT NULL,
  quantity   INT  NOT NULL,
  notes      VARCHAR(255),
  moved_by   INT  NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (moved_by)   REFERENCES users(id)    ON DELETE CASCADE
);

-- =====================================================
--  TABLE: monitoring
--  Low-stock alerts — auto-triggered when quantity
--  drops at or below the product's min_qty threshold
-- =====================================================
CREATE TABLE monitoring (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  product_id   INT  NOT NULL UNIQUE,
  threshold    INT  NOT NULL DEFAULT 5,
  alert_status ENUM('active','resolved') NOT NULL DEFAULT 'active',
  alerted_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at  TIMESTAMP NULL,
  resolved_by  INT,
  FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (resolved_by) REFERENCES users(id)    ON DELETE SET NULL
);

-- =====================================================
--  TABLE: orders
--  Customer order header records
-- =====================================================
CREATE TABLE orders (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(20)  NOT NULL UNIQUE,
  customer     VARCHAR(120) NOT NULL,
  status       ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  total        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  notes        TEXT,
  created_by   INT  NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
--  TABLE: order_items
--  Line items linking orders to products
-- =====================================================
CREATE TABLE order_items (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT  NOT NULL,
  product_id INT  NOT NULL,
  quantity   INT  NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- =====================================================
--  STORED PROCEDURES
-- =====================================================
DELIMITER $$

-- Returns all active products with computed stock status
CREATE PROCEDURE sp_getAllProducts()
BEGIN
  SELECT p.*, u.full_name AS added_by_name,
    CASE
      WHEN p.quantity = 0          THEN 'Out of Stock'
      WHEN p.quantity <= p.min_qty THEN 'Low Stock'
      ELSE 'In Stock'
    END AS stock_status
  FROM products p
  LEFT JOIN users u ON u.id = p.added_by
  WHERE p.is_active = 1
  ORDER BY p.created_at DESC;
END$$

-- Returns a single active product by its ID
CREATE PROCEDURE sp_getProductById(IN p_id INT)
BEGIN
  SELECT p.*, u.full_name AS added_by_name
  FROM products p
  LEFT JOIN users u ON u.id = p.added_by
  WHERE p.id = p_id AND p.is_active = 1;
END$$

-- Searches products by name/SKU, category and stock status
CREATE PROCEDURE sp_searchProducts(
  IN p_query    VARCHAR(150),
  IN p_category VARCHAR(50),
  IN p_status   VARCHAR(20)
)
BEGIN
  SELECT p.*, u.full_name AS added_by_name,
    CASE
      WHEN p.quantity = 0          THEN 'Out of Stock'
      WHEN p.quantity <= p.min_qty THEN 'Low Stock'
      ELSE 'In Stock'
    END AS stock_status
  FROM products p
  LEFT JOIN users u ON u.id = p.added_by
  WHERE p.is_active = 1
    AND (p_query    IS NULL OR p.name LIKE CONCAT('%',p_query,'%') OR p.sku LIKE CONCAT('%',p_query,'%'))
    AND (p_category IS NULL OR p.category = p_category)
    AND (p_status IS NULL
      OR (p_status = 'in'  AND p.quantity > p.min_qty)
      OR (p_status = 'low' AND p.quantity > 0 AND p.quantity <= p.min_qty)
      OR (p_status = 'out' AND p.quantity = 0))
  ORDER BY p.name;
END$$

-- Inserts a new product and logs initial stock movement
CREATE PROCEDURE sp_addProduct(
  IN p_name     VARCHAR(150), IN p_sku      VARCHAR(40),
  IN p_category VARCHAR(20),  IN p_desc     TEXT,
  IN p_price    DECIMAL(10,2),IN p_quantity INT,
  IN p_min_qty  INT,          IN p_supplier VARCHAR(100),
  IN p_added_by INT
)
BEGIN
  DECLARE v_new_id INT;
  INSERT INTO products (name,sku,category,description,price,quantity,min_qty,supplier,added_by)
  VALUES (p_name,p_sku,p_category,p_desc,p_price,p_quantity,p_min_qty,p_supplier,p_added_by);
  SET v_new_id = LAST_INSERT_ID();
  IF p_quantity > 0 THEN
    INSERT INTO stock_movements (product_id,type,quantity,moved_by,notes)
    VALUES (v_new_id,'IN',p_quantity,p_added_by,'Initial stock on product creation');
  END IF;
  IF p_quantity <= p_min_qty THEN
    INSERT IGNORE INTO monitoring (product_id,threshold) VALUES (v_new_id,p_min_qty);
  END IF;
  SELECT v_new_id AS new_id;
END$$

-- Updates an existing product's editable details
CREATE PROCEDURE sp_updateProduct(
  IN p_id       INT,          IN p_name     VARCHAR(150),
  IN p_sku      VARCHAR(40),  IN p_category VARCHAR(20),
  IN p_desc     TEXT,         IN p_price    DECIMAL(10,2),
  IN p_min_qty  INT,          IN p_supplier VARCHAR(100)
)
BEGIN
  UPDATE products
  SET name=p_name, sku=p_sku, category=p_category, description=p_desc,
      price=p_price, min_qty=p_min_qty, supplier=p_supplier
  WHERE id=p_id AND is_active=1;
END$$

-- Soft-deletes a product by setting is_active to 0
CREATE PROCEDURE sp_deleteProduct(IN p_id INT)
BEGIN
  UPDATE products SET is_active=0 WHERE id=p_id;
END$$

-- Records a stock movement and updates product quantity
-- Returns new quantity and any error message
CREATE PROCEDURE sp_addStockMovement(
  IN  p_product_id INT,   IN  p_type     VARCHAR(20),
  IN  p_quantity   INT,   IN  p_moved_by INT,
  IN  p_notes      VARCHAR(255),
  OUT p_new_qty    INT,   OUT p_error    VARCHAR(255)
)
BEGIN
  DECLARE v_current INT;
  DECLARE v_min     INT;
  DECLARE v_change  INT;
  SELECT quantity, min_qty INTO v_current, v_min
  FROM products WHERE id=p_product_id AND is_active=1;
  SET v_change  = IF(p_type='OUT', -p_quantity, p_quantity);
  SET p_new_qty = v_current + v_change;
  IF p_new_qty < 0 THEN
    SET p_error = CONCAT('Cannot reduce below zero. Current stock: ', v_current);
  ELSE
    INSERT INTO stock_movements (product_id,type,quantity,moved_by,notes)
    VALUES (p_product_id,p_type, ABS(v_change),p_moved_by,p_notes);
    UPDATE products SET quantity=p_new_qty WHERE id=p_product_id;
    IF p_new_qty <= v_min THEN
      INSERT IGNORE INTO monitoring (product_id,threshold) VALUES (p_product_id,v_min);
      UPDATE monitoring SET alert_status='active', alerted_at=NOW(),
             resolved_at=NULL, resolved_by=NULL
      WHERE product_id=p_product_id AND alert_status='resolved';
    END IF;
    SET p_error = NULL;
  END IF;
END$$

-- Returns filtered stock movement history
CREATE PROCEDURE sp_getStockHistory(
  IN p_product_id INT,  IN p_type      VARCHAR(20),
  IN p_date_from  DATE, IN p_date_to   DATE
)
BEGIN
  SELECT sm.*, p.name AS product_name, p.sku, u.full_name AS moved_by_name
  FROM stock_movements sm
  JOIN products p ON p.id = sm.product_id
  JOIN users u    ON u.id = sm.moved_by
  WHERE (p_product_id IS NULL OR sm.product_id = p_product_id)
    AND (p_type       IS NULL OR sm.type = p_type)
    AND (p_date_from  IS NULL OR DATE(sm.created_at) >= p_date_from)
    AND (p_date_to    IS NULL OR DATE(sm.created_at) <= p_date_to)
  ORDER BY sm.created_at DESC;
END$$

-- Returns all active low-stock alerts with product details
CREATE PROCEDURE sp_getActiveAlerts()
BEGIN
  SELECT m.*, p.name AS product_name, p.sku, p.quantity AS current_qty,
    (m.threshold - p.quantity) AS shortfall
  FROM monitoring m
  JOIN products p ON p.id = m.product_id
  WHERE m.alert_status = 'active'
  ORDER BY m.alerted_at DESC;
END$$

-- Marks an alert as resolved by the given user
CREATE PROCEDURE sp_resolveAlert(IN p_alert_id INT, IN p_user_id INT)
BEGIN
  UPDATE monitoring
  SET alert_status='resolved', resolved_at=NOW(), resolved_by=p_user_id
  WHERE id=p_alert_id AND alert_status='active';
END$$

-- Creates a new order and returns its generated ID
CREATE PROCEDURE sp_createOrder(
  IN  p_number   VARCHAR(20), IN  p_customer VARCHAR(120),
  IN  p_notes    TEXT,        IN  p_by       INT,
  OUT p_order_id INT
)
BEGIN
  INSERT INTO orders (order_number,customer,notes,created_by)
  VALUES (p_number,p_customer,p_notes,p_by);
  SET p_order_id = LAST_INSERT_ID();
END$$

-- Updates the status of an existing order
CREATE PROCEDURE sp_updateOrderStatus(IN p_id INT, IN p_status VARCHAR(20))
BEGIN
  UPDATE orders SET status=p_status WHERE id=p_id;
END$$

-- Returns dashboard summary stats in a single query
CREATE PROCEDURE sp_getDashboardStats()
BEGIN
  SELECT
    (SELECT COUNT(*)                FROM products   WHERE is_active=1)                                        AS total_products,
    (SELECT COUNT(*)                FROM products   WHERE is_active=1 AND quantity > min_qty)                 AS in_stock,
    (SELECT COUNT(*)                FROM products   WHERE is_active=1 AND quantity > 0 AND quantity<=min_qty) AS low_stock,
    (SELECT COUNT(*)                FROM products   WHERE is_active=1 AND quantity=0)                         AS out_of_stock,
    (SELECT COUNT(*)                FROM orders     WHERE status='pending')                                   AS pending_orders,
    (SELECT COUNT(*)                FROM monitoring WHERE alert_status='active')                              AS active_alerts,
    (SELECT COALESCE(SUM(price*quantity),0) FROM products WHERE is_active=1)                                  AS total_inventory_value;
END$$

-- Report: products at or below minimum stock level
CREATE PROCEDURE sp_getLowStockReport()
BEGIN
  SELECT p.name, p.sku, p.category, p.quantity, p.min_qty,
    (p.min_qty - p.quantity) AS shortfall, p.supplier
  FROM products p
  WHERE p.is_active=1 AND p.quantity <= p.min_qty
  ORDER BY shortfall DESC;
END$$

-- Report: total inventory value per product
CREATE PROCEDURE sp_getStockValueReport()
BEGIN
  SELECT p.name, p.sku, p.category, p.price, p.quantity,
    (p.price * p.quantity) AS total_value
  FROM products p
  WHERE p.is_active=1
  ORDER BY total_value DESC;
END$$

-- Report: order summary by status
CREATE PROCEDURE sp_getOrderSummaryReport()
BEGIN
  SELECT status,
    COUNT(*) AS order_count,
    SUM(total) AS total_revenue
  FROM orders
  GROUP BY status
  ORDER BY FIELD(status,'completed','processing','pending','cancelled');
END$$

DELIMITER ;

-- =====================================================
--  SEED: Default user accounts
--  Both passwords: Admin@123
-- =====================================================
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Store Owner', 'owner@electrostock.com',
 '$2y$10$TKh8H1.PfuBiCwTsYcATauSJkQQCrmGqLaQXb5w0T7x2dWj1b5KxC', 'store_owner'),
('Alex Johnson', 'staff@electrostock.com',
 '$2y$10$TKh8H1.PfuBiCwTsYcATauSJkQQCrmGqLaQXb5w0T7x2dWj1b5KxC', 'employee');

-- =====================================================
--  SEED: Apple product catalogue
-- =====================================================
INSERT INTO products (name,sku,category,description,price,quantity,min_qty,supplier,added_by) VALUES
('iPhone 16 Pro Max 256GB','IP16PM256','iPhone','Latest flagship with A18 Pro chip and titanium design',               1199.00,24, 8,'Apple Inc.',1),
('iPhone 16 Pro 128GB',    'IP16P128', 'iPhone','Pro performance with 48MP camera system and A18 Pro chip',            999.00,18, 8,'Apple Inc.',1),
('iPhone 15 128GB',        'IP15128',  'iPhone','Dynamic Island and USB-C connectivity',                               799.00,35,10,'Apple Inc.',1),
('iPhone 14 128GB',        'IP14128',  'iPhone','Crash Detection and Emergency SOS via satellite',                     699.00, 4, 8,'Apple Inc.',1),
('MacBook Pro 16 M4 Pro',  'MBP16M4P', 'Mac',   'Powerhouse laptop with M4 Pro chip and 24GB RAM',                  2499.00, 6, 3,'Apple Inc.',1),
('MacBook Pro 14 M4',      'MBP14M4',  'Mac',   '14-inch MacBook Pro with M4 chip, all-day battery',                1999.00,11, 3,'Apple Inc.',1),
('MacBook Air 15 M3',      'MBA15M3',  'Mac',   'Thin and light 15-inch with M3 chip',                              1299.00,20, 5,'Apple Inc.',1),
('Mac mini M4',            'MM4',      'Mac',   'Compact desktop Mac with M4 chip',                                   599.00, 9, 5,'Apple Inc.',1),
('iPad Pro 13 M4',         'IPADP13M4','iPad',  'Most advanced iPad with M4 chip and Ultra Retina XDR display',      1299.00, 5, 3,'Apple Inc.',1),
('iPad Air 11 M2',         'IPADA11M2','iPad',  'Versatile 11-inch iPad Air with M2 chip',                            749.00,14, 5,'Apple Inc.',1),
('iPad 10th Gen 64GB',     'IPAD10G64','iPad',  'Colorful redesigned iPad with USB-C',                                449.00,22, 8,'Apple Inc.',1),
('iPad mini 7th Gen',      'IPADMINI7','iPad',  'Portable 8.3-inch iPad mini with A17 Pro chip',                      499.00, 3, 5,'Apple Inc.',1),
('Apple Watch Series 10',  'AWS10GPS', 'Watch', 'Thinnest Apple Watch with larger display and faster charging',        399.00,28,10,'Apple Inc.',1),
('Apple Watch Ultra 2',    'AWULTRA2', 'Watch', 'Ultimate sports watch with precision dual-frequency GPS',             799.00, 6, 3,'Apple Inc.',1),
('Apple Watch SE 2',       'AWSE2',    'Watch', 'Essential smartwatch at an affordable price',                         249.00,19, 8,'Apple Inc.',1),
('AirPods Pro 2nd Gen',    'APP2',     'Accessory','Active Noise Cancellation with Adaptive Audio',                    249.00, 2, 8,'Apple Inc.',1),
('AirPods 4',              'AP4',      'Accessory','Redesigned AirPods with ANC and H2 chip',                         129.00,31,10,'Apple Inc.',1),
('Apple Pencil Pro',       'APENCILP', 'Accessory','Advanced Pencil with barrel roll and squeeze gesture',             129.00,12, 5,'Apple Inc.',1),
('MagSafe Charger 15W',    'MAGSAFE',  'Accessory','Wireless MagSafe charger up to 15W for iPhone 12 and later',       39.00,45,15,'Apple Inc.',1),
('USB-C Cable Woven 1m',   'USBC1M',   'Accessory','Braided USB-C cable supporting up to 240W charging',               29.00, 4,15,'Apple Inc.',1);

-- =====================================================
--  SEED: Stock movement history (test data)
-- =====================================================
INSERT INTO stock_movements (product_id,type,quantity,moved_by,notes,created_at) VALUES
(1,'IN', 30, 1,'Initial delivery batch #001','2025-09-01 09:00:00'),
(1,'OUT', 6, 2,'Store sales week 1',         '2025-09-07 14:30:00'),
(2,'IN', 20, 1,'Initial delivery batch #001','2025-09-01 09:00:00'),
(2,'OUT', 2, 2,'Store sales',                '2025-09-10 11:00:00'),
(5,'IN',  8, 1,'Delivery batch #002',        '2025-09-05 10:00:00'),
(5,'OUT', 2, 2,'Corporate sale',             '2025-09-12 15:00:00'),
(16,'IN',10, 1,'Initial delivery',           '2025-09-01 09:00:00'),
(16,'OUT', 8,2,'High demand weekend sales',  '2025-09-14 16:00:00'),
(20,'IN', 20, 1,'Initial delivery',           '2025-09-01 09:00:00'),
(20,'OUT',16,2,'Bundle sales with iPhones', '2025-09-13 12:00:00');

-- =====================================================
--  SEED: Low-stock alerts
-- =====================================================
INSERT INTO monitoring (product_id,threshold,alert_status) VALUES
(4, 8, 'active'),
(12,5, 'active'),
(16,8, 'active'),
(20,15,'active');

-- =====================================================
--  SEED: Sample orders with items
-- =====================================================
INSERT INTO orders (order_number,customer,status,total,notes,created_by,created_at) VALUES
('ORD-20251001-A1B2','John Smith',     'completed', 2398.00,'Corporate bulk order',      1,'2025-10-01 10:00:00'),
('ORD-20251005-C3D4','Sarah Williams', 'processing',1999.00,'Online order',              2,'2025-10-05 14:00:00'),
('ORD-20251010-E5F6','Tech Store Ltd', 'pending',   3596.00,'Reseller — priority',       1,'2025-10-10 09:30:00'),
('ORD-20251012-G7H8','Emma Brown',     'cancelled',  249.00,'Customer cancelled',        2,'2025-10-12 11:00:00');

INSERT INTO order_items (order_id,product_id,quantity,unit_price) VALUES
(1,1,2,1199.00),
(2,6,1,1999.00),
(3,1,2,1199.00),(3,13,1,399.00),(3,19,2,399.00),
(4,16,1,249.00);
