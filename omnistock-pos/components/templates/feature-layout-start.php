<?php
declare(strict_types=1);
$featureEscape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$featureActive = static fn(string $name): string => $activeFeature === $name ? ' selected' : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <meta name="description" content="<?= $featureEscape($featureDescription ?? 'Módulo de administración de OmniStock POS.') ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="app-base-path" content="<?= $featureEscape($appBasePath) ?>">
  <title><?= $featureEscape($featureTitle) ?> · OmniStock POS</title>
  <script src="<?= $featureEscape($appBasePath) ?>/assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="<?= $featureEscape($appBasePath) ?>/assets/css/app.css">
  <link rel="stylesheet" href="<?= $featureEscape($appBasePath) ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= $featureEscape($appBasePath) ?>/assets/css/advanced-features.css">
  <link rel="stylesheet" href="<?= $featureEscape($appBasePath) ?>/assets/css/theme-toggle.css">
</head>
<body class="admin-page">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="brand admin-brand" href="<?= $featureEscape($appBasePath) ?>/index.php" aria-label="OmniStock, ir al punto de venta"><span class="brand-mark">O</span><span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span></a>
      <div class="sidebar-label">OPERACIONES</div>
      <nav class="admin-nav" aria-label="Navegación principal">
        <a class="admin-nav-link" href="<?= $featureEscape($appBasePath) ?>/index.php" aria-label="Punto de venta"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Punto de venta</span></a>
        <a class="admin-nav-link<?= $featureActive('reviews') ?>" href="<?= $featureEscape($appBasePath) ?>/resenas.php" aria-label="Reseñas"<?= $activeFeature === 'reviews' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z"/></svg><span>Reseñas</span></a>
        <a class="admin-nav-link<?= $featureActive('map') ?>" href="<?= $featureEscape($appBasePath) ?>/mapa-sucursales.php" aria-label="Mapa de sucursales"<?= $activeFeature === 'map' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span>Mapa de sucursales</span></a>
        <?php if ($user['role'] === 'admin'): ?>
          <a class="admin-nav-link<?= $featureActive('summary') ?>" href="<?= $featureEscape($appBasePath) ?>/admin/sucursal-resumen.php" aria-label="Resumen de sucursales"<?= $activeFeature === 'summary' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 14.8 8.7l6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z"/></svg><span>Resumen de sucursales</span></a>
          <a class="admin-nav-link" href="<?= $featureEscape($appBasePath) ?>/admin/reports.php" aria-label="Reportes"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/></svg><span>Reportes</span></a>
        <?php endif; ?>
      </nav>
      <div class="admin-sidebar-footer">
        <div class="admin-user-avatar"><?= $featureEscape(strtoupper(substr($user['username'], 0, 2))) ?></div>
        <div class="admin-user-copy"><strong><?= $featureEscape($user['username']) ?></strong><span><?= $user['role'] === 'admin' ? 'Administrador' : 'Vendedor' ?></span></div>
        <a class="admin-logout" href="<?= $featureEscape($appBasePath) ?>/logout.php" aria-label="Cerrar sesión" title="Cerrar sesión">↪</a>
      </div>
    </aside>
    <main class="admin-main">
      <?php require __DIR__ . '/../organisms/feature-header.php'; ?>
      <div class="admin-content feature-content">
