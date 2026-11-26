const alertButton = document.querySelector('#open-stock-alerts');
const alertDialog = document.querySelector('#stock-alert-modal');
const alertList = document.querySelector('#critical-products-list');
const alertBadge = document.querySelector('#stock-alert-badge');
const alertToast = document.querySelector('#stock-alert-toast');
let alertToastTimer;

function escapeAlertText(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[character]);
}

function productImagePath(path) {
  const image = String(path || '');
  if (/^https?:\/\//i.test(image)) return image;
  if (image.startsWith('public/img/productos/')) return `../${image}`;
  return '';
}

function updateAlertCount(count) {
  const total = Math.max(0, Number(count) || 0);
  alertBadge.textContent = String(total);
  alertBadge.setAttribute('aria-label', `${total} productos con stock crítico`);
  alertButton.classList.toggle('has-critical-stock', total > 0);
  document.querySelector('#critical-products-count').textContent = total
    ? `${total} producto${total === 1 ? '' : 's'} requieren atención`
    : 'No hay productos con stock crítico';
}

function showAlertToast(message, error = false) {
  alertToast.textContent = message;
  alertToast.classList.toggle('error', error);
  alertToast.classList.add('visible');
  clearTimeout(alertToastTimer);
  alertToastTimer = setTimeout(() => alertToast.classList.remove('visible'), 3000);
}

function renderCriticalProducts(products) {
  updateAlertCount(products.length);
  if (!products.length) {
    alertList.innerHTML = '<tr><td colspan="4" class="critical-loading">Sin productos con stock crítico. ¡Todo está abastecido!</td></tr>';
    return;
  }

  alertList.innerHTML = products.map((product) => {
    const imagePath = productImagePath(product.image);
    const photo = imagePath
      ? `<img class="critical-product-image" src="${escapeAlertText(imagePath)}" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'critical-product-placeholder',textContent:'▦'}))">`
      : '<span class="critical-product-placeholder">▦</span>';
    const action = product.pending_order
      ? `<span class="order-pending">En camino · Orden #${escapeAlertText(product.pending_order)} · ${escapeAlertText(product.pending_quantity)} pzas.</span>`
      : `<button class="reorder-button" type="button" data-reorder="${escapeAlertText(product.id)}" data-name="${escapeAlertText(product.name)}">Pedir y avisar al vendedor</button>`;
    return `<tr data-critical-row="${escapeAlertText(product.id)}">
      <td><div class="critical-product">${photo}<span><strong>${escapeAlertText(product.name)}</strong><small>ID ${escapeAlertText(product.id)}</small></span></div></td>
      <td><span class="critical-stock">${escapeAlertText(product.stock)}</span></td>
      <td>${escapeAlertText(product.minimum)}</td>
      <td>${action}</td>
    </tr>`;
  }).join('');
}

async function loadCriticalProducts() {
  alertList.innerHTML = '<tr><td colspan="4" class="critical-loading">Cargando alertas...</td></tr>';
  try {
    const response = await fetch('../api/stock-alerts.php', {
      headers: { Accept: 'application/json' },
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudieron cargar las alertas.');
    renderCriticalProducts(result.products || []);
  } catch (error) {
    alertList.innerHTML = `<tr><td colspan="4" class="critical-error">${escapeAlertText(error.message || 'No se pudieron cargar las alertas.')}</td></tr>`;
  }
}

window.refreshStockAlerts = loadCriticalProducts;

alertButton.addEventListener('click', () => {
  alertDialog.showModal();
  loadCriticalProducts();
});

document.querySelectorAll('[data-close-stock-alerts]').forEach((button) => {
  button.addEventListener('click', () => alertDialog.close());
});

alertDialog.addEventListener('click', (event) => {
  if (event.target === alertDialog) alertDialog.close();
});

alertList.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-reorder]');
  if (!button) return;
  button.disabled = true;
  const previousText = button.textContent;
  button.textContent = 'Enviando pedido...';
  const data = new FormData();
  data.set('product_id', button.dataset.reorder);

  try {
    const response = await fetch('../api/stock-alerts.php', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': window.omniStockAlerts.csrf,
      },
      body: data,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo generar la orden.');
    showAlertToast(`${result.message} Orden #${result.order_id}, cantidad solicitada: ${result.quantity}.`);
    await loadCriticalProducts();
  } catch (error) {
    button.disabled = false;
    button.textContent = previousText;
    showAlertToast(error.message || 'No se pudo generar la orden.', true);
  }
});

updateAlertCount(window.omniStockAlerts?.count || 0);
