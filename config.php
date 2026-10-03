<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'foodfeedback');
define('DB_USER', 'admin');
define('DB_PASS', '1234');

define('APP_PEPPER', 'test-local-pepper');
define('BASE_URL', 'http://localhost/foodfeedback');   // без слэша в конце
define('API_KEY', 'test-local-pepper');
define('RATE_LIMIT', 10);          // попыток отправки с одного IP
define('RATE_WINDOW_MIN', 10);     // за N минут
define('MAX_COMMENT', 500);

// Подозрительные слова: комментарий уйдёт на модерацию (дополните список)
$GLOBALS['BAD_WORDS'] = array('дурак', 'идиот', 'тварь', 'сука', 'хуй', 'пизд', 'бляд', 'ебан');
