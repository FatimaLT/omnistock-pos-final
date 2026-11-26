<header class="admin-topbar">
  <div><span>OmniStock</span><b>/</b><strong><?= $featureEscape($featureTitle) ?></strong></div>
  <div class="theme-header-actions">
    <a href="<?= $featureEscape($appBasePath) ?>/index.php" class="view-pos-link">Abrir POS <span aria-hidden="true">↗</span></a>
    <?php require __DIR__ . '/../atoms/theme-toggle.php'; ?>
  </div>
</header>
