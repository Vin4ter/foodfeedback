<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));
    }
    return $pdo;
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function norm_code($c) { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$c)); }
function code_hash($c) { return hash_hmac('sha256', norm_code($c), APP_PEPPER); }
function ip_hash() { return hash_hmac('sha256', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', APP_PEPPER); }

function generate_code() {
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // без O/0/I/1
    $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $alpha[random_int(0, strlen($alpha) - 1)];
    return substr($s, 0, 4) . '-' . substr($s, 4);
}

// Подозрительный комментарий -> на модерацию
function is_suspicious($t) {
    if (preg_match('~https?://|www\.|@|t\.me|\.(ru|com|net|org)\b~iu', $t)) return true;
    if (preg_match('~(.)\1{5,}~u', $t)) return true;
    $letters = preg_replace('~[^\p{L}]~u', '', $t);
    if (mb_strlen($letters) > 10 && mb_strtoupper($letters) === $letters) return true;
    $low = mb_strtolower($t);
    foreach ($GLOBALS['BAD_WORDS'] as $w) if (mb_strpos($low, $w) !== false) return true;
    return false;
}

function require_admin() {
    session_name('ffadmin');
    session_start();
    if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; }
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function check_csrf($t) { return isset($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t); }
