<?php require __DIR__ . '/../db.php'; require_admin(); ?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Дашборд — FoodFeedback</title><link rel="stylesheet" href="../assets/style.css"></head><body>
<main class="card wide">
  <div class="nav"><h1 style="margin:0">📊 Дашборд</h1>
    <a href="index.php">Аналитика</a><a href="manage.php">Коды и меню</a><a href="logout.php">Выйти</a>
    <select id="days" style="width:auto"><option value="7">7 дней</option><option value="30" selected>30 дней</option><option value="90">90 дней</option></select>
  </div>
  <div class="kpis" id="kpis"></div>
  <h2>Динамика средней оценки по дням</h2>
  <canvas id="line" width="1000" height="320"></canvas>
  <div class="grid2">
    <div><h2>Средняя оценка по категориям</h2><canvas id="bars" width="500" height="260"></canvas></div>
    <div><h2>Распределение оценок</h2><canvas id="dist" width="500" height="260"></canvas></div>
  </div>
  <h2>Рейтинг блюд</h2>
  <table id="dishes"><thead><tr><th>Блюдо</th><th>Средняя</th><th>Оценок</th></tr></thead><tbody></tbody></table>
  <h2>Комментарии и модерация</h2>
  <div id="comments"></div>
</main>
<script>var CSRF = <?= json_encode($_SESSION['csrf']) ?>;</script>
<script src="../assets/admin.js"></script>
</body></html>
