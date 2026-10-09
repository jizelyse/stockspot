<?php
require __DIR__ . '/../includes/bootstrap.php';
if (is_post()) { csrf_verify(); $_SESSION = []; session_destroy(); session_start(); flash('You have been logged out.'); }
redirect('index.php');
