-- Обновление уже установленной базы (v1 -> v2). Запуск: sudo mysql < migration_v2.sql
-- Старые отзывы (по категориям) сохраняются в таблице feedback_old и в новой аналитике не участвуют.
USE foodfeedback;

CREATE TABLE canteens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  address VARCHAR(200) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
INSERT INTO canteens (address) VALUES ('Основная столовая');

ALTER TABLE tokens ADD COLUMN canteen_id INT UNSIGNED NULL AFTER id;
UPDATE tokens SET canteen_id = 1;
ALTER TABLE tokens MODIFY canteen_id INT UNSIGNED NOT NULL,
  ADD CONSTRAINT fk_tokens_canteen FOREIGN KEY (canteen_id) REFERENCES canteens(id);

RENAME TABLE feedback TO feedback_old;

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

CREATE TABLE review_dishes (
  review_id BIGINT UNSIGNED NOT NULL,
  dish_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (review_id, dish_id),
  KEY idx_dish (dish_id),
  FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
  FOREIGN KEY (dish_id) REFERENCES dishes(id)
) ENGINE=InnoDB;
