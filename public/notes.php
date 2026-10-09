<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db(); $err = '';
if (is_post()) {
    csrf_verify();
    if (post('action') === 'delete') {
        $id = (int)parse_uint($_POST['id'] ?? '');
        $own = $pdo->prepare('SELECT user_id FROM shift_notes WHERE note_id = ?'); $own->execute([$id]); $o = $own->fetchColumn();
        if ($o !== false && ($u['role'] === 'Owner' || (int)$o === $u['id'])) { $pdo->prepare('DELETE FROM shift_notes WHERE note_id = ?')->execute([$id]); flash('Note deleted.'); }
        else flash('You can only delete your own notes.', 'error');
        redirect('notes.php');
    }
    $t = post('note_text');
    if ($t === '') $err = 'Please write a note first.';
    elseif (mb_strlen($t) > 1000) $err = 'Note is too long (max 1000 characters).';
    else { $pdo->prepare('INSERT INTO shift_notes (user_id, note_text, shift_date) VALUES (?,?,CURDATE())')->execute([$u['id'], $t]); flash('Note posted.'); redirect('notes.php'); }
}
$notes = $pdo->query('SELECT n.*, u.full_name FROM shift_notes n JOIN users u ON u.user_id = n.user_id ORDER BY n.created_at DESC, n.note_id DESC LIMIT 100')->fetchAll();
page_header('Shift Notes', 'notes');
?>
<form method="post" class="stack panel"><?= csrf_field() ?>
<?php if ($err): ?><div class="alert error"><?= e($err) ?></div><?php endif; ?>
<label for="note_text">Leave a note for the next shift</label><textarea id="note_text" name="note_text" rows="3" maxlength="1000" required></textarea>
<p><button class="btn" type="submit">Post note</button></p></form>
<?php foreach ($notes as $n): ?><div class="note"><?= nl2br(e($n['note_text'])) ?><br><small><?= e($n['full_name']) ?> &middot; <?= e($n['created_at']) ?></small>
<?php if ($u['role'] === 'Owner' || (int)$n['user_id'] === $u['id']): ?><form method="post" class="inline" onsubmit="return confirm('Delete this note?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$n['note_id'] ?>"> <button class="link" style="color:#c0392b" type="submit">delete</button></form><?php endif; ?></div>
<?php endforeach; if (!$notes): ?><p class="empty">No notes yet.</p><?php endif; ?>
<?php page_footer();
