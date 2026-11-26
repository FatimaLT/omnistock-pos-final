(() => {
  const storageKey = 'omnistock-theme';
  const root = document.documentElement;
  const themeColor = document.querySelector('meta[name="theme-color"]');
  let storageAvailable = true;
  let theme = 'dark';

  try {
    theme = window.localStorage.getItem(storageKey) === 'light' ? 'light' : 'dark';
  } catch {
    storageAvailable = false;
  }
  const applyTheme = (nextTheme) => {
    root.dataset.theme = nextTheme;
    if (themeColor) themeColor.setAttribute('content', nextTheme === 'light' ? '#FFFFFF' : '#080D19');
  };
  applyTheme(theme);

  document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('#theme-toggle');
    const status = document.querySelector('#theme-toggle-status');
    if (!button) return;

    const updateButton = () => {
      const isLight = root.dataset.theme === 'light';
      button.setAttribute('aria-pressed', String(isLight));
      button.setAttribute('aria-label', `Cambiar a modo ${isLight ? 'oscuro' : 'claro'}`);
    };

    updateButton();
    if (!storageAvailable && status) {
      status.textContent = 'El almacenamiento local no está disponible; el tema no se conservará al navegar.';
    }

    button.addEventListener('click', () => {
      applyTheme(root.dataset.theme === 'light' ? 'dark' : 'light');
      updateButton();
      if (!storageAvailable) return;
      try {
        window.localStorage.setItem(storageKey, root.dataset.theme);
        if (status) status.textContent = '';
      } catch {
        storageAvailable = false;
        if (status) status.textContent = 'No se pudo guardar el tema; el cambio solo durará en esta página.';
      }
    });
  });
})();
