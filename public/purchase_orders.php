<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db();
$tab = get('tab') === 'history' ? 'history' : 'suggest';

if (is_post()) {
    csrf_verify();
    $sel = $_POST['sel'] ?? []; $qtys = $_POST['qty'] ?? [];
    if (!is_array($sel) || !is_array($qtys) || !$sel) { flash('Select at least one product first.', 'error'); redirect('purchase_orders.php'); }
    $bySup = []; $skipped = 0;
    foreach ($sel as $pidRaw) {
        $pid = parse_uint($pidRaw); if (!$pid) continue;
        $q = parse_uint($qtys[$pid] ?? '', 100000);
        if ($q === null || $q < 1) { flash('Every selected product needs a quantity of at least 1.', 'error'); redirect('purchase_orders.php'); }
        $st = $pdo->prepare('SELECT product_id, supplier_id, cost_price FROM products WHERE product_id = ? AND is_archived = 0'); $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p || !$p['supplier_id']) { $skipped++; continue; }
        $bySup[(int)$p['supplier_id']][] = [$pid, $q, $p['cost_price']];
    }
    if (!$bySup) { flash('Nothing was created. Selected products need a supplier.', 'error'); redirect('purchase_orders.php'); }
    $pdo->beginTransaction();
    $ids = [];
    foreach ($bySup as $sid => $items) {
        $pdo->prepare('INSERT INTO purchase_orders (supplier_id, user_id, order_date, status) VALUES (?,?,CURDATE(),\'Draft\')')->execute([$sid, $u['id']]);
        $poId = (int)$pdo->lastInsertId(); $ids[] = $poId;
        $ins = $pdo->prepare('INSERT INTO po_items (po_id, product_id, qty_ordered, unit_cost) VALUES (?,?,?,?)');
        foreach ($items as [$pid, $q, $cost]) $ins->execute([$poId, $pid, $q, $cost]);
    }
    $pdo->commit();
    flash('Created ' . count($ids) . ' draft purchase order(s), one per supplier.' . ($skipped ? " $skipped product(s) skipped because they have no supplier." : ''));
    redirect(count($ids) === 1 ? 'po_view.php?id=' . $ids[0] : 'purchase_orders.php?tab=history');
}
page_header('Purchase Orders', 'po');
?>
<div class="tabs"><a class="<?= $tab === 'suggest' ? 'on' : '' ?>" href="purchase_orders.php">Reorder suggestions</a><a class="<?= $tab === 'history' ? 'on' : '' ?>" href="purchase_orders.php?tab=history">PO history</a><a href="po_new.php">+ Create PO manually</a></div>
<?php if ($tab === 'suggest'):
  $rows = [];
  foreach ($pdo->query(product_sql('p.is_archived = 0'))->fetchAll() as $p) { if ((int)$p['qty'] <= (int)$p['reorder_level']) { $p['suggest'] = max(1, (int)$p['reorder_level'] * 2 - (int)$p['qty']); $rows[] = $p; } }
  usort($rows, fn($a, $b) => strcmp((string)$a['supplier_name'], (string)$b['supplier_name']));
?>
<p>Products at or below their reorder level, grouped by supplier. Tick the items to order and adjust quantities. One draft order is created per supplier.</p>
<form method="post"><?= csrf_field() ?>
<div class="tablewrap"><table><tr><th></th><th>Product</th><th>Supplier</th><th class="num">On hand</th><th class="num">Reorder lvl</th><th>Order qty</th></tr>
<?php foreach ($rows as $p): ?><tr>
<td><input type="checkbox" name="sel[]" value="<?= (int)$p['product_id'] ?>" <?= $p['supplier_id'] ? 'checked' : 'disabled' ?>></td>
<td><?= e($p['product_name']) ?></td><td><?= $p['supplier_id'] ? e($p['supplier_name']) : '<span class="badge bad">No supplier</span>' ?></td>
<td class="num"><?= (int)$p['qty'] ?></td><td class="num"><?= (int)$p['reorder_level'] ?></td>
<td><input type="text" inputmode="numeric" style="width:90px" name="qty[<?= (int)$p['product_id'] ?>]" value="<?= (int)$p['suggest'] ?>"></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">Nothing needs reordering right now.</td></tr><?php endif; ?></table></div>
<?php if ($rows): ?><p><button class="btn" type="submit">Create purchase order(s)</button></p><?php endif; ?>
</form>
<?php else:
  $rows = $pdo->query('SELECT po.*, s.supplier_name, u.full_name, (SELECT COUNT(*) FROM po_items i WHERE i.po_id = po.po_id) AS items FROM purchase_orders po JOIN suppliers s ON s.supplier_id = po.supplier_id JOIN users u ON u.user_id = po.user_id ORDER BY po.po_id DESC LIMIT 100')->fetchAll();
?>
<div class="tablewrap"><table><tr><th>PO #</th><th>Date</th><th>Supplier</th><th class="num">Items</th><th>Status</th><th>Created by</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= (int)$r['po_id'] ?></td><td><?= e($r['order_date']) ?></td><td><?= e($r['supplier_name']) ?></td><td class="num"><?= (int)$r['items'] ?></td>
<td><span class="badge <?= $r['status'] === 'Received' ? 'ok' : ($r['status'] === 'Ordered' ? 'info' : 'warn') ?>"><?= e($r['status']) ?></span></td><td><?= e($r['full_name']) ?></td><td><a class="btn sm sec" href="po_view.php?id=<?= (int)$r['po_id'] ?>">Open</a></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No purchase orders yet.</td></tr><?php endif; ?></table></div>
<?php endif; page_footer();
