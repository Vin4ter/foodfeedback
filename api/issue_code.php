<?php
/**
 * API для кассы/POS: выписывает одноразовый код и QR для печати на чеке.
 *
 * POST /api/issue_code.php
 * Заголовок:  X-API-Key: <API_KEY из config.php>   (или Authorization: Bearer <API_KEY>)
 * Параметры (JSON-тело или form/query):
 *   canteen_id  ОБЯЗАТЕЛЬНО. ID столовой (виден в админке, «Коды и меню»)
 *   ttl_hours   1..168, срок жизни кода, по умолчанию 24
 *   qr_scale    2..20,  размер модуля QR в пикселях, по умолчанию 6
 *   format      json (по умолчанию) | png | svg
 *
 * format=json -> {"ok":true,"code":"K7M2-9QXP","canteen_id":1,"canteen":"...","url":"...","expires_at":"...",
 *                 "qr_png_base64":"...","qr_png_data_uri":"data:image/png;base64,...","qr_svg":"<svg…>"}
 * format=png|svg -> сам файл картинки, код и ссылка в заголовках X-Feedback-Code / X-Feedback-Url
 */
require __DIR__ . '/../db.php';
require __DIR__ . '/../qr.php';

header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_out(array('ok' => false, 'error' => 'Используйте POST'), 405);
}

// --- авторизация по ключу ---
if (!defined('API_KEY') || API_KEY === '' || API_KEY === 'change-me') {
    json_out(array('ok' => false, 'error' => 'API_KEY не настроен в config.php'), 503);
}
$key = '';
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $key = $_SERVER['HTTP_X_API_KEY'];
} else {
    $auth = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (stripos($auth, 'Bearer ') === 0) $key = trim(substr($auth, 7));
}
if (!hash_equals((string)API_KEY, (string)$key)) {
    json_out(array('ok' => false, 'error' => 'Неверный API-ключ'), 401);
}

// --- параметры ---
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_REQUEST;
$ttl = isset($in['ttl_hours']) ? max(1, min(168, (int)$in['ttl_hours'])) : 24;
$scale = isset($in['qr_scale']) ? max(2, min(20, (int)$in['qr_scale'])) : 6;
$format = isset($in['format']) ? strtolower((string)$in['format']) : 'json';
if (!in_array($format, array('json', 'png', 'svg'), true)) $format = 'json';

$pdo = db();

// --- столовая ---
$cid = isset($in['canteen_id']) ? (int)$in['canteen_id'] : 0;
if ($cid < 1) json_out(array('ok' => false, 'error' => 'Укажите canteen_id (ID столовой)'), 422);
$st = $pdo->prepare('SELECT id, address FROM canteens WHERE id=? AND active=1');
$st->execute(array($cid));
$canteen = $st->fetch();
if (!$canteen) json_out(array('ok' => false, 'error' => 'Столовая не найдена или находится в архиве'), 404);

// --- выпуск кода (в БД только хэш) ---
$ins = $pdo->prepare('INSERT INTO tokens (canteen_id, code_hash, issued_date, expires_at) VALUES (?, ?, CURDATE(), DATE_ADD(NOW(), INTERVAL ? HOUR))');
$code = null;
$id = null;
for ($try = 0; $try < 5 && $code === null; $try++) {
    $c = generate_code();
    try {
        $ins->execute(array($canteen['id'], code_hash($c), $ttl));
        $id = $pdo->lastInsertId();
        $code = $c;
    } catch (PDOException $e) {
        // коллизия хэша — пробуем другой код
    }
}
if ($code === null) json_out(array('ok' => false, 'error' => 'Не удалось выпустить код'), 500);

$st = $pdo->prepare('SELECT expires_at FROM tokens WHERE id=?');
$st->execute(array($id));
$expires = $st->fetchColumn();

$url = BASE_URL . '/index.php?c=' . str_replace('-', '', $code);
$mod = QrMini::matrix($url);

if ($format === 'png' || $format === 'svg') {
    header('X-Feedback-Code: ' . $code);
    header('X-Feedback-Url: ' . $url);
    header('X-Feedback-Expires: ' . $expires);
    header('X-Feedback-Canteen: ' . (int)$canteen['id']);
    if ($format === 'png') {
        header('Content-Type: image/png');
        echo QrMini::png($mod, $scale);
    } else {
        header('Content-Type: image/svg+xml; charset=utf-8');
        echo QrMini::svg($mod);
    }
    exit;
}

$png = QrMini::png($mod, $scale);
json_out(array(
    'ok' => true,
    'code' => $code,
    'canteen_id' => (int)$canteen['id'],
    'canteen' => $canteen['address'],
    'url' => $url,
    'expires_at' => $expires,
    'qr_png_base64' => base64_encode($png),
    'qr_png_data_uri' => 'data:image/png;base64,' . base64_encode($png),
    'qr_svg' => QrMini::svg($mod),
));
