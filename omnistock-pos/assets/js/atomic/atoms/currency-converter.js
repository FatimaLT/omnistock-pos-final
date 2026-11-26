(() => {
  const amountInput = document.querySelector('#currency-amount');
  const currencyInput = document.querySelector('#currency-from');
  const result = document.querySelector('#currency-result');
  const status = document.querySelector('#currency-note');
  if (!amountInput || !currencyInput || !result || !status) return;

  const basePath = document.querySelector('meta[name="app-base-path"]')?.content.replace(/\/+$/, '') || '';
  let rates = null;
  const mxnFormat = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

  const render = () => {
    const amount = Number(amountInput.value);
    if (!Number.isFinite(amount) || amount < 0 || !rates) {
      result.textContent = rates ? 'Ingresa un importe válido.' : 'Tasas no disponibles.';
      return;
    }
    const rate = rates[currencyInput.value];
    if (!Number.isFinite(rate)) {
      result.textContent = 'Moneda no disponible.';
      return;
    }
    result.textContent = `${amount.toLocaleString('es-MX')} ${currencyInput.value} ≈ ${mxnFormat.format(amount * rate)}`;
  };

  amountInput.addEventListener('input', render);
  currencyInput.addEventListener('change', render);
  fetch(`${basePath}/api/exchange-rates.php`, { headers: { Accept: 'application/json' } })
    .then(async (response) => {
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'No se pudieron cargar las tasas.');
      if (payload.base !== 'MXN' || !payload.rates || typeof payload.rates !== 'object') {
        throw new Error('El servicio de divisas devolvió datos no válidos.');
      }
      rates = { MXN: 1, ...payload.rates };
      render();
      status.textContent = 'Tasas de demostración, no actualizadas en tiempo real; no se usan para cobrar.';
    })
    .catch((error) => {
      result.textContent = 'Conversión no disponible.';
      status.textContent = error.message || 'No fue posible consultar las tasas.';
      status.classList.add('error');
    });
})();
