(() => {
  const form = document.querySelector('#satisfaction-form');
  const list = document.querySelector('#review-list');
  const status = document.querySelector('#reviews-status');
  const counter = document.querySelector('#review-counter');
  const feedback = document.querySelector('#review-feedback');
  const comment = document.querySelector('#review-comment');
  const submit = document.querySelector('#review-submit');
  const count = document.querySelector('#reviews-count');
  const search = document.querySelector('#review-search');
  if (!form || !list || !status || !comment || !submit || !search) return;

  const basePath = document.querySelector('meta[name="app-base-path"]')?.content.replace(/\/+$/, '') || '';
  const satisfactionStorageKey = 'omnistock-satisfaction-responses';
  const foundLabels = { si: 'Sí', parcial: 'Parcial', no: 'No' };
  const priceLabels = { excelentes: 'Excelentes', buenos: 'Buenos', altos: 'Altos' };
  const ratingInputs = [...form.querySelectorAll('input[name="rating"]')];
  const selectedRating = () => form.querySelector('input[name="rating"]:checked')?.value || '';
  let reviews = [];
  let localSatisfaction = [];
  let databaseError = '';
  let storageError = '';

  const readLocalSatisfaction = () => {
    try {
      const stored = window.localStorage.getItem(satisfactionStorageKey);
      if (stored === null) {
        storageError = '';
        return [];
      }
      const responses = JSON.parse(stored);
      if (!Array.isArray(responses)) throw new Error('El registro de opiniones no tiene un formato válido.');
      storageError = '';
      return responses.filter((response) =>
        response?.source === 'pos-satisfaction' &&
        Number.isInteger(Number(response.sale_id)) && Number(response.sale_id) > 0 &&
        Number.isInteger(Number(response.rating)) && Number(response.rating) >= 1 && Number(response.rating) <= 5 &&
        Object.hasOwn(foundLabels, response.found) &&
        Object.hasOwn(priceLabels, response.prices) &&
        typeof response.comment === 'string' && response.comment.length <= 300
      );
    } catch {
      storageError = 'No se pudieron leer las opiniones guardadas en este dispositivo.';
      return [];
    }
  };

  const renderReviews = () => {
    const query = search.value.trim().toLocaleLowerCase('es-MX');
    const allReviews = [...reviews, ...localSatisfaction]
      .sort((left, right) => new Date(right.updated_at || right.created_at).getTime() - new Date(left.updated_at || left.created_at).getTime());
    const visible = allReviews.filter((review) => {
      const details = review.source === 'pos-satisfaction'
        ? `${foundLabels[review.found]} ${priceLabels[review.prices]}`
        : '';
      return `${review.author || ''} ${review.comment || ''} ${details}`.toLocaleLowerCase('es-MX').includes(query);
    }
    );
    list.innerHTML = visible.map(window.OmniReviewCard).join('');
    count.textContent = query ? `${visible.length}/${allReviews.length}` : String(allReviews.length);
    status.textContent = storageError || databaseError || (allReviews.length === 0
      ? 'Todavía no hay reseñas. ¡Sé la primera persona en compartir su experiencia!'
      : visible.length === 0 ? 'No hay reseñas que coincidan con tu búsqueda.' : '');
    status.hidden = visible.length > 0 && !storageError && !databaseError;
    status.classList.toggle('error', Boolean(storageError || databaseError));
  };

  const validate = () => {
    const characters = [...comment.value.trim()].length;
    counter.textContent = `${characters}/1000`;
    const validText = characters >= 10;
    comment.setCustomValidity(validText ? '' : 'Escribe al menos 10 caracteres.');
    comment.setAttribute('aria-invalid', validText || !comment.value ? 'false' : 'true');
    feedback.classList.remove('error');
    feedback.textContent = validText ? 'Gracias; tu comentario cumple con la longitud mínima.' : 'Escribe al menos 10 caracteres.';
    return validText && selectedRating() !== '';
  };

  const loadReviews = async () => {
    status.hidden = false;
    status.classList.remove('error');
    status.textContent = 'Cargando reseñas…';
    try {
      const response = await fetch(`${basePath}/api/reviews.php`, { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'No se pudieron cargar las reseñas.');
      reviews = Array.isArray(payload.reviews) ? payload.reviews : [];
      databaseError = '';
    } catch (error) {
      databaseError = error.message || 'No fue posible consultar las reseñas.';
    }
    renderReviews();
  };

  comment.addEventListener('input', validate);
  search.addEventListener('input', renderReviews);
  ratingInputs.forEach((input) => input.addEventListener('change', validate));
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!validate()) {
      form.reportValidity();
      if (!selectedRating()) ratingInputs[0].focus();
      else comment.focus();
      return;
    }
    submit.disabled = true;
    submit.textContent = 'Enviando…';
    feedback.textContent = 'Guardando tu reseña…';
    try {
      const response = await fetch(`${basePath}/api/reviews.php`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': form.elements.csrf_token.value,
        },
        body: JSON.stringify({ rating: Number(selectedRating()), comment: comment.value.trim() }),
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'No se pudo guardar la reseña.');
      feedback.textContent = payload.message;
      comment.value = '';
      form.querySelector('input[name="rating"]:checked').checked = false;
      validate();
      await loadReviews();
      document.querySelector('#review-comment').focus();
    } catch (error) {
      feedback.textContent = error.message || 'No se pudo guardar tu reseña.';
      feedback.classList.add('error');
    } finally {
      submit.disabled = false;
      submit.textContent = 'Enviar reseña';
    }
  });
  window.addEventListener('storage', (event) => {
    if (event.key !== satisfactionStorageKey) return;
    localSatisfaction = readLocalSatisfaction();
    renderReviews();
  });
  validate();
  localSatisfaction = readLocalSatisfaction();
  renderReviews();
  loadReviews();
})();
