const productDialog = document.querySelector('#product-modal');
const stockDialog = document.querySelector('#stock-modal');
const productForm = document.querySelector('#product-form');
const imageInput = document.querySelector('#product-image');
const imagePreview = document.querySelector('#image-preview');
const imagePreviewEmpty = document.querySelector('#image-preview-empty');
const removeImageWrap = document.querySelector('#remove-image-wrap');
const removeImageInput = document.querySelector('#remove-image');
let selectedStockButton = null;
let previewObjectUrl = null;
let toastTimeout;

function showAdminToast(message, error = false) {
  const toast = document.querySelector('#admin-toast');
  toast.textContent = message;
  toast.classList.toggle('error', error);
  toast.classList.add('visible');
  clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => toast.classList.remove('visible'), 2800);
}

function closeDialogs() {
  document.querySelectorAll('.admin-modal[open]').forEach((dialog) => dialog.close());
}

function setProductPreview(url) {
  if (!imagePreview || !imagePreviewEmpty) return;
  if (previewObjectUrl && previewObjectUrl !== url) {
    URL.revokeObjectURL(previewObjectUrl);
  }
  previewObjectUrl = url?.startsWith('blob:') ? url : null;
  if (url) {
    imagePreview.src = url;
    imagePreview.hidden = false;
    imagePreviewEmpty.hidden = true;
  } else {
    imagePreview.removeAttribute('src');
    imagePreview.hidden = true;
    imagePreviewEmpty.hidden = false;
  }
}

function openProductEditor(product = null) {
  productForm.reset();
  document.querySelector('#product-id').value = product?.id || '';
  document.querySelector('#modal-title').textContent = product ? 'Editar producto' : 'Nuevo producto';
  document.querySelector('#product-name').value = product?.name || '';
  document.querySelector('#product-price').value = product?.price ?? '';
  const setField = (id, value) => {
    const field = document.querySelector(id);
    if (field) field.value = value ?? '';
  };
  setField('#product-stock', product?.stock ?? 0);
  setField('#product-stock-minimum', product?.stock_minimum ?? 5);
  setField('#product-category', product?.category);
  setField('#product-code', product?.code);
  setField('#product-description', product?.description);
  if (removeImageInput) removeImageInput.checked = false;
  if (removeImageWrap) removeImageWrap.hidden = !product?.image;
  const imageUrl = product?.image
    ? (/^https?:\/\//i.test(String(product.image)) ? String(product.image) : `../${String(product.image).replace(/^\/+/, '')}`)
    : '';
  setProductPreview(imageUrl);
  if (productDialog) productDialog.showModal();
  document.querySelector('#product-name').focus();
}

document.querySelector('#new-product').addEventListener('click', () => openProductEditor());

document.querySelectorAll('.edit-product').forEach((button) => {
  button.addEventListener('click', () => {
    try {
      openProductEditor(JSON.parse(button.dataset.product));
    } catch {
      showAdminToast('No se pudo abrir la información del producto.', true);
    }
  });
});

document.querySelectorAll('[data-close-modal]').forEach((button) => {
  button.addEventListener('click', closeDialogs);
});

document.querySelectorAll('.admin-modal').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
});

document.querySelectorAll('.delete-form').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm('¿Eliminar este producto del catálogo? Esta acción no se puede deshacer.')) {
      event.preventDefault();
    }
  });
});

if (imageInput) {
  imageInput.addEventListener('change', () => {
    const file = imageInput.files?.[0];
    if (!file) return;
    const acceptedTypes = ['image/png', 'image/jpeg', 'image/webp'];
    if (!acceptedTypes.includes(file.type) || file.size > 5 * 1024 * 1024) {
      imageInput.value = '';
      showAdminToast('Elige una imagen PNG, JPG o WEBP de máximo 5 MB.', true);
      return;
    }
    if (removeImageWrap) removeImageWrap.hidden = true;
    previewObjectUrl = URL.createObjectURL(file);
    setProductPreview(previewObjectUrl);
  });
}

document.querySelector('#product-search').addEventListener('input', (event) => {
  const query = event.target.value.trim().toLocaleLowerCase('es');
  let visible = 0;
  document.querySelectorAll('[data-product-row]').forEach((row) => {
    const matches = row.dataset.search.includes(query);
    row.hidden = !matches;
    if (matches) visible++;
  });
  const empty = document.querySelector('#table-empty');
  empty.hidden = visible !== 0;
  if (visible === 0) {
    empty.querySelector('strong').textContent = 'No encontramos productos';
    empty.querySelector('small').textContent = 'Prueba con otro término de búsqueda.';
  }
});

document.querySelectorAll('[data-stock-edit]').forEach((button) => {
  button.addEventListener('click', () => {
    selectedStockButton = button;
    document.querySelector('#stock-product-id').value = button.dataset.id;
    document.querySelector('#stock-product-name').textContent = button.dataset.name;
    document.querySelector('#stock-quantity').value = button.dataset.stock;
    document.querySelector('#stock-feedback').textContent = '';
    document.querySelector('#stock-save').disabled = false;
    stockDialog.showModal();
    document.querySelector('#stock-quantity').focus();
  });
});

document.querySelector('#stock-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = document.querySelector('#stock-save');
  const feedback = document.querySelector('#stock-feedback');
  button.disabled = true;
  feedback.textContent = '';

  try {
    const response = await fetch('index.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      body: new FormData(event.currentTarget),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo actualizar la existencia.');
    if (selectedStockButton) {
      selectedStockButton.dataset.stock = result.stock;
      selectedStockButton.dataset.minimum = result.minimum;
      selectedStockButton.innerHTML = `<span></span>${escapeHtml(result.stock)} <small>Editar</small>`;
      selectedStockButton.classList.toggle('low', Number(result.stock) <= Number(result.minimum));
    }
    const lowStockMetric = document.querySelectorAll('[data-stock-edit]').length
      ? [...document.querySelectorAll('[data-stock-edit]')].filter((stockButton) => Number(stockButton.dataset.stock) <= Number(stockButton.dataset.minimum || 5)).length
      : 0;
    const stockMetric = document.querySelector('.metric-card:nth-child(2) strong');
    if (stockMetric) stockMetric.textContent = String(lowStockMetric);
    if (typeof window.refreshStockAlerts === 'function') {
      window.refreshStockAlerts();
    }
    stockDialog.close();
    showAdminToast('Existencias actualizadas.');
  } catch (error) {
    feedback.textContent = error.message || 'No se pudo actualizar la existencia.';
  } finally {
    button.disabled = false;
  }
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

if (window.omniStockCatalog?.reopenEditor) {
  openProductEditor({
    ...window.omniStockCatalog.product,
    id: window.omniStockCatalog.editingId,
  });
}
