# FoodFeedback — анонимная оценка столовой (PHP 7.2 + MySQL)

## Установка
1. `mysql -u root -p < schema.sql`
2. Отредактируйте `config.php` (БД, `APP_PEPPER`, `BASE_URL`).
3. Создайте администратора: `php tools/create_admin.php admin StrongPass123`
4. Откройте `/admin/` → «Коды и меню» → выпустите коды, распечатайте QR.
5. Студенты открывают QR (ссылка с `?c=КОД`) или вводят код вручную на `/index.php`.

## Обновление с v1 на v2
```bash
sudo mysql < migration_v2.sql   # добавляет столовые, новые таблицы отзывов; старые отзывы -> feedback_old
```
Новые установки: просто `schema.sql`.

## API для кассы (код + QR на чек)
В `config.php` задайте `API_KEY` (например `openssl rand -hex 24`).
`canteen_id` обязателен: ID столовой виден в админке («Столовые, коды и меню»).

```bash
# JSON: код, ссылка, срок, QR (PNG base64 и SVG)
curl -X POST http://localhost/foodfeedback/api/issue_code.php -H "X-API-Key: ВАШ_КЛЮЧ" -d "canteen_id=1"

# Готовая картинка QR (код — в заголовке X-Feedback-Code)
curl -X POST http://localhost/foodfeedback/api/issue_code.php -H "X-API-Key: ВАШ_КЛЮЧ" \
     -d "canteen_id=1&format=png&qr_scale=8" -D - -o qr.png
```
Параметры: `canteen_id` (обязательно), `ttl_hours` (1-168, по умолчанию 24), `qr_scale` (2-20), `format` (`json`|`png`|`svg`).
Проверка генератора QR без БД: `php tools/qr_demo.php`.
