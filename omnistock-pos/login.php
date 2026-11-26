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
$demoPublicEnabled = getenv('OMNISTOCK_PUBLIC_DEMO_ENABLED') === '1';
$demoAccounts = [
    [
        'role' => 'Administrador',
        'username' => '',
        'password' => '',
        'username_env' => 'OMNISTOCK_PUBLIC_DEMO_ADMIN_USERNAME',
        'password_env' => 'OMNISTOCK_PUBLIC_DEMO_ADMIN_PASSWORD',
    ],
    [
        'role' => 'Vendedor de prueba',
        'username' => '',
        'password' => '',
        'username_env' => 'OMNISTOCK_PUBLIC_DEMO_SELLER_USERNAME',
        'password_env' => 'OMNISTOCK_PUBLIC_DEMO_SELLER_PASSWORD',
    ],
];

foreach ($demoAccounts as &$demoAccount) {
    if ($demoPublicEnabled) {
        $demoAccount['username'] = trim((string) (getenv($demoAccount['username_env']) ?: ''));
        $demoAccount['password'] = (string) (getenv($demoAccount['password_env']) ?: '');
    }
    $demoAccount['available'] = $demoPublicEnabled &&
        $demoAccount['username'] !== '' &&
        strlen($demoAccount['username']) <= 80 &&
        strlen($demoAccount['password']) >= 12 &&
        strlen($demoAccount['password']) <= 200;
    unset($demoAccount['username_env'], $demoAccount['password_env']);
}
unset($demoAccount);

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
            
            // Detección automática del nombre de la tabla (usuarios o users)
            $userColumns = [];
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
                throw new RuntimeException('La tabla de usuarios no tiene las columnas necesarias para autenticar.');
            }

            $quoteIdentifier = static fn(string $identifier): string => '`' . str_replace('`', '``', $identifier) . '`';
            $statement =$pdo->prepare(
                'SELECT ' . $quoteIdentifier($idColumn) . ' AS user_id, ' .
                    $quoteIdentifier($usernameColumn) . ' AS account_name, ' .
                    $quoteIdentifier($passwordColumn) . ' AS account_hash, ' .
                    $quoteIdentifier($roleColumn) . ' AS account_role
                 FROM ' . $quoteIdentifier($tableName) . '
                 WHERE ' . $quoteIdentifier($usernameColumn) . ' = :username
                 LIMIT 1'
            );
            $statement->execute(['username' =>$username]);
            $user =$statement->fetch();

            if (
                $user !== false &&
                in_array($user['account_role'], ['vendedor', 'admin'], true) &&
                password_verify($password,$user['account_hash'])
            ) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['username'] = (string)$user['account_name'];
                $_SESSION['role'] = (string)$user['account_role'];
                unset($_SESSION['login_csrf']);

                if (password_needs_rehash($user['account_hash'], PASSWORD_DEFAULT)) {
                    try {
                        $rehash =$pdo->prepare(
                            'UPDATE ' . $quoteIdentifier($tableName) . ' SET ' . $quoteIdentifier($passwordColumn) .
                            ' = :password_hash WHERE ' . $quoteIdentifier($idColumn) . ' = :id'
                        );
                        $rehash->execute([
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                            'id' => $user['user_id'],
                        ]);
                    } catch (PDOException $exception) {
                        error_log('OmniStock password rehash: ' . $exception->getMessage());
                    }
                }

                header('Location: ' . $appBasePath . ($user['account_role'] === 'admin' ? '/admin/' : '/views/productos.php'));
                exit;
            }

            $errorMessage = 'Usuario o contraseña incorrectos.';
        } catch (PDOException $exception) {
            error_log('OmniStock login: ' . $exception->getMessage());$errorMessage = 'No fue posible iniciar sesión en este momento. Inténtalo más tarde.';
        } catch (RuntimeException $exception) {
            error_log('OmniStock login configuration: ' . $exception->getMessage());$errorMessage = 'No fue posible iniciar sesión en este momento. Inténtalo más tarde.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <meta name="description" content="Inicia sesión en OmniStock POS para administrar ventas, productos e inventario.">
  <meta name="robots" content="noindex, nofollow">
  <title>Iniciar sesión · OmniStock POS</title>
  <script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/app.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/theme-toggle.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/login.css?v=2">
</head>
<body class="login-page">
  <main class="login-layout">
    <section class="login-showcase" aria-label="OmniStock punto de venta">
      <a class="brand login-brand" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/login.php" aria-label="OmniStock">
        <span class="brand-mark">O</span>
        <span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span>
      </a>
      <div class="showcase-content">
        <span class="showcase-kicker"><i></i> OPERACIÓN INTELIGENTE</span>
        <h1>Tu negocio,<br>siempre en <span>control.</span></h1>
        <p>Ventas e inventario en un solo lugar. Rápido, confiable y listo para operar.</p>
        <div class="showcase-visual" aria-hidden="true">
          <div class="visual-glow"></div>
          <div class="visual-window">
            <div class="visual-top"><span></span><span></span><span></span><i>OMNISTOCK / TERMINAL</i></div>
            <div class="visual-content">
              <div class="visual-sidebar"><b></b><span></span><span></span><span></span></div>
              <div class="visual-main"><small>RESUMEN DE VENTAS</small><strong>$ 24,580<span>.00</span></strong><div class="visual-chart"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="visual-lines"><i></i><i></i><i></i></div></div>
            </div>
          </div>
          <div class="floating-tag"><span>↗</span> Todo sincronizado</div>
        </div>
      </div>
      <div class="showcase-footer"><span>© <?= date('Y') ?> OmniStock</span><span><i></i> Plataforma segura</span></div>
    </section>

    <section class="login-side">
      <div class="login-card">
        <div class="login-topbar">
          <div class="mobile-brand">
            <span class="brand-mark">O</span>
            <span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span>
          </div>
          <div class="login-theme-control"><?php require __DIR__ . '/components/atoms/theme-toggle.php'; ?></div>
        </div>
        <div class="login-heading">
          <span class="welcome-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.9 8.3
