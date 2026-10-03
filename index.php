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
  <p class="muted">Анонимно: мы не знаем, кто вы. Код на чеке нужен только чтобы подтвердить, что вы реально были в столовой — он одноразовый и не связывается с вашим отзывом.</p>

  <div id="done" class="ok" hidden>Спасибо! Ваш отзыв принят анонимно. 💚</div>

  <div id="formBox">
    <label class="field">Код с чека или подноса
      <input id="code" value="<?= h($code) ?>" placeholder="XXXX-XXXX" maxlength="9" autocomplete="off">
    </label>

    <?php if ($dishes): ?>
    <section class="block">
      <h2>Что вы ели?</h2>
      <p class="muted small">Отметьте блюда (можно несколько)</p>
      <div class="checks">
        <?php foreach ($dishes as $d): ?>
          <label class="check"><input type="checkbox" class="dish" value="<?= (int)$d['id'] ?>"> <?= h($d['name']) ?></label>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="block">
      <h2>Общая оценка</h2>
      <div class="stars" data-key="overall" data-rating="0"></div>
      <textarea id="comment" maxlength="500" placeholder="Ваш отзыв (необязательно)"></textarea>
    </section>

    <section class="block">
      <h2>Чистота</h2>
      <div class="stars" data-key="cleanliness" data-rating="0"></div>
    </section>

    <section class="block">
      <h2>Обслуживание</h2>
      <div class="stars" data-key="service" data-rating="0"></div>
    </section>

    <div id="err" class="err" hidden></div>
    <button id="send" class="btn">Отправить анонимно</button>
    <p class="muted small">Общая оценка обязательна, чистота и обслуживание — по желанию. Комментарии со ссылками и оскорблениями проходят модерацию.</p>
  </div>
</main>
<script src="assets/app.js"></script>
</body></html>
