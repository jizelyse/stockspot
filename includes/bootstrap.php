<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/layout.php';

// Friendly error page for unexpected failures (details go to the server log, never to the browser).
set_exception_handler(function (Throwable $e) {
    error_log('StockSpot error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Error</title><body style="font-family:sans-serif;padding:2rem"><h1>Something went wrong</h1><p>Please go back and try again. If it keeps happening, tell the shop owner.</p><p><a href="dashboard.php">Back to dashboard</a></p></body>';
    exit;
});
