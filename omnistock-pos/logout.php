<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
start_user_session();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parameters = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parameters['path'],
        'domain' => $parameters['domain'],
        'secure' => $parameters['secure'],
        'httponly' => $parameters['httponly'],
        'samesite' => $parameters['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
header('Location: ' . application_base_path() . '/login.php');
exit;
