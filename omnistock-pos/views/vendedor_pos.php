<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/app_paths.php';

$currentUser = require_authenticated_user();
if (!in_array($currentUser['role'], ['vendedor', 'admin'], true)) {
    header('Location: ' . app_base_path() . '/login.php');
    exit;
}

require dirname(__DIR__) . '/index.php';
