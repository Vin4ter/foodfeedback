<?php
require __DIR__ . '/../db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(array('error' => 'Метод не поддерживается'), 405);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) json_out(array('error' => 'Некорректный запрос'), 400);

$pdo = db();

// 1. Антифлуд по хэшу IP
$pdo->prepare('DELETE FROM rate_limits WHERE hit_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')->execute();
$st = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE ip_hash=? AND hit_at > DATE_SUB(NOW(), INTERVAL ' . (int)RATE_WINDOW_MIN . ' MINUTE)');
$st->execute(array(ip_hash()));
if ((int)$st->fetchColumn() >= RATE_LIMIT) json_out(array('error' => 'Слишком много попыток. Попробуйте позже.'), 429);
$pdo->prepare('INSERT INTO rate_limits (ip_hash, hit_at) VALUES (?, NOW())')->execute(array(ip_hash()));

// 2. Валидация
$code = isset($in['code']) ? norm_code($in['code']) : '';
if (strlen($code) !== 8) json_out(array('error' => 'Введите код с чека или подноса'), 422);
$items = isset($in['items']) && is_array($in['items']) ? $in['items'] : array();
if (!$items || count($items) > 3) json_out(array('error' => 'Оцените хотя бы один пункт'), 422);

$clean = array(); $seen = array();
foreach ($items as $it) {
    $cat = isset($it['category']) ? (int)$it['category'] : 0;
    $rating = isset($it['rating']) ? (int)$it['rating'] : 0;
    if ($cat < 1 || $cat > 3 || isset($seen[$cat]) || $rating < 1 || $rating > 5) json_out(array('error' => 'Некорректные данные'), 422);
    $seen[$cat] = 1;
    $dish = null;
    if ($cat === 1) {
        $dish = isset($it['dish_id']) ? (int)$it['dish_id'] : 0;
        $c = $pdo->prepare('SELECT COUNT(*) FROM dishes WHERE id=? AND active=1');
        $c->execute(array($dish));
        if (!$c->fetchColumn()) json_out(array('error' => 'Выберите блюдо'), 422);
    }
    $comment = isset($it['comment']) ? trim((string)$it['comment']) : '';
    $comment = mb_substr(strip_tags($comment), 0, MAX_COMMENT);
    $status = 'none';
    if ($comment !== '') $status = is_suspicious($comment) ? 'pending' : 'approved'; else $comment = null;
    $clean[] = array($cat, $dish, $rating, $comment, $status);
}

// 3. Погашение кода + запись отзывов в одной транзакции
$ts = date('Y-m-d H:00:00'); // округляем время до часа
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id FROM tokens WHERE code_hash=? AND used=0 AND expires_at > NOW() FOR UPDATE');
    $st->execute(array(code_hash($code)));
    $tid = $st->fetchColumn();
    if (!$tid) { $pdo->rollBack(); json_out(array('error' => 'Код недействителен, просрочен или уже использован'), 403); }
    $pdo->prepare('UPDATE tokens SET used=1, used_date=CURDATE() WHERE id=?')->execute(array($tid));
    $ins = $pdo->prepare('INSERT INTO feedback (category_id, dish_id, rating, comment, comment_status, created_at) VALUES (?,?,?,?,?,?)');
    foreach ($clean as $r) $ins->execute(array($r[0], $r[1], $r[2], $r[3], $r[4], $ts));
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(array('error' => 'Ошибка сервера'), 500);
}
json_out(array('ok' => true));
