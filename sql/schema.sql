CREATE DATABASE IF NOT EXISTS gebeya_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gebeya_db;


CREATE TABLE roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NULL,
  status ENUM('active','pending','banned') NOT NULL DEFAULT 'active',
  otp_hash VARCHAR(255) NULL,
  otp_expires_at DATETIME NULL,
  reset_token_hash VARCHAR(255) NULL,
  reset_token_expires_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_roles_role
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(80) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  product_id INT AUTO_INCREMENT PRIMARY KEY,
  seller_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  product_condition ENUM('new','used') NOT NULL DEFAULT 'used',
  status ENUM('active','pending','hidden') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_seller
    FOREIGN KEY (seller_id) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_products_title (title),
  INDEX idx_products_seller (seller_id),
  INDEX idx_products_status (status)
) ENGINE=InnoDB;

CREATE TABLE product_categories (
  product_id INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (product_id, category_id),
  CONSTRAINT fk_pc_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pc_category
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_images (
  image_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_images_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


CREATE TABLE addresses (
  address_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT 'Home',
  city VARCHAR(80) NOT NULL,
  street VARCHAR(160) NOT NULL,
  house_no VARCHAR(40) NULL,
  postal_code VARCHAR(30) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_addresses_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_addresses_user (user_id)
) ENGINE=InnoDB;


CREATE TABLE orders (
  order_id INT AUTO_INCREMENT PRIMARY KEY,
  buyer_id INT NOT NULL,
  address_id INT NULL,
  order_status ENUM('pending','paid','shipped','cancelled') NOT NULL DEFAULT 'pending',
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_buyer
    FOREIGN KEY (buyer_id) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_orders_address
    FOREIGN KEY (address_id) REFERENCES addresses(address_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_orders_buyer (buyer_id),
  INDEX idx_orders_status (order_status)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  seller_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  item_status ENUM('pending','packed','shipped','cancelled') NOT NULL DEFAULT 'pending',
  CONSTRAINT fk_oi_order
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_oi_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_oi_seller
    FOREIGN KEY (seller_id) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_oi_order (order_id),
  INDEX idx_oi_seller (seller_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL UNIQUE,
  payment_method ENUM('cash_on_delivery','bank_transfer','mobile_money','card') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


CREATE TABLE reviews (
  review_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  buyer_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reviews_buyer
    FOREIGN KEY (buyer_id) REFERENCES users(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),
  UNIQUE KEY uq_review_once (product_id, buyer_id)
) ENGINE=InnoDB;
