<?php
require __DIR__ . '/../db.php';
session_name('ffadmin'); session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT * FROM admins WHERE login=?');
    $st->execute(array(isset($_POST['login']) ? $_POST['login'] : ''));
    $a = $st->fetch();
    if ($a && password_verify(isset($_POST['password']) ? $_POST['password'] : '', $a['pass_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = $a['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: index.php'); exit;
    }
    sleep(1);
    $error = 'Неверный логин или пароль';
}
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход — FoodFeedback</title><link rel="stylesheet" href="../assets/style.css"></head><body>
<main class="card" style="max-width:380px"><h1>Вход для администрации</h1>
<?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
<form method="post">
<label class="field">Логин<input name="login" required autofocus></label>
<label class="field">Пароль<input name="password" type="password" required></label>
<button class="btn">Войти</button></form></main></body></html>
