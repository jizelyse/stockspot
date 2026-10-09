<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db();
$id = parse_uint(get('id'));
$p = ['product_name'=>'','sku'=>'','category_id'=>'','supplier_id'=>'','unit'=>'pc','cost_price'=>'0.00','selling_price'=>'0.00','reorder_level'=>'0','has_expiry'=>0];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM products WHERE product_id = ?'); $st->execute([$id]);
    $p = $st->fetch() ?: null;
    if (!$p) { http_response_code(404); exit('Product not found.'); }
}
$errors = [];
if (is_post()) {
    csrf_verify();
    $in = ['product_name'=>post('product_name'),'sku'=>strtoupper(post('sku')),'category_id'=>post('category_id'),'supplier_id'=>post('supplier_id'),
           'unit'=>post('unit'),'cost_price'=>post('cost_price'),'selling_price'=>post('selling_price'),'reorder_level'=>post('reorder_level'),'has_expiry'=>isset($_POST['has_expiry']) ? 1 : 0];
    $p = array_merge($p, $in);
    if ($in['product_name'] === '' || mb_strlen($in['product_name']) > 100) $errors['product_name'] = 'Product name is required (max 100 characters).';
    if (!preg_match('/^[A-Z0-9._-]{2,50}$/', $in['sku'])) $errors['sku'] = 'SKU / barcode is required: 2-50 letters, numbers, dot, dash or underscore.';
    $cid = parse_uint($in['category_id']);
    $chk = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = ?'); $chk->execute([(int)$cid]);
    if (!$cid || !$chk->fetchColumn()) $errors['category_id'] = 'Please choose a category.';
    $sid = $in['supplier_id'] === '' ? null : parse_uint($in['supplier_id']);
    if ($in['supplier_id'] !== '') { $chk = $pdo->prepare('SELECT 1 FROM suppliers WHERE supplier_id = ?'); $chk->execute([(int)$sid]); if (!$sid || !$chk->fetchColumn()) $errors['supplier_id'] = 'Invalid supplier.'; }
    if ($in['unit'] === '' || mb_strlen($in['unit']) > 20) $errors['unit'] = 'Unit is required (e.g. pc, pack, bottle).';
    $cost = parse_money($in['cost_price']);  if ($cost === null)  $errors['cost_price'] = 'Enter a valid cost price (0 or more, up to 2 decimals).';
    $sell = parse_money($in['selling_price']); if ($sell === null) $errors['selling_price'] = 'Enter a valid selling price (0 or more, up to 2 decimals).';
    $rl = parse_uint($in['reorder_level'], 100000); if ($rl === null) $errors['reorder_level'] = 'Reorder level must be a whole number, 0 or more.';
    if (!isset($errors['sku'])) {
        $d = $pdo->prepare('SELECT product_id FROM products WHERE sku = ? AND product_id <> ?'); $d->execute([$in['sku'], (int)$id]);
        if ($d->fetchColumn()) $errors['sku'] = 'That SKU / barcode is already used by another product.';
    }
    if (!$errors) {
        if ($id) {
            $pdo->prepare('UPDATE products SET category_id=?, supplier_id=?, sku=?, product_name=?, unit=?, cost_price=?, selling_price=?, reorder_level=?, has_expiry=? WHERE product_id=?')
                ->execute([$cid,$sid,$in['sku'],$in['product_name'],$in['unit'],$cost,$sell,$rl,$in['has_expiry'],$id]);
            flash('Product updated.');
        } else {
            $pdo->prepare('INSERT INTO products (category_id, supplier_id, sku, product_name, unit, cost_price, selling_price, reorder_level, has_expiry) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$cid,$sid,$in['sku'],$in['product_name'],$in['unit'],$cost,$sell,$rl,$in['has_expiry']]);
            $id = (int)$pdo->lastInsertId();
            flash('Product added. You can now record stock for it.');
        }
        redirect('product_view.php?id=' . $id);
    }
}
$cats = $pdo->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();
$sups = $pdo->query('SELECT * FROM suppliers ORDER BY supplier_name')->fetchAll();
$err = fn($k) => isset($errors[$k]) ? '<div class="err">' . e($errors[$k]) . '</div>' : '';
page_header($id ? 'Edit Product' : 'Add Product', 'inventory');
?>
<form method="post" class="stack panel"><?= csrf_field() ?>
<label for="product_name">Product name *</label><input type="text" id="product_name" name="product_name" value="<?= e($p['product_name']) ?>" maxlength="100" required><?= $err('product_name') ?>
<label for="sku">SKU / barcode *</label><input type="text" id="sku" name="sku" value="<?= e($p['sku']) ?>" maxlength="50" required><?= $err('sku') ?>
<label for="category_id">Category *</label><select id="category_id" name="category_id" required><option value="">Choose...</option>
<?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>" <?= (string)$p['category_id'] === (string)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select><?= $err('category_id') ?>
<label for="supplier_id">Supplier</label><select id="supplier_id" name="supplier_id"><option value="">None</option>
<?php foreach ($sups as $s): ?><option value="<?= (int)$s['supplier_id'] ?>" <?= (string)$p['supplier_id'] === (string)$s['supplier_id'] ? 'selected' : '' ?>><?= e($s['supplier_name']) ?></option><?php endforeach; ?></select><?= $err('supplier_id') ?>
<label for="unit">Unit *</label><input type="text" id="unit" name="unit" value="<?= e($p['unit']) ?>" maxlength="20" required><?= $err('unit') ?>
<label for="cost_price">Cost price (₱) *</label><input type="text" inputmode="decimal" id="cost_price" name="cost_price" value="<?= e($p['cost_price']) ?>" required><?= $err('cost_price') ?>
<label for="selling_price">Selling price (₱) *</label><input type="text" inputmode="decimal" id="selling_price" name="selling_price" value="<?= e($p['selling_price']) ?>" required><?= $err('selling_price') ?>
<label for="reorder_level">Reorder level *</label><input type="text" inputmode="numeric" id="reorder_level" name="reorder_level" value="<?= e($p['reorder_level']) ?>" required>
<div class="hint">When stock falls to this number or lower, the product shows as LOW.</div><?= $err('reorder_level') ?>
<label><input type="checkbox" name="has_expiry" value="1" <?= $p['has_expiry'] ? 'checked' : '' ?>> This product has an expiry date</label>
<p><button class="btn" type="submit">Save product</button> <a class="btn sec" href="products.php">Cancel</a></p>
</form>
<?php page_footer();
