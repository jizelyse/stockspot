<?php
require __DIR__ . '/../includes/bootstrap.php';
require_owner();
$pdo = db(); $err = '';
if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'add') {
        $n = post('category_name');
        if ($n === '' || mb_strlen($n) > 60) $err = 'Category name is required (max 60 characters).';
        else {
            $d = $pdo->prepare('SELECT 1 FROM categories WHERE category_name = ?'); $d->execute([$n]);
            if ($d->fetchColumn()) $err = 'That category already exists.';
            else { $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute([$n]); flash('Category added.'); redirect('categories.php'); }
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int)parse_uint($_POST['id'] ?? '');
        $d = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?'); $d->execute([$id]);
        if ($d->fetchColumn() > 0) flash('Cannot delete: products still use this category.', 'error');
        else { $pdo->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$id]); flash('Category deleted.'); }
        redirect('categories.php');
    }
}
$rows = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.category_id) AS n FROM categories c ORDER BY category_name')->fetchAll();
page_header('Categories', 'categories');
?>
<?php if ($err): ?><div class="alert error"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="row panel"><?= csrf_field() ?><input type="hidden" name="action" value="add">
<div><label for="cn">New category</label><input type="text" id="cn" name="category_name" maxlength="60" required></div><div style="flex:0 0 auto"><button class="btn" type="submit">Add</button></div></form>
<div class="tablewrap"><table><tr><th>Category</th><th class="num">Products</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['category_name']) ?></td><td class="num"><?= (int)$r['n'] ?></td><td>
<form method="post" class="inline" onsubmit="return confirm('Delete this category?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['category_id'] ?>"><button class="btn sm danger" type="submit">Delete</button></form></td></tr>
<?php endforeach; ?></table></div>
<?php page_footer();
