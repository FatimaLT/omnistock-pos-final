<?php
declare(strict_types=1);
$featureTitle = 'Mapa de sucursales';
$activeFeature = 'map';
$branches = array_map(
    static fn(array $branch): array => [
        'name' => $branch['name'],
        'address' => $branch['address'],
        'lat' => (float) $branch['latitude'],
        'lng' => (float) $branch['longitude'],
    ],
    require __DIR__ . '/../../config/branches.php'
);
require __DIR__ . '/../templates/feature-layout-start.php';
?>
<section class="feature-heading"><div><p class="eyebrow">UBICACIONES</p><h1>Mapa de sucursales</h1><p>Explora las sucursales configuradas en OpenStreetMap.</p></div></section>
<?php require __DIR__ . '/../organisms/branch-map.php'; ?>
<script>window.omniStockBranches = <?= json_encode($branches, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/atomic/organisms/branch-map.js" defer></script>
<?php require __DIR__ . '/../templates/feature-layout-end.php'; ?>
