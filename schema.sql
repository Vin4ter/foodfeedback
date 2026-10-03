CREATE DATABASE IF NOT EXISTS foodfeedback CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE foodfeedback;

-- Адреса столовых
CREATE TABLE canteens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  address VARCHAR(200) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
INSERT INTO canteens (address) VALUES ('Основная столовая');

CREATE TABLE dishes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
INSERT INTO dishes (name) VALUES ('Борщ'),('Гречка с котлетой'),('Плов'),('Салат «Витаминный»'),('Компот');

-- Одноразовые коды (на чеке / QR). Храним ТОЛЬКО хэш. Код привязан к столовой.
CREATE TABLE tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  canteen_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL UNIQUE,
  issued_date DATE NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  used_date DATE NULL,
  FOREIGN KEY (canteen_id) REFERENCES canteens(id)
) ENGINE=InnoDB;

-- Отзывы. Связи с tokens НЕТ, время округлено до часа => деанонимизация невозможна.
CREATE TABLE reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  canteen_id INT UNSIGNED NOT NULL,
  overall_rating TINYINT UNSIGNED NOT NULL,
  cleanliness_rating TINYINT UNSIGNED NULL,
  service_rating TINYINT UNSIGNED NULL,
  comment VARCHAR(500) NULL,
  comment_status ENUM('none','approved','pending','hidden') NOT NULL DEFAULT 'none',
  created_at DATETIME NOT NULL,
  KEY idx_canteen_created (canteen_id, created_at),
  KEY idx_created (created_at),
  FOREIGN KEY (canteen_id) REFERENCES canteens(id)
) ENGINE=InnoDB;

-- Какие блюда клиент отметил в отзыве
CREATE TABLE review_dishes (
  review_id BIGINT UNSIGNED NOT NULL,
  dish_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (review_id, dish_id),
  KEY idx_dish (dish_id),
  FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
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
