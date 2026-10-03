<?php
require __DIR__ . '/../db.php'; require_admin();
$pdo = db(); $new = array(); $newCanteen = null; $msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf(isset($_POST['csrf']) ? $_POST['csrf'] : null)) exit('CSRF');
    $a = isset($_POST['action']) ? $_POST['action'] : '';

    if ($a === 'canteen_add') {
        $addr = trim(isset($_POST['address']) ? $_POST['address'] : '');
        if ($addr === '' || mb_strlen($addr) > 200) {
            $err = 'Введите адрес (до 200 символов)';
        } else {
            $pdo->prepare('INSERT INTO canteens (address) VALUES (?)')->execute(array($addr));
            $msg = 'Столовая добавлена, ID: ' . $pdo->lastInsertId();
        }
    } elseif ($a === 'canteen_delete') {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE canteen_id=?');
        $st->execute(array($id));
        if ((int)$st->fetchColumn() === 0) {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM tokens WHERE canteen_id=?')->execute(array($id));
            $pdo->prepare('DELETE FROM canteens WHERE id=?')->execute(array($id));
            $pdo->commit();
            $msg = 'Столовая удалена';
        } else {
            $pdo->prepare('UPDATE canteens SET active=0 WHERE id=?')->execute(array($id));
            $msg = 'У столовой есть отзывы, поэтому она перенесена в архив: новые коды не выдаются, статистика сохранена.';
        }
    } elseif ($a === 'canteen_restore') {
        $pdo->prepare('UPDATE canteens SET active=1 WHERE id=?')->execute(array((int)$_POST['id']));
        $msg = 'Столовая возвращена из архива';
    } elseif ($a === 'gen') {
        $cid = isset($_POST['canteen_id']) ? (int)$_POST['canteen_id'] : 0;
        $st = $pdo->prepare('SELECT id, address FROM canteens WHERE id=? AND active=1');
        $st->execute(array($cid));
        $newCanteen = $st->fetch();
        if (!$newCanteen) {
            $err = 'Выберите столовую';
        } else {
            $n = max(1, min(500, (int)$_POST['count'])); $ttl = max(1, min(168, (int)$_POST['ttl']));
            $ins = $pdo->prepare('INSERT INTO tokens (canteen_id, code_hash, issued_date, expires_at) VALUES (?, ?, CURDATE(), DATE_ADD(NOW(), INTERVAL ? HOUR))');
            for ($i = 0; $i < $n; $i++) {
                $c = generate_code();
                try { $ins->execute(array($cid, code_hash($c), $ttl)); $new[] = $c; } catch (PDOException $e) { $i--; }
            }
        }
    } elseif ($a === 'dish_add' && trim($_POST['name']) !== '') {
        $pdo->prepare('INSERT INTO dishes (name) VALUES (?)')->execute(array(mb_substr(trim($_POST['name']), 0, 100)));
    } elseif ($a === 'dish_toggle') {
        $pdo->prepare('UPDATE dishes SET active = 1 - active WHERE id=?')->execute(array((int)$_POST['id']));
    }
}
$canteens = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM reviews r WHERE r.canteen_id = c.id) reviews FROM canteens c ORDER BY c.active DESC, c.address')->fetchAll();
$activeCanteens = array_filter($canteens, function ($c) { return $c['active']; });
$dishes = $pdo->query('SELECT * FROM dishes ORDER BY active DESC, name')->fetchAll();
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Столовые, коды и меню — FoodFeedback</title><link rel="stylesheet" href="../assets/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script></head><body>
<main class="card wide">
<div class="nav noprint"><h1 style="margin:0">🏷 Столовые, коды и меню</h1><a href="index.php">Аналитика</a><a href="manage.php">Столовые, коды и меню</a><a href="logout.php">Выйти</a></div>
<?php if ($msg): ?><div class="msg noprint"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="err noprint"><?= h($err) ?></div><?php endif; ?>

<section class="noprint"><h2>Столовые (адреса)</h2>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="canteen_add">
<input name="address" placeholder="Адрес столовой, например: ул. Ленина, 5" maxlength="200" required style="min-width:320px"><button class="btn">Добавить</button></form>
<table><thead><tr><th>ID (для API)</th><th>Адрес</th><th>Статус</th><th>Отзывов</th><th></th></tr></thead>
<?php foreach ($canteens as $c): ?><tr>
<td><b><?= (int)$c['id'] ?></b></td><td><?= h($c['address']) ?></td><td><?= $c['active'] ? 'активна' : 'в архиве' ?></td><td><?= (int)$c['reviews'] ?></td>
<td><form method="post" style="display:flex;gap:6px" onsubmit="return confirm('<?= $c['reviews'] ? 'У столовой есть отзывы — она будет перенесена в архив. Продолжить?' : 'Удалить столовую и её неиспользованные коды?' ?>')">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
<?php if (!$c['active']): ?><button class="btn sm sec" name="action" value="canteen_restore" onclick="this.form.onsubmit=null">Вернуть</button><?php endif; ?>
<button class="btn sm red" name="action" value="canteen_delete">Удалить</button></form></td></tr>
<?php endforeach; ?></table></section>

<section class="noprint"><h2>Выпустить одноразовые коды</h2>
<p class="muted">Коды привязываются к выбранной столовой. Печатайте на чеках или наклейках на подносы. В базе хранится только хэш — повторно показать коды нельзя.</p>
<?php if ($activeCanteens): ?>
<form method="post" class="inline">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="gen">
<label>Столовая<select name="canteen_id"><?php foreach ($activeCanteens as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['address']) ?></option><?php endforeach; ?></select></label>
<label>Количество<input name="count" type="number" value="50" min="1" max="500"></label>
<label>Срок жизни (часов)<input name="ttl" type="number" value="24" min="1" max="168"></label>
<button class="btn">Сгенерировать</button></form>
<?php else: ?><p class="err">Сначала добавьте хотя бы одну столовую.</p><?php endif; ?></section>

<?php if ($new): ?>
<section><h2>Новые коды (<?= count($new) ?>) — <?= h($newCanteen['address']) ?></h2>
<button class="btn sec noprint" onclick="window.print()">🖨 Печать</button>
<div class="codes" id="codes">
<?php foreach ($new as $c): ?><div class="ticket"><div class="qr" data-url="<?= h(BASE_URL . '/index.php?c=' . $c) ?>"></div><b><?= h($c) ?></b></div><?php endforeach; ?>
</div></section>
<script>document.querySelectorAll('.qr').forEach(function(e){new QRCode(e,{text:e.dataset.url,width:110,height:110});});</script>
<?php endif; ?>

<section class="noprint"><h2>Меню (блюда для отметки в отзыве)</h2>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="dish_add">
<input name="name" placeholder="Название блюда" required><button class="btn">Добавить</button></form>
<table><?php foreach ($dishes as $d): ?><tr><td><?= h($d['name']) ?></td><td><?= $d['active'] ? 'в меню' : 'скрыто' ?></td><td>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="dish_toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
<button class="btn sm sec"><?= $d['active'] ? 'Убрать' : 'Вернуть' ?></button></form></td></tr><?php endforeach; ?></table></section>
</main></body></html>
