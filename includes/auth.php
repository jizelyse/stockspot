<?php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");

if (isset($_SESSION['uid']) && isset($_SESSION['last']) && time() - $_SESSION['last'] > SESSION_TIMEOUT) {
    $_SESSION = []; session_destroy(); session_start();
    flash('You were logged out after 30 minutes of inactivity.', 'error');
}
if (isset($_SESSION['uid'])) $_SESSION['last'] = time();

function current_user(): ?array { return isset($_SESSION['uid']) ? ['id' => $_SESSION['uid'], 'name' => $_SESSION['name'], 'role' => $_SESSION['role']] : null; }
function require_login(): array {
    $u = current_user();
    if (!$u) redirect('index.php');
    return $u;
}
function require_owner(): array {
    $u = require_login();
    if ($u['role'] !== 'Owner') { http_response_code(403); exit('Only the Owner can access this page. <a href="dashboard.php">Back</a>'); }
    return $u;
}
function login_user(array $row): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['user_id'];
    $_SESSION['name'] = $row['full_name'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['last'] = time();
}
