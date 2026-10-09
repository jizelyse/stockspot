<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db();
if (is_post()) {
    csrf_verify();
    $id = parse_uint($_POST['id'] ?? '');
    if ($id && ($_POST['action'] ?? '') === 'archive') {
        $pdo->prepare('UPDATE products SET is_archived = 1 WHERE product_id = ?')->execute([$id]);
        flash('Product archived. Its history is kept.');
    } elseif ($id && ($_POST['action'] ?? '') === 'restore') {
        $pdo->prepare('UPDATE products SET is_archived = 0 WHERE product_id = ?')->execute([$id]);
        flash('Product restored.');
    }
    redirect('products.php' . (get('show') === 'archived' ? '?show=archived' : ''));
}
$q = get('q'); $cat = parse_uint(get('cat')); $status = get('status'); $archived = get('show') === 'archived';
$where = $archived ? 'p.is_archived = 1' : 'p.is_archived = 0'; $params = [];
if ($q !== '') { $where .= ' AND (p.product_name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat) { $where .= ' AND p.category_id = ?'; $params[] = $cat; }
$st = $pdo->prepare(product_sql($where)); $st->execute($params);
$rows = [];
foreach ($st->fetchAll() as $p) { $p['status'] = product_status($p); if ($status === '' || $status === $p['status']) $rows[] = $p; }
[$page, $pages, $off, $size] = paginate(count($rows), (int)get('page', '1'));
$rows = array_slice($rows, $off, $size);
$cats = $pdo->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();
page_header($archived ? 'Archived Products' : 'Inventory', 'inventory');
?>
<form method="get" class="row">
  <?php if ($archived): ?><input type="hidden" name="show" value="archived"><?php endif; ?>
  <div><label for="q">Search name / SKU</label><input type="search" id="q" name="q" value="<?= e($q) ?>" maxlength="100"></div>
  <div><label for="cat">Category</label><select id="cat" name="cat"><option value="">All</option>
    <?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>" <?= $cat === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">All</option>
    <?php foreach (['OK','LOW','OUT','EXPIRING','EXPIRED'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div style="flex:0 0 auto"><button class="btn" type="submit">Filter</button> <a class="btn sec" href="products.php<?= $archived ? '?show=archived' : '' ?>">Reset</a></div>
</form>
<p><a class="btn" href="product_form.php">+ Add Product</a>
 <a class="btn sec" href="<?= $archived ? 'products.php' : 'products.php?show=archived' ?>"><?= $archived ? 'Show active products' : 'Show archived' ?></a></p>
<div class="tablewrap"><table>
<tr><th>SKU</th><th>Product</th><th>Category</th><th>Supplier</th><th class="num">Qty</th><th class="num">Reorder lvl</th><th>Next expiry</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($rows as $p): ?>
<tr><td class="nw"><?= e($p['sku']) ?></td><td><a href="product_view.php?id=<?= (int)$p['product_id'] ?>"><?= e($p['product_name']) ?></a></td>
<td><?= e($p['category_name']) ?></td><td><?= e($p['supplier_name'] ?? '-') ?></td>
<td class="num"><?= (int)$p['qty'] ?> <?= e($p['unit']) ?></td><td class="num"><?= (int)$p['reorder_level'] ?></td>
<td><?= $p['has_expiry'] ? e($p['next_expiry'] ?? '-') : 'n/a' ?></td><td><?= status_badge($p['status']) ?></td>
<td class="nw"><a class="btn sm sec" href="product_view.php?id=<?= (int)$p['product_id'] ?>">View</a>
 <a class="btn sm sec" href="product_form.php?id=<?= (int)$p['product_id'] ?>">Edit</a>
 <form method="post" class="inline" onsubmit="return confirm('<?= $archived ? 'Restore' : 'Archive' ?> this product?')"><?= csrf_field() ?>
 <input type="hidden" name="id" value="<?= (int)$p['product_id'] ?>"><input type="hidden" name="action" value="<?= $archived ? 'restore' : 'archive' ?>">
 <button class="btn sm <?= $archived ? '' : 'danger' ?>" type="submit"><?= $archived ? 'Restore' : 'Archive' ?></button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="empty">No products found.</td></tr><?php endif; ?>
</table></div>
<?= pager($page, $pages) ?>
<?php page_footer();
