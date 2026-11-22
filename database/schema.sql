-- Bukid Basket — MySQL 8.0+ / MariaDB 10.4+ Schema (InnoDB, utf8mb4)
-- Money is stored as INT centavos (₱180.00 -> 18000).
-- Stock is NEVER stored on product_variants; it is SUM(quantity) in stock_movements.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Fresh import: drop everything first (trips is a leftover table from the old packer/driver build).
DROP TABLE IF EXISTS order_status_events, stock_movements, order_items, orders, trips, product_variants, products, categories, users;

CREATE TABLE IF NOT EXISTS users (
  id CHAR(24) NOT NULL,
  name VARCHAR(120) NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('CUSTOMER','ADMIN') NOT NULL DEFAULT 'CUSTOMER',
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  barangay VARCHAR(80) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id CHAR(24) NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id CHAR(24) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  image_url VARCHAR(255) NULL,
  category_id CHAR(24) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_products_category (category_id),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
  id CHAR(24) NOT NULL,
  product_id CHAR(24) NOT NULL,
  name VARCHAR(120) NOT NULL,
  sku VARCHAR(80) NOT NULL,
  price_centavos INT NOT NULL,
  price_updated_at DATETIME(3) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY uq_variants_sku (sku),
  KEY idx_variants_product (product_id),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id CHAR(24) NOT NULL,
  order_number INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id CHAR(24) NOT NULL,
  status ENUM('PENDING','CONFIRMED','READY','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  payment_method ENUM('GCASH','COD') NOT NULL,
  delivery_name VARCHAR(140) NOT NULL,
  delivery_phone VARCHAR(40) NOT NULL,
  delivery_address VARCHAR(255) NOT NULL,
  delivery_barangay VARCHAR(80) NOT NULL,
  delivery_slot VARCHAR(40) NOT NULL,
  delivery_date DATE NULL,
  delivery_fee_centavos INT NOT NULL DEFAULT 0,
  total_centavos INT NOT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_number (order_number),
  KEY idx_orders_status_created (status, created_at),
  KEY idx_orders_created (created_at),
  KEY idx_orders_customer_created (customer_id, created_at),
  KEY idx_orders_slot_cap (delivery_date, delivery_slot, status),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id CHAR(24) NOT NULL,
  order_id CHAR(24) NOT NULL,
  variant_id CHAR(24) NOT NULL,
  quantity INT NOT NULL,
  price_at_time INT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_items_order (order_id),
  KEY idx_items_variant (variant_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
  id CHAR(24) NOT NULL,
  variant_id CHAR(24) NOT NULL,
  quantity INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  order_id CHAR(24) NULL,
  actor_id CHAR(24) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_sm_variant_created (variant_id, created_at),
  KEY idx_sm_variant_qty (variant_id, quantity),
  KEY idx_sm_order (order_id),
  KEY idx_sm_actor (actor_id),
  CONSTRAINT fk_sm_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id),
  CONSTRAINT fk_sm_order FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_sm_actor FOREIGN KEY (actor_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_events (
  id CHAR(24) NOT NULL,
  order_id CHAR(24) NOT NULL,
  from_status ENUM('PENDING','CONFIRMED','READY','COMPLETED','CANCELLED') NULL,
  to_status ENUM('PENDING','CONFIRMED','READY','COMPLETED','CANCELLED') NOT NULL,
  note VARCHAR(255) NULL,
  actor_id CHAR(24) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_ose_order_created (order_id, created_at),
  KEY idx_ose_status_created (to_status, created_at),
  KEY idx_ose_actor (actor_id),
  CONSTRAINT fk_ose_order FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_ose_actor FOREIGN KEY (actor_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
