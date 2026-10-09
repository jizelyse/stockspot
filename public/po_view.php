<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db();
$id = parse_uint(get('id'));
$st = $pdo->prepare('SELECT po.*, s.supplier_name, s.contact_number, u.full_name FROM purchase_orders po JOIN suppliers s ON s.supplier_id = po.supplier_id JOIN users u ON u.user_id = po.user_id WHERE po.po_id = ?'); $st->execute([(int)$id]);
$po = $st->fetch();
if (!$id || !$po) { http_response_code(404); exit('Purchase order not found. <a href="purchase_orders.php">Back</a>'); }
$it = $pdo->prepare('SELECT i.*, p.product_name, p.unit, p.has_expiry FROM po_items i JOIN products p ON p.product_id = i.product_id WHERE i.po_id = ? ORDER BY p.product_name'); $it->execute([$id]);
$items = $it->fetchAll();
$errors = [];
if (is_post()) {
    csrf_verify();
    $action = post('action');
    if ($action === 'order' && $po['status'] === 'Draft') {
        $pdo->prepare("UPDATE purchase_orders SET status='Ordered' WHERE po_id=?")->execute([$id]); flash('Marked as Ordered.'); redirect("po_view.php?id=$id");
    } elseif ($action === 'delete' && $po['status'] === 'Draft') {
        $pdo->prepare('DELETE FROM purchase_orders WHERE po_id=?')->execute([$id]); flash('Draft deleted.'); redirect('purchase_orders.php?tab=history');
    } elseif ($action === 'receive' && $po['status'] === 'Ordered') {
        $recv = [];
        foreach ($items as $i) {
            $k = (int)$i['po_item_id'];
            $q = parse_uint($_POST['recv'][$k] ?? '', 100000);
            $ex = trim((string)($_POST['exp'][$k] ?? ''));
            if ($q === null) { $errors[] = $i['product_name'] . ': enter the quantity received (0 or more).'; continue; }
            if ($q > 0 && $i['has_expiry']) {
                if ($ex === '' || !valid_date($ex)) { $errors[] = $i['product_name'] . ': enter a valid expiry date.'; continue; }
                if ($ex < date('Y-m-d')) { $errors[] = $i['product_name'] . ': expiry date is in the past.'; continue; }
            }
            $recv[$k] = [$q, $i['has_expiry'] && $ex !== '' ? $ex : null, (int)$i['product_id']];
        }
        if (!$errors) {
            $pdo->beginTransaction();
            foreach ($recv as $k => [$q, $ex, $pid]) {
                if ($q > 0) stock_in($pdo, $pid, $q, $ex, $u['id'], "Received from PO #$id", $k);
                $pdo->prepare('UPDATE po_items SET qty_received = ? WHERE po_item_id = ?')->execute([$q, $k]);
            }
            $pdo->prepare("UPDATE purchase_orders SET status='Received' WHERE po_id=?")->execute([$id]);
            $pdo->commit(); flash('Order received. Stock has been updated.'); redirect("po_view.php?id=$id");
        }
    }
}
$total = 0; foreach ($items as $i) $total += $i['qty_ordered'] * $i['unit_cost'];
page_header('Purchase Order #' . $id, 'po');
?>
<?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
<p><span class="badge <?= $po['status'] === 'Received' ? 'ok' : ($po['status'] === 'Ordered' ? 'info' : 'warn') ?>"><?= e($po['status']) ?></span>
 &nbsp; Supplier: <strong><?= e($po['supplier_name']) ?></strong> <?= e($po['contact_number'] ?? '') ?> &middot; Date: <?= e($po['order_date']) ?> &middot; By <?= e($po['full_name']) ?></p>
<form method="post"><?= csrf_field() ?>
<div class="tablewrap"><table><tr><th>Product</th><th class="num">Unit cost</th><th class="num">Ordered</th><?php if ($po['status'] === 'Ordered'): ?><th>Qty received</th><th>Expiry date</th><?php elseif ($po['status'] === 'Received'): ?><th class="num">Received</th><?php endif; ?><th class="num">Line total</th></tr>
<?php foreach ($items as $i): $k = (int)$i['po_item_id']; ?><tr><td><?= e($i['product_name']) ?></td><td class="num"><?= money($i['unit_cost']) ?></td><td class="num"><?= (int)$i['qty_ordered'] ?> <?= e($i['unit']) ?></td>
<?php if ($po['status'] === 'Ordered'): ?><td><input type="text" inputmode="numeric" style="width:90px" name="recv[<?= $k ?>]" value="<?= e($_POST['recv'][$k] ?? $i['qty_ordered']) ?>"></td><td><?= $i['has_expiry'] ? '<input type="date" name="exp[' . $k . ']" value="' . e($_POST['exp'][$k] ?? '') . '">' : 'n/a' ?></td>
<?php elseif ($po['status'] === 'Received'): ?><td class="num"><?= (int)$i['qty_received'] ?></td><?php endif; ?>
<td class="num"><?= money($i['qty_ordered'] * $i['unit_cost']) ?></td></tr><?php endforeach; ?>
<tr><td colspan="<?= $po['status'] === 'Ordered' ? 5 : ($po['status'] === 'Received' ? 4 : 3) ?>" class="num"><strong>Total</strong></td><td class="num"><strong><?= money($total) ?></strong></td></tr></table></div>
<p>
<?php if ($po['status'] === 'Draft'): ?>
 <button class="btn" name="action" value="order" type="submit">Mark as Ordered</button>
 <button class="btn danger" name="action" value="delete" type="submit" onclick="return confirm('Delete this draft?')">Delete draft</button>
<?php elseif ($po['status'] === 'Ordered'): ?>
 <button class="btn" name="action" value="receive" type="submit">Receive order and update stock</button>
<?php endif; ?>
 <a class="btn sec" href="purchase_orders.php?tab=history">Back to history</a> <button type="button" class="btn sec" onclick="window.print()">Print</button></p>
</form>
<?php page_footer();
