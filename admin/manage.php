<?php
require __DIR__ . '/../db.php'; require_admin();
$pdo = db(); $new = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf(isset($_POST['csrf']) ? $_POST['csrf'] : null)) exit('CSRF');
    $a = isset($_POST['action']) ? $_POST['action'] : '';
    if ($a === 'gen') {
        $n = max(1, min(500, (int)$_POST['count'])); $ttl = max(1, min(168, (int)$_POST['ttl']));
        $ins = $pdo->prepare('INSERT INTO tokens (code_hash, issued_date, expires_at) VALUES (?, CURDATE(), DATE_ADD(NOW(), INTERVAL ? HOUR))');
        for ($i = 0; $i < $n; $i++) {
            $c = generate_code();
            try { $ins->execute(array(code_hash($c), $ttl)); $new[] = $c; } catch (PDOException $e) { $i--; }
        }
    } elseif ($a === 'dish_add' && trim($_POST['name']) !== '') {
        $pdo->prepare('INSERT INTO dishes (name) VALUES (?)')->execute(array(mb_substr(trim($_POST['name']), 0, 100)));
    } elseif ($a === 'dish_toggle') {
        $pdo->prepare('UPDATE dishes SET active = 1 - active WHERE id=?')->execute(array((int)$_POST['id']));
    }
}
$dishes = $pdo->query('SELECT * FROM dishes ORDER BY active DESC, name')->fetchAll();
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Коды и меню — FoodFeedback</title><link rel="stylesheet" href="../assets/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script></head><body>
<main class="card wide">
<div class="nav noprint"><h1 style="margin:0">🎟 Коды и меню</h1><a href="index.php">Аналитика</a><a href="manage.php">Коды и меню</a><a href="logout.php">Выйти</a></div>

<section class="noprint"><h2>Выпустить одноразовые коды</h2>
<p class="muted">Печатайте на чеках или наклейках на подносы. В базе хранится только хэш — повторно показать коды нельзя.</p>
<form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="gen">
<label>Количество<input name="count" type="number" value="50" min="1" max="500"></label>
<label>Срок жизни (часов)<input name="ttl" type="number" value="24" min="1" max="168"></label>
<button class="btn">Сгенерировать</button></form></section>

<?php if ($new): ?>
<section><h2>Новые коды (<?= count($new) ?>)</h2>
<button class="btn sec noprint" onclick="window.print()">🖨 Печать</button>
<div class="codes" id="codes">
<?php foreach ($new as $c): ?><div class="ticket"><div class="qr" data-url="<?= h(BASE_URL . '/index.php?c=' . $c) ?>"></div><b><?= h($c) ?></b></div><?php endforeach; ?>
</div></section>
<script>document.querySelectorAll('.qr').forEach(function(e){new QRCode(e,{text:e.dataset.url,width:110,height:110});});</script>
<?php endif; ?>

<section class="noprint"><h2>Меню (блюда для оценки)</h2>
<form method="post" style="display:flex;gap:10px"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="dish_add">
<input name="name" placeholder="Название блюда" required><button class="btn">Добавить</button></form>
<table><?php foreach ($dishes as $d): ?><tr><td><?= h($d['name']) ?></td><td><?= $d['active'] ? 'в меню' : 'скрыто' ?></td><td>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="dish_toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
<button class="btn sm sec"><?= $d['active'] ? 'Убрать' : 'Вернуть' ?></button></form></td></tr><?php endforeach; ?></table></section>
</main></body></html>
