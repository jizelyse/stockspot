<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
$pdo = db();
$all = $pdo->query(product_sql('p.is_archived = 0'))->fetchAll();
$low = $expiring = []; $outCount = 0;
foreach ($all as $p) {
    $s = product_status($p);
    if (in_array($s, ['LOW','OUT'], true)) $low[] = $p + ['status' => $s];
    if (in_array($s, ['EXPIRING','EXPIRED'], true)) $expiring[] = $p + ['status' => $s];
    if ($s === 'OUT') $outCount++;
}
$today = (int)$pdo->query('SELECT COUNT(*) FROM stock_movements WHERE DATE(moved_at) = CURDATE()')->fetchColumn();
$notes = $pdo->query('SELECT n.*, u.full_name FROM shift_notes n JOIN users u ON u.user_id = n.user_id ORDER BY n.created_at DESC, n.note_id DESC LIMIT 3')->fetchAll();
page_header('Dashboard', 'dashboard');
?>
<div class="cards">
  <div class="card"><div class="n"><?= count($all) ?></div><div class="l">Total products</div></div>
  <div class="card bad"><div class="n"><?= count($low) ?></div><div class="l">Low or out of stock</div></div>
  <div class="card warn"><div class="n"><?= count($expiring) ?></div><div class="l">Expiring in <?= EXPIRY_WARN_DAYS ?> days (or expired)</div></div>
  <div class="card"><div class="n"><?= $today ?></div><div class="l">Today's movements</div></div>
</div>
<h2>Low-stock alerts</h2>
<div class="tablewrap"><table>
<tr><th>Product</th><th class="num">On hand</th><th class="num">Reorder level</th><th>Supplier</th><th>Status</th></tr>
<?php foreach ($low as $p): ?>
<tr><td><a href="product_view.php?id=<?= (int)$p['product_id'] ?>"><?= e($p['product_name']) ?></a></td><td class="num"><?= (int)$p['qty'] ?></td><td class="num"><?= (int)$p['reorder_level'] ?></td><td><?= e($p['supplier_name'] ?? '-') ?></td><td><?= status_badge($p['status']) ?></td></tr>
<?php endforeach; if (!$low): ?><tr><td colspan="5" class="empty">All products are above their reorder level.</td></tr><?php endif; ?>
</table></div>
<?php if ($low): ?><p><a class="btn sec" href="purchase_orders.php">Go to reorder suggestions</a></p><?php endif; ?>
<h2>Expiring soon</h2>
<div class="tablewrap"><table>
<tr><th>Product</th><th class="num">On hand</th><th>Nearest expiry</th><th>Status</th></tr>
<?php foreach ($expiring as $p): ?>
<tr><td><a href="product_view.php?id=<?= (int)$p['product_id'] ?>"><?= e($p['product_name']) ?></a></td><td class="num"><?= (int)$p['qty'] ?></td><td><?= e($p['next_expiry']) ?></td><td><?= status_badge($p['status']) ?></td></tr>
<?php endforeach; if (!$expiring): ?><tr><td colspan="4" class="empty">Nothing is close to expiry.</td></tr><?php endif; ?>
</table></div>
<h2>Latest shift notes</h2>
<?php foreach ($notes as $n): ?><div class="note"><?= nl2br(e($n['note_text'])) ?><br><small><?= e($n['full_name']) ?> &middot; <?= e($n['shift_date']) ?></small></div>
<?php endforeach; if (!$notes): ?><p class="empty">No notes yet.</p><?php endif; ?>
<p><a href="notes.php">All shift notes &rarr;</a></p>
<?php page_footer();
