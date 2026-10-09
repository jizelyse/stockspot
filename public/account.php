<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$errors = [];
if (is_post()) {
    csrf_verify();
    $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $conf = (string)($_POST['confirm'] ?? '');
    $st = db()->prepare('SELECT password_hash FROM users WHERE user_id = ?'); $st->execute([$u['id']]);
    if (!password_verify($cur, (string)$st->fetchColumn())) $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $errors[] = 'New password must contain letters and numbers.';
    if ($new !== $conf) $errors[] = 'New password and confirmation do not match.';
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        flash('Password changed.'); redirect('account.php');
    }
}
page_header('My Account', '');
?>
<p>Signed in as <strong><?= e($u['name']) ?></strong> (<?= e($u['role']) ?>).</p>
<div class="panel"><h2 style="margin-top:0">Change password</h2>
<?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="stack"><?= csrf_field() ?>
<label>Current password</label><input type="password" name="current" required>
<label>New password</label><input type="password" name="new" required minlength="8"><div class="hint">At least 8 characters, with letters and numbers.</div>
<label>Confirm new password</label><input type="password" name="confirm" required>
<p><button class="btn" type="submit">Change password</button></p></form></div>
<?php page_footer();
