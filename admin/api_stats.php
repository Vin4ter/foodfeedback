<?php
require __DIR__ . '/../db.php'; require_admin();
$days = isset($_GET['days']) ? max(1, min(365, (int)$_GET['days'])) : 30;
$cid = isset($_GET['canteen']) ? max(0, (int)$_GET['canteen']) : 0;
$pdo = db();
$q = function ($sql, $p) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); };

$w = 'r.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
$p = array($days);
if ($cid > 0) { $w .= ' AND r.canteen_id = ?'; $p[] = $cid; }

$daily = $q("SELECT DATE(r.created_at) d, ROUND(AVG(r.overall_rating),2) o, ROUND(AVG(r.cleanliness_rating),2) c,
  ROUND(AVG(r.service_rating),2) s, COUNT(*) n FROM reviews r WHERE $w GROUP BY d ORDER BY d", $p);
$sum = $q("SELECT ROUND(AVG(r.overall_rating),2) o, ROUND(AVG(r.cleanliness_rating),2) c,
  ROUND(AVG(r.service_rating),2) s, COUNT(*) n FROM reviews r WHERE $w", $p);
$dishes = $q("SELECT d.name, ROUND(AVG(r.overall_rating),2) a, COUNT(*) n FROM review_dishes rd
  JOIN reviews r ON r.id = rd.review_id JOIN dishes d ON d.id = rd.dish_id WHERE $w
  GROUP BY d.id, d.name ORDER BY a DESC, n DESC", $p);
$dist = $q("SELECT r.overall_rating r, COUNT(*) n FROM reviews r WHERE $w GROUP BY r.overall_rating ORDER BY r.overall_rating", $p);
$comments = $q("SELECT r.id, r.overall_rating rating, r.comment, r.comment_status s, r.created_at t, c.address canteen
  FROM reviews r JOIN canteens c ON c.id = r.canteen_id
  WHERE r.comment IS NOT NULL AND $w
  ORDER BY (r.comment_status = 'pending') DESC, r.created_at DESC LIMIT 100", $p);

$tw = 't.issued_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
$tp = array($days);
if ($cid > 0) { $tw .= ' AND t.canteen_id = ?'; $tp[] = $cid; }
$tok = $q("SELECT COUNT(*) issued, SUM(t.used) used FROM tokens t WHERE $tw", $tp);

$canteens = $q('SELECT c.id, c.address, c.active, ROUND(AVG(r.overall_rating),2) a, COUNT(r.id) n
  FROM canteens c LEFT JOIN reviews r ON r.canteen_id = c.id AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
  GROUP BY c.id, c.address, c.active ORDER BY c.active DESC, c.address', array($days));

json_out(array('daily' => $daily, 'summary' => $sum[0], 'dishes' => $dishes, 'dist' => $dist,
    'comments' => $comments, 'tokens' => $tok[0], 'canteens' => $canteens));
