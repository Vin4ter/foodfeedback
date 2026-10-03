<?php
// Проверка генератора QR без БД: php tools/qr_demo.php "http://localhost/foodfeedback/index.php?c=TEST1234"
// Создаст qr_demo.png и qr_demo.svg — отсканируйте картинку телефоном.
if (php_sapi_name() !== 'cli') exit('CLI only');
require __DIR__ . '/../qr.php';
$text = isset($argv[1]) ? $argv[1] : 'http://localhost/foodfeedback/index.php?c=TEST1234';
$m = QrMini::matrix($text);
file_put_contents('qr_demo.png', QrMini::png($m, 8));
file_put_contents('qr_demo.svg', QrMini::svg($m));
echo "Готово: qr_demo.png и qr_demo.svg ($text)\n";
