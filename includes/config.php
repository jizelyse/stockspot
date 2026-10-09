<?php
// Configuration. Values can be overridden with environment variables (used by Docker).
define('APP_NAME',   'StockSpot');
define('STORE_NAME', "Leonny G's");
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'stockspot');
define('DB_USER', getenv('DB_USER') ?: 'stockspot');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'stockspot_pw');
define('EXPIRY_WARN_DAYS', (int)(getenv('EXPIRY_WARN_DAYS') ?: 14));
define('SESSION_TIMEOUT', 1800);      // seconds of inactivity before auto-logout
define('PAGE_SIZE', 25);
date_default_timezone_set('Asia/Manila');
