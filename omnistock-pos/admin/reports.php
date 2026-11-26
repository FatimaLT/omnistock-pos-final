<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stock_alerts.php';
$user = require_authenticated_user();
if ($user['role'] !== 'admin') {
    header('Location: ' . app_base_path() . '/index.php?access_denied=reports', true, 303);
    exit;
}

if (!isset($_SESSION['backup_csrf'])) {
    $_SESSION['backup_csrf'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['catalog_csrf'])) {
    $_SESSION['catalog_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['backup_csrf'];
$errorMessage = '';
$backupMessage = $_SESSION['backup_message'] ?? '';
unset($_SESSION['backup_message']);

$todayTotal = 0.0;
$monthTotal = 0.0;
$todayCount = 0;
$dailySales = [];
$monthlySales = [];
$topProducts = [];
$reportsAvailable = false;
$stockAlertCount = 0;

try {
    $pdo = database_connection();
    $todayQuery = $pdo->query(
        'SELECT COALESCE(SUM(total), 0) AS total, COUNT(*) AS sale_count
         FROM ventas
         WHERE fecha_venta >= CURDATE() AND fecha_venta < CURDATE() + INTERVAL 1 DAY'
    );
    $today = $todayQuery->fetch();
    $todayTotal = (float) $today['total'];
    $todayCount = (int) $today['sale_count'];

    $monthQuery = $pdo->query(
        'SELECT COALESCE(SUM(total), 0) FROM ventas
         WHERE fecha_venta >= DATE_FORMAT(CURDATE(), "%Y-%m-01")
           AND fecha_venta < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, "%Y-%m-01")'
    );
    $monthTotal = (float) $monthQuery->fetchColumn();

    $dailyQuery = $pdo->query(
        'SELECT DATE(fecha_venta) AS sale_date, COALESCE(SUM(total), 0) AS total
         FROM ventas
         WHERE fecha_venta >= CURDATE() - INTERVAL 13 DAY
           AND fecha_venta < CURDATE() + INTERVAL 1 DAY
         GROUP BY DATE(fecha_venta)
         ORDER BY sale_date'
    );
    foreach ($dailyQuery->fetchAll() as $row) {
        $dailySales[(string) $row['sale_date']] = (float) $row['total'];
    }

    $monthlyQuery = $pdo->query(
        'SELECT DATE_FORMAT(fecha_venta, "%Y-%m-01") AS sale_month, COALESCE(SUM(total), 0) AS total
         FROM ventas
         WHERE fecha_venta >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, "%Y-%m-01")
           AND fecha_venta < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, "%Y-%m-01")
         GROUP BY DATE_FORMAT(fecha_venta, "%Y-%m-01")
         ORDER BY sale_month'
    );
    foreach ($monthlyQuery->fetchAll() as $row) {
        $monthlySales[(string) $row['sale_month']] = (float) $row['total'];
    }

    $topQuery = $pdo->query(
        'SELECT nombre_producto, SUM(cantidad) AS units_sold, SUM(subtotal_linea) AS revenue
         FROM detalle_ventas
         GROUP BY producto_id, nombre_producto
         ORDER BY units_sold DESC, revenue DESC
         LIMIT 5'
    );
    $topProducts = $topQuery->fetchAll();
    $stockAlertCount = count(critical_stock_products($pdo));
    $reportsAvailable = true;
} catch (Throwable $exception) {
    error_log('OmniStock admin reports: ' . $exception->getMessage());
    $errorMessage = 'No se pudieron cargar los reportes. Verifica que MySQL y el esquema de ventas estén disponibles.';
}

function report_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$dailyChart = [];
for ($offset = 13; $offset >= 0; $offset--) {
    $date = new DateTimeImmutable('today -' . $offset . ' days');
    $key = $date->format('Y-m-d');
    $dailyChart[] = [
        'label' => $date->format('d/m'),
        'value' => $dailySales[$key] ?? 0,
    ];
}
$dailyMax = max(1, ...array_column($dailyChart, 'value'));

$monthlyChart = [];
$monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
for ($offset = 5; $offset >= 0; $offset--) {
    $date = new DateTimeImmutable('first day of this month -' . $offset . ' months');
    $key = $date->format('Y-m-01');
    $monthlyChart[] = [
        'label' => $monthNames[(int) $date->format('n') - 1],
        'value' => $monthlySales[$key] ?? 0,
    ];
}
$monthlyMax = max(1, ...array_column($monthlyChart, 'value'));
$topMax = max(1, ...array_map(static fn(array $product): float => (float) $product['units_sold'], $topProducts ?: [['units_sold' => 0]]));
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <title>Reportes · OmniStock POS</title>
  <script src="../assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="../assets/css/app.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/reports.css">
  <link rel="stylesheet" href="../assets/css/theme-toggle.css">
</head>
<body class="admin-page">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="brand admin-brand" href="index.php"><span class="brand-mark">O</span><span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span></a>
      <div class="sidebar-label">ADMINISTRACIÓN</div>
      <nav class="admin-nav">
        <a class="admin-nav-link" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16l-1.5 13h-13L4 7Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg><span>Catálogo</span></a>
        <a class="admin-nav-link selected" href="reports.php" aria-current="page"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/></svg><span>Reportes</span></a>
        <a class="admin-nav-link" href="sucursal-resumen.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/></svg><span>Resumen de sucursales</span></a>
        <a class="admin-nav-link" href="messages.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H8l-4 4V5Z"/><path d="M8 9h8m-8 4h5"/></svg><span>Mensajería</span></a>
        <a class="admin-nav-link" href="users.php"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1m2-11a3 3 0 0 1 0 6m1 1a5 5 0 0 1 3 4"/></svg><span>Usuarios</span></a>
        <?php require __DIR__ . '/_stock_alert_modal.php'; ?>
        <a class="admin-nav-link" href="../index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Punto de venta</span></a>
      </nav>
      <div class="admin-sidebar-footer">
        <div class="admin-user-avatar"><?= report_escape(strtoupper(substr($user['username'], 0, 2))) ?></div>
        <div class="admin-user-copy"><strong><?= report_escape($user['username']) ?></strong><span>Administrador</span></div>
        <a class="admin-logout" href="../logout.php" title="Cerrar sesión" aria-label="Cerrar sesión">↪</a>
      </div>
    </aside>

    <main class="admin-main">
      <header class="admin-topbar"><div><span>Administración</span><b>/</b><strong>Reportes y respaldo</strong></div><div class="theme-header-actions"><a href="../index.php" class="view-pos-link">Abrir POS <span>↗</span></a><?php require __DIR__ . '/../components/atoms/theme-toggle.php'; ?></div></header>
      <div class="admin-content report-content">
        <section class="admin-heading">
          <div><p class="eyebrow">ANÁLISIS DEL NEGOCIO</p><h1>Reportes</h1><p>Consulta el rendimiento de ventas y el respaldo local de la base de datos.</p></div>
          <span class="report-period"><?= $reportsAvailable ? 'Datos actualizados' : 'Base de datos no disponible' ?></span>
        </section>

        <?php if ($errorMessage !== ''): ?><div class="admin-alert error" role="alert"><?= report_escape($errorMessage) ?></div><?php endif; ?>
        <?php if ($backupMessage !== ''): ?><div class="admin-alert success" role="status"><?= report_escape($backupMessage) ?></div><?php endif; ?>

        <section class="report-metrics">
          <article class="report-metric"><span class="metric-icon cyan">↗</span><div><small>VENTAS DE HOY</small><strong>$<?= number_format($todayTotal, 2) ?></strong><span><?= $todayCount ?> transacciones</span></div></article>
          <article class="report-metric"><span class="metric-icon green">▤</span><div><small>VENTAS DEL MES</small><strong>$<?= number_format($monthTotal, 2) ?></strong><span><?= $monthNames[(int) date('n') - 1] . ' ' . date('Y') ?></span></div></article>
          <article class="report-metric"><span class="metric-icon amber">★</span><div><small>PRODUCTO MÁS VENDIDO</small><strong class="best-product"><?= isset($topProducts[0]) ? report_escape($topProducts[0]['nombre_producto']) : 'Sin ventas' ?></strong><span><?= isset($topProducts[0]) ? (int) $topProducts[0]['units_sold'] . ' unidades vendidas' : 'Aún no hay ventas' ?></span></div></article>
        </section>

        <section class="report-charts">
          <article class="report-card daily-chart-card">
            <header class="report-card-header"><div><h2>Ventas por día</h2><p>Últimos 14 días · total diario</p></div><span class="chart-legend"><i></i> Ventas</span></header>
            <div class="bar-chart daily-chart" role="img" aria-label="Gráfico de ventas diarias de los últimos 14 días">
              <?php foreach ($dailyChart as $bar): $height = $bar['value'] > 0 ? max(4, $bar['value'] / $dailyMax * 100) : 0; ?>
                <div class="bar-column" title="<?= report_escape($bar['label'] . ': $' . number_format($bar['value'], 2)) ?>">
                  <div class="bar-value"><?= $bar['value'] > 0 ? '$' . number_format($bar['value'], 0) : '' ?></div>
                  <div class="bar-track"><i style="height: <?= $height ?>%"></i></div>
                  <span><?= report_escape($bar['label']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </article>
          <article class="report-card monthly-chart-card">
            <header class="report-card-header"><div><h2>Ventas por mes</h2><p>Comparativo de los últimos 6 meses</p></div><span class="chart-legend"><i></i> Ventas</span></header>
            <div class="bar-chart monthly-chart" role="img" aria-label="Gráfico comparativo de ventas mensuales">
              <?php foreach ($monthlyChart as $bar): $height = $bar['value'] > 0 ? max(4, $bar['value'] / $monthlyMax * 100) : 0; ?>
                <div class="bar-column" title="<?= report_escape($bar['label'] . ': $' . number_format($bar['value'], 2)) ?>">
                  <div class="bar-value"><?= $bar['value'] > 0 ? '$' . number_format($bar['value'], 0) : '' ?></div>
                  <div class="bar-track"><i style="height: <?= $height ?>%"></i></div>
                  <span><?= report_escape($bar['label']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </article>
        </section>

        <section class="report-bottom-grid">
          <article class="report-card top-products-card">
            <header class="report-card-header"><div><h2>Productos más vendidos</h2><p>Unidades vendidas, histórico</p></div><span class="ranking-icon">↗</span></header>
            <?php if (!$topProducts): ?><div class="report-empty">Todavía no hay productos vendidos.</div>
            <?php else: ?><div class="top-products-list">
              <?php foreach ($topProducts as $index => $product): $percent = (int) round((float) $product['units_sold'] / $topMax * 100); ?>
                <div class="top-product-row">
                  <span class="top-rank"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                  <div class="top-product-info"><div><strong><?= report_escape($product['nombre_producto']) ?></strong><span><?= (int) $product['units_sold'] ?> uds.</span></div><div class="rank-track"><i style="width: <?= $percent ?>%"></i></div></div>
                  <strong class="top-product-revenue">$<?= number_format((float) $product['revenue'], 2) ?></strong>
                </div>
              <?php endforeach; ?>
            </div><?php endif; ?>
          </article>

          <article class="report-card backup-card">
            <header class="report-card-header"><div><h2>Respaldo local</h2><p>Copia SQL completa de la base</p></div><span class="backup-icon">▣</span></header>
            <div class="backup-visual"><span class="backup-database"><svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg></span><span class="backup-arrows">↔</span><span class="backup-file">.SQL</span></div>
            <p class="backup-description">Genera un dump local descargable que incluye las tablas, datos, vistas y rutinas disponibles en MySQL.</p>
            <form method="post" action="backup.php" class="backup-form">
              <input type="hidden" name="csrf_token" value="<?= report_escape($csrfToken) ?>">
              <button type="submit" class="primary-button" <?= $reportsAvailable ? '' : 'disabled' ?>><span>↓</span> Generar y descargar respaldo</button>
            </form>
            <small class="backup-note"><i></i> Se guardará una copia protegida en el servidor.</small>
          </article>
        </section>
      </div>
    </main>
  </div>
  <script>window.omniStockAlerts = { csrf: <?= json_encode($_SESSION['catalog_csrf']) ?>, count: <?= (int) $stockAlertCount ?> };</script>
  <script src="../assets/js/stock-alerts.js" defer></script>
</body>
</html>
