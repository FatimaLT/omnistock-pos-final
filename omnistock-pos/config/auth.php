<?php
declare(strict_types=1);

require_once __DIR__ . '/app_paths.php';

function start_user_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    session_start();
}

function application_base_path(): string
{
    return app_base_path();
}

function require_authenticated_user(): array
{
    start_user_session();

    if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['username'])) {
        header('Location: ' . application_base_path() . '/login.php');
        exit;
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
        'role' => (string) $_SESSION['role'],
    ];
}

function require_admin_user(): array
{
    $user = require_authenticated_user();

    if ($user['role'] !== 'admin') {
        header('Location: ' . application_base_path() . '/index.php');
        exit;
    }

    return $user;
}
