(() => {
  const form = document.querySelector('#currency-rates-form');
  const status = document.querySelector('#currency-admin-status');
  const submit = document.querySelector('#save-currency-rates');
  if (!form || !status || !submit) return;

  const basePath = document.querySelector('meta[name="app-base-path"]')?.content.replace(/\/+$/, '') || '';
  const csrf = window.omniStockCurrencyAdmin?.csrf || '';
  const fields = ['USD', 'EUR', 'GBP'].map((code) => form.elements.namedItem(code));

  const request = async (method, rates = null) => {
    const response = await fetch(`${basePath}/api/exchange-rates.php`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(method === 'POST' ? { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf } : {}),
      },
      ...(method === 'POST' ? { body: JSON.stringify({ rates }) } : {}),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'No se pudieron consultar las tasas.');
    return payload;
  };

  const load = async () => {
    status.textContent = 'Cargando tasas…';
    status.classList.remove('error');
    try {
      const payload = await request('GET');
      fields.forEach((field) => {
        const rate = Number(payload.rates?.[field.name]);
        if (!Number.isFinite(rate) || rate <= 0) throw new Error(`Tasa no válida para ${field.name}.`);
        field.value = rate.toFixed(4);
      });
      status.textContent = 'Tasas de referencia cargadas. Usa un valor MXN por cada unidad de moneda.';
    } catch (error) {
      status.textContent = error.message || 'No se pudieron cargar las tasas.';
      status.classList.add('error');
    }
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    submit.disabled = true;
    status.textContent = 'Guardando tasas…';
    status.classList.remove('error');
    const rates = Object.fromEntries(fields.map((field) => [field.name, Number(field.value)]));
    try {
      const payload = await request('POST', rates);
      fields.forEach((field) => { field.value = Number(payload.rates[field.name]).toFixed(4); });
      status.textContent = 'Tasas guardadas. Se aplican solo al conversor indicativo del POS; no cambian los importes de venta.';
    } catch (error) {
      status.textContent = error.message || 'No se pudieron guardar las tasas.';
      status.classList.add('error');
    } finally {
      submit.disabled = false;
    }
  });

  load();
})();
