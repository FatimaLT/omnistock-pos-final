<section class="feature-card currency-rates-card" aria-labelledby="currency-rates-title">
  <div class="feature-card-heading">
    <div><p class="eyebrow">AJUSTES DEL POS</p><h2 id="currency-rates-title">Tasas de referencia</h2></div>
    <span aria-hidden="true">⇄</span>
  </div>
  <p class="currency-admin-note">MXN por cada unidad de moneda. Valores de demostración hasta que el administrador los ajuste; no son cotizaciones en tiempo real ni se usan para cobrar.</p>
  <form id="currency-rates-form" class="currency-rates-form">
    <label class="feature-field" for="rate-usd">Dólar estadounidense (USD)
      <input id="rate-usd" name="USD" type="number" min="0.0001" max="999999.999999" step="0.0001" required inputmode="decimal">
    </label>
    <label class="feature-field" for="rate-eur">Euro (EUR)
      <input id="rate-eur" name="EUR" type="number" min="0.0001" max="999999.999999" step="0.0001" required inputmode="decimal">
    </label>
    <label class="feature-field" for="rate-gbp">Libra esterlina (GBP)
      <input id="rate-gbp" name="GBP" type="number" min="0.0001" max="999999.999999" step="0.0001" required inputmode="decimal">
    </label>
    <button class="primary-button" id="save-currency-rates" type="submit">Guardar tasas</button>
  </form>
  <p class="currency-admin-status" id="currency-admin-status" role="status" aria-live="polite">Cargando tasas…</p>
</section>
