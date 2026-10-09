<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db();
$sups = $pdo->query('SELECT * FROM suppliers ORDER BY supplier_name')->fetchAll();
$prods = $pdo->query('SELECT product_id, product_name, supplier_id, cost_price FROM products WHERE is_archived = 0 AND supplier_id IS NOT NULL ORDER BY product_name')->fetchAll();
$errors = []; $sid = parse_uint(get('supplier'));
if (is_post()) {
    csrf_verify();
    $sid = parse_uint(post('supplier'));
    $chk = $pdo->prepare('SELECT 1 FROM suppliers WHERE supplier_id = ?'); $chk->execute([(int)$sid]);
    if (!$sid || !$chk->fetchColumn()) $errors[] = 'Please choose a supplier.';
    $lines = [];
    foreach (($_POST['qty'] ?? []) as $pid => $qRaw) {
        $pid = parse_uint((string)$pid); if (!$pid || trim((string)$qRaw) === '' || trim((string)$qRaw) === '0') continue;
        $q = parse_uint($qRaw, 100000);
        if ($q === null) { $errors[] = 'Quantities must be whole numbers.'; break; }
        $ps = $pdo->prepare('SELECT cost_price FROM products WHERE product_id = ? AND supplier_id = ? AND is_archived = 0'); $ps->execute([$pid, (int)$sid]);
        $cost = $ps->fetchColumn();
        if ($cost === false) { $errors[] = 'A product does not belong to the chosen supplier.'; break; }
        $lines[] = [$pid, $q, $cost];
    }
    if (!$errors && !$lines) $errors[] = 'Enter a quantity for at least one product.';
    if (!$errors) {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO purchase_orders (supplier_id, user_id, order_date, status) VALUES (?,?,CURDATE(),\'Draft\')')->execute([$sid, $u['id']]);
        $poId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO po_items (po_id, product_id, qty_ordered, unit_cost) VALUES (?,?,?,?)');
        foreach ($lines as $l) $ins->execute([$poId, $l[0], $l[1], $l[2]]);
        $pdo->commit(); flash('Draft purchase order created.'); redirect('po_view.php?id=' . $poId);
    }
}
page_header('Create Purchase Order', 'po');
?>
<?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
<form method="get" class="row panel"><div><label>Supplier</label><select name="supplier" onchange="this.form.submit()"><option value="">Choose...</option><?php foreach ($sups as $s): ?><option value="<?= (int)$s['supplier_id'] ?>" <?= $sid === (int)$s['supplier_id'] ? 'selected' : '' ?>><?= e($s['supplier_name']) ?></option><?php endforeach; ?></select></div><noscript><div style="flex:0 0 auto"><button class="btn" type="submit">Load products</button></div></noscript></form>
<?php if ($sid): ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="supplier" value="<?= (int)$sid ?>">
<div class="tablewrap"><table><tr><th>Product</th><th class="num">Unit cost</th><th>Order qty</th></tr>
<?php $n = 0; foreach ($prods as $p) { if ((int)$p['supplier_id'] !== $sid) continue; $n++; ?>
<tr><td><?= e($p['product_name']) ?></td><td class="num"><?= money($p['cost_price']) ?></td><td><input type="text" inputmode="numeric" style="width:90px" name="qty[<?= (int)$p['product_id'] ?>]" value="0"></td></tr>
<?php } if (!$n): ?><tr><td colspan="3" class="empty">This supplier has no products yet.</td></tr><?php endif; ?></table></div>
<p><button class="btn" type="submit">Create draft PO</button> <a class="btn sec" href="purchase_orders.php">Cancel</a></p></form>
<?php endif; page_footer();
