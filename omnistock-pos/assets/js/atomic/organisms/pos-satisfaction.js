(() => {
  const form = document.querySelector('#pos-satisfaction-form');
  const feedback = document.querySelector('#pos-satisfaction-feedback');
  if (!form || !feedback) return;

  const storageKey = 'omnistock-satisfaction-responses';
  const saleId = Number(form.dataset.saleId);
  const readResponses = () => {
    const stored = window.localStorage.getItem(storageKey);
    if (stored === null) return [];
    const responses = JSON.parse(stored);
    if (!Array.isArray(responses)) throw new Error('El registro de opiniones guardado no es válido.');
    return responses;
  };
  const showSavedMessage = () => {
    form.hidden = true;
    feedback.textContent = '¡Gracias por tu opinión!';
    feedback.classList.add('success');
  };

  try {
    if (readResponses().some((response) => Number(response.sale_id) === saleId)) {
      showSavedMessage();
    }
  } catch {
    feedback.textContent = 'No se pudieron leer las opiniones guardadas. Si continúas, no se sobrescribirán.';
    feedback.classList.add('error');
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const formData = new FormData(form);
    const now = new Date().toISOString();
    const response = {
      source: 'pos-satisfaction',
      sale_id: saleId,
      author: 'Cliente de caja',
      found: formData.get('satisfaction_found'),
      prices: formData.get('satisfaction_prices'),
      rating: Number(formData.get('satisfaction_rating')),
      comment: String(formData.get('satisfaction_comment') || '').trim(),
      created_at: now,
      updated_at: now,
    };

    feedback.classList.remove('error', 'success');
    try {
      if (!Number.isInteger(saleId) || saleId < 1) {
        throw new Error('No se pudo identificar el ticket para guardar tu opinión.');
      }
      const responses = readResponses();
      if (responses.some((savedResponse) => Number(savedResponse.sale_id) === saleId)) {
        showSavedMessage();
        return;
      }
      responses.push(response);
      window.localStorage.setItem(storageKey, JSON.stringify(responses.slice(-200)));
      showSavedMessage();
    } catch (error) {
      feedback.textContent = error.message || 'No se pudo guardar tu opinión en este dispositivo.';
      feedback.classList.add('error');
    }
  });
})();
