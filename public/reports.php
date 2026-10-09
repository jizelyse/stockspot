<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
$pdo = db();
$types = ['lowstock' => 'Low stock', 'expiry' => 'Expiry', 'movements' => 'Movement summary', 'wastage' => 'Wastage'];
$type = isset($types[get('type')]) ? get('type') : 'lowstock';
$from = valid_date(get('from')) ? get('from') : date('Y-m-01');
$to   = valid_date(get('to')) ? get('to') : date('Y-m-d');
if ($from > $to) { [$from, $to] = [$to, $from]; }
$cols = []; $rows = [];
if ($type === 'lowstock') {
    $cols = ['SKU', 'Product', 'Supplier', 'On hand', 'Reorder level', 'Status'];
    foreach ($pdo->query(product_sql('p.is_archived = 0'))->fetchAll() as $p) { $s = product_status($p); if (in_array($s, ['LOW','OUT'], true)) $rows[] = [$p['sku'], $p['product_name'], $p['supplier_name'] ?? '-', (int)$p['qty'], (int)$p['reorder_level'], $s]; }
} elseif ($type === 'expiry') {
    $cols = ['SKU', 'Product', 'Batch #', 'Expiry date', 'Qty in batch', 'Days left'];
    $st = $pdo->prepare('SELECT p.sku, p.product_name, b.batch_id, b.expiry_date, b.quantity_on_hand, DATEDIFF(b.expiry_date, CURDATE()) AS days FROM stock_batches b JOIN products p ON p.product_id = b.product_id WHERE b.quantity_on_hand > 0 AND b.expiry_date IS NOT NULL AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY b.expiry_date');
    $st->execute([EXPIRY_WARN_DAYS]);
    foreach ($st->fetchAll() as $r) $rows[] = [$r['sku'], $r['product_name'], (int)$r['batch_id'], $r['expiry_date'], (int)$r['quantity_on_hand'], (int)$r['days'] < 0 ? 'EXPIRED' : (int)$r['days']];
} elseif ($type === 'movements') {
    $cols = ['SKU', 'Product', 'Stock in', 'Sold / out', 'Waste', 'Adjustments (net)'];
    $st = $pdo->prepare("SELECT p.sku, p.product_name,
        COALESCE(SUM(CASE WHEN m.type='IN' THEN m.quantity END),0) AS qin,
        COALESCE(-SUM(CASE WHEN m.type='OUT' THEN m.quantity END),0) AS qout,
        COALESCE(-SUM(CASE WHEN m.type='WASTE' THEN m.quantity END),0) AS qw,
        COALESCE(SUM(CASE WHEN m.type='ADJUST' THEN m.quantity END),0) AS qa
        FROM stock_movements m JOIN products p ON p.product_id = m.product_id
        WHERE m.moved_at BETWEEN ? AND ? GROUP BY p.product_id ORDER BY p.product_name");
    $st->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    foreach ($st->fetchAll() as $r) $rows[] = [$r['sku'], $r['product_name'], (int)$r['qin'], (int)$r['qout'], (int)$r['qw'], (int)$r['qa']];
} else {
    $cols = ['SKU', 'Product', 'Units wasted', 'Estimated cost lost'];
    $st = $pdo->prepare("SELECT p.sku, p.product_name, -SUM(m.quantity) AS u, -SUM(m.quantity) * p.cost_price AS c FROM stock_movements m JOIN products p ON p.product_id = m.product_id WHERE m.type='WASTE' AND m.moved_at BETWEEN ? AND ? GROUP BY p.product_id ORDER BY c DESC");
    $st->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    foreach ($st->fetchAll() as $r) $rows[] = [$r['sku'], $r['product_name'], (int)$r['u'], number_format((float)$r['c'], 2)];
}
if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stockspot_' . $type . '_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, $cols);
    foreach ($rows as $r) fputcsv($o, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, $r));
    exit;
}
$maxw = 1; if ($type === 'wastage') foreach ($rows as $r) $maxw = max($maxw, (float)str_replace(',', '', $r[3]));
page_header('Reports', 'reports');
?>
<div class="tabs"><?php foreach ($types as $k => $v): ?><a class="<?= $k === $type ? 'on' : '' ?>" href="?type=<?= $k ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"><?= e($v) ?></a><?php endforeach; ?></div>
<?php if (in_array($type, ['movements', 'wastage'], true)): ?>
<form method="get" class="row"><input type="hidden" name="type" value="<?= e($type) ?>">
<div><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div><div><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
<div style="flex:0 0 auto"><button class="btn" type="submit">Apply</button></div></form>
<?php else: ?><p class="hint">Current snapshot<?= $type === 'expiry' ? ' (batches expiring within ' . EXPIRY_WARN_DAYS . ' days, or already expired)' : '' ?>.</p><?php endif; ?>
<div class="tablewrap"><table><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?><?php if ($type === 'wastage'): ?><th style="width:25%"></th><?php endif; ?></tr>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?>
<?php if ($type === 'wastage'): $w = (float)str_replace(',', '', $r[3]); ?><td><div class="bar"><i style="width:<?= round($w / $maxw * 100) ?>%"></i></div></td><?php endif; ?></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="<?= count($cols) ?>" class="empty">No data for this report.</td></tr><?php endif; ?></table></div>
<p><a class="btn sec" href="?type=<?= e($type) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv">Download CSV</a></p>
<?php page_footer();
