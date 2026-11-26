const helpList = document.querySelector('#warehouse-help-list');
const helpCount = document.querySelector('#warehouse-help-count');
const helpToast = document.querySelector('#warehouse-admin-message-toast');
const helpToastTitle = document.querySelector('#warehouse-admin-message-toast-title');
const helpToastText = document.querySelector('#warehouse-admin-message-toast-text');
const helpToastOpen = document.querySelector('#warehouse-admin-message-toast-open');
const helpSeen = new Set();
let helpMessages = [];
let helpPollInProgress = false;
let helpToastTimer;

function escapeHelpText(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[character]);
}

function helpDate(value) {
  const date = new Date(String(value || '').replace(' ', 'T'));
  return Number.isNaN(date.getTime())
    ? ''
    : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function updateHelpCount(count) {
  const unread = Math.max(0, Number(count) || 0);
  helpCount.textContent = String(unread);
  helpCount.classList.toggle('has-unread', unread > 0);
}

function renderHelpMessages() {
  if (!helpMessages.length) {
    helpList.innerHTML = '<div class="warehouse-history-empty">Aún no hay solicitudes de vendedores.</div>';
    return;
  }
  helpList.innerHTML = helpMessages.map((message) => `
    <article class="warehouse-help-item${Number(message.leido) ? '' : ' unread'}" data-help-item="${escapeHelpText(message.id)}">
      <div class="warehouse-history-item-top"><strong>${escapeHelpText(message.asunto)}</strong><time>${escapeHelpText(helpDate(message.fecha_envio))}</time></div>
      <span class="warehouse-help-sender">De: ${escapeHelpText(message.emisor_nombre || 'Vendedor')}</span>
      <p>${escapeHelpText(message.mensaje).replace(/\n/g, '<br>')}</p>
      ${message.tipo === 'solicitud_reabastecimiento' && message.estado === 'pendiente'
        ? `<form class="restock-fulfill-form" data-fulfill-restock="${escapeHelpText(message.id)}">
            <label>Unidades recibidas <input type="number" name="quantity_received" min="1" max="1000000" value="${Math.max(1, Number(message.cantidad_sugerida) || 1)}" required></label>
            <button type="submit" class="warehouse-help-mark-read">Actualizar stock y atender</button>
          </form>`
        : message.tipo === 'solicitud_reabastecimiento'
          ? `<span class="warehouse-help-read-label">Atendida · ${Number(message.cantidad_recibida) || 0} unidades agregadas</span>`
          : Number(message.leido)
        ? '<span class="warehouse-help-read-label">Leído</span>'
          : `<button type="button" class="warehouse-help-mark-read" data-mark-help-read="${escapeHelpText(message.id)}">Marcar como leído</button>`}
      <form class="warehouse-admin-reply-form" data-admin-reply="${escapeHelpText(message.id)}">
          <label>Responder al vendedor<textarea name="message" rows="2" maxlength="2000" required placeholder="Escribe una respuesta..."></textarea></label>
          <button type="submit" class="warehouse-help-mark-read">Enviar respuesta</button>
          <span class="warehouse-admin-reply-feedback" role="status"></span>
      </form>
    </article>
  `).join('');
}

function showHelpToast(message) {
  helpToastTitle.textContent = message.asunto;
  const text = String(message.mensaje || '').trim();
  helpToastText.textContent = text.length > 110 ? `${text.slice(0, 110)}…` : text;
  helpToast.hidden = false;
  helpToast.classList.add('visible');
  clearTimeout(helpToastTimer);
  helpToastTimer = setTimeout(() => {
    helpToast.classList.remove('visible');
    helpToastTimer = setTimeout(() => { helpToast.hidden = true; }, 250);
  }, 7000);
}

async function loadHelpMessages(initial = false) {
  if (helpPollInProgress) return;
  helpPollInProgress = true;
  try {
    const response = await fetch('../api/warehouse-messages.php', {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudieron cargar las solicitudes.');
    helpMessages = Array.isArray(result.messages) ? result.messages : [];
    updateHelpCount(result.unread_count);
    renderHelpMessages();

    const unread = helpMessages.filter((message) => !Number(message.leido));
    const unseen = unread.filter((message) => !helpSeen.has(String(message.id)));
    helpMessages.forEach((message) => helpSeen.add(String(message.id)));
    if (unseen.length) showHelpToast(unseen[0]);
    else if (initial && unread.length) showHelpToast(unread[0]);
  } catch (error) {
    if (initial) console.error(error);
  } finally {
    helpPollInProgress = false;
  }
}

helpList.addEventListener('submit', async (event) => {
  const replyForm = event.target.closest('[data-admin-reply]');
  if (replyForm) {
    event.preventDefault();
    const button = replyForm.querySelector('[type="submit"]');
    const feedback = replyForm.querySelector('.warehouse-admin-reply-feedback');
    const data = new FormData(replyForm);
    data.set('action', 'admin_reply');
    data.set('message_id', replyForm.dataset.adminReply);
    button.disabled = true;
    feedback.textContent = 'Enviando...';
    try {
      const response = await fetch('../api/warehouse-messages.php', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-Token': window.omniStockMessages.csrf,
        },
        body: data,
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'No se pudo enviar la respuesta.');
      replyForm.reset();
      feedback.textContent = result.message;
      await loadHelpMessages();
      showHelpToast({ asunto: 'Respuesta enviada', mensaje: result.message });
    } catch (error) {
      button.disabled = false;
      feedback.textContent = error.message || 'No se pudo enviar la respuesta.';
    }
    return;
  }

  const form = event.target.closest('[data-fulfill-restock]');
  if (!form) return;
  event.preventDefault();
  const button = form.querySelector('[type="submit"]');
  button.disabled = true;
  const data = new FormData(form);
  data.set('action', 'fulfill_restock');
  data.set('message_id', form.dataset.fulfillRestock);
  try {
    const response = await fetch('../api/warehouse-messages.php', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': window.omniStockMessages.csrf,
      },
      body: data,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo atender el reabastecimiento.');
    await loadHelpMessages();
    showHelpToast({ asunto: 'Reabastecimiento atendido', mensaje: result.message });
  } catch (error) {
    button.disabled = false;
    showHelpToast({ asunto: 'No se pudo actualizar el inventario', mensaje: error.message });
  }
});

helpList.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-mark-help-read]');
  if (!button) return;
  button.disabled = true;
  const formData = new FormData();
  formData.set('action', 'mark_read');
  formData.append('ids[]', button.dataset.markHelpRead);
  try {
    const response = await fetch('../api/warehouse-messages.php', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': window.omniStockMessages.csrf,
      },
      body: formData,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo actualizar la solicitud.');
    await loadHelpMessages();
  } catch (error) {
    button.disabled = false;
    showHelpToast({ asunto: 'No se pudo actualizar la solicitud', mensaje: error.message });
  }
});

helpToastOpen.addEventListener('click', () => {
  clearTimeout(helpToastTimer);
  helpToast.classList.remove('visible');
  helpList.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

loadHelpMessages(true);
window.setInterval(() => loadHelpMessages(), 10000);
