window.OmniReviewCard = function renderReviewCard(review) {
  const escape = (value) => String(value).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  })[character]);
  const rating = Math.min(5, Math.max(1, Number(review.rating) || 0));
  const date = new Date(String(review.updated_at || review.created_at).replace(' ', 'T'));
  const dateLabel = Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(date);
  const foundLabels = { si: 'Sí', parcial: 'Parcial', no: 'No' };
  const priceLabels = { excelentes: 'Excelentes', buenos: 'Buenos', altos: 'Altos' };
  const surveyAnswers = review.source === 'pos-satisfaction'
    ? `<div class="review-survey-answers"><span><strong>Productos:</strong> ${escape(foundLabels[review.found] || 'Sin respuesta')}</span><span><strong>Precios:</strong> ${escape(priceLabels[review.prices] || 'Sin respuesta')}</span></div>`
    : '';
  const comment = typeof review.comment === 'string' ? review.comment.trim() : '';
  return `<article class="review-card" role="listitem">
    <header><span class="review-avatar" aria-hidden="true">${escape(String(review.author || '?').slice(0, 1).toUpperCase())}</span>
      <div><h3>${escape(review.author || 'Usuario')}</h3><time datetime="${escape(review.updated_at || review.created_at || '')}">${escape(dateLabel)}</time></div>
      <span class="review-stars" role="img" aria-label="${rating} de 5 estrellas">${'★'.repeat(rating)}<span>${'★'.repeat(5 - rating)}</span></span>
    </header>${surveyAnswers}${comment ? `<p>${escape(comment)}</p>` : ''}
  </article>`;
};
