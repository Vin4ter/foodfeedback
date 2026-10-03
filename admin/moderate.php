<?php
require __DIR__ . '/../db.php'; require_admin();
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !check_csrf(isset($in['csrf']) ? $in['csrf'] : null)) json_out(array('error' => 'CSRF'), 403);
$status = isset($in['status']) ? $in['status'] : '';
if (!in_array($status, array('approved', 'hidden'), true)) json_out(array('error' => 'bad status'), 422);
db()->prepare("UPDATE feedback SET comment_status=? WHERE id=? AND comment IS NOT NULL")->execute(array($status, (int)$in['id']));
json_out(array('ok' => true));
