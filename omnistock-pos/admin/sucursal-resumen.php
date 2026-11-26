<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/app_paths.php';
$user = require_admin_user();
$appBasePath = app_base_path();
if (!isset($_SESSION['summary_csrf'])) {
    $_SESSION['summary_csrf'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['currency_csrf'])) {
    $_SESSION['currency_csrf'] = bin2hex(random_bytes(32));
}
$summaryCsrf = $_SESSION['summary_csrf'];
$currencyCsrf = $_SESSION['currency_csrf'];
require __DIR__ . '/../components/pages/branch-summary-page.php';
