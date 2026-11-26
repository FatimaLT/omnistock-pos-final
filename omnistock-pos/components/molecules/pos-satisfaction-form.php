<details class="pos-satisfaction receipt-satisfaction" open>
  <summary>
    <span><strong>¿Cómo fue tu visita?</strong><small>Opinión rápida · opcional</small></span>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
  </summary>
  <form id="pos-satisfaction-form" class="pos-satisfaction-form" data-sale-id="<?= (int) $satisfactionSaleId ?>">
    <fieldset class="satisfaction-question">
      <legend>¿Encontraste todo lo que buscabas?</legend>
      <div class="satisfaction-choices">
        <label><input type="radio" name="satisfaction_found" value="si" required><span>Sí</span></label>
        <label><input type="radio" name="satisfaction_found" value="parcial" required><span>Parcial</span></label>
        <label><input type="radio" name="satisfaction_found" value="no" required><span>No</span></label>
      </div>
    </fieldset>
    <fieldset class="satisfaction-question">
      <legend>¿Qué te parecieron los precios?</legend>
      <div class="satisfaction-choices">
        <label><input type="radio" name="satisfaction_prices" value="excelentes" required><span>Excelentes</span></label>
        <label><input type="radio" name="satisfaction_prices" value="buenos" required><span>Buenos</span></label>
        <label><input type="radio" name="satisfaction_prices" value="altos" required><span>Altos</span></label>
      </div>
    </fieldset>
    <fieldset class="satisfaction-question">
      <legend>¿Cómo calificas la atención y rapidez?</legend>
      <div class="pos-satisfaction-stars" role="radiogroup" aria-label="Calificación de atención y rapidez">
        <?php for ($star = 5; $star >= 1; $star--): ?>
          <input id="pos-satisfaction-star-<?= $star ?>" type="radio" name="satisfaction_rating" value="<?= $star ?>" required>
          <label for="pos-satisfaction-star-<?= $star ?>" aria-label="<?= $star ?> <?= $star === 1 ? 'estrella' : 'estrellas' ?>">★</label>
        <?php endfor; ?>
      </div>
    </fieldset>
    <label class="satisfaction-comment" for="pos-satisfaction-comment">¿Algún comentario o producto faltante?
      <textarea id="pos-satisfaction-comment" name="satisfaction_comment" rows="2" maxlength="300" placeholder="Opcional"></textarea>
    </label>
    <button class="satisfaction-submit" type="submit">Enviar opinión</button>
  </form>
  <p class="satisfaction-feedback" id="pos-satisfaction-feedback" role="status" aria-live="polite"></p>
</details>
