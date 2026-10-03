CREATE DATABASE IF NOT EXISTS foodfeedback CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE foodfeedback;

CREATE TABLE categories (
  id TINYINT UNSIGNED PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  title VARCHAR(50) NOT NULL
) ENGINE=InnoDB;
INSERT INTO categories (id, code, title) VALUES
 (1,'dish','Блюдо'),(2,'cleanliness','Чистота'),(3,'service','Обслуживание');

CREATE TABLE dishes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
INSERT INTO dishes (name) VALUES ('Борщ'),('Гречка с котлетой'),('Плов'),('Салат «Витаминный»'),('Компот');

-- Одноразовые коды (на чеке / QR на подносе). Храним ТОЛЬКО хэш.
CREATE TABLE tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_hash CHAR(64) NOT NULL UNIQUE,
  issued_date DATE NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  used_date DATE NULL
) ENGINE=InnoDB;

-- Отзывы. Связи с tokens НЕТ, время округлено до часа => деанонимизация невозможна.
CREATE TABLE feedback (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id TINYINT UNSIGNED NOT NULL,
  dish_id INT UNSIGNED NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment VARCHAR(500) NULL,
  comment_status ENUM('none','approved','pending','hidden') NOT NULL DEFAULT 'none',
  created_at DATETIME NOT NULL,
  KEY idx_created (created_at),
  KEY idx_cat (category_id, created_at),
  KEY idx_dish (dish_id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (dish_id) REFERENCES dishes(id)
) ENGINE=InnoDB;

-- Антифлуд по хэшу IP (с солью), не связан с отзывами
CREATE TABLE rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_hash CHAR(64) NOT NULL,
  hit_at DATETIME NOT NULL,
  KEY idx_ip (ip_hash, hit_at)
) ENGINE=InnoDB;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(50) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB;
