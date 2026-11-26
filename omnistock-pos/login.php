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
$demoPublicEnabled = omni_public_demo_enabled();
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
        } catch (PDOException $exception) {
            error_log('OmniStock login: ' . $exception->getMessage());
            $errorMessage = 'No fue posible iniciar sesión en este momento. Inténtalo más tarde.';
        } catch (RuntimeException $exception) {
            error_log('OmniStock login configuration: ' . $exception->getMessage());
            $errorMessage = 'No fue posible iniciar sesión en este momento. Inténtalo más tarde.';
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
          <div class="login-theme-control"><?php if (file_exists(__DIR__ . '/components/atoms/theme-toggle.php')) { require __DIR__ . '/components/atoms/theme-toggle.php'; } ?></div>
        </div>
        <div class="login-heading">
          <span class="welcome-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.9 8.3 7 10 4.1-1.7 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg></span>
          <p class="eyebrow">ACCESO A TU CUENTA</p>
          <h2>Qué bueno verte</h2>
          <p class="login-subtitle">Ingresa tus credenciales para continuar.</p>
        </div>

        <?php if ($errorMessage !== ''): ?>
          <div class="login-alert" role="alert">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/></svg>
            <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php endif; ?>

        <form class="login-form" method="post" action="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/login.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
          <label for="username">Usuario o correo electrónico</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            <input id="username" name="username" type="text" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" placeholder="Tu usuario o correo" autocomplete="username" maxlength="100" required autofocus>
          </div>
          <div class="password-label"><label for="password">Contraseña</label></div>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
            <input id="password" name="password" type="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" required>
            <button type="button" class="password-toggle" id="toggle-password" aria-label="Mostrar contraseña">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <button class="login-submit" type="submit"><span>Iniciar sesión</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
        </form>

        <a class="login-demo-link" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/demo.php">
          <span>
            <strong>¿Presentación en clase?</strong>
            <small>Entra a la demo sin usuario ni contraseña. Usa productos ficticios y no modifica el inventario real.</small>
          </span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>

        <?php if ($demoPublicEnabled): ?>
        <details class="demo-access-panel">
          <summary>
            <span class="demo-access-title">
              <strong>Accesos Rápidos de Demostración</strong>
              <small>Modo Portafolio / Credenciales visibles</small>
            </span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </summary>
          <div class="demo-access-content">
            <p class="demo-access-intro">Selecciona un perfil para autocompletar el formulario; el acceso conserva la validación habitual.</p>
            <?php foreach ($demoAccounts as $demoAccount): ?>
              <article class="demo-account">
                <h3><?= htmlspecialchars($demoAccount['role'], ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if ($demoAccount['available']): ?>
                  <dl class="demo-credentials">
                    <div><dt>Usuario / correo</dt><dd><code><?= htmlspecialchars($demoAccount['username'], ENT_QUOTES, 'UTF-8') ?></code></dd></div>
                    <div><dt>Contraseña de prueba</dt><dd><code><?= htmlspecialchars($demoAccount['password'], ENT_QUOTES, 'UTF-8') ?></code></dd></div>
                  </dl>
                  <button
                    class="demo-autofill"
                    type="button"
                    aria-label="Autocompletar como <?= htmlspecialchars($demoAccount['role'], ENT_QUOTES, 'UTF-8') ?>"
                    data-demo-username="<?= htmlspecialchars($demoAccount['username'], ENT_QUOTES, 'UTF-8') ?>"
                    data-demo-password="<?= htmlspecialchars($demoAccount['password'], ENT_QUOTES, 'UTF-8') ?>"
                  >Autocompletar</button>
                <?php else: ?>
                  <p class="demo-account-unavailable">Credenciales demo no configuradas.</p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>

        <div class="login-security"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>Conexión protegida · Tus datos están seguros</span></div>
      </div>
      <footer class="login-mobile-footer">© <?= date('Y') ?> OmniStock POS</footer>
    </section>
  </main>
  <script>
    const toggleBtn = document.querySelector('#toggle-password');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        const input = document.querySelector('#password');
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        this.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
      });
    }
    document.querySelectorAll('[data-demo-username][data-demo-password]').forEach((button) => {
      button.addEventListener('click', () => {
        const usernameInput = document.querySelector('#username');
        const passwordInput = document.querySelector('#password');
        usernameInput.value = button.dataset.demoUsername;
        passwordInput.value = button.dataset.demoPassword;
        usernameInput.dispatchEvent(new Event('input', { bubbles: true }));
        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
        usernameInput.focus();
      });
    });
  </script>
</body>
</html>
