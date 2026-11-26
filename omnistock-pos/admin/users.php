<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_paths.php';

$admin = require_admin_user();
if (!isset($_SESSION['users_csrf'])) {
    $_SESSION['users_csrf'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$successMessage = '';
$pdo = database_connection();
$columns = [];
foreach ($pdo->query('SHOW COLUMNS FROM `usuarios`')->fetchAll() as $column) {
    $columns[strtolower((string) $column['Field'])] = (string) $column['Field'];
}
$findColumn = static function (array $aliases) use ($columns): ?string {
    foreach ($aliases as $alias) {
        if (isset($columns[$alias])) {
            return $columns[$alias];
        }
    }

    return null;
};
$idColumn = $findColumn(['id', 'usuario_id', 'id_usuario']);
$nameColumn = $findColumn(['nombre_usuario', 'nombre', 'username', 'usuario']);
$emailColumn = $findColumn(['email', 'correo']);
$passwordColumn = $findColumn(['password_hash', 'password', 'contrasena', 'contraseña']);
$roleColumn = $findColumn(['tipo_usuario', 'rol', 'role']);
if ($idColumn === null || $passwordColumn === null || $roleColumn === null || ($nameColumn === null && $emailColumn === null)) {
    http_response_code(500);
    exit('La tabla usuarios no tiene las columnas necesarias para administrar cuentas.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals((string) $_SESSION['users_csrf'], $csrfToken)) {
        $errorMessage = 'La sesión expiró. Actualiza la página e inténtalo nuevamente.';
    } elseif ($name === '' || mb_strlen($name, 'UTF-8') > 80) {
        $errorMessage = 'Ingresa un nombre de hasta 80 caracteres.';
    } elseif ($emailColumn !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 150)) {
        $errorMessage = 'Ingresa un correo válido de hasta 150 caracteres.';
    } elseif (strlen($password) < 12 || strlen($password) > 200) {
        $errorMessage = 'La contraseña debe tener entre 12 y 200 caracteres.';
    } else {
        $insertData = [$passwordColumn => password_hash($password, PASSWORD_DEFAULT), $roleColumn => 'vendedor'];
        if ($nameColumn !== null) {
            $insertData[$nameColumn] = $name;
        }
        if ($emailColumn !== null) {
            $insertData[$emailColumn] = $email;
        }

        $quotedColumns = array_map(
            static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
            array_keys($insertData)
        );
        $placeholders = array_map(
            static fn(string $column): string => ':' . $column,
            array_keys($insertData)
        );
        try {
            $insert = $pdo->prepare(
                'INSERT INTO `usuarios` (' . implode(', ', $quotedColumns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );
            $insert->execute($insertData);
            $successMessage = 'Cuenta de vendedor creada correctamente. Ya puede iniciar sesión.';
            $_SESSION['users_csrf'] = bin2hex(random_bytes(32));
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errorMessage = 'Ya existe una cuenta con ese nombre o correo.';
            } else {
                error_log('OmniStock create seller: ' . $exception->getMessage());
                $errorMessage = 'No se pudo crear la cuenta. Revisa la configuración de usuarios.';
            }
        }
    }
}

$select = ['u.' . '`' . str_replace('`', '``', $idColumn) . '` AS user_id'];
if ($nameColumn !== null) {
    $select[] = 'u.' . '`' . str_replace('`', '``', $nameColumn) . '` AS display_name';
} elseif ($emailColumn !== null) {
    $select[] = 'u.' . '`' . str_replace('`', '``', $emailColumn) . '` AS display_name';
}
if ($emailColumn !== null && $emailColumn !== $nameColumn) {
    $select[] = 'u.' . '`' . str_replace('`', '``', $emailColumn) . '` AS email';
}
$select[] = 'u.' . '`' . str_replace('`', '``', $roleColumn) . '` AS user_role';
$users = $pdo->query(
    'SELECT ' . implode(', ', $select) . ' FROM `usuarios` AS u ORDER BY u.`' .
    str_replace('`', '``', $idColumn) . '` DESC'
)->fetchAll();

function users_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <title>Usuarios · OmniStock POS</title>
  <script src="../assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="../assets/css/app.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <style>
    .users-grid { display: grid; grid-template-columns: minmax(270px, .75fr) minmax(0, 1.25fr); gap: 18px; }
    .users-card { padding: 20px; border: 1px solid var(--border); border-radius: 11px; background: rgba(16, 26, 45, .68); }
    .users-card h2 { margin: 0 0 7px; color: #e3ebf5; font: 600 14px var(--display); }
    .users-card > p { margin: 0 0 18px; color: #8797ac; font-size: 10px; line-height: 1.6; }
    .users-form { display: grid; gap: 13px; }
    .users-form label { display: grid; gap: 6px; color: #bac6d5; font-size: 10px; }
    .users-form input { width: 100%; height: 38px; padding: 0 10px; border: 1px solid var(--border-strong); border-radius: 6px; outline: 0; color: #e1eaf4; background: #0c1525; font-size: 11px; }
    .users-form input:focus { border-color: rgba(6, 182, 212, .48); }
    .users-table { width: 100%; border-collapse: collapse; text-align: left; }
    .users-table th, .users-table td { padding: 11px 9px; border-bottom: 1px solid rgba(148, 163, 184, .08); color: #afbdce; font-size: 10px; overflow-wrap: anywhere; }
    .users-table th { color: #74849a; font-size: 8px; letter-spacing: .7px; }
    .users-role { color: #9deaf5; }
    .users-alert { margin-bottom: 15px; padding: 11px; border: 1px solid rgba(248, 113, 113, .2); border-radius: 7px; color: #fca5a5; font-size: 10px; }
    .users-alert.success { border-color: rgba(52, 211, 153, .2); color: #a7e8ce; }
    @media (max-width: 760px) { .users-grid { grid-template-columns: 1fr; } }
  </style>
  <link rel="stylesheet" href="../assets/css/theme-toggle.css">
</head>
<body class="admin-page">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="brand admin-brand" href="index.php"><span class="brand-mark">O</span><span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span></a>
      <div class="sidebar-label">ADMINISTRACIÓN</div>
      <nav class="admin-nav">
        <a class="admin-nav-link" href="index.php"><span>Catálogo</span></a>
        <a class="admin-nav-link" href="reports.php"><span>Reportes</span></a>
        <a class="admin-nav-link" href="messages.php"><span>Mensajería</span></a>
        <a class="admin-nav-link selected" href="users.php" aria-current="page"><span>Usuarios</span></a>
        <a class="admin-nav-link" href="../index.php"><span>Punto de venta</span></a>
      </nav>
      <div class="admin-sidebar-footer">
        <div class="admin-user-avatar"><?= users_escape(strtoupper(substr($admin['username'], 0, 2))) ?></div>
        <div class="admin-user-copy"><strong><?= users_escape($admin['username']) ?></strong><span>Administrador</span></div>
        <a class="admin-logout" href="../logout.php" title="Cerrar sesión" aria-label="Cerrar sesión">↪</a>
      </div>
    </aside>
    <main class="admin-main">
      <header class="admin-topbar"><div><span>Administración</span><b>/</b><strong>Usuarios</strong></div><div class="theme-header-actions"><a href="../index.php" class="view-pos-link">Abrir POS <span>↗</span></a><?php require __DIR__ . '/../components/atoms/theme-toggle.php'; ?></div></header>
      <div class="admin-content">
        <section class="admin-heading"><div><p class="eyebrow">EQUIPO</p><h1>Usuarios</h1><p>Crea cuentas de vendedor para operar la caja y recibir avisos de almacén.</p></div></section>
        <?php if ($errorMessage !== ''): ?><div class="users-alert" role="alert"><?= users_escape($errorMessage) ?></div><?php endif; ?>
        <?php if ($successMessage !== ''): ?><div class="users-alert success" role="status"><?= users_escape($successMessage) ?></div><?php endif; ?>
        <div class="users-grid">
          <section class="users-card">
            <h2>Crear perfil de vendedor</h2>
            <p>La contraseña se guarda con `password_hash()`. El vendedor inicia sesión con su correo.</p>
            <form class="users-form" method="post">
              <input type="hidden" name="csrf_token" value="<?= users_escape($_SESSION['users_csrf']) ?>">
              <label>Nombre del vendedor<input name="name" maxlength="80" autocomplete="name" required></label>
              <?php if ($emailColumn !== null): ?><label>Correo electrónico<input name="email" type="email" maxlength="150" autocomplete="email" required></label><?php endif; ?>
              <label>Contraseña (mínimo 12 caracteres)<input name="password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required></label>
              <button class="primary-button" type="submit">Crear vendedor</button>
            </form>
          </section>
          <section class="users-card">
            <h2>Cuentas registradas</h2>
            <p>El rol determina las secciones disponibles al iniciar sesión.</p>
            <div class="products-table-wrap"><table class="users-table">
              <thead><tr><th>NOMBRE / CORREO</th><?php if ($emailColumn !== null && $emailColumn !== $nameColumn): ?><th>CORREO</th><?php endif; ?><th>ROL</th></tr></thead>
              <tbody><?php foreach ($users as $account): ?>
                <tr><td><?= users_escape($account['display_name'] ?? '') ?></td><?php if ($emailColumn !== null && $emailColumn !== $nameColumn): ?><td><?= users_escape($account['email'] ?? '') ?></td><?php endif; ?><td class="users-role"><?= users_escape(ucfirst((string) $account['user_role'])) ?></td></tr>
              <?php endforeach; ?></tbody>
            </table></div>
          </section>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
