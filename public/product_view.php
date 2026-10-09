<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
$pdo = db();
$id = parse_uint(get('id'));
$st = $pdo->prepare(product_sql('p.product_id = ?')); $st->execute([(int)$id]);
$p = $st->fetch();
if (!$id || !$p) { http_response_code(404); exit('Product not found. <a href="products.php">Back</a>'); }
$b = $pdo->prepare('SELECT * FROM stock_batches WHERE product_id = ? ORDER BY (quantity_on_hand = 0), (expiry_date IS NULL), expiry_date, batch_id'); $b->execute([$id]);
$batches = $b->fetchAll();
$m = $pdo->prepare('SELECT m.*, u.full_name FROM stock_movements m JOIN users u ON u.user_id = m.user_id WHERE m.product_id = ? ORDER BY m.moved_at DESC, m.movement_id DESC LIMIT 30'); $m->execute([$id]);
$moves = $m->fetchAll();
page_header($p['product_name'], 'inventory');
?>
<p><?= status_badge(product_status($p)) ?> &nbsp; SKU <strong><?= e($p['sku']) ?></strong> &middot; <?= e($p['category_name']) ?> &middot; Supplier: <?= e($p['supplier_name'] ?? 'none') ?></p>
<div class="cards">
 <div class="card"><div class="n"><?= (int)$p['qty'] ?></div><div class="l">On hand (<?= e($p['unit']) ?>)</div></div>
 <div class="card"><div class="n"><?= (int)$p['reorder_level'] ?></div><div class="l">Reorder level</div></div>
 <div class="card"><div class="n"><?= money($p['cost_price']) ?></div><div class="l">Cost price</div></div>
 <div class="card"><div class="n"><?= money($p['selling_price']) ?></div><div class="l">Selling price</div></div>
</div>
<p><a class="btn" href="stock_move.php?product=<?= (int)$id ?>">Record stock movement</a> <a class="btn sec" href="product_form.php?id=<?= (int)$id ?>">Edit product</a> <a class="btn sec" href="products.php">Back to list</a></p>
<h2>Batches</h2>
<div class="tablewrap"><table><tr><th>Batch #</th><th>Received</th><th>Expiry</th><th class="num">Qty on hand</th></tr>
<?php foreach ($batches as $x): ?><tr><td><?= (int)$x['batch_id'] ?></td><td><?= e($x['date_received']) ?></td><td><?= e($x['expiry_date'] ?? 'n/a') ?></td><td class="num"><?= (int)$x['quantity_on_hand'] ?></td></tr>
<?php endforeach; if (!$batches): ?><tr><td colspan="4" class="empty">No stock recorded yet.</td></tr><?php endif; ?></table></div>
<h2>Recent movements</h2>
<div class="tablewrap"><table><tr><th>When</th><th>Type</th><th class="num">Qty</th><th>Reason</th><th>By</th></tr>
<?php foreach ($moves as $x): ?><tr><td class="nw"><?= e($x['moved_at']) ?></td><td><?= e($x['type']) ?></td><td class="num"><?= (int)$x['quantity'] > 0 ? '+' : '' ?><?= (int)$x['quantity'] ?></td><td><?= e($x['reason'] ?? '') ?></td><td><?= e($x['full_name']) ?></td></tr>
<?php endforeach; if (!$moves): ?><tr><td colspan="5" class="empty">No movements yet.</td></tr><?php endif; ?></table></div>
<?php page_footer();
