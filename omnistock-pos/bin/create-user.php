<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$username = trim((string) ($argv[1] ?? ''));
$role = (string) ($argv[2] ?? '');

if ($username === '' || strlen($username) > 80 || !in_array($role, ['vendedor', 'admin'], true)) {
    fwrite(STDERR, "Uso: php bin/create-user.php <usuario> <vendedor|admin>\n");
    exit(1);
}

fwrite(STDOUT, "Contraseña nueva: ");
$password = rtrim((string) fgets(STDIN), "\r\n");

if (strlen($password) < 12) {
    fwrite(STDERR, "\nLa contraseña debe tener al menos 12 caracteres.\n");
    exit(1);
}

try {
    $pdo = database_connection();
    $statement = $pdo->prepare(
        'INSERT INTO usuarios (nombre_usuario, password_hash, tipo_usuario)
         VALUES (:username, :password_hash, :role)'
    );
    $statement->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);
    fwrite(STDOUT, "\nUsuario creado correctamente.\n");
} catch (PDOException $exception) {
    error_log('OmniStock create user: ' . $exception->getMessage());
    fwrite(STDERR, "\nNo se pudo crear el usuario. Revisa la conexión y que el nombre no esté en uso.\n");
    exit(1);
}
