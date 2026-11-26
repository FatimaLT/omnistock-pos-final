<?php
declare(strict_types=1);
$featureTitle = 'Reseñas';
$featureLabel = 'Satisfacción de clientes';
$activeFeature = 'reviews';
require __DIR__ . '/../templates/feature-layout-start.php';
?>
<section class="feature-heading"><div><p class="eyebrow">EXPERIENCIA OMNISTOCK</p><h1>Reseñas</h1><p>Tu opinión nos ayuda a mejorar la atención en cada sucursal.</p></div></section>
<div class="reviews-layout">
  <?php require __DIR__ . '/../molecules/satisfaction-form.php'; ?>
  <?php require __DIR__ . '/../organisms/reviews-board.php'; ?>
</div>
<script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/components/molecules/review-card.js" defer></script>
<script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/atomic/pages/reviews-page.js" defer></script>
<?php require __DIR__ . '/../templates/feature-layout-end.php'; ?>
