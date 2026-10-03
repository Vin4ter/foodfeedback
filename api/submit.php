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

$overall = isset($in['overall']) ? (int)$in['overall'] : 0;
if ($overall < 1 || $overall > 5) json_out(array('error' => 'Поставьте общую оценку'), 422);

$optional = array();
foreach (array('cleanliness', 'service') as $k) {
    $v = isset($in[$k]) ? (int)$in[$k] : 0;
    if ($v < 0 || $v > 5) json_out(array('error' => 'Некорректные данные'), 422);
    $optional[$k] = $v > 0 ? $v : null;
}

$dishIds = array();
if (!empty($in['dish_ids']) && is_array($in['dish_ids'])) {
    foreach ($in['dish_ids'] as $d) {
        $d = (int)$d;
        if ($d > 0) $dishIds[$d] = $d;
    }
    $dishIds = array_values($dishIds);
    if (count($dishIds) > 30) json_out(array('error' => 'Слишком много блюд'), 422);
}
if ($dishIds) {
    $ph = implode(',', array_fill(0, count($dishIds), '?'));
    $c = $pdo->prepare('SELECT COUNT(*) FROM dishes WHERE active=1 AND id IN (' . $ph . ')');
    $c->execute($dishIds);
    if ((int)$c->fetchColumn() !== count($dishIds)) json_out(array('error' => 'Некорректный список блюд'), 422);
}

$comment = isset($in['comment']) ? trim((string)$in['comment']) : '';
$comment = mb_substr(strip_tags($comment), 0, MAX_COMMENT);
$status = 'none';
if ($comment !== '') $status = is_suspicious($comment) ? 'pending' : 'approved';
else $comment = null;

// 3. Погашение кода + запись отзыва в одной транзакции
$ts = date('Y-m-d H:00:00'); // округляем время до часа
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id, canteen_id FROM tokens WHERE code_hash=? AND used=0 AND expires_at > NOW() FOR UPDATE');
    $st->execute(array(code_hash($code)));
    $tok = $st->fetch();
    if (!$tok) { $pdo->rollBack(); json_out(array('error' => 'Код недействителен, просрочен или уже использован'), 403); }
    $pdo->prepare('UPDATE tokens SET used=1, used_date=CURDATE() WHERE id=?')->execute(array($tok['id']));

    $pdo->prepare('INSERT INTO reviews (canteen_id, overall_rating, cleanliness_rating, service_rating, comment, comment_status, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute(array($tok['canteen_id'], $overall, $optional['cleanliness'], $optional['service'], $comment, $status, $ts));
    $rid = $pdo->lastInsertId();

    if ($dishIds) {
        $ins = $pdo->prepare('INSERT INTO review_dishes (review_id, dish_id) VALUES (?, ?)');
        foreach ($dishIds as $d) $ins->execute(array($rid, $d));
    }
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(array('error' => 'Ошибка сервера'), 500);
}
json_out(array('ok' => true));
