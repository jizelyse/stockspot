<?php
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function money($v): string { return '₱' . number_format((float)$v, 2); }

function flash(?string $msg = null, string $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'][] = ['m' => $msg, 't' => $type]; return null; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Security check failed (invalid or expired form). Go back, refresh the page, and try again.');
    }
}
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k, string $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }

/** Validate a non-negative whole number; returns int or null. */
function parse_uint($v, int $max = 1000000): ?int {
    if (!is_string($v) && !is_int($v)) return null;
    $v = trim((string)$v);
    if ($v === '' || !ctype_digit($v)) return null;
    $n = (int)$v;
    return ($n <= $max) ? $n : null;
}
/** Validate a non-negative decimal money amount; returns float or null. */
function parse_money($v): ?float {
    $v = trim((string)$v);
    if ($v === '' || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v)) return null;
    return (float)$v;
}
function valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

/** Status of a product row (needs qty, reorder_level, next_expiry). */
function product_status(array $p): string {
    $qty = (int)$p['qty'];
    $today = date('Y-m-d');
    if ($qty <= 0) return 'OUT';
    if (!empty($p['next_expiry']) && $p['next_expiry'] < $today) return 'EXPIRED';
    if ($qty <= (int)$p['reorder_level']) return 'LOW';
    if (!empty($p['next_expiry']) && $p['next_expiry'] <= date('Y-m-d', strtotime('+' . EXPIRY_WARN_DAYS . ' days'))) return 'EXPIRING';
    return 'OK';
}
function status_badge(string $s): string {
    $cls = ['OK' => 'ok', 'LOW' => 'warn', 'EXPIRING' => 'warn', 'OUT' => 'bad', 'EXPIRED' => 'bad'][$s] ?? 'ok';
    return '<span class="badge ' . $cls . '">' . e($s) . '</span>';
}

/** Base query: active products with total quantity and nearest expiry of stock still on hand. */
function product_sql(string $where = '1=1', string $having = '', string $order = 'p.product_name'): string {
    return "SELECT p.*, c.category_name, s.supplier_name,
              COALESCE(SUM(b.quantity_on_hand),0) AS qty,
              MIN(CASE WHEN b.quantity_on_hand > 0 THEN b.expiry_date END) AS next_expiry
            FROM products p
            JOIN categories c ON c.category_id = p.category_id
            LEFT JOIN suppliers s ON s.supplier_id = p.supplier_id
            LEFT JOIN stock_batches b ON b.product_id = p.product_id
            WHERE $where
            GROUP BY p.product_id
            " . ($having ? "HAVING $having" : '') . "
            ORDER BY $order";
}
function paginate(int $total, int $page, int $size = PAGE_SIZE): array {
    $pages = max(1, (int)ceil($total / $size));
    $page = min(max(1, $page), $pages);
    return [$page, $pages, ($page - 1) * $size, $size];
}
function pager(int $page, int $pages): string {
    if ($pages <= 1) return '';
    $q = $_GET; $out = '<nav class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $q['page'] = $i;
        $out .= $i === $page ? "<span class='cur'>$i</span>" : '<a href="?' . e(http_build_query($q)) . '">' . $i . '</a>';
    }
    return $out . '</nav>';
}
