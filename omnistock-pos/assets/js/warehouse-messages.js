const messageBell = document.querySelector('#warehouse-message-bell');
const messageBadge = document.querySelector('#warehouse-message-badge');
const messageDialog = document.querySelector('#warehouse-message-modal');
const messageList = document.querySelector('#warehouse-message-list');
const messageToast = document.querySelector('#warehouse-message-toast');
const messageToastTitle = document.querySelector('#warehouse-message-toast-title');
const messageToastBody = document.querySelector('#warehouse-message-toast-body');
const messageToastOpen = document.querySelector('#warehouse-message-toast-open');
const sellerMessageForm = document.querySelector('#seller-message-form');
const messageAppBasePath = document.querySelector('meta[name="app-base-path"]')?.content.replace(/\/+$/, '') || '';
const messageSeen = new Set();
let messageRecords = [];
let lastMessageSignature = '';
let messageToastTimer;
let messagePollInProgress = false;

function escapeMessageText(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[character]);
}

function setUnreadCount(count) {
  const unreadCount = Math.max(0, Number(count) || 0);
  messageBadge.hidden = unreadCount === 0;
  messageBadge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
  messageBell.classList.toggle('has-unread', unreadCount > 0);
  messageBell.setAttribute('aria-label', unreadCount
    ? `Avisos de almacén: ${unreadCount} sin leer`
    : 'Avisos de almacén');
}

function showMessageToast(message) {
  messageToastTitle.textContent = message.asunto;
  const preview = String(message.mensaje || '').trim();
  messageToastBody.textContent = preview.length > 110 ? `${preview.slice(0, 110)}…` : preview;
  messageToast.hidden = false;
  messageToast.classList.add('visible');
  clearTimeout(messageToastTimer);
  messageToastTimer = setTimeout(() => {
    messageToast.classList.remove('visible');
    messageToastTimer = setTimeout(() => { messageToast.hidden = true; }, 250);
  }, 6500);
}

function safeMessageImage(path) {
  return /^public\/img\/mensajes\/[a-f0-9]{32}\.(?:png|jpg|webp)$/i.test(String(path || ''))
    ? `${messageAppBasePath}/${String(path)}`
    : '';
}

function formatMessageDate(value) {
  const date = new Date(String(value || '').replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function renderMessageList() {
  if (!messageRecords.length) {
    messageList.innerHTML = '<div class="warehouse-message-empty"><span>♧</span><strong>No tienes avisos</strong><small>Los mensajes enviados por administración aparecerán aquí.</small></div>';
    return;
  }

  messageList.innerHTML = messageRecords.map((message) => {
    const imagePath = safeMessageImage(message.imagen_adjunta);
    const attachment = imagePath
      ? `<a class="warehouse-message-image-link" href="${escapeMessageText(imagePath)}" target="_blank" rel="noopener"><img src="${escapeMessageText(imagePath)}" alt="Imagen adjunta al aviso" loading="lazy"><span>Ver imagen adjunta ↗</span></a>`
      : '';
    return `<article class="warehouse-message-card${Number(message.leido) ? '' : ' unread'}">
      <header><span class="warehouse-message-sender"><i></i> Administración</span><time>${escapeMessageText(formatMessageDate(message.fecha_envio))}</time></header>
      <h3>${escapeMessageText(message.asunto)}</h3>
      <p>${escapeMessageText(message.mensaje).replace(/\n/g, '<br>')}</p>
      ${attachment}
      <div class="warehouse-help-actions">
        <button type="button" class="warehouse-help-toggle" data-help-toggle="${escapeMessageText(message.id)}">Solicitar apoyo al administrador</button>
        <form class="warehouse-help-form" data-help-form="${escapeMessageText(message.id)}" hidden>
          <label for="help-note-${escapeMessageText(message.id)}">Cuéntale al administrador qué impide atender este aviso.</label>
          <textarea id="help-note-${escapeMessageText(message.id)}" name="mensaje" rows="2" maxlength="2000" required placeholder="Describe qué necesitas o qué problema encontraste..."></textarea>
          <button type="submit" class="warehouse-help-submit" data-message-id="${escapeMessageText(message.id)}">Enviar solicitud</button>
          <span class="warehouse-help-feedback" role="status"></span>
        </form>
      </div>
    </article>`;
  }).join('');
}

sellerMessageForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = sellerMessageForm.querySelector('[type="submit"]');
  const feedback = sellerMessageForm.querySelector('.warehouse-vendor-compose-feedback');
  const formData = new FormData(sellerMessageForm);
  formData.set('action', 'contact_admin');
  button.disabled = true;
  feedback.textContent = 'Enviando...';
  try {
    const response = await fetch(`${messageAppBasePath}/api/warehouse-messages.php`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': window.omniStockMessages.csrf,
      },
      body: formData,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo enviar el mensaje.');
    sellerMessageForm.reset();
    feedback.textContent = result.message;
  } catch (error) {
    feedback.textContent = error.message || 'No se pudo enviar el mensaje.';
  } finally {
    button.disabled = false;
  }
});

function updateMessageListIfChanged() {
  const signature = JSON.stringify(messageRecords);
  if (signature !== lastMessageSignature) {
    lastMessageSignature = signature;
    renderMessageList();
  }
}

function openMessageInbox() {
  clearTimeout(messageToastTimer);
  messageToast.classList.remove('visible');
  messageDialog.showModal();
  const unreadIds = messageRecords
    .filter((message) => !Number(message.leido))
    .map((message) => String(message.id));
  if (unreadIds.length) markMessagesRead(unreadIds);
}

async function markMessagesRead(ids) {
  try {
    const formData = new FormData();
    formData.set('action', 'mark_read');
    ids.forEach((id) => formData.append('ids[]', id));
    const response = await fetch(`${messageAppBasePath}/api/warehouse-messages.php`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': window.omniStockMessages.csrf,
      },
      body: formData,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudieron marcar los avisos como leídos.');
    messageRecords = messageRecords.map((message) => ids.includes(String(message.id))
      ? { ...message, leido: 1 }
      : message);
    updateMessageListIfChanged();
    setUnreadCount(result.unread_count);
  } catch (error) {
    console.error(error);
    showMessageToast({ asunto: 'No se pudo actualizar la lectura', mensaje: error.message });
  }
}

messageList.addEventListener('click', (event) => {
  const toggle = event.target.closest('[data-help-toggle]');
  if (!toggle) return;
  const form = [...messageList.querySelectorAll('[data-help-form]')]
    .find((candidate) => candidate.dataset.helpForm === toggle.dataset.helpToggle);
  if (!form) return;
  form.hidden = !form.hidden;
  toggle.setAttribute('aria-expanded', String(!form.hidden));
  if (!form.hidden) form.querySelector('textarea').focus();
});

messageList.addEventListener('submit', async (event) => {
  const form = event.target.closest('[data-help-form]');
  if (!form) return;
  event.preventDefault();
  const submit = form.querySelector('[type="submit"]');
  const feedback = form.querySelector('.warehouse-help-feedback');
  const formData = new FormData(form);
  formData.set('action', 'help_request');
  formData.set('message_id', form.dataset.helpForm);
  submit.disabled = true;
  feedback.textContent = 'Enviando solicitud...';

  try {
    const response = await fetch(`${messageAppBasePath}/api/warehouse-messages.php`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': window.omniStockMessages.csrf,
      },
      body: formData,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudo enviar la solicitud.');
    feedback.textContent = result.message;
    form.querySelector('textarea').disabled = true;
    submit.hidden = true;
    showMessageToast({ asunto: 'Solicitud enviada', mensaje: result.message });
  } catch (error) {
    feedback.textContent = error.message || 'No se pudo enviar la solicitud.';
    feedback.classList.add('error');
    submit.disabled = false;
  }
});

async function pollWarehouseMessages(initial = false) {
  if (messagePollInProgress) return;
  messagePollInProgress = true;
  try {
    const response = await fetch(`${messageAppBasePath}/api/warehouse-messages.php`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No se pudieron consultar los avisos.');
    messageRecords = Array.isArray(result.messages) ? result.messages : [];
    setUnreadCount(result.unread_count);
    updateMessageListIfChanged();

    const unreadMessages = messageRecords.filter((message) => !Number(message.leido));
    const unseen = unreadMessages.filter((message) => !messageSeen.has(String(message.id)));
    messageRecords.forEach((message) => messageSeen.add(String(message.id)));
    if (unseen.length) showMessageToast(unseen[0]);
    else if (initial && unreadMessages.length) showMessageToast(unreadMessages[0]);
  } catch (error) {
    if (initial) {
      console.error(error);
      showMessageToast({ asunto: 'Avisos de almacén no disponibles', mensaje: error.message });
    }
  } finally {
    messagePollInProgress = false;
  }
}

messageBell.addEventListener('click', openMessageInbox);
messageToastOpen.addEventListener('click', openMessageInbox);
document.querySelectorAll('[data-close-warehouse-messages]').forEach((button) => {
  button.addEventListener('click', () => messageDialog.close());
});
messageDialog.addEventListener('click', (event) => {
  if (event.target === messageDialog) messageDialog.close();
});

pollWarehouseMessages(true);
window.setInterval(() => pollWarehouseMessages(), 10000);
