<button type="button" class="admin-nav-link stock-alert-trigger" id="open-stock-alerts" aria-haspopup="dialog" title="Atención: Productos con Stock Crítico">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4"/><path d="M12 5v4m0 3h.01"/></svg>
  <span>Atención: Productos con Stock Crítico</span>
  <span class="stock-alert-badge" id="stock-alert-badge" aria-label="<?= (int) $stockAlertCount ?> productos con stock crítico"><?= (int) $stockAlertCount ?></span>
</button>

<dialog class="admin-modal stock-alert-modal" id="stock-alert-modal">
  <section class="modal-card">
    <header class="modal-header">
      <div><span class="eyebrow">REABASTECIMIENTO</span><h2>Atención: Productos con Stock Crítico</h2></div>
      <button type="button" class="modal-close" data-close-stock-alerts aria-label="Cerrar">×</button>
    </header>
    <div class="stock-alert-summary"><span class="stock-alert-summary-icon">!</span><p>Productos con existencias iguales o inferiores a su mínimo configurado.</p></div>
    <div class="critical-products-wrap">
      <table class="critical-products-table">
        <thead><tr><th>PRODUCTO</th><th>STOCK</th><th>MÍNIMO</th><th>ACCIÓN</th></tr></thead>
        <tbody id="critical-products-list"><tr><td colspan="4" class="critical-loading">Cargando alertas...</td></tr></tbody>
      </table>
    </div>
    <footer class="modal-footer"><span class="critical-products-count" id="critical-products-count"></span><button type="button" class="secondary-button" data-close-stock-alerts>Cerrar</button></footer>
  </section>
</dialog>
<div class="admin-toast" id="stock-alert-toast" role="status" aria-live="polite"></div>
