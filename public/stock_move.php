<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db();
$products = $pdo->query(product_sql('p.is_archived = 0'))->fetchAll();
$byId = []; foreach ($products as $p) $byId[(int)$p['product_id']] = $p;
$types = ['in' => 'Stock In (delivery received)', 'out' => 'Stock Out (sold / used)', 'waste' => 'Waste (damaged / expired)', 'adj_plus' => 'Adjustment (+) after a count', 'adj_minus' => 'Adjustment (-) after a count'];
$f = ['product' => get('product'), 'type' => 'in', 'qty' => '', 'expiry' => '', 'reason' => ''];
$errors = [];
if (is_post()) {
    csrf_verify();
    $f = ['product' => post('product'), 'type' => post('type'), 'qty' => post('qty'), 'expiry' => post('expiry'), 'reason' => post('reason')];
    $pid = parse_uint($f['product']); $p = $pid ? ($byId[$pid] ?? null) : null;
    if (!$p) $errors['product'] = 'Please choose a product.';
    if (!isset($types[$f['type']])) $errors['type'] = 'Please choose a movement type.';
    $qty = parse_uint($f['qty'], 100000);
    if ($qty === null || $qty < 1) $errors['qty'] = 'Quantity must be a whole number of at least 1.';
    if (mb_strlen($f['reason']) > 255) $errors['reason'] = 'Reason is too long (max 255 characters).';
    if (in_array($f['type'], ['waste','adj_plus','adj_minus'], true) && $f['reason'] === '') $errors['reason'] = 'Please give a reason for waste or adjustments.';
    $expiry = null;
    if ($p && in_array($f['type'], ['in','adj_plus'], true) && $p['has_expiry']) {
        if ($f['type'] === 'in' && $f['expiry'] === '') $errors['expiry'] = 'This product has an expiry date. Please enter it.';
        elseif ($f['expiry'] !== '') {
            if (!valid_date($f['expiry'])) $errors['expiry'] = 'Enter a valid date.';
            elseif ($f['expiry'] < date('Y-m-d')) $errors['expiry'] = 'The expiry date is in the past. Check the date, or record the item as waste.';
            else $expiry = $f['expiry'];
        }
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            switch ($f['type']) {
                case 'in':       stock_in($pdo, $pid, $qty, $expiry, $u['id'], $f['reason'] ?: 'Stock received'); break;
                case 'adj_plus': stock_in($pdo, $pid, $qty, $expiry, $u['id'], $f['reason'], null, 'ADJUST'); break;
                case 'out':      stock_out($pdo, $pid, $qty, 'OUT', $u['id'], $f['reason'] ?: 'Sold / used'); break;
                case 'waste':    stock_out($pdo, $pid, $qty, 'WASTE', $u['id'], $f['reason']); break;
                case 'adj_minus':stock_out($pdo, $pid, $qty, 'ADJUST', $u['id'], $f['reason']); break;
            }
            $pdo->commit();
            flash('Saved: ' . $types[$f['type']] . ' of ' . $qty . ' ' . $p['unit'] . ' for ' . $p['product_name'] . '.');
            redirect('stock_move.php');
        } catch (RuntimeException $ex) { $pdo->rollBack(); $errors['qty'] = $ex->getMessage(); }
    }
}
$err = fn($k) => isset($errors[$k]) ? '<div class="err">' . e($errors[$k]) . '</div>' : '';
page_header('Record Stock Movement', 'stock');
?>
<form method="post" class="stack panel"><?= csrf_field() ?>
<label for="product">Product *</label>
<select id="product" name="product" required>
  <option value="">Choose a product...</option>
  <?php foreach ($products as $p): ?><option value="<?= (int)$p['product_id'] ?>" data-qty="<?= (int)$p['qty'] ?>" data-unit="<?= e($p['unit']) ?>" data-exp="<?= (int)$p['has_expiry'] ?>" <?= (string)$f['product'] === (string)$p['product_id'] ? 'selected' : '' ?>><?= e($p['product_name']) ?> (<?= e($p['sku']) ?>)</option><?php endforeach; ?>
</select><?= $err('product') ?><div class="hint" id="cur">&nbsp;</div>
<label for="type">Movement type *</label>
<select id="type" name="type"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select><?= $err('type') ?>
<label for="qty">Quantity *</label><input type="text" inputmode="numeric" id="qty" name="qty" value="<?= e($f['qty']) ?>" required><?= $err('qty') ?>
<div id="expwrap"><label for="expiry">Expiry date <span class="hint">(for stock in, if the product expires)</span></label><input type="date" id="expiry" name="expiry" value="<?= e($f['expiry']) ?>"><?= $err('expiry') ?></div>
<label for="reason">Reason / remarks</label><input type="text" id="reason" name="reason" value="<?= e($f['reason']) ?>" maxlength="255"><?= $err('reason') ?>
<p><button class="btn" type="submit">Save</button> <a class="btn sec" href="dashboard.php">Cancel</a></p>
</form>
<script>
(function(){
  var ps=document.getElementById('product'),cur=document.getElementById('cur'),ty=document.getElementById('type'),ew=document.getElementById('expwrap');
  function upd(){var o=ps.options[ps.selectedIndex];
    cur.textContent=o&&o.dataset.qty!==undefined?'Current stock: '+o.dataset.qty+' '+o.dataset.unit:'';
    var show=o&&o.dataset.exp==='1'&&(ty.value==='in'||ty.value==='adj_plus');ew.style.display=show?'block':'none';}
  ps.addEventListener('change',upd);ty.addEventListener('change',upd);upd();
})();
</script>
<?php page_footer();
