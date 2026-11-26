<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/app_paths.php';
$user = require_authenticated_user();
$appBasePath = app_base_path();
if (!isset($_SESSION['reviews_csrf'])) {
    $_SESSION['reviews_csrf'] = bin2hex(random_bytes(32));
}
$reviewCsrf = $_SESSION['reviews_csrf'];
require __DIR__ . '/components/pages/reviews-page.php';
