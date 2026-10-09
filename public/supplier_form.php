<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
$pdo = db(); $id = parse_uint(get('id'));
$s = ['supplier_name'=>'','contact_number'=>'','address'=>''];
if ($id) { $st = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_id = ?'); $st->execute([$id]); $s = $st->fetch() ?: null; if (!$s) { http_response_code(404); exit('Supplier not found.'); } }
$errors = [];
if (is_post()) {
    csrf_verify();
    $s = array_merge($s, ['supplier_name'=>post('supplier_name'),'contact_number'=>post('contact_number'),'address'=>post('address')]);
    if ($s['supplier_name'] === '' || mb_strlen($s['supplier_name']) > 100) $errors['supplier_name'] = 'Supplier name is required (max 100 characters).';
    if ($s['contact_number'] !== '' && !preg_match('/^[0-9 +()\-]{7,30}$/', $s['contact_number'])) $errors['contact_number'] = 'Contact number may only contain digits, spaces, + ( ) and - (7-30 characters).';
    if (mb_strlen($s['address']) > 255) $errors['address'] = 'Address is too long (max 255 characters).';
    if (!$errors) {
        if ($id) { $pdo->prepare('UPDATE suppliers SET supplier_name=?, contact_number=?, address=? WHERE supplier_id=?')->execute([$s['supplier_name'], $s['contact_number'] ?: null, $s['address'] ?: null, $id]); flash('Supplier updated.'); }
        else { $pdo->prepare('INSERT INTO suppliers (supplier_name, contact_number, address) VALUES (?,?,?)')->execute([$s['supplier_name'], $s['contact_number'] ?: null, $s['address'] ?: null]); flash('Supplier added.'); }
        redirect('suppliers.php');
    }
}
$err = fn($k) => isset($errors[$k]) ? '<div class="err">' . e($errors[$k]) . '</div>' : '';
page_header($id ? 'Edit Supplier' : 'Add Supplier', 'suppliers');
?>
<form method="post" class="stack panel"><?= csrf_field() ?>
<label for="supplier_name">Supplier name *</label><input type="text" id="supplier_name" name="supplier_name" value="<?= e($s['supplier_name']) ?>" maxlength="100" required><?= $err('supplier_name') ?>
<label for="contact_number">Contact number</label><input type="text" id="contact_number" name="contact_number" value="<?= e($s['contact_number']) ?>" maxlength="30"><?= $err('contact_number') ?>
<label for="address">Address</label><input type="text" id="address" name="address" value="<?= e($s['address']) ?>" maxlength="255"><?= $err('address') ?>
<p><button class="btn" type="submit">Save supplier</button> <a class="btn sec" href="suppliers.php">Cancel</a></p></form>
<?php page_footer();
