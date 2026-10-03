<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'foodfeedback');
define('DB_USER', 'root');
define('DB_PASS', '');

// Секрет для хэширования кодов и IP. ОБЯЗАТЕЛЬНО замените на случайную строку!
define('APP_PEPPER', 'change-me-to-long-random-string');

define('BASE_URL', 'http://localhost/foodfeedback');   // без слэша в конце
// Ключ для API кассы (api/issue_code.php). Замените: openssl rand -hex 24
define('API_KEY', 'change-me');

define('RATE_LIMIT', 10);          // попыток отправки с одного IP
define('RATE_WINDOW_MIN', 10);     // за N минут
define('MAX_COMMENT', 500);

// Подозрительные слова: комментарий уйдёт на модерацию (дополните список)
$GLOBALS['BAD_WORDS'] = array('дурак', 'идиот', 'тварь', 'сука', 'хуй', 'пизд', 'бляд', 'ебан');
