<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/app_paths.php';
$user = require_authenticated_user();
$appBasePath = app_base_path();
require __DIR__ . '/components/pages/branch-map-page.php';
