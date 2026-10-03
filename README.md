# FoodFeedback — анонимная оценка столовой (PHP 7.2 + MySQL)

## Установка
1. `mysql -u root -p < schema.sql`
2. Отредактируйте `config.php` (БД, `APP_PEPPER`, `BASE_URL`).
3. Создайте администратора: `php tools/create_admin.php admin StrongPass123`
4. Откройте `/admin/` → «Коды и меню» → выпустите коды, распечатайте QR.
5. Студенты открывают QR (ссылка с `?c=КОД`) или вводят код вручную на `/index.php`.
