<?php
require __DIR__ . '/../includes/bootstrap.php';
if (current_user()) redirect('dashboard.php');
$error = '';
if (is_post()) {
    csrf_verify();
    $_SESSION['fails'] = $_SESSION['fails'] ?? 0;
    if (($_SESSION['lock_until'] ?? 0) > time()) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } else {
        $username = post('username'); $password = (string)($_POST['password'] ?? '');
        $st = db()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
        $st->execute([$username]);
        $row = $st->fetch();
        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } elseif ($row && password_verify($password, $row['password_hash'])) {
            $_SESSION['fails'] = 0; login_user($row); redirect('dashboard.php');
        } else {
            $_SESSION['fails']++;
            if ($_SESSION['fails'] >= 5) { $_SESSION['lock_until'] = time() + 60; $_SESSION['fails'] = 0; }
            $error = 'Incorrect username or password.';
        }
    }
}
page_header('Staff Login');
?>
<div class="login-wrap">
  <h2 style="text-align:center;margin:0 0 12px;color:#b0316b">Sign in to StockSpot</h2>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack" autocomplete="off">
    <?= csrf_field() ?>
    <label for="username">Username</label><input type="text" id="username" name="username" required maxlength="50" autofocus>
    <label for="password">Password</label><input type="password" id="password" name="password" required>
    <p><button class="btn" type="submit" style="width:100%">Log in</button></p>
  </form>
</div>
<?php page_footer();
