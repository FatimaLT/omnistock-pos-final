<fieldset class="rating-field" aria-describedby="rating-help">
  <legend>Calificación</legend>
  <div class="rating-options">
    <?php for ($star = 5; $star >= 1; $star--): ?>
      <input id="rating-<?= $star ?>" type="radio" name="rating" value="<?= $star ?>" required>
      <label for="rating-<?= $star ?>" aria-label="<?= $star ?> <?= $star === 1 ? 'estrella' : 'estrellas' ?>">★</label>
    <?php endfor; ?>
  </div>
  <small id="rating-help">Elige de 1 a 5 estrellas.</small>
</fieldset>
