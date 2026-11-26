<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/app_paths.php';

start_user_session();
$appBasePath = app_base_path();

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ' . $appBasePath . ($_SESSION['role'] === 'admin' ? '/admin/' : '/views/productos.php'));
    exit;
}

header('Location: ' . $appBasePath . '/login.php');
exit;
