<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db();
if (is_post()) {
    csrf_verify();
    if ($u['role'] !== 'Owner') { http_response_code(403); exit('Only the Owner can delete suppliers.'); }
    $id = (int)parse_uint($_POST['id'] ?? '');
    $d = $pdo->prepare('SELECT (SELECT COUNT(*) FROM products WHERE supplier_id = ?) + (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?)');
    $d->execute([$id, $id]);
    if ($d->fetchColumn() > 0) flash('Cannot delete: this supplier is linked to products or purchase orders.', 'error');
    else { $pdo->prepare('DELETE FROM suppliers WHERE supplier_id = ?')->execute([$id]); flash('Supplier deleted.'); }
    redirect('suppliers.php');
}
$rows = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.supplier_id AND p.is_archived = 0) AS n FROM suppliers s ORDER BY supplier_name')->fetchAll();
page_header('Suppliers', 'suppliers');
?>
<p><a class="btn" href="supplier_form.php">+ Add Supplier</a></p>
<div class="tablewrap"><table><tr><th>Supplier</th><th>Contact</th><th>Address</th><th class="num">Products</th><th>Actions</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['supplier_name']) ?></td><td><?= e($r['contact_number'] ?? '') ?></td><td><?= e($r['address'] ?? '') ?></td><td class="num"><?= (int)$r['n'] ?></td><td>
<a class="btn sm sec" href="supplier_form.php?id=<?= (int)$r['supplier_id'] ?>">Edit</a>
<?php if ($u['role'] === 'Owner'): ?><form method="post" class="inline" onsubmit="return confirm('Delete this supplier?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['supplier_id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">No suppliers yet.</td></tr><?php endif; ?></table></div>
<?php page_footer();
