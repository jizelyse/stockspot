-- StockSpot database schema (MySQL / MariaDB, InnoDB, utf8mb4)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS shift_notes, stock_movements, stock_batches, po_items, purchase_orders, products, suppliers, categories, users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
  user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('Owner','Staff') NOT NULL DEFAULT 'Staff',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  supplier_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_name  VARCHAR(100) NOT NULL,
  contact_number VARCHAR(30)  NULL,
  address        VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE products (
  product_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id   INT UNSIGNED NOT NULL,
  supplier_id   INT UNSIGNED NULL,
  sku           VARCHAR(50)  NOT NULL UNIQUE,
  product_name  VARCHAR(100) NOT NULL,
  unit          VARCHAR(20)  NOT NULL DEFAULT 'pc',
  cost_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
  selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
  has_expiry    TINYINT(1) NOT NULL DEFAULT 0,
  is_archived   TINYINT(1) NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(category_id),
  CONSTRAINT fk_prod_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL,
  CONSTRAINT chk_prices CHECK (cost_price >= 0 AND selling_price >= 0),
  INDEX idx_prod_name (product_name)
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
  po_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  order_date  DATE NOT NULL,
  status      ENUM('Draft','Ordered','Received') NOT NULL DEFAULT 'Draft',
  CONSTRAINT fk_po_sup  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
  CONSTRAINT fk_po_user FOREIGN KEY (user_id)     REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE po_items (
  po_item_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  po_id        INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  qty_ordered  INT UNSIGNED NOT NULL,
  qty_received INT UNSIGNED NOT NULL DEFAULT 0,
  unit_cost    DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_poi_po   FOREIGN KEY (po_id)      REFERENCES purchase_orders(po_id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_prod FOREIGN KEY (product_id) REFERENCES products(product_id),
  CONSTRAINT chk_poi_qty CHECK (qty_ordered > 0)
) ENGINE=InnoDB;

CREATE TABLE stock_batches (
  batch_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id       INT UNSIGNED NOT NULL,
  po_item_id       INT UNSIGNED NULL,
  quantity_on_hand INT UNSIGNED NOT NULL DEFAULT 0,
  date_received    DATE NOT NULL,
  expiry_date      DATE NULL,
  CONSTRAINT fk_batch_prod FOREIGN KEY (product_id) REFERENCES products(product_id),
  CONSTRAINT fk_batch_poi  FOREIGN KEY (po_item_id) REFERENCES po_items(po_item_id) ON DELETE SET NULL,
  INDEX idx_batch_prod_exp (product_id, expiry_date)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  movement_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  batch_id    INT UNSIGNED NULL,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('IN','OUT','ADJUST','WASTE') NOT NULL,
  quantity    INT NOT NULL,                 -- signed: + adds stock, - removes stock
  reason      VARCHAR(255) NULL,
  moved_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_prod  FOREIGN KEY (product_id) REFERENCES products(product_id),
  CONSTRAINT fk_mov_batch FOREIGN KEY (batch_id)   REFERENCES stock_batches(batch_id) ON DELETE SET NULL,
  CONSTRAINT fk_mov_user  FOREIGN KEY (user_id)    REFERENCES users(user_id),
  INDEX idx_mov_date (moved_at),
  INDEX idx_mov_prod (product_id)
) ENGINE=InnoDB;

CREATE TABLE shift_notes (
  note_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  note_text  VARCHAR(1000) NOT NULL,
  shift_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_note_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;
