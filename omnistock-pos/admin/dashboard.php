<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/app_paths.php';

start_user_session();
require_admin_user();

$pdo = database_connection();
$appBasePath = app_base_path();

// Obtener datos para gráficos
$periodo = $_GET['periodo'] ?? '7days';
$fechaInicio = match($periodo) {
  '7days' => date('Y-m-d', strtotime('-7 days')),
  '30days' => date('Y-m-d', strtotime('-30 days')),
  '90days' => date('Y-m-d', strtotime('-90 days')),
  default => date('Y-m-d', strtotime('-7 days')),
};
$fechaFin = date('Y-m-d');

// Obtener datos de ventas por día
$ventasStmt = $pdo->prepare('
  SELECT 
    DATE(fecha_venta) as fecha,
    COUNT(*) as cantidad_ventas,
    SUM(total) as total_ventas
  FROM ventas
  WHERE DATE(fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
  GROUP BY DATE(fecha_venta)
  ORDER BY DATE(fecha_venta) ASC
');
$ventasStmt->execute([
  ':fecha_inicio' => $fechaInicio,
  ':fecha_fin' => $fechaFin,
]);
$ventasPorDia = $ventasStmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos más vendidos
$topProductosStmt = $pdo->prepare('
  SELECT 
    p.nombre,
    SUM(dv.cantidad) as cantidad_vendida,
    SUM(dv.cantidad * dv.precio_unitario) as ingresos
  FROM detalle_ventas dv
  JOIN ventas v ON dv.venta_id = v.id
  JOIN productos p ON dv.producto_id = p.id
  WHERE DATE(v.fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
  GROUP BY p.id
  ORDER BY cantidad_vendida DESC
  LIMIT 5
');
$topProductosStmt->execute([
  ':fecha_inicio' => $fechaInicio,
  ':fecha_fin' => $fechaFin,
]);
$topProductos = $topProductosStmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener datos de métodos de pago
$pagosStmt = $pdo->prepare('
  SELECT 
    metodo_pago as tipo_pago,
    COUNT(*) as cantidad,
    SUM(total) as monto
  FROM ventas
  WHERE DATE(fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
  GROUP BY metodo_pago
');
$pagosStmt->execute([
  ':fecha_inicio' => $fechaInicio,
  ':fecha_fin' => $fechaFin,
]);
$datosPagos = $pagosStmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener totales resumen
$resumenStmt = $pdo->prepare('
  SELECT 
    COUNT(DISTINCT id) as total_ventas,
    SUM(total) as total_ingresos,
    AVG(total) as ticket_promedio,
    COUNT(DISTINCT id) as ventas_completadas
  FROM ventas
  WHERE DATE(fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
');
$resumenStmt->execute([
  ':fecha_inicio' => $fechaInicio,
  ':fecha_fin' => $fechaFin,
]);
$resumen = $resumenStmt->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#080D19">
  <title>Dashboard · OmniStock POS</title>
  <script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/theme-modern.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/theme-toggle.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .dashboard-header { margin-bottom: 2rem; }
    .dashboard-controls {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;
    }
    .stat-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-backdrop);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 1.5rem;
      transition: all 0.3s ease;
    }
    .stat-card:hover {
      border-color: var(--accent-cyan);
      box-shadow: var(--shadow-cyan);
    }
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--accent-cyan);
      margin: 0.5rem 0;
    }
    .stat-label {
      font-size: 0.875rem;
      color: var(--gray-400);
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .charts-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .chart-container {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-backdrop);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: var(--shadow-md);
    }
    .chart-title {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--white);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .chart-title::before {
      content: '';
      display: inline-block;
      width: 4px;
      height: 1.125rem;
      background: var(--accent-cyan);
      border-radius: 2px;
    }
  </style>
</head>
<body class="theme-modern-page">
  <header class="navbar">
    <div class="container">
      <div class="navbar-brand">
        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <circle cx="50" cy="50" r="48" fill="url(#logoGradient)" opacity="0.1" stroke="#06b6d4" stroke-width="2"/>
          <defs>
            <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" style="stop-color:#06b6d4;stop-opacity:1" />
              <stop offset="100%" style="stop-color:#0891b2;stop-opacity:1" />
            </linearGradient>
          </defs>
          <g transform="translate(50, 50)">
            <circle cx="-15" cy="20" r="4" fill="#06b6d4"/>
            <circle cx="15" cy="20" r="4" fill="#06b6d4"/>
            <path d="M -20 15 L -18 -8 L 18 -8 L 20 15 Z" fill="none" stroke="#06b6d4" stroke-width="2.5" stroke-linejoin="round"/>
          </g>
        </svg>
        OmniStock
      </div>
      <div class="theme-header-actions">
        <?php require __DIR__ . '/../components/atoms/theme-toggle.php'; ?>
      </div>
    </div>
  </header>

  <main class="container" style="padding: 2rem 1.5rem;">
    <div class="dashboard-header">
      <h1 style="font-size: 2rem; color: var(--white); margin-bottom: 0.5rem;">📊 Dashboard de Ventas</h1>
      <p style="color: var(--gray-400); font-size: 0.875rem;">Período: <?= htmlspecialchars($fechaInicio, ENT_QUOTES, 'UTF-8') ?> a <?= htmlspecialchars($fechaFin, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="dashboard-controls">
      <a href="?periodo=7days" class="btn btn-secondary" style="<?= $periodo === '7days' ? 'background: var(--accent-cyan); color: var(--primary-dark);' : '' ?>">
        Últimos 7 días
      </a>
      <a href="?periodo=30days" class="btn btn-secondary" style="<?= $periodo === '30days' ? 'background: var(--accent-cyan); color: var(--primary-dark);' : '' ?>">
        Últimos 30 días
      </a>
      <a href="?periodo=90days" class="btn btn-secondary" style="<?= $periodo === '90days' ? 'background: var(--accent-cyan); color: var(--primary-dark);' : '' ?>">
        Últimos 90 días
      </a>
      <a href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/admin/" class="btn btn-info" style="margin-left: auto;">
        ← Volver
      </a>
    </div>

    <!-- Tarjetas de estadísticas -->
    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-label">💰 Total de Ingresos</div>
        <div class="stat-value">$<?= number_format((float)($resumen['total_ingresos'] ?? 0), 2) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">📦 Total de Ventas</div>
        <div class="stat-value"><?= htmlspecialchars((string)($resumen['total_ventas'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">📋 Ticket Promedio</div>
        <div class="stat-value">$<?= number_format((float)($resumen['ticket_promedio'] ?? 0), 2) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">✅ Ventas Completadas</div>
        <div class="stat-value"><?= htmlspecialchars((string)($resumen['ventas_completadas'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>

    <!-- Gráficos -->
    <div class="charts-grid">
      <!-- Gráfico de Ventas por Día -->
      <div class="chart-container">
        <div class="chart-title">Ventas por Día</div>
        <canvas id="ventasChart"></canvas>
      </div>

      <!-- Gráfico de Métodos de Pago -->
      <div class="chart-container">
        <div class="chart-title">Métodos de Pago</div>
        <canvas id="pagosChart"></canvas>
      </div>

      <!-- Gráfico de Productos Top -->
      <div class="chart-container">
        <div class="chart-title">Productos Más Vendidos</div>
        <canvas id="productosChart"></canvas>
      </div>
    </div>
  </main>

  <script>
    // Gráfico de Ventas por Día
    const ventasData = <?= json_encode($ventasPorDia, JSON_UNESCAPED_UNICODE) ?>;
    const ventasCtx = document.getElementById('ventasChart').getContext('2d');
    new Chart(ventasCtx, {
      type: 'line',
      data: {
        labels: ventasData.map(d => d.fecha),
        datasets: [{
          label: 'Total de Ventas ($)',
          data: ventasData.map(d => d.total_ventas),
          borderColor: '#06b6d4',
          backgroundColor: 'rgba(6, 182, 212, 0.1)',
          borderWidth: 2,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#06b6d4',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#999' } } },
        scales: {
          y: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.1)' } },
          x: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.1)' } }
        }
      }
    });

    // Gráfico de Métodos de Pago
    const pagosData = <?= json_encode($datosPagos, JSON_UNESCAPED_UNICODE) ?>;
    const pagosCtx = document.getElementById('pagosChart').getContext('2d');
    new Chart(pagosCtx, {
      type: 'doughnut',
      data: {
        labels: pagosData.map(p => p.tipo_pago),
        datasets: [{
          data: pagosData.map(p => p.cantidad),
          backgroundColor: ['#06b6d4', '#10b981', '#f59e0b', '#3b82f6', '#ef4444'],
          borderColor: '#1e293b',
          borderWidth: 2,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#999' } } }
      }
    });

    // Gráfico de Productos Top
    const productosData = <?= json_encode($topProductos, JSON_UNESCAPED_UNICODE) ?>;
    const productosCtx = document.getElementById('productosChart').getContext('2d');
    new Chart(productosCtx, {
      type: 'bar',
      data: {
        labels: productosData.map(p => p.nombre),
        datasets: [{
          label: 'Cantidad Vendida',
          data: productosData.map(p => p.cantidad_vendida),
          backgroundColor: 'rgba(6, 182, 212, 0.6)',
          borderColor: '#06b6d4',
          borderWidth: 2,
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { labels: { color: '#999' } } },
        scales: {
          x: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.1)' } },
          y: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.1)' } }
        }
      }
    });
  </script>
</body>
</html>
