<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/app_paths.php';
require_once __DIR__ . '/config/payment.php';
$currentUser = require_authenticated_user();
$appBasePath = app_base_path();
$transferDetails = transfer_payment_details();
$transferDataPresent = $transferDetails['bank'] !== '' &&
    $transferDetails['clabe'] !== '' &&
    $transferDetails['holder'] !== '';
$transferClabeValid = transfer_clabe_is_valid($transferDetails['clabe']);
$transferConfigured = $transferDataPresent && $transferClabeValid;
if (!isset($_SESSION['pos_csrf'])) {
    $_SESSION['pos_csrf'] = bin2hex(random_bytes(32));
}
if ($currentUser['role'] === 'vendedor' && !isset($_SESSION['messages_csrf'])) {
    $_SESSION['messages_csrf'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <meta name="app-base-path" content="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description" content="Punto de venta OmniStock para gestionar cobros, tickets e inventario.">
  <meta name="robots" content="noindex, nofollow">
  <title>OmniStock POS</title>
  <script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/app.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/advanced-features.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/theme-toggle.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand" href="#" aria-label="OmniStock inicio">
        <span class="brand-mark">O</span>
        <span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span>
      </a>
      <div class="sidebar-label">PUNTO DE VENTA</div>
      <nav class="main-nav" aria-label="Navegación principal">
        <a class="nav-item active" href="#" aria-current="page">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
          <span>Terminal de venta</span>
        </a>
        <?php if ($currentUser['role'] === 'admin'): ?>
          <a class="nav-item" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/admin/reports.php">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/></svg>
            <span>Reportes</span>
          </a>
          <a class="nav-item" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/admin/sucursal-resumen.php">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/></svg>
            <span>Resumen de sucursales</span>
          </a>
        <?php endif; ?>
        <a class="nav-item" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/resenas.php">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z"/></svg>
          <span>Reseñas</span>
        </a>
        <a class="nav-item" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/mapa-sucursales.php">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
          <span>Mapa de sucursales</span>
        </a>
      </nav>
      <div class="sidebar-bottom">
        <div class="sync-card">
          <span class="sync-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9a7 7 0 0 1 11.6-2L20 12M4 12l2.8 5a7 7 0 0 0 11.6-2"/></svg></span>
          <div><strong>Modo local</strong><small>Listo para vender</small></div>
          <span class="online-dot" title="Conectado"></span>
        </div>
        <div class="user-profile">
          <div class="avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['username'], 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="user-copy"><strong><?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= $currentUser['role'] === 'admin' ? 'Administrador' : 'Vendedor' ?></small></div>
          <a class="more-button" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/logout.php" aria-label="Cerrar sesión" title="Cerrar sesión">↪</a>
        </div>
      </div>
    </aside>

    <main class="workspace">
      <?php if (($_GET['access_denied'] ?? '') === 'reports'): ?>
        <div class="access-denied-notice" role="alert">Acceso denegado: los reportes están disponibles únicamente para administradores.</div>
      <?php endif; ?>
      <header class="topbar">
        <div class="breadcrumbs"><span>Operaciones</span><span class="crumb-separator">/</span><strong>Punto de venta</strong></div>
        <div class="topbar-right">
          <?php if ($currentUser['role'] === 'vendedor'): ?>
            <button type="button" class="warehouse-message-bell" id="warehouse-message-bell" aria-label="Avisos de almacén">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4"/><path d="M12 5v4m0 3h.01"/></svg>
              <span class="warehouse-message-label">Avisos</span>
              <span class="warehouse-message-badge" id="warehouse-message-badge" hidden></span>
            </button>
          <?php endif; ?>
          <span class="live-status"><i></i> Terminal activa</span>
          <span class="topbar-divider"></span>
          <time id="clock"></time>
          <?php require __DIR__ . '/components/atoms/theme-toggle.php'; ?>
        </div>
      </header>

      <div class="pos-layout">
        <section class="catalog" aria-labelledby="catalog-title">
          <div class="page-heading">
            <div>
              <p class="eyebrow">VENTA RÁPIDA</p>
              <h1 id="catalog-title">Punto de venta</h1>
              <p class="subtitle">Selecciona productos para agregarlos al ticket.</p>
            </div>
            <div class="page-heading-actions">
              <button class="history-button" id="open-history" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5m4-1v5l3 2"/></svg><span>Historial del día</span></button>
              <div class="date-chip"><span class="calendar-icon">▦</span><span id="today"></span></div>
            </div>
          </div>

          <div class="catalog-toolbar">
            <label class="search-box">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
              <input id="search-input" type="search" placeholder="Buscar producto o código..." autocomplete="off" aria-label="Buscar producto">
              <kbd>⌘ K</kbd>
            </label>
            <button class="filter-button" id="refresh-button" type="button" title="Actualizar catálogo">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9a7 7 0 0 1 11.6-2L20 12M4 12l2.8 5a7 7 0 0 0 11.6-2"/></svg>
              <span>Actualizar</span>
            </button>
            <button class="inventory-alert-button" id="inventory-alert-button" type="button" hidden>
              <span class="inventory-alert-dot"></span><span id="inventory-alert-count">0</span> con stock crítico
            </button>
          </div>

          <div class="category-row" id="category-row" role="group" aria-label="Filtrar por categoría">
            <button class="category-pill selected" type="button" data-category="all">Todos <span id="product-count">0</span></button>
          </div>

          <div id="catalog-status" class="catalog-status" role="status" aria-live="polite">
            <span class="loading-spinner"></span> Cargando catálogo...
          </div>
          <div class="product-grid" id="product-grid"></div>
        </section>

        <aside class="ticket-panel" aria-labelledby="ticket-title">
          <div class="ticket-header">
            <div>
              <div class="ticket-title-row"><h2 id="ticket-title">Ticket actual</h2><span class="ticket-number" id="ticket-number">#0001</span></div>
              <p class="ticket-subtitle">Venta en curso <span class="ticket-live-dot"></span></p>
            </div>
            <button class="icon-button" id="clear-cart" type="button" title="Vaciar ticket" aria-label="Vaciar ticket">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/></svg>
            </button>
          </div>

          <div class="ticket-customer">
            <div class="customer-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></div>
            <div><small>CLIENTE</small><strong>Venta de mostrador</strong></div>
            <button type="button" class="customer-change" title="Selección de cliente no disponible">+</button>
          </div>

          <div class="cart-heading"><span>PRODUCTO</span><span>CANT.</span><span>TOTAL</span></div>
          <div id="cart-items" class="cart-items">
            <div class="empty-cart">
              <div class="empty-cart-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 12.2a2 2 0 0 0 2 1.8h8.6a2 2 0 0 0 2-1.6L21 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg></div>
              <strong>Tu ticket está vacío</strong>
              <span>Agrega productos desde el catálogo</span>
            </div>
          </div>

          <div class="ticket-summary">
            <div class="summary-row"><span>Subtotal</span><strong id="subtotal">$0.00</strong></div>
            <div class="summary-row tax-row"><label for="tax-rate">IVA <span>(<input id="tax-rate" type="number" min="0" max="100" step="0.01" value="16" aria-label="Porcentaje de IVA">%)</span></label><strong id="tax-amount">$0.00</strong></div>
            <div class="summary-total"><span>Total a pagar</span><strong id="grand-total">$0.00</strong></div>
            <button class="checkout-button" id="checkout-button" type="button" disabled>
              <span>Completar venta</span><span id="checkout-total">$0.00</span>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </button>
            <p class="checkout-note">El stock se confirma al completar la venta.</p>
            <section class="currency-widget" id="currency-converter" aria-labelledby="currency-title">
              <div class="currency-widget-heading"><h3 id="currency-title">Conversor de divisas</h3><span aria-hidden="true">⇄</span></div>
              <div class="currency-controls">
                <label for="currency-amount">Importe</label>
                <input id="currency-amount" type="number" min="0" step="0.01" value="1" inputmode="decimal" aria-label="Importe a convertir">
                <label class="sr-only" for="currency-from">Moneda de origen</label>
                <select id="currency-from" aria-label="Moneda de origen">
                  <option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option><option value="MXN">MXN</option>
                </select>
              </div>
              <strong class="currency-result" id="currency-result">Cargando tasas…</strong>
              <small class="currency-note" id="currency-note" role="status" aria-live="polite">Tasas referenciales de demostración; no se usan para cobrar.</small>
            </section>
          </div>
        </aside>
      </div>
    </main>
  </div>
  <dialog class="pos-modal" id="checkout-modal">
    <form class="pos-modal-card" id="checkout-form">
      <header class="pos-modal-header"><div><span class="eyebrow">CONFIRMAR VENTA</span><h2>Completar venta</h2></div><button class="pos-modal-close" type="button" data-close-pos-modal aria-label="Cerrar">×</button></header>
      <div class="pos-modal-body">
        <div class="checkout-total-card"><span>Total a cobrar</span><strong id="checkout-modal-total">$0.00</strong></div>
        <label class="checkout-field">Método de pago
          <select id="payment-method" name="payment_method"><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia" <?= $transferConfigured ? '' : 'disabled' ?>>Transferencia<?= $transferConfigured ? '' : ($transferDataPresent ? ' · CLABE inválida' : ' · requiere configuración') ?></option></select>
        </label>
        <label class="checkout-field" id="card-pin-field" hidden>PIN de tarjeta (4 dígitos)
          <input id="card-pin" name="card_pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" placeholder="••••" aria-describedby="card-payment-note">
        </label>
        <p class="card-payment-note" id="card-payment-note" hidden>Simulación local: el PIN no se envía, guarda ni valida con el banco.</p>
        <label class="checkout-field" id="cash-field">Efectivo recibido
          <input id="cash-received" name="cash_received" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
        </label>
        <section class="transfer-panel" id="transfer-panel" aria-live="polite" hidden>
          <p class="transfer-panel-title">Datos para realizar la transferencia</p>
          <dl class="transfer-details">
            <div><dt>Banco</dt><dd><?= htmlspecialchars($transferDetails['bank'], ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>CLABE interbancaria</dt><dd><?= htmlspecialchars($transferDetails['clabe'], ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Titular</dt><dd><?= htmlspecialchars($transferDetails['holder'], ENT_QUOTES, 'UTF-8') ?></dd></div>
          </dl>
          <p class="transfer-instructions">Al elegir Transferencia se abrirá la pantalla con los datos bancarios y el temporizador antes de generar el ticket.</p>
        </section>
        <?php if (!$transferDataPresent): ?>
          <p class="transfer-configuration-note">Para habilitar transferencias, configura banco, CLABE y titular en el entorno de OmniStock.</p>
        <?php elseif (!$transferClabeValid): ?>
          <p class="transfer-configuration-note" role="alert">La CLABE debe contener exactamente 18 dígitos numéricos. Verifica los datos antes de habilitar transferencias.</p>
        <?php endif; ?>
        <div class="change-line" id="change-line"><span>Cambio</span><strong id="change-amount">$0.00</strong></div>
        <div class="card-processing" id="card-processing" role="status" aria-live="polite" hidden>
          <span class="loading-spinner" aria-hidden="true"></span><span>Procesando pago con tarjeta...</span>
        </div>
        <p class="checkout-error" id="checkout-error" role="alert"></p>
      </div>
      <footer class="pos-modal-footer"><button type="button" class="checkout-cancel" data-close-pos-modal>Cancelar</button><button type="submit" class="checkout-confirm" id="confirm-sale">Confirmar cobro <span>→</span></button></footer>
    </form>
  </dialog>
  <dialog class="pos-modal transfer-wait-modal" id="transfer-wait-modal" aria-labelledby="transfer-wait-title">
    <section class="pos-modal-card">
      <header class="pos-modal-header">
        <div><span class="eyebrow">PAGO POR TRANSFERENCIA</span><h2 id="transfer-wait-title">Realiza tu transferencia</h2></div>
        <button class="pos-modal-close" id="cancel-transfer-close" type="button" aria-label="Cancelar transferencia">×</button>
      </header>
      <div class="pos-modal-body">
        <div class="checkout-total-card"><span>Total a transferir</span><strong id="transfer-wait-total">$0.00</strong></div>
        <dl class="transfer-details transfer-wait-details">
          <div><dt>Banco</dt><dd><?= htmlspecialchars($transferDetails['bank'], ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>CLABE interbancaria</dt><dd><span id="transfer-wait-clabe"><?= htmlspecialchars($transferDetails['clabe'], ENT_QUOTES, 'UTF-8') ?></span><button class="copy-clabe-button" id="copy-transfer-clabe" type="button" <?= $transferDetails['clabe'] === '' ? 'disabled' : '' ?>>Copiar</button></dd></div>
          <div><dt>Titular</dt><dd><?= htmlspecialchars($transferDetails['holder'], ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>Concepto de pago</dt><dd><?= htmlspecialchars($transferDetails['concept'], ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>Referencia numérica</dt><dd><?= htmlspecialchars($transferDetails['reference'], ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>Folio de autorización (simulado)</dt><dd><?= htmlspecialchars($transferDetails['authorization'], ENT_QUOTES, 'UTF-8') ?></dd></div>
        </dl>
        <label class="checkout-field" for="transfer-reference">Referencia de la transferencia
          <input id="transfer-reference" name="transfer_reference" type="text" maxlength="50" autocomplete="off" data-default-reference="<?= htmlspecialchars($transferDetails['reference'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ingresa el folio confirmado" required disabled>
        </label>
        <p class="transfer-confirmation-note">Demostración ficticia: al continuar se registrará la venta y se actualizará el stock, pero OmniStock no envía ni verifica pagos bancarios.</p>
        <div class="transfer-countdown" id="transfer-countdown" hidden>
          <span id="transfer-countdown-status">Después de confirmar, el ticket estará disponible en</span>
          <strong id="transfer-countdown-time">00:30</strong>
        </div>
        <p class="checkout-error" id="transfer-wait-error" role="alert"></p>
      </div>
      <footer class="pos-modal-footer transfer-wait-footer">
        <button type="button" class="checkout-cancel" id="cancel-transfer">Cancelar</button>
        <button type="button" class="checkout-confirm" id="start-transfer-countdown">Simular transferencia y continuar <span>→</span></button>
      </footer>
    </section>
  </dialog>
  <dialog class="pos-modal history-modal" id="history-modal">
    <section class="pos-modal-card">
      <header class="pos-modal-header"><div><span class="eyebrow">TURNO ACTUAL</span><h2>Historial del día</h2></div><button class="pos-modal-close" type="button" data-close-pos-modal aria-label="Cerrar">×</button></header>
      <div class="history-summary"><div><span>VENTAS REALIZADAS</span><strong id="history-count">0</strong></div><div><span>TOTAL VENDIDO</span><strong id="history-total">$0.00</strong></div></div>
      <div class="history-list" id="history-list"><p class="history-empty">Cargando ventas...</p></div>
      <footer class="pos-modal-footer"><button type="button" class="checkout-cancel" data-close-pos-modal>Cerrar</button></footer>
    </section>
  </dialog>
  <div class="toast" id="toast" role="status" aria-live="polite"></div>
  <?php if ($currentUser['role'] === 'vendedor'): ?>
    <dialog class="pos-modal warehouse-message-modal" id="warehouse-message-modal">
      <section class="pos-modal-card">
        <header class="pos-modal-header"><div><span class="eyebrow">COMUNICACIÓN INTERNA</span><h2>Avisos de almacén</h2></div><button class="pos-modal-close" type="button" data-close-warehouse-messages aria-label="Cerrar">×</button></header>
        <form class="warehouse-vendor-compose" id="seller-message-form">
          <label>Enviar nota al administrador<input name="subject" maxlength="150" required placeholder="Asunto"></label>
          <label>Mensaje<textarea name="message" rows="3" maxlength="2000" required placeholder="Escribe una solicitud o nota para administración..."></textarea></label>
          <button type="submit" class="warehouse-help-submit">Enviar mensaje</button>
          <span class="warehouse-vendor-compose-feedback" role="status"></span>
        </form>
        <div class="warehouse-message-list" id="warehouse-message-list"><div class="warehouse-message-empty"><span>♧</span><strong>Cargando avisos...</strong></div></div>
        <footer class="pos-modal-footer"><button type="button" class="checkout-cancel" data-close-warehouse-messages>Cerrar</button></footer>
      </section>
    </dialog>
    <div class="warehouse-message-toast" id="warehouse-message-toast" role="status" aria-live="polite" hidden>
      <span class="warehouse-toast-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4"/></svg></span>
      <span class="warehouse-toast-copy"><strong id="warehouse-message-toast-title">Nuevo aviso de almacén</strong><small id="warehouse-message-toast-body"></small></span>
      <button type="button" id="warehouse-message-toast-open">Leer</button>
    </div>
  <?php endif; ?>
  <script>window.omniStockPOS = { csrf: <?= json_encode($_SESSION['pos_csrf']) ?>, messagesCsrf: <?= json_encode($_SESSION['messages_csrf'] ?? '') ?>, role: <?= json_encode($currentUser['role']) ?> };</script>
  <script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/app.js" defer></script>
  <script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/atomic/atoms/currency-converter.js" defer></script>
  <?php if ($currentUser['role'] === 'vendedor'): ?>
    <script>window.omniStockMessages = { csrf: <?= json_encode($_SESSION['messages_csrf']) ?> };</script>
    <script src="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/warehouse-messages.js" defer></script>
  <?php endif; ?>
</body>
</html>
