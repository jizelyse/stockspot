<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
$pdo = db();
$from = get('from'); $to = get('to'); $type = get('type'); $pid = parse_uint(get('product'));
$where = '1=1'; $params = [];
if ($from !== '' && valid_date($from)) { $where .= ' AND m.moved_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to !== '' && valid_date($to))     { $where .= ' AND m.moved_at <= ?'; $params[] = $to . ' 23:59:59'; }
if (in_array($type, ['IN','OUT','ADJUST','WASTE'], true)) { $where .= ' AND m.type = ?'; $params[] = $type; }
if ($pid) { $where .= ' AND m.product_id = ?'; $params[] = $pid; }
$c = $pdo->prepare("SELECT COUNT(*) FROM stock_movements m WHERE $where"); $c->execute($params);
[$page, $pages, $off, $size] = paginate((int)$c->fetchColumn(), (int)get('page', '1'));
$st = $pdo->prepare("SELECT m.*, p.product_name, p.unit, u.full_name FROM stock_movements m JOIN products p ON p.product_id = m.product_id JOIN users u ON u.user_id = m.user_id WHERE $where ORDER BY m.moved_at DESC, m.movement_id DESC LIMIT $size OFFSET $off");
$st->execute($params); $rows = $st->fetchAll();
$plist = $pdo->query('SELECT product_id, product_name FROM products ORDER BY product_name')->fetchAll();
page_header('Movement Log', 'movements');
?>
<form method="get" class="row">
<div><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
<div><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
<div><label>Type</label><select name="type"><option value="">All</option><?php foreach (['IN','OUT','ADJUST','WASTE'] as $t): ?><option <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
<div><label>Product</label><select name="product"><option value="">All</option><?php foreach ($plist as $p): ?><option value="<?= (int)$p['product_id'] ?>" <?= $pid === (int)$p['product_id'] ? 'selected' : '' ?>><?= e($p['product_name']) ?></option><?php endforeach; ?></select></div>
<div style="flex:0 0 auto"><button class="btn" type="submit">Filter</button> <a class="btn sec" href="movements.php">Reset</a></div></form>
<div class="tablewrap"><table><tr><th>When</th><th>Product</th><th>Type</th><th class="num">Qty</th><th>Reason</th><th>By</th></tr>
<?php foreach ($rows as $r): ?><tr><td class="nw"><?= e($r['moved_at']) ?></td><td><a href="product_view.php?id=<?= (int)$r['product_id'] ?>"><?= e($r['product_name']) ?></a></td><td><?= e($r['type']) ?></td>
<td class="num"><?= (int)$r['quantity'] > 0 ? '+' : '' ?><?= (int)$r['quantity'] ?> <?= e($r['unit']) ?></td><td><?= e($r['reason'] ?? '') ?></td><td><?= e($r['full_name']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No movements match your filters.</td></tr><?php endif; ?></table></div>
<?= pager($page, $pages) ?>
<?php page_footer();
