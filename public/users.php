<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_owner();
$pdo = db(); $errors = []; $f = ['full_name'=>'','username'=>'','role'=>'Staff'];
if (is_post()) {
    csrf_verify();
    $action = post('action');
    if ($action === 'add') {
        $f = ['full_name'=>post('full_name'),'username'=>post('username'),'role'=>post('role')]; $pw = (string)($_POST['password'] ?? '');
        if ($f['full_name'] === '' || mb_strlen($f['full_name']) > 100) $errors[] = 'Full name is required (max 100 characters).';
        if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $f['username'])) $errors[] = 'Username must be 3-50 letters, numbers, dots or underscores.';
        if (!in_array($f['role'], ['Owner','Staff'], true)) $errors[] = 'Choose a role.';
        if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $errors[] = 'Password must be at least 8 characters with letters and numbers.';
        $d = $pdo->prepare('SELECT 1 FROM users WHERE username = ?'); $d->execute([$f['username']]);
        if ($d->fetchColumn()) $errors[] = 'That username is already taken.';
        if (!$errors) { $pdo->prepare('INSERT INTO users (full_name, username, password_hash, role) VALUES (?,?,?,?)')->execute([$f['full_name'], $f['username'], password_hash($pw, PASSWORD_DEFAULT), $f['role']]); flash('User created.'); redirect('users.php'); }
    } elseif ($action === 'toggle') {
        $id = (int)parse_uint($_POST['id'] ?? '');
        if ($id === $me['id']) flash('You cannot deactivate your own account.', 'error');
        else { $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE user_id = ?')->execute([$id]); flash('User updated.'); }
        redirect('users.php');
    } elseif ($action === 'reset') {
        $id = (int)parse_uint($_POST['id'] ?? ''); $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) flash('New password must be at least 8 characters with letters and numbers.', 'error');
        else { $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]); flash('Password reset.'); }
        redirect('users.php');
    }
}
$users = $pdo->query('SELECT * FROM users ORDER BY role, full_name')->fetchAll();
page_header('Users', 'users');
?>
<?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
<div class="tablewrap"><table><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($users as $x): ?><tr><td><?= e($x['full_name']) ?></td><td><?= e($x['username']) ?></td><td><?= e($x['role']) ?></td><td><?= $x['is_active'] ? '<span class="badge ok">Active</span>' : '<span class="badge bad">Inactive</span>' ?></td><td>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$x['user_id'] ?>"><button class="btn sm sec" type="submit"><?= $x['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$x['user_id'] ?>"><input type="password" name="password" placeholder="New password" style="width:150px;padding:3px 6px" required> <button class="btn sm" type="submit">Reset</button></form></td></tr>
<?php endforeach; ?></table></div>
<h2>Add user</h2>
<form method="post" class="stack panel"><?= csrf_field() ?><input type="hidden" name="action" value="add">
<label>Full name *</label><input type="text" name="full_name" value="<?= e($f['full_name']) ?>" maxlength="100" required>
<label>Username *</label><input type="text" name="username" value="<?= e($f['username']) ?>" maxlength="50" required>
<label>Role *</label><select name="role"><option <?= $f['role'] === 'Staff' ? 'selected' : '' ?>>Staff</option><option <?= $f['role'] === 'Owner' ? 'selected' : '' ?>>Owner</option></select>
<label>Password *</label><input type="password" name="password" required minlength="8"><div class="hint">At least 8 characters, letters and numbers.</div>
<p><button class="btn" type="submit">Create user</button></p></form>
<?php page_footer();
