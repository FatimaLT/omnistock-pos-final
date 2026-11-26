(() => {
  const state = document.querySelector('#summary-state');
  const error = document.querySelector('#summary-error');
  const metrics = document.querySelector('#summary-metrics');
  const insights = document.querySelector('#summary-insights');
  const branchRows = document.querySelector('#branch-rows');
  const refresh = document.querySelector('#refresh-summary');
  if (!state || !error || !metrics || !insights || !branchRows) return;

  const basePath = document.querySelector('meta[name="app-base-path"]')?.content.replace(/\/+$/, '') || '';
  const csrf = window.omniStockSummary?.csrf || '';
  const escape = (value) => String(value).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  })[character]);
  const money = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value) || 0);

  const load = async () => {
    state.hidden = false;
    state.innerHTML = '<span class="loading-spinner" aria-hidden="true"></span> Consultando métricas del sistema…';
    error.hidden = true;
    refresh.disabled = true;
    try {
      const response = await fetch(`${basePath}/api/branch-summary.php`, {
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf },
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'No se pudo cargar el resumen.');
      const data = payload.metrics;
      const cards = [
        ['INGRESOS · 30 DÍAS', money(data.revenue), `${data.sale_count} ventas registradas`],
        ['TICKET PROMEDIO', money(data.average_ticket), 'MXN por venta'],
        ['PRODUCTOS EN CATÁLOGO', data.product_count, `${data.stock_units} unidades disponibles`],
        ['STOCK BAJO', data.low_stock_count, `${money(data.stock_value)} valor estimado del inventario`],
        ['SUCURSALES', data.branch_count, 'Con ubicación configurada'],
      ];
      metrics.innerHTML = cards.map(([label, value, caption]) =>
        `<article class="summary-metric"><small>${escape(label)}</small><strong>${escape(value)}</strong><span>${escape(caption)}</span></article>`
      ).join('');
      metrics.hidden = false;
      insights.innerHTML = payload.insights.map((item) => `<li>${escape(item)}</li>`).join('');
      branchRows.innerHTML = payload.branches.length
        ? payload.branches.map((branch) => `<tr><td>${escape(branch.name)}</td><td>${escape(branch.address)}<br><small>${escape(branch.location)}</small></td><td><span class="branch-status">${escape(branch.status)}</span></td></tr>`).join('')
        : '<tr><td colspan="3" class="branch-empty">No hay sucursales configuradas.</td></tr>';
      state.textContent = `Resumen generado ${new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(payload.generated_at))}. Análisis simulado basado en datos locales.`;
      state.classList.remove('error');
    } catch (exception) {
      state.hidden = true;
      error.textContent = exception.message || 'No fue posible calcular las métricas.';
      error.hidden = false;
    } finally {
      refresh.disabled = false;
    }
  };

  refresh.addEventListener('click', load);
  load();
})();
