<?php
function page_header(string $title, string $active = ''): void {
    $u = current_user();
    $nav = [
        'dashboard' => ['dashboard.php', 'Dashboard'],
        'inventory' => ['products.php', 'Inventory'],
        'stock'     => ['stock_move.php', 'Stock In / Out'],
        'movements' => ['movements.php', 'Movement Log'],
        'suppliers' => ['suppliers.php', 'Suppliers'],
        'po'        => ['purchase_orders.php', 'Purchase Orders'],
        'reports'   => ['reports.php', 'Reports'],
        'notes'     => ['notes.php', 'Shift Notes'],
        'import'    => ['import_sales.php', 'Import Sales'],
    ];
    if ($u && $u['role'] === 'Owner') { $nav['categories'] = ['categories.php', 'Categories']; $nav['users'] = ['users.php', 'Users']; }
    ?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head><body>
<header class="top">
  <button class="menu-btn" type="button" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">&#9776;</button>
  <a class="brand" href="<?= $u ? 'dashboard.php' : 'index.php' ?>"><?= e(APP_NAME) ?></a>
  <span class="store"><?= e(STORE_NAME) ?> &middot; Greenwoods Branch</span>
  <?php if ($u): ?>
  <span class="who"><?= e($u['name']) ?> (<?= e($u['role']) ?>) &middot; <a href="account.php">Account</a>
    <form method="post" action="logout.php" class="inline"><?= csrf_field() ?><button class="link" type="submit">Logout</button></form></span>
  <?php endif; ?>
</header>
<div class="shell">
<?php if ($u): ?>
<nav class="side"><?php foreach ($nav as $k => [$href, $label]): ?>
  <a href="<?= e($href) ?>" class="<?= $k === $active ? 'active' : '' ?>"><?= e($label) ?></a>
<?php endforeach; ?></nav>
<?php endif; ?>
<main class="content">
<?php foreach (flash() ?? [] as $f): ?><div class="alert <?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endforeach; ?>
<h1><?= e($title) ?></h1>
<?php }
function page_footer(): void { ?>
</main></div>
</body></html>
<?php }
