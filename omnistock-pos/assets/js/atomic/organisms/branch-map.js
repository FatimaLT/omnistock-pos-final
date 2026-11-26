(() => {
  const container = document.querySelector('#branch-map');
  const status = document.querySelector('#map-status');
  if (!container || !status) return;

  const branches = Array.isArray(window.omniStockBranches) ? window.omniStockBranches : [];
  const validBranches = branches.filter((branch) => branch && typeof branch === 'object' && (
    branch.lat !== null && branch.lat !== undefined && String(branch.lat).trim() !== '' &&
    branch.lng !== null && branch.lng !== undefined && String(branch.lng).trim() !== '' &&
    Number.isFinite(Number(branch.lat)) &&
    Number.isFinite(Number(branch.lng)) &&
    Number(branch.lat) >= -90 && Number(branch.lat) <= 90 &&
    Number(branch.lng) >= -180 && Number(branch.lng) <= 180
  ));
  const invalidCoordinateCount = branches.length - validBranches.length;
  const showFallback = (message) => {
    container.hidden = true;
    status.textContent = message;
    status.classList.add('error');
  };

  if (!validBranches.length) {
    showFallback(branches.length
      ? 'No hay coordenadas válidas para mostrar el mapa. Consulta las direcciones de las sucursales.'
      : 'No hay sucursales configuradas para mostrar.');
    return;
  }

  if (typeof window.L?.map !== 'function' || typeof window.L?.tileLayer !== 'function') {
    showFallback('El mapa interactivo no pudo cargarse. Consulta las direcciones de las sucursales.');
    return;
  }

  let loadedTiles = 0;
  let failedTiles = 0;
  let fallbackTimer;
  const updateMapStatus = () => {
    if (loadedTiles === 0) return;
    container.hidden = false;
    const issues = [];
    if (failedTiles > 0) issues.push('los mapas cargaron parcialmente');
    if (invalidCoordinateCount > 0) issues.push(`${invalidCoordinateCount} sucursal(es) no tienen coordenadas válidas`);
    status.classList.toggle('error', issues.length > 0);
    status.textContent = issues.length > 0
      ? `${validBranches.length} sucursal(es) en el mapa; ${issues.join(' y ')}. Consulta las direcciones.`
      : `${validBranches.length} sucursal(es) mostrada(s).`;
  };

  try {
    const map = window.L.map(container, { scrollWheelZoom: false })
      .setView([Number(validBranches[0].lat), Number(validBranches[0].lng)], validBranches.length > 1 ? 13 : 15);
    const tileLayer = window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    });
    tileLayer.on('tileload', () => {
      loadedTiles += 1;
      window.clearTimeout(fallbackTimer);
      updateMapStatus();
    });
    tileLayer.on('tileerror', () => {
      failedTiles += 1;
      updateMapStatus();
    });
    tileLayer.addTo(map);

    validBranches.forEach((branch) => {
      const escape = (value) => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
      window.L.marker([Number(branch.lat), Number(branch.lng)]).addTo(map)
        .bindPopup(`<strong>${escape(branch.name)}</strong><br>${escape(branch.address)}`);
    });
    if (validBranches.length > 1) {
      map.fitBounds(window.L.latLngBounds(validBranches.map((branch) => [Number(branch.lat), Number(branch.lng)])).pad(0.25));
    }

    fallbackTimer = window.setTimeout(() => {
      if (loadedTiles === 0) {
        showFallback('No se pudieron cargar los mapas de OpenStreetMap. Consulta las direcciones de las sucursales.');
      }
    }, 8000);
  } catch (error) {
    console.error('OmniStock branch map:', error);
    showFallback('No se pudo preparar el mapa interactivo. Consulta las direcciones de las sucursales.');
  }
})();
