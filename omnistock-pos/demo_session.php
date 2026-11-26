<?php
declare(strict_types=1);

require_once __DIR__ . '/config/env.php';

$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (
    getenv('OMNISTOCK_ENABLE_LOCAL_DEMO_SESSION') !== '1' ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)
) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/auth.php';

$allowedTargets = [
    '/omnistock-pos/index.php',
    '/omnistock-pos/admin/index.php',
    '/omnistock-pos/admin/reports.php',
    '/omnistock-pos/admin/messages.php',
];

$target = '/' . ltrim((string) ($_GET['target'] ?? 'omnistock-pos/index.php'), '/');
if (str_starts_with($target, 'omnistock-pos/')) {
    $target = '/' . $target;
}
if (!in_array($target, $allowedTargets, true)) {
    http_response_code(400);
    exit('Destino demo no permitido.');
}

require_once __DIR__ . '/config/database.php';

try {
    $pdo = database_connection();
    $statement = $pdo->prepare(
        'SELECT id, nombre_usuario, tipo_usuario
         FROM usuarios
         WHERE nombre_usuario = :username
           AND tipo_usuario = :role
         LIMIT 1'
    );
    $statement->execute([
        'username' => 'admin@omnistock.com',
        'role' => 'admin',
    ]);
    $demoUser = $statement->fetch();
} catch (Throwable $exception) {
    error_log('OmniStock demo session: ' . $exception->getMessage());
    http_response_code(503);
    exit('No fue posible preparar la sesión de demostración.');
}

if (!$demoUser) {
    http_response_code(503);
    exit('La cuenta demo de administrador no está disponible.');
}

start_user_session();
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $demoUser['id'];
$_SESSION['username'] = (string) $demoUser['nombre_usuario'];
$_SESSION['role'] = 'admin';

header('Cache-Control: no-store');
header('Location: ' . $target, true, 303);
exit;
