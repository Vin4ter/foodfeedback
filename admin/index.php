<?php require __DIR__ . '/../db.php'; require_admin(); ?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Дашборд — FoodFeedback</title><link rel="stylesheet" href="../assets/style.css"></head><body>
<main class="card wide">
  <div class="nav"><h1 style="margin:0">📊 Дашборд</h1>
    <a href="index.php">Аналитика</a><a href="manage.php">Столовые, коды и меню</a><a href="logout.php">Выйти</a>
  </div>
  <div class="nav">
    <select id="canteen" style="width:auto;min-width:240px;margin:0"><option value="0">Все столовые</option></select>
    <span class="avg" id="avgBadge">—</span>
    <select id="days" style="width:auto;margin:0"><option value="7">7 дней</option><option value="30" selected>30 дней</option><option value="90">90 дней</option></select>
  </div>
  <div class="kpis" id="kpis"></div>
  <h2>Динамика средней оценки по дням</h2>
  <canvas id="line" width="1000" height="320"></canvas>
  <div class="grid2">
    <div><h2>Средние оценки</h2><canvas id="bars" width="500" height="260"></canvas></div>
    <div><h2>Распределение общей оценки</h2><canvas id="dist" width="500" height="260"></canvas></div>
  </div>
  <h2>Блюда</h2>
  <p class="muted small">Средняя общая оценка отзывов, в которых клиенты отметили это блюдо.</p>
  <table id="dishes"><thead><tr><th>Блюдо</th><th>Средняя общая оценка</th><th>Отзывов</th></tr></thead><tbody></tbody></table>
  <h2>Комментарии и модерация</h2>
  <div id="comments"></div>
</main>
<script>var CSRF = <?= json_encode($_SESSION['csrf']) ?>;</script>
<script src="../assets/admin.js"></script>
</body></html>
