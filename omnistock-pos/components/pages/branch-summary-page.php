<?php
declare(strict_types=1);
$featureTitle = 'Resumen de sucursales';
$activeFeature = 'summary';
require __DIR__ . '/../templates/feature-layout-start.php';
?>
<section class="feature-heading summary-heading"><div><p class="eyebrow">PANEL ADMINISTRATIVO</p><h1>Resumen de sucursales</h1><p>Indicadores de ventas e inventario; el análisis narrativo es una simulación local, no un servicio de IA externo.</p></div><button class="secondary-button" id="refresh-summary" type="button">Actualizar métricas</button></section>
<?php require __DIR__ . '/../organisms/branch-summary.php'; ?>
<?php require __DIR__ . '/../organisms/currency-rates.php'; ?>
<script>window.omniStockSummary = { csrf: <?= json_encode($summaryCsrf) ?> }; window.omniStockCurrencyAdmin = { csrf: <?= json_encode($currencyCsrf) ?> };</script>
<script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/atomic/organisms/branch-summary.js" defer></script>
<script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/atomic/organisms/currency-rates-admin.js" defer></script>
<?php require __DIR__ . '/../templates/feature-layout-end.php'; ?>
