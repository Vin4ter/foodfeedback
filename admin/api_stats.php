<?php
require __DIR__ . '/../db.php'; require_admin();
$days = isset($_GET['days']) ? max(1, min(365, (int)$_GET['days'])) : 30;
$pdo = db();
$q = function ($sql, $p) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); };

$daily = $q('SELECT DATE(created_at) d, category_id c, ROUND(AVG(rating),2) a, COUNT(*) n FROM feedback
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY d, c ORDER BY d', array($days));
$cats = $q('SELECT c.id, c.title, ROUND(AVG(f.rating),2) a, COUNT(f.id) n FROM categories c
  LEFT JOIN feedback f ON f.category_id=c.id AND f.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY c.id ORDER BY c.id', array($days));
$dishes = $q('SELECT d.name, ROUND(AVG(f.rating),2) a, COUNT(f.id) n FROM feedback f JOIN dishes d ON d.id=f.dish_id
  WHERE f.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY d.id ORDER BY a DESC, n DESC', array($days));
$dist = $q('SELECT rating r, COUNT(*) n FROM feedback WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY rating ORDER BY rating', array($days));
$comments = $q("SELECT f.id, f.rating, f.comment, f.comment_status s, f.created_at t, c.title cat, d.name dish
  FROM feedback f JOIN categories c ON c.id=f.category_id LEFT JOIN dishes d ON d.id=f.dish_id
  WHERE f.comment IS NOT NULL AND f.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
  ORDER BY (f.comment_status='pending') DESC, f.created_at DESC LIMIT 100", array($days));
$tok = $q('SELECT COUNT(*) issued, SUM(used) used FROM tokens WHERE issued_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)', array($days));
json_out(array('daily' => $daily, 'cats' => $cats, 'dishes' => $dishes, 'dist' => $dist, 'comments' => $comments, 'tokens' => $tok[0]));
