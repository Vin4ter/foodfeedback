<?php
require __DIR__ . '/db.php';
$dishes = db()->query('SELECT id, name FROM dishes WHERE active=1 ORDER BY name')->fetchAll();
$code = isset($_GET['c']) ? norm_code($_GET['c']) : '';
if (strlen($code) === 8) $code = substr($code, 0, 4) . '-' . substr($code, 4);
?><!doctype html>
<html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>FoodFeedback — оцените столовую</title>
<link rel="stylesheet" href="assets/style.css">
</head><body>
<main class="card">
  <h1>🍽 FoodFeedback</h1>
  <p class="muted">Анонимно: мы не знаем, кто вы. Код на чеке/подносе нужен только чтобы подтвердить, что вы реально были в столовой — он одноразовый и не связывается с вашим отзывом.</p>

  <div id="done" class="ok" hidden>Спасибо! Ваш отзыв принят анонимно. 💚</div>

  <div id="formBox">
    <label class="field">Код с чека или подноса
      <input id="code" value="<?= h($code) ?>" placeholder="XXXX-XXXX" maxlength="9" autocomplete="off">
    </label>

    <section class="block" data-cat="1">
      <h2>Блюдо</h2>
      <select id="dish"><option value="">— выберите блюдо —</option>
        <?php foreach ($dishes as $d): ?><option value="<?= (int)$d['id'] ?>"><?= h($d['name']) ?></option><?php endforeach; ?>
      </select>
      <div class="stars" data-rating="0"></div>
      <textarea maxlength="500" placeholder="Комментарий (необязательно)"></textarea>
    </section>

    <section class="block" data-cat="2">
      <h2>Чистота</h2>
      <div class="stars" data-rating="0"></div>
      <textarea maxlength="500" placeholder="Комментарий (необязательно)"></textarea>
    </section>

    <section class="block" data-cat="3">
      <h2>Обслуживание</h2>
      <div class="stars" data-rating="0"></div>
      <textarea maxlength="500" placeholder="Комментарий (необязательно)"></textarea>
    </section>

    <div id="err" class="err" hidden></div>
    <button id="send" class="btn">Отправить анонимно</button>
    <p class="muted small">Оцените хотя бы один пункт. Комментарии со ссылками и оскорблениями проходят модерацию.</p>
  </div>
</main>
<script src="assets/app.js"></script>
</body></html>
