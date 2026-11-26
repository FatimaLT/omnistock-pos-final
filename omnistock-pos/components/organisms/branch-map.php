<section class="feature-card map-card" aria-labelledby="map-title">
  <div class="feature-card-heading"><div><p class="eyebrow">OPENSTREETMAP</p><h2 id="map-title">Ubicaciones registradas</h2></div><span aria-hidden="true">⌖</span></div>
  <div id="branch-map" class="branch-map" role="region" aria-label="Mapa interactivo de sucursales" aria-describedby="map-status"></div>
  <p class="map-status" id="map-status" role="status" aria-live="polite">Preparando mapa; las direcciones están disponibles abajo.</p>
  <div class="branch-map-addresses" aria-labelledby="branch-addresses-title">
    <h3 id="branch-addresses-title">Direcciones de sucursales</h3>
    <ul>
      <?php foreach ($branches as $branch): ?>
        <?php
          $latitude = number_format((float) $branch['lat'], 6, '.', '');
          $longitude = number_format((float) $branch['lng'], 6, '.', '');
          $mapUrl = "https://www.openstreetmap.org/?mlat={$latitude}&mlon={$longitude}#map=16/{$latitude}/{$longitude}";
        ?>
        <li class="branch-address-card">
          <strong><?= htmlspecialchars($branch['name'], ENT_QUOTES, 'UTF-8') ?></strong>
          <address><?= htmlspecialchars($branch['address'], ENT_QUOTES, 'UTF-8') ?></address>
          <a href="<?= htmlspecialchars($mapUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Abrir en OpenStreetMap <span aria-hidden="true">↗</span></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
