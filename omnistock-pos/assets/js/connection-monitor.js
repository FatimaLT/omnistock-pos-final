/**
 * OmniStock - Connection Monitor
 * Monitorea la conexión a internet y muestra alerta discreta
 */

class ConnectionMonitor {
  constructor() {
    this.isOnline = navigator.onLine;
    this.checkInterval = null;
    this.alertElement = null;
    this.init();
  }

  init() {
    // Escuchar cambios de conexión
    window.addEventListener('online', () => this.handleOnline());
    window.addEventListener('offline', () => this.handleOffline());
    
    // Verificar periódicamente con fetch
    this.checkInterval = setInterval(() => this.checkConnection(), 30000);
  }

  checkConnection() {
    fetch(window.location.href, { method: 'HEAD', cache: 'no-store' })
      .then(() => {
        if (!this.isOnline) {
          this.handleOnline();
        }
      })
      .catch(() => {
        if (this.isOnline) {
          this.handleOffline();
        }
      });
  }

  handleOffline() {
    if (this.isOnline) {
      this.isOnline = false;
      this.showAlert();
      console.warn('⚠️ OmniStock: Conexión perdida. Algunas funciones pueden estar limitadas.');
    }
  }

  handleOnline() {
    if (!this.isOnline) {
      this.isOnline = true;
      this.hideAlert();
      console.log('✅ OmniStock: Conexión restaurada.');
    }
  }

  showAlert() {
    if (!this.alertElement) {
      this.alertElement = document.createElement('div');
      this.alertElement.className = 'alert-connection-lost';
      this.alertElement.innerHTML = `
        <strong>⚠️ Conexión perdida</strong>
        <span style="font-size: 0.875rem; display: block; margin-top: 0.25rem;">
          Algunas funciones pueden estar limitadas. Verifica tu conexión a internet.
        </span>
      `;
      document.body.appendChild(this.alertElement);
    }
  }

  hideAlert() {
    if (this.alertElement) {
      this.alertElement.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => {
        if (this.alertElement && this.alertElement.parentElement) {
          this.alertElement.remove();
          this.alertElement = null;
        }
      }, 300);
    }
  }

  destroy() {
    if (this.checkInterval) {
      clearInterval(this.checkInterval);
    }
    this.hideAlert();
  }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
  window.connectionMonitor = new ConnectionMonitor();
});
