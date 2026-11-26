<form class="feature-card satisfaction-form" id="satisfaction-form" novalidate>
  <div class="feature-card-heading"><div><p class="eyebrow">TU OPINIÓN</p><h2>¿Cómo fue tu experiencia?</h2></div><span aria-hidden="true">✦</span></div>
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($reviewCsrf, ENT_QUOTES, 'UTF-8') ?>">
  <?php require __DIR__ . '/../atoms/star-rating.php'; ?>
  <label class="feature-field" for="review-comment">Comentario
    <textarea id="review-comment" name="comment" rows="4" minlength="10" maxlength="1000" required aria-describedby="review-feedback review-counter" placeholder="Cuéntanos qué podemos mejorar (mínimo 10 caracteres)."></textarea>
  </label>
  <div class="form-meta"><span id="review-feedback" role="status" aria-live="polite">Escribe al menos 10 caracteres.</span><span id="review-counter">0/1000</span></div>
  <button class="primary-button" id="review-submit" type="submit">Enviar reseña</button>
  <p class="feature-disclaimer">Puedes actualizar tu reseña; solo se conserva la más reciente por cuenta.</p>
</form>
