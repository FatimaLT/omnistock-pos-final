<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/app_paths.php';

start_user_session();
$appBasePath = app_base_path();

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ' . $appBasePath . ($_SESSION['role'] === 'admin' ? '/admin/' : '/views/productos.php'));
    exit;
}

if (!isset($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['login_csrf'], $csrfToken)) {
        $errorMessage = 'No se pudo validar la solicitud. Actualiza la página e inténtalo nuevamente.';
    } elseif ($username === '' || $password === '') {
        $errorMessage = 'Usuario o contraseña incorrectos.';
    } else {
        try {
            $pdo = database_connection();
            
            // Detección automática de la tabla de usuarios
            $tableName = 'usuarios';
            try {
                $pdo->query('SHOW COLUMNS FROM `usuarios`');
            } catch (PDOException $e) {
                try {
                    $tableName = 'users';
                    $pdo->query('SHOW COLUMNS FROM `users`');
                } catch (PDOException $e2) {
                    $tableName = 'user';
                }
            }

            $userColumns = [];
            foreach ($pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll() as $column) {
                $userColumns[strtolower((string) $column['Field'])] = (string) $column['Field'];
            }

            $columnByAlias = static function (array $columns, array $aliases): ?string {
                foreach ($aliases as $alias) {
                    if (isset($columns[$alias])) {
                        return $columns[$alias];
                    }
                }
                return null;
            };
            
            $idColumn = $columnByAlias($userColumns, ['id', 'usuario_id', 'id_usuario']);
            $usernameColumn = $columnByAlias($userColumns, ['nombre_usuario', 'username', 'usuario', 'email', 'correo']);
            $passwordColumn = $columnByAlias($userColumns, ['password_hash', 'password', 'contrasena', 'contraseña']);
            $roleColumn = $columnByAlias($userColumns, ['tipo_usuario', 'rol', 'role']);

            if ($idColumn === null || $usernameColumn === null || $passwordColumn === null || $roleColumn === null) {
                throw new RuntimeException('Faltan columnas en la tabla de usuarios.');
            }

            $quoteIdentifier = static fn(string $identifier): string => '`' . str_replace('`', '``', $identifier) . '`';
            $statement = $pdo->prepare(
                'SELECT ' . $quoteIdentifier($idColumn) . ' AS user_id, ' .
                    $quoteIdentifier($usernameColumn) . ' AS account_name, ' .
                    $quoteIdentifier($passwordColumn) . ' AS account_hash, ' .
                    $quoteIdentifier($roleColumn) . ' AS account_role
                 FROM ' . $quoteIdentifier($tableName) . '
                 WHERE ' . $quoteIdentifier($usernameColumn) . ' = :username
                 LIMIT 1'
            );
            $statement->execute(['username' => $username]);
            $user = $statement->fetch();

            if (
                $user !== false &&
                in_array($user['account_role'], ['vendedor', 'admin'], true) &&
                password_verify($password, $user['account_hash'])
            ) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['username'] = (string) $user['account_name'];
                $_SESSION['role'] = (string) $user['account_role'];
                unset($_SESSION['login_csrf']);

                header('Location: ' . $appBasePath . ($user['account_role'] === 'admin' ? '/admin/' : '/views/productos.php'));
                exit;
            }

            $errorMessage = 'Usuario o contraseña incorrectos.';
        } catch (Exception $exception) {
            $errorMessage = 'Error al iniciar sesión. Inténtalo más tarde.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión · OmniStock POS</title>
</head>
<body style="background:#0b1120; color:#fff; font-family:system-ui, -apple-system, sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh; margin:0;">
  <div style="background:#1e293b; padding:2.5rem; border-radius:12px; width:100%; max-width:420px; box-shadow:0 10px 25px rgba(0,0,0,0.5);">
    <h2 style="margin-top:0; text-align:center; color:#38bdf8; font-size:1.75rem; margin-bottom:0.5rem;">OmniStock POS</h2>
    <p style="text-align:center; color:#94a3b8; font-size:0.9rem; margin-bottom:2rem;">Ingresa tus credenciales para continuar</p>

    <?php if ($errorMessage !== ''): ?>
      <div style="background:#7f1d1d; color:#fca5a5; padding:0.75rem; border-radius:6px; margin-bottom:1.5rem; font-size:0.85rem; text-align:center; border:1px solid #991b1b;">
        <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
      
      <div style="margin-bottom:1.25rem;">
        <label style="display:block; font-size:0.85rem; margin-bottom:0.5rem; color:#cbd5e1; font-weight:500;">Usuario o correo</label>
        <input name="username" type="text" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" placeholder="Tu usuario o correo" required style="width:100%; padding:0.75rem; border-radius:6px; border:1px solid #334155; background:#0f172a; color:#fff; box-sizing:border-box; font-size:1rem;">
      </div>

      <div style="margin-bottom:1.75rem;">
        <label style="display:block; font-size:0.85rem; margin-bottom:0.5rem; color:#cbd5e1; font-weight:500;">Contraseña</label>
        <input name="password" type="password" placeholder="Ingresa tu contraseña" required style="width:100%; padding:0.75rem; border-radius:6px; border:1px solid #334155; background:#0f172a; color:#fff; box-sizing:border-box; font-size:1rem;">
      </div>

      <button type="submit" style="width:100%; padding:0.85rem; border:none; border-radius:6px; background:#0284c7; color:#fff; font-weight:bold; cursor:pointer; font-size:1rem; transition:background 0.2s;">Iniciar sesión</button>
    </form>
  </div>
</body>
</html>
