<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pdo = db(); $errors = []; $done = 0;
if (is_post()) {
    csrf_verify();
    $f = $_FILES['csv'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) $errors[] = 'Please choose a CSV file to upload.';
    elseif ($f['size'] > 1048576) $errors[] = 'File is too large (max 1 MB).';
    elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'csv') $errors[] = 'Only .csv files are accepted.';
    else {
        $h = fopen($f['tmp_name'], 'r');
        $header = array_map(fn($x) => strtolower(trim((string)$x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($h) ?: []);
        if (array_slice($header, 0, 2) !== ['sku', 'quantity']) $errors[] = 'The first row must be a header: sku,quantity (and optionally date).';
        else {
            $ln = 1; $pdo->beginTransaction();
            $find = $pdo->prepare('SELECT product_id FROM products WHERE sku = ? AND is_archived = 0');
            while (($r = fgetcsv($h)) !== false) {
                $ln++;
                if (count($r) === 1 && trim((string)$r[0]) === '') continue;
                if ($ln > 1001) { $errors[] = 'Too many rows (max 1000).'; break; }
                $sku = strtoupper(trim((string)($r[0] ?? ''))); $q = parse_uint($r[1] ?? '', 100000); $d = trim((string)($r[2] ?? ''));
                if ($sku === '') { $errors[] = "Line $ln: SKU is empty."; continue; }
                if ($q === null || $q < 1) { $errors[] = "Line $ln: quantity must be a whole number of at least 1."; continue; }
                if ($d !== '' && (!valid_date($d) || $d > date('Y-m-d'))) { $errors[] = "Line $ln: date must be YYYY-MM-DD and not in the future."; continue; }
                $find->execute([$sku]); $pid = $find->fetchColumn();
                if (!$pid) { $errors[] = "Line $ln: unknown SKU '" . $sku . "'."; continue; }
                try { stock_out($pdo, (int)$pid, $q, 'OUT', $u['id'], 'Imported sales (CSV)', $d !== '' ? $d . ' 12:00:00' : null); $done++; }
                catch (RuntimeException $ex) { $errors[] = "Line $ln ($sku): " . $ex->getMessage(); }
            }
            if ($errors) { $pdo->rollBack(); $done = 0; } else { $pdo->commit(); flash("Imported $done sales row(s). Stock has been reduced."); redirect('import_sales.php'); }
        }
        fclose($h);
    }
}
page_header('Import Sales', 'import');
?>
<p>Upload a CSV of items sold (for example, exported from your POS) to reduce stock in one step. Nothing is imported unless every row is valid.</p>
<?php if ($errors): ?><div class="alert error"><strong>Import cancelled. Please fix these and try again:</strong><ul><?php foreach (array_slice($errors, 0, 30) as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="stack panel"><?= csrf_field() ?>
<label for="csv">CSV file (columns: sku, quantity, date optional)</label><input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
<div class="hint">Example row: <code>SNK-001,3,2026-10-01</code> &middot; <a href="assets/sample_sales.csv" download>Download a sample file</a></div>
<p><button class="btn" type="submit">Import</button></p></form>
<?php page_footer();
