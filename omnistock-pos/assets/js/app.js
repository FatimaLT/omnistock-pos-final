const state = {
  products: [],
  categories: [],
  cart: new Map(),
  category: 'all',
  search: '',
  restockRequests: new Set(),
};

const appBasePath = document.querySelector('meta[name="app-base-path"]')?.content.replace(/\/+$/, '') || '';

const elements = {
  grid: document.querySelector('#product-grid'),
  catalogStatus: document.querySelector('#catalog-status'),
  categoryRow: document.querySelector('#category-row'),
  count: document.querySelector('#product-count'),
  search: document.querySelector('#search-input'),
  cart: document.querySelector('#cart-items'),
  subtotal: document.querySelector('#subtotal'),
  tax: document.querySelector('#tax-amount'),
  total: document.querySelector('#grand-total'),
  checkoutTotal: document.querySelector('#checkout-total'),
  checkout: document.querySelector('#checkout-button'),
  taxRate: document.querySelector('#tax-rate'),
  toast: document.querySelector('#toast'),
  checkoutModal: document.querySelector('#checkout-modal'),
  checkoutForm: document.querySelector('#checkout-form'),
  transferWaitModal: document.querySelector('#transfer-wait-modal'),
  transferWaitTotal: document.querySelector('#transfer-wait-total'),
  transferWaitClabe: document.querySelector('#transfer-wait-clabe'),
  transferWaitReference: document.querySelector('#transfer-reference'),
  transferWaitError: document.querySelector('#transfer-wait-error'),
  transferCountdown: document.querySelector('#transfer-countdown'),
  transferCountdownStatus: document.querySelector('#transfer-countdown-status'),
  transferCountdownTime: document.querySelector('#transfer-countdown-time'),
  startTransferCountdown: document.querySelector('#start-transfer-countdown'),
  cancelTransfer: document.querySelector('#cancel-transfer'),
  paymentMethod: document.querySelector('#payment-method'),
  cardPin: document.querySelector('#card-pin'),
  cardPinField: document.querySelector('#card-pin-field'),
  cardPaymentNote: document.querySelector('#card-payment-note'),
  cardProcessing: document.querySelector('#card-processing'),
  cashReceived: document.querySelector('#cash-received'),
  cashField: document.querySelector('#cash-field'),
  transferPanel: document.querySelector('#transfer-panel'),
  changeLine: document.querySelector('#change-line'),
  changeAmount: document.querySelector('#change-amount'),
  checkoutError: document.querySelector('#checkout-error'),
  confirmSale: document.querySelector('#confirm-sale'),
  historyModal: document.querySelector('#history-modal'),
  historyList: document.querySelector('#history-list'),
};
let transferCountdownTimer = null;
let cardPaymentInProgress = false;

const currency = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
});

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[character]);
}

function safeImageUrl(value) {
  if (!value) return '';
  const path = String(value).trim();
  if (/^https?:\/\//i.test(path)) {
    return path;
  }
  if (/^\/?(?:public|assets)\/img\/productos\/[a-zA-Z0-9._-]+\.(?:png|jpe?g|webp)$/i.test(path) && !path.includes('..')) {
    return `${appBasePath}/${path.replace(/^\/+/, '')}`;
  }
  return '';
}

function productImageFallback(name) {
  const safeName = escapeHtml(name || 'Producto');
  return `<div class="product-image-fallback" role="img" aria-label="Imagen no disponible: ${safeName}">
    <svg viewBox="0 0 32 32" aria-hidden="true"><rect x="4" y="5" width="24" height="22" rx="5"/><circle cx="12" cy="13" r="2"/><path d="m7 23 7-7 4 4 3-3 5 6"/></svg>
    <span>${safeName}</span>
  </div>`;
}

function showProductImageFallback(image) {
  image.onerror = null;
  image.outerHTML = productImageFallback(image.dataset.productName || image.alt);
}

function productImage(product) {
  const image = safeImageUrl(product.image);
  if (!image) return productImageFallback(product.name);
  return `<img src="${escapeHtml(image)}" data-product-name="${escapeHtml(product.name)}" alt="${escapeHtml(product.name)}" loading="lazy" onerror="showProductImageFallback(this)">`;
}

function setCatalogMessage(message, type = 'loading') {
  elements.catalogStatus.className = `catalog-status ${type}`;
  elements.catalogStatus.innerHTML = type === 'loading'
    ? `<span class="loading-spinner"></span> ${escapeHtml(message)}`
    : escapeHtml(message);
}

async function loadProducts() {
  setCatalogMessage('Cargando catálogo...');
  try {
    const response = await fetch(`${appBasePath}/api/products.php`, { headers: { Accept: 'application/json' } });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo cargar el catálogo.');
    state.products = Array.isArray(result.products) ? result.products : [];
    state.categories = Array.isArray(result.categories) ? result.categories : [];
    updateInventoryAlert();
    renderCategories();
    renderProducts();
    if (state.products.length === 0) setCatalogMessage('No hay productos con nombre y precio disponibles.', 'empty');
    else elements.catalogStatus.hidden = true;
  } catch (error) {
    setCatalogMessage(error.message || 'Ocurrió un error al cargar los productos.', 'error');
    elements.grid.innerHTML = '';
  }
}

function renderCategories() {
  const categoryRows = state.categories.length
    ? state.categories
    : [...new Map(state.products.map((product) => {
      const name = product.category?.trim() || 'General';
      return [String(product.category_id ?? name), { id: String(product.category_id ?? name), name, count: 0 }];
    })).values()];
  if (!state.categories.length) {
    categoryRows.forEach((category) => {
      category.count = state.products.filter((product) => String(product.category_id ?? product.category ?? 'General') === category.id).length;
    });
  }
  const categoryOrder = ['Bebidas', 'Botanas', 'Galletas', 'Abarrotes'];
  categoryRows.sort((a, b) => {
    const aIndex = categoryOrder.findIndex((item) => item.localeCompare(a.name, 'es', { sensitivity: 'base' }) === 0);
    const bIndex = categoryOrder.findIndex((item) => item.localeCompare(b.name, 'es', { sensitivity: 'base' }) === 0);
    if (aIndex === -1 && bIndex === -1) return a.name.localeCompare(b.name, 'es');
    if (aIndex === -1) return 1;
    if (bIndex === -1) return -1;
    if (aIndex !== bIndex) return aIndex - bIndex;
    return a.name.localeCompare(b.name, 'es');
  });
  const activeCategory = categoryRows.some((category) => String(category.id) === state.category) || state.category === 'all';
  if (!activeCategory) state.category = 'all';
  elements.categoryRow.innerHTML = [
    `<button class="category-pill${state.category === 'all' ? ' selected' : ''}" type="button" aria-pressed="${state.category === 'all'}" data-category="all">Todos <span>${state.products.length}</span></button>`,
    ...categoryRows.map((category) => {
      const selected = state.category === String(category.id);
      return `<button class="category-pill${selected ? ' selected' : ''}" type="button" aria-pressed="${selected}" data-category="${escapeHtml(category.id)}">${escapeHtml(category.name)} <span>${Number(category.count) || 0}</span></button>`;
    }),
  ].join('');
  elements.count.textContent = String(state.products.length);
}

function renderProducts() {
  const search = state.search.toLocaleLowerCase('es');
  const filtered = state.products.filter((product) => {
    const matchesCategory = state.category === 'all' || String(product.category_id ?? product.category ?? 'General') === state.category;
    const matchesSearch = `${product.name} ${product.code || ''}`.toLocaleLowerCase('es').includes(search);
    return matchesCategory && matchesSearch;
  });

  if (!filtered.length) {
    elements.grid.innerHTML = '';
    setCatalogMessage(state.products.length ? 'No encontramos productos con esa búsqueda.' : 'No hay productos disponibles.', 'empty');
    return;
  }

  elements.catalogStatus.hidden = true;
  elements.grid.innerHTML = filtered.map((product) => {
    const outOfStock = product.stock !== null && product.stock <= 0;
    const lowStock = product.stock !== null && product.stock <= Number(product.stock_minimum ?? 5);
    const stockText = product.stock === null ? 'Disponible' : (outOfStock ? 'Agotado' : `${product.stock} en stock`);
    return `
      <article class="product-card${outOfStock ? ' out-of-stock' : ''}">
        <div class="product-image">${productImage(product)}
          <span class="stock-badge${outOfStock ? ' stock-empty' : ''}"><i></i>${escapeHtml(stockText)}</span>
          ${product.code ? `<span class="product-code">${escapeHtml(product.code)}</span>` : ''}
        </div>
        <div class="product-info">
          <span class="product-category">${escapeHtml(product.category || 'General')}</span>
          ${lowStock ? `<span class="low-stock-badge">${window.omniStockPOS.role === 'vendedor' ? 'Poco Stock' : (outOfStock ? 'Agotado · requiere reabastecimiento' : 'Stock bajo · mínimo ' + escapeHtml(product.stock_minimum ?? 5))}</span>` : ''}
          <h3 title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</h3>
          ${product.description ? `<p class="product-description">${escapeHtml(product.description)}</p>` : ''}
          <div class="product-bottom"><strong>${currency.format(Number(product.price) || 0)}</strong>
            <button class="add-button" type="button" data-add="${escapeHtml(product.id)}" ${outOfStock ? 'disabled' : ''} aria-label="Agregar ${escapeHtml(product.name)} al carrito">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m-7-7h14"/></svg><span>Agregar al carrito</span>
            </button>
          </div>
          ${lowStock && window.omniStockPOS.role === 'vendedor'
            ? `<button class="restock-request-button" type="button" data-restock-request="${escapeHtml(product.id)}" ${state.restockRequests.has(String(product.id)) ? 'disabled' : ''}>${state.restockRequests.has(String(product.id)) ? 'Solicitud enviada' : 'Solicitar al Admin'}</button>`
            : ''}
        </div>
      </article>`;
  }).join('');
}

function updateInventoryAlert() {
  const criticalCount = state.products.filter((product) =>
    product.stock !== null && Number(product.stock) <= Number(product.stock_minimum ?? 5)
  ).length;
  const button = document.querySelector('#inventory-alert-button');
  button.hidden = criticalCount === 0;
  document.querySelector('#inventory-alert-count').textContent = String(criticalCount);
  button.setAttribute('aria-label', `${criticalCount} productos con stock crítico`);
}

async function requestProductRestock(button) {
  const productId = String(button.dataset.restockRequest);
  const originalLabel = button.textContent;
  button.disabled = true;
  button.textContent = 'Enviando solicitud...';
  const data = new FormData();
  data.set('action', 'restock_request');
  data.set('product_id', productId);

  try {
    const response = await fetch(`${appBasePath}/enviar_mensaje.php`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': window.omniStockPOS.messagesCsrf,
      },
      body: data,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo enviar la solicitud.');
    state.restockRequests.add(productId);
    button.textContent = 'Solicitud enviada';
    showToast(result.message || 'Solicitud enviada al administrador.');
  } catch (error) {
    button.disabled = false;
    button.textContent = originalLabel;
    showToast(error.message || 'No se pudo enviar la solicitud.');
  }
}

function addToCart(productId) {
  const product = state.products.find((item) => item.id === productId);
  if (!product) return;
  const existing = state.cart.get(productId);
  if (existing && product.stock !== null && existing.quantity >= product.stock) {
    showToast('No hay más unidades disponibles.');
    return;
  }
  state.cart.set(productId, { product, quantity: (existing?.quantity || 0) + 1 });
  renderCart();
}

function changeQuantity(productId, change) {
  const item = state.cart.get(productId);
  if (!item) return;
  const quantity = item.quantity + change;
  if (quantity <= 0) state.cart.delete(productId);
  else if (item.product.stock === null || quantity <= item.product.stock) {
    state.cart.set(productId, { ...item, quantity });
  } else {
    showToast('La cantidad supera las existencias disponibles.');
  }
  renderCart();
}

function renderCart() {
  const items = [...state.cart.entries()];
  if (!items.length) {
    elements.cart.innerHTML = `
      <div class="empty-cart">
        <div class="empty-cart-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 12.2a2 2 0 0 0 2 1.8h8.6a2 2 0 0 0 2-1.6L21 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg></div>
        <strong>Tu ticket está vacío</strong><span>Agrega productos desde el catálogo</span>
      </div>`;
  } else {
    elements.cart.innerHTML = items.map(([id, item]) => `
      <div class="cart-item">
        <div class="cart-item-info"><strong title="${escapeHtml(item.product.name)}">${escapeHtml(item.product.name)}</strong><small>${currency.format(Number(item.product.price) || 0)} c/u</small></div>
        <div class="quantity-control">
          <button type="button" data-quantity="${escapeHtml(id)}" data-change="-1" aria-label="Quitar una unidad">−</button>
          <span>${item.quantity}</span>
          <button type="button" data-quantity="${escapeHtml(id)}" data-change="1" aria-label="Agregar una unidad">+</button>
        </div>
        <strong class="cart-line-total">${currency.format(item.quantity * (Number(item.product.price) || 0))}</strong>
      </div>`).join('');
  }

  const subtotal = items.reduce((sum, [, item]) => sum + item.quantity * (Number(item.product.price) || 0), 0);
  const rate = Math.min(100, Math.max(0, Number(elements.taxRate.value) || 0)) / 100;
  const tax = subtotal * rate;
  const total = subtotal + tax;
  elements.subtotal.textContent = currency.format(subtotal);
  elements.tax.textContent = currency.format(tax);
  elements.total.textContent = currency.format(total);
  elements.checkoutTotal.textContent = currency.format(total);
  elements.checkout.disabled = items.length === 0;
  document.querySelector('#checkout-button').setAttribute('aria-label', `Completar venta por ${currency.format(total)}`);
}

let toastTimeout;
function showToast(message) {
  elements.toast.textContent = message;
  elements.toast.classList.add('visible');
  clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => elements.toast.classList.remove('visible'), 2600);
}

function updateClock() {
  const now = new Date();
  document.querySelector('#clock').textContent = new Intl.DateTimeFormat('es-MX', {
    hour: '2-digit', minute: '2-digit',
  }).format(now);
  document.querySelector('#today').textContent = new Intl.DateTimeFormat('es-MX', {
    weekday: 'short', day: '2-digit', month: 'short', year: 'numeric',
  }).format(now);
}

function modalTotals() {
  const subtotal = [...state.cart.values()].reduce(
    (sum, item) => sum + Math.round((Number(item.product.price) || 0) * 100) * item.quantity,
    0,
  );
  const rate = Math.min(100, Math.max(0, Number(elements.taxRate.value) || 0));
  const tax = Math.round(subtotal * rate / 100);
  return { subtotal, tax, total: subtotal + tax, rate };
}

function updateCashChange() {
  const { total } = modalTotals();
  const received = Math.round((Number(elements.cashReceived.value) || 0) * 100);
  const change = Math.max(0, received - total);
  elements.changeAmount.textContent = currency.format(change / 100);
  elements.changeLine.hidden = elements.paymentMethod.value !== 'efectivo';
}

function updatePaymentFields() {
  const isCash = elements.paymentMethod.value === 'efectivo';
  const isCard = elements.paymentMethod.value === 'tarjeta';
  const isTransfer = elements.paymentMethod.value === 'transferencia';
  elements.cashField.hidden = !isCash;
  elements.cashReceived.required = isCash;
  elements.cardPinField.hidden = !isCard;
  elements.cardPaymentNote.hidden = !isCard;
  elements.cardPin.required = isCard;
  if (!isCard) elements.cardPin.value = '';
  elements.transferPanel.hidden = !isTransfer;
  updateCashChange();
}

function openTransferScreen() {
  elements.transferWaitTotal.textContent = currency.format(modalTotals().total / 100);
  elements.transferWaitReference.value = elements.transferWaitReference.dataset.defaultReference || '';
  elements.transferWaitReference.disabled = false;
  elements.transferWaitReference.required = true;
  elements.transferWaitError.textContent = '';
  elements.transferCountdown.hidden = true;
  elements.transferCountdownStatus.textContent = 'Después de confirmar, el ticket estará disponible en';
  elements.transferCountdownTime.textContent = '00:30';
  elements.startTransferCountdown.disabled = false;
  elements.startTransferCountdown.hidden = false;
  elements.cancelTransfer.disabled = false;
  elements.checkoutModal.close();
  elements.transferWaitModal.showModal();
  elements.transferWaitReference.focus();
}

function cancelTransferScreen() {
  if (transferCountdownTimer !== null) {
    clearInterval(transferCountdownTimer);
    transferCountdownTimer = null;
  }
  elements.transferWaitModal.close();
  elements.transferWaitReference.disabled = true;
  elements.transferWaitReference.required = false;
  elements.paymentMethod.value = 'efectivo';
  updatePaymentFields();
  elements.checkoutError.textContent = '';
  elements.checkoutModal.showModal();
}

function startTransferCountdown() {
  const reference = elements.transferWaitReference.value.trim();
  if (!reference || reference.length > 50) {
    elements.transferWaitError.textContent = 'Ingresa una referencia válida de hasta 50 caracteres.';
    elements.transferWaitReference.focus();
    return;
  }

  let salePayload;
  try {
    salePayload = collectCartPayload();
  } catch (error) {
    elements.transferWaitError.textContent = error.message || 'Revisa los productos antes de continuar.';
    return;
  }

  elements.transferWaitError.textContent = '';
  elements.transferCountdown.hidden = false;
  elements.transferCountdownStatus.textContent = 'El ticket se generará automáticamente en';
  elements.startTransferCountdown.disabled = true;
  elements.startTransferCountdown.hidden = true;
  const deadline = Date.now() + 30_000;

  const updateCountdown = () => {
    const secondsLeft = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    const minutes = Math.floor(secondsLeft / 60);
    const seconds = secondsLeft % 60;
    elements.transferCountdownTime.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    if (secondsLeft === 0) {
      clearInterval(transferCountdownTimer);
      transferCountdownTimer = null;
      elements.transferCountdownStatus.textContent = 'Tiempo terminado. Registrando la venta y preparando el ticket...';
      elements.cancelTransfer.disabled = true;
      completeSale(salePayload, true);
    }
  };

  updateCountdown();
  transferCountdownTimer = setInterval(updateCountdown, 250);
}

function collectCartPayload() {
  if (state.cart.size === 0) {
    throw new Error('Agrega al menos un producto antes de cobrar.');
  }

  const items = [...state.cart.entries()].map(([id, item]) => {
    const quantity = Number(item.quantity);
    const unitPrice = Number(item.product.price);
    const stock = item.product.stock === null ? null : Number(item.product.stock);

    if (!Number.isInteger(quantity) || quantity < 1) {
      throw new Error(`La cantidad de ${item.product.name} no es válida.`);
    }
    if (stock !== null && (!Number.isFinite(stock) || quantity > stock)) {
      throw new Error(`La cantidad de ${item.product.name} supera las existencias disponibles.`);
    }
    if (!Number.isFinite(unitPrice) || unitPrice < 0) {
      throw new Error(`El precio de ${item.product.name} no es válido.`);
    }

    return {
      id,
      name: item.product.name,
      quantity,
      unit_price: Math.round(unitPrice * 100) / 100,
    };
  });

  const transferReference = elements.transferWaitReference.value.trim();
  if (elements.paymentMethod.value === 'transferencia' && !transferReference) {
    throw new Error('Ingresa el número de referencia o folio de la transferencia.');
  }

  return {
    items,
    total: modalTotals().total / 100,
    tax_rate: Number(elements.taxRate.value),
    payment_method: elements.paymentMethod.value,
    cash_received: elements.paymentMethod.value === 'efectivo' ? elements.cashReceived.value : null,
    transfer_reference: elements.paymentMethod.value === 'transferencia' ? transferReference : null,
  };
}

async function completeSale(salePayload = null, transferFlow = false, receiptPopup = null) {
  if (salePayload === null) {
    try {
      salePayload = collectCartPayload();
    } catch (error) {
      elements.checkoutError.textContent = error.message || 'Revisa los productos antes de cobrar.';
      return;
    }
  }

  const popup = transferFlow ? null : (receiptPopup || window.open('about:blank', '_blank'));
  elements.confirmSale.disabled = true;
  elements.confirmSale.textContent = 'Procesando venta...';
  elements.checkoutError.textContent = '';
  try {
    const response = await fetch(`${appBasePath}/procesar_venta.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': window.omniStockPOS.csrf,
      },
      body: JSON.stringify(salePayload),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo completar la venta.');

    for (const [id, item] of state.cart) {
      const current = state.products.find((product) => product.id === id);
      if (current && current.stock !== null) current.stock = Math.max(0, Number(current.stock) - item.quantity);
    }
    state.cart.clear();
    renderCart();
    renderProducts();
    updateInventoryAlert();
    elements.checkoutModal.close();
    if (transferFlow) {
      elements.transferWaitReference.disabled = true;
      elements.transferWaitReference.required = false;
      elements.transferWaitModal.close();
    }
    showToast(`Venta #${result.sale_id} completada correctamente.`);
    loadHistory();

    const receiptUrl = `${appBasePath}/ticket.php?id=${encodeURIComponent(result.sale_id)}`;
    if (popup && !popup.closed) popup.location.href = receiptUrl;
    else window.location.href = receiptUrl;
  } catch (error) {
    if (popup && !popup.closed) popup.close();
    if (transferFlow) {
      elements.transferWaitModal.close();
      elements.checkoutError.textContent = error.message || 'No se pudo completar la venta.';
      elements.checkoutModal.showModal();
    } else {
      elements.checkoutError.textContent = error.message || 'No se pudo completar la venta.';
    }
    loadProducts();
  } finally {
    elements.confirmSale.disabled = false;
    elements.confirmSale.innerHTML = 'Confirmar cobro <span>→</span>';
  }
}

async function processCardPayment() {
  const pin = elements.cardPin.value;
  if (!/^\d{4}$/.test(pin)) {
    elements.checkoutError.textContent = 'Ingresa un PIN de exactamente 4 dígitos.';
    elements.cardPin.focus();
    return;
  }

  let salePayload;
  try {
    salePayload = collectCartPayload();
  } catch (error) {
    elements.checkoutError.textContent = error.message || 'Revisa los productos antes de continuar.';
    return;
  }

  const receiptPopup = window.open('about:blank', '_blank');
  if (receiptPopup) {
    receiptPopup.document.title = 'OmniStock · Pago con tarjeta';
    receiptPopup.document.body.textContent = 'Procesando pago con tarjeta...';
  }
  cardPaymentInProgress = true;
  elements.cardProcessing.hidden = false;
  elements.paymentMethod.disabled = true;
  elements.cardPin.disabled = true;
  elements.confirmSale.disabled = true;
  elements.confirmSale.textContent = 'Procesando pago...';
  elements.checkoutError.textContent = '';
  document.querySelectorAll('#checkout-form [data-close-pos-modal], #checkout-form .checkout-cancel')
    .forEach((button) => { button.disabled = true; });

  try {
    await new Promise((resolve) => window.setTimeout(resolve, 5000));
    await completeSale(salePayload, false, receiptPopup);
  } finally {
    cardPaymentInProgress = false;
    elements.cardProcessing.hidden = true;
    elements.paymentMethod.disabled = false;
    elements.cardPin.disabled = false;
    elements.cardPin.value = '';
    document.querySelectorAll('#checkout-form [data-close-pos-modal], #checkout-form .checkout-cancel')
      .forEach((button) => { button.disabled = false; });
  }
}

async function loadHistory() {
  elements.historyList.innerHTML = '<p class="history-empty">Cargando ventas...</p>';
  try {
    const response = await fetch(`${appBasePath}/api/sales.php`, { headers: { Accept: 'application/json' } });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo cargar el historial.');
    document.querySelector('#history-count').textContent = String(result.count);
    document.querySelector('#history-total').textContent = currency.format(Number(result.total) || 0);
    if (!result.sales.length) {
      elements.historyList.innerHTML = '<p class="history-empty">Aún no tienes ventas registradas hoy.</p>';
      return;
    }
    elements.historyList.innerHTML = result.sales.map((sale) => `
      <a class="history-sale" href="${appBasePath}/ticket.php?id=${encodeURIComponent(sale.id)}" target="_blank" rel="noopener">
        <span class="history-sale-icon">#${escapeHtml(sale.id)}</span>
        <span class="history-sale-info"><strong>Venta #${escapeHtml(sale.id)}</strong><small>${escapeHtml(new Intl.DateTimeFormat('es-MX', { hour: '2-digit', minute: '2-digit' }).format(new Date(sale.fecha_venta.replace(' ', 'T'))))} · ${escapeHtml(sale.metodo_pago)}</small></span>
        <strong class="history-sale-total">${currency.format(Number(sale.total) || 0)}</strong>
        <span class="history-sale-arrow">↗</span>
      </a>`).join('');
  } catch (error) {
    elements.historyList.innerHTML = `<p class="history-error">${escapeHtml(error.message || 'No se pudo cargar el historial.')}</p>`;
  }
}

elements.search.addEventListener('input', (event) => {
  state.search = event.target.value.trim();
  renderProducts();
});

elements.categoryRow.addEventListener('click', (event) => {
  const button = event.target.closest('[data-category]');
  if (!button) return;
  state.category = button.dataset.category;
  renderCategories();
  renderProducts();
});

elements.grid.addEventListener('click', (event) => {
  const restockButton = event.target.closest('[data-restock-request]');
  if (restockButton) {
    requestProductRestock(restockButton);
    return;
  }
  const button = event.target.closest('[data-add]');
  if (button) addToCart(button.dataset.add);
});

document.querySelector('#inventory-alert-button').addEventListener('click', () => {
  const firstCritical = [...document.querySelectorAll('.product-card')].find((card) =>
    card.querySelector('.low-stock-badge')
  );
  firstCritical?.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

elements.cart.addEventListener('click', (event) => {
  const button = event.target.closest('[data-quantity]');
  if (button) changeQuantity(button.dataset.quantity, Number(button.dataset.change));
});

elements.taxRate.addEventListener('input', renderCart);
document.querySelector('#clear-cart').addEventListener('click', () => {
  if (state.cart.size) {
    state.cart.clear();
    renderCart();
    showToast('Ticket vaciado.');
  }
});
document.querySelector('#refresh-button').addEventListener('click', loadProducts);
elements.checkout.addEventListener('click', () => {
  if (!state.cart.size) return;
  elements.checkoutError.textContent = '';
  document.querySelector('#checkout-modal-total').textContent = currency.format(modalTotals().total / 100);
  elements.cashReceived.value = '';
  elements.checkoutModal.showModal();
  updateCashChange();
  if (elements.paymentMethod.value === 'efectivo') elements.cashReceived.focus();
});
elements.paymentMethod.addEventListener('change', () => {
  updatePaymentFields();
  if (elements.paymentMethod.value === 'transferencia') openTransferScreen();
});
elements.cashReceived.addEventListener('input', updateCashChange);
elements.startTransferCountdown.addEventListener('click', startTransferCountdown);
elements.cancelTransfer.addEventListener('click', cancelTransferScreen);
document.querySelector('#cancel-transfer-close').addEventListener('click', cancelTransferScreen);
elements.transferWaitModal.addEventListener('cancel', (event) => {
  event.preventDefault();
  cancelTransferScreen();
});
document.querySelector('#copy-transfer-clabe').addEventListener('click', async (event) => {
  const button = event.currentTarget;
  try {
    await navigator.clipboard.writeText(elements.transferWaitClabe.textContent.trim());
    button.textContent = 'Copiada';
    setTimeout(() => { button.textContent = 'Copiar'; }, 1800);
  } catch {
    elements.transferWaitError.textContent = 'No se pudo copiar. Selecciona la CLABE y cópiala manualmente.';
  }
});
elements.checkoutForm.addEventListener('submit', (event) => {
  event.preventDefault();
  if (elements.paymentMethod.value === 'tarjeta') {
    void processCardPayment();
    return;
  }
  if (elements.paymentMethod.value === 'transferencia') {
    openTransferScreen();
    return;
  }
  if (elements.paymentMethod.value === 'efectivo' && Number(elements.cashReceived.value) < modalTotals().total / 100) {
    elements.checkoutError.textContent = 'El efectivo recibido no cubre el total de la venta.';
    return;
  }
  completeSale();
});
document.querySelector('#open-history').addEventListener('click', () => {
  elements.historyModal.showModal();
  loadHistory();
});
document.querySelectorAll('[data-close-pos-modal]').forEach((button) => {
  button.addEventListener('click', () => {
    if (!cardPaymentInProgress) button.closest('dialog').close();
  });
});
document.querySelectorAll('.pos-modal').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (cardPaymentInProgress) {
      event.preventDefault();
      return;
    }
    if (event.target !== dialog) return;
    if (dialog === elements.transferWaitModal) cancelTransferScreen();
    else dialog.close();
  });
  dialog.addEventListener('cancel', (event) => {
    if (cardPaymentInProgress) event.preventDefault();
  });
});
document.addEventListener('keydown', (event) => {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    elements.search.focus();
  }
});

updateClock();
updatePaymentFields();
setInterval(updateClock, 30_000);
renderCart();
loadProducts();
