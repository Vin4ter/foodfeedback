<?php
// Использование: php tools/create_admin.php логин пароль
if (php_sapi_name() !== 'cli') exit('CLI only');
require __DIR__ . '/../db.php';
if ($argc < 3) exit("php tools/create_admin.php <login> <password>\n");
$st = db()->prepare('INSERT INTO admins (login, pass_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE pass_hash=VALUES(pass_hash)');
$st->execute(array($argv[1], password_hash($argv[2], PASSWORD_DEFAULT)));
echo "Администратор {$argv[1]} создан/обновлён\n";
