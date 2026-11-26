<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/warehouse_messages.php';
require_once __DIR__ . '/../config/stock_alerts.php';
$user = require_admin_user();

foreach (['catalog_csrf', 'messages_csrf'] as $tokenName) {
    if (!isset($_SESSION[$tokenName])) {
        $_SESSION[$tokenName] = bin2hex(random_bytes(32));
    }
}

$errorMessage = '';
$successMessage = $_SESSION['warehouse_message_flash'] ?? '';
unset($_SESSION['warehouse_message_flash']);
$subject = '';
$messageBody = '';
$messageRows = [];
$helpRows = [];
$stockAlertCount = 0;
$messagesAvailable = false;

try {
    $pdo = database_connection();
    $userColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `usuarios`')->fetchAll() as $column) {
        $userColumns[strtolower((string) $column['Field'])] = (string) $column['Field'];
    }
    $senderColumn = null;
    foreach (['nombre_usuario', 'nombre', 'username', 'email', 'correo'] as $candidate) {
        if (isset($userColumns[$candidate])) {
            $senderColumn = $userColumns[$candidate];
            break;
        }
    }
    if ($senderColumn === null) {
        throw new RuntimeException('La tabla usuarios no tiene una columna reconocible para mostrar al emisor.');
    }
    $senderIdentifier = '`' . str_replace('`', '``', $senderColumn) . '`';
    $messageRows = $pdo->query(
        "SELECT id, asunto, mensaje, imagen_adjunta, leido, fecha_envio
         FROM mensajes_almacen WHERE receptor_rol = 'vendedor'
         ORDER BY fecha_envio DESC, id DESC LIMIT 25"
    )->fetchAll();
    $helpQuery = $pdo->prepare(
        "SELECT m.id, m.emisor_id, u.$senderIdentifier AS emisor_nombre,
                m.asunto, m.mensaje, m.fecha_envio, m.tipo, m.producto_id,
                m.cantidad_sugerida, m.cantidad_recibida, m.estado,
                (r.usuario_id IS NOT NULL) AS leido
         FROM mensajes_almacen AS m
         INNER JOIN usuarios AS u ON u.id = m.emisor_id
         LEFT JOIN mensajes_almacen_lecturas AS r
           ON r.mensaje_id = m.id AND r.usuario_id = :admin_id
         WHERE m.receptor_rol = 'admin'
         ORDER BY m.fecha_envio DESC, m.id DESC
         LIMIT 50"
    );
    $helpQuery->execute(['admin_id' => $user['id']]);
    $helpRows = $helpQuery->fetchAll();
    $messagesAvailable = true;
    $stockAlertCount = count(critical_stock_products($pdo));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) $_SESSION['messages_csrf'], $submittedToken)) {
            throw new RuntimeException('La solicitud expiró. Actualiza la página e inténtalo nuevamente.');
        }

        $subject = trim((string) ($_POST['asunto'] ?? ''));
        $messageBody = trim((string) ($_POST['mensaje'] ?? ''));
        if ($subject === '' || mb_strlen($subject, 'UTF-8') > 150) {
            throw new RuntimeException('El asunto es obligatorio y no debe exceder 150 caracteres.');
        }
        if ($messageBody === '' || mb_strlen($messageBody, 'UTF-8') > 5000) {
            throw new RuntimeException('El aviso es obligatorio y no debe exceder 5000 caracteres.');
        }

        $imagePath = upload_warehouse_message_image($_FILES['imagen_adjunta'] ?? []);
        try {
            $insert = $pdo->prepare(
                "INSERT INTO mensajes_almacen
                 (emisor_id, receptor_rol, asunto, mensaje, imagen_adjunta)
                 VALUES (:sender_id, 'vendedor', :subject, :message, :image)"
            );
            $insert->execute([
                'sender_id' => $user['id'],
                'subject' => $subject,
                'message' => $messageBody,
                'image' => $imagePath,
            ]);
        } catch (Throwable $exception) {
            delete_warehouse_message_image($imagePath);
            throw $exception;
        }

        $_SESSION['warehouse_message_flash'] = 'Aviso enviado a los vendedores.';
        header('Location: messages.php');
        exit;
    }
} catch (PDOException $exception) {
    error_log('OmniStock warehouse message administration: ' . $exception->getMessage());
    $messagesAvailable = false;
    $errorMessage = 'No se pudieron cargar o enviar los avisos. Verifica MySQL y que hayas importado el esquema de mensajería.';
} catch (RuntimeException $exception) {
    $errorMessage = $exception->getMessage();
    if (isset($pdo)) {
        try {
            $messageRows = $pdo->query(
                "SELECT id, asunto, mensaje, imagen_adjunta, leido, fecha_envio
                 FROM mensajes_almacen WHERE receptor_rol = 'vendedor'
                 ORDER BY fecha_envio DESC, id DESC LIMIT 25"
            )->fetchAll();
            $helpQuery = $pdo->prepare(
                "SELECT m.id, m.emisor_id, u.$senderIdentifier AS emisor_nombre,
                        m.asunto, m.mensaje, m.fecha_envio, m.tipo, m.producto_id,
                        m.cantidad_sugerida, m.cantidad_recibida, m.estado,
                        (r.usuario_id IS NOT NULL) AS leido
                 FROM mensajes_almacen AS m
                 INNER JOIN usuarios AS u ON u.id = m.emisor_id
                 LEFT JOIN mensajes_almacen_lecturas AS r
                   ON r.mensaje_id = m.id AND r.usuario_id = :admin_id
                 WHERE m.receptor_rol = 'admin'
                 ORDER BY m.fecha_envio DESC, m.id DESC
                 LIMIT 50"
            );
            $helpQuery->execute(['admin_id' => $user['id']]);
            $helpRows = $helpQuery->fetchAll();
        } catch (Throwable $loadException) {
            error_log('OmniStock warehouse message history: ' . $loadException->getMessage());
        }
    }
} catch (Throwable $exception) {
    error_log('OmniStock warehouse messages administration: ' . $exception->getMessage());
    $errorMessage = 'No se pudieron cargar o enviar los avisos. Verifica MySQL y que hayas importado el esquema de mensajería.';
}

function warehouse_message_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <title>Mensajería de almacén · OmniStock POS</title>
  <script src="../assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="../assets/css/app.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/theme-toggle.css">
</head>
<body class="admin-page">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="brand admin-brand" href="index.php"><span class="brand-mark">O</span><span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span></a>
      <div class="sidebar-label">ADMINISTRACIÓN</div>
      <nav class="admin-nav">
        <a class="admin-nav-link" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16l-1.5 13h-13L4 7Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg><span>Catálogo</span></a>
        <a class="admin-nav-link" href="reports.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/></svg><span>Reportes</span></a>
        <a class="admin-nav-link selected" href="messages.php" aria-current="page"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H8l-4 4V5Z"/><path d="M8 9h8m-8 4h5"/></svg><span>Mensajería</span></a>
        <a class="admin-nav-link" href="users.php"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1m2-11a3 3 0 0 1 0 6m1 1a5 5 0 0 1 3 4"/></svg><span>Usuarios</span></a>
        <?php require __DIR__ . '/_stock_alert_modal.php'; ?>
        <a class="admin-nav-link" href="../index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Punto de venta</span></a>
      </nav>
      <div class="admin-sidebar-footer">
        <div class="admin-user-avatar"><?= warehouse_message_escape(strtoupper(substr($user['username'], 0, 2))) ?></div>
        <div class="admin-user-copy"><strong><?= warehouse_message_escape($user['username']) ?></strong><span>Administrador</span></div>
        <a class="admin-logout" href="../logout.php" title="Cerrar sesión" aria-label="Cerrar sesión">↪</a>
      </div>
    </aside>

    <main class="admin-main">
      <header class="admin-topbar"><div><span>Administración</span><b>/</b><strong>Mensajería de almacén</strong></div><div class="theme-header-actions"><a href="../index.php" class="view-pos-link">Abrir POS <span>↗</span></a><?php require __DIR__ . '/../components/atoms/theme-toggle.php'; ?></div></header>
      <div class="admin-content warehouse-admin-content">
        <section class="admin-heading">
          <div><p class="eyebrow">COMUNICACIÓN INTERNA</p><h1>Avisos de almacén</h1><p>Envía instrucciones, incidencias y novedades al equipo de vendedores.</p></div>
          <span class="message-audience"><i></i> Para vendedores</span>
        </section>

        <?php if ($errorMessage !== ''): ?><div class="admin-alert error" role="alert"><?= warehouse_message_escape($errorMessage) ?></div><?php endif; ?>
        <?php if ($successMessage !== ''): ?><div class="admin-alert success" role="status"><?= warehouse_message_escape($successMessage) ?></div><?php endif; ?>

        <section class="warehouse-admin-grid">
          <article class="warehouse-compose-card">
            <header><span class="warehouse-compose-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H8l-4 4V5Z"/><path d="M8 9h8m-8 4h5"/></svg></span><div><h2>Redactar aviso</h2><p>El equipo lo recibirá en su terminal POS.</p></div></header>
            <form method="post" enctype="multipart/form-data" class="warehouse-compose-form">
              <input type="hidden" name="csrf_token" value="<?= warehouse_message_escape($_SESSION['messages_csrf']) ?>">
              <label class="form-field"><span class="field-label">Asunto</span><input name="asunto" maxlength="150" required value="<?= warehouse_message_escape($subject) ?>" placeholder="Ej. Llegó mercancía nueva"></label>
              <label class="form-field"><span class="field-label">Mensaje para vendedores</span><textarea name="mensaje" rows="6" maxlength="5000" required placeholder="Escribe los detalles del aviso..."><?= warehouse_message_escape($messageBody) ?></textarea><small class="message-field-hint">Máximo 5000 caracteres.</small></label>
              <div class="warehouse-attachment-row">
                <label class="warehouse-attachment-picker" for="warehouse-message-image"><input type="file" id="warehouse-message-image" name="imagen_adjunta" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"><span class="upload-icon">↑</span><span><strong>Adjuntar imagen</strong><small>PNG, JPG o WEBP · Máximo 5 MB</small></span></label>
                <div class="warehouse-attachment-preview"><img id="warehouse-message-image-preview" alt="Vista previa de imagen adjunta" hidden><span id="warehouse-message-image-empty">Sin imagen</span></div>
                <small class="warehouse-image-feedback" id="warehouse-message-image-feedback" role="status"></small>
              </div>
              <button type="submit" class="primary-button warehouse-send-button" <?= $messagesAvailable ? '' : 'disabled' ?>><span>➤</span> Enviar aviso a vendedores</button>
            </form>
          </article>

          <section class="warehouse-history-panel">
            <header><div><h2>Avisos enviados</h2><p>Últimos 25 mensajes al equipo</p></div><span class="warehouse-history-count"><?= count($messageRows) ?></span></header>
            <?php if (!$messageRows): ?><div class="warehouse-history-empty">Aún no has enviado avisos.</div>
            <?php else: ?><div class="warehouse-history-list">
              <?php foreach ($messageRows as $row): ?>
                <article class="warehouse-history-item">
                  <div class="warehouse-history-item-top"><strong><?= warehouse_message_escape($row['asunto']) ?></strong><time><?= warehouse_message_escape(date('d/m/Y H:i', strtotime((string) $row['fecha_envio']))) ?></time></div>
                  <p><?= warehouse_message_escape(mb_strimwidth((string) $row['mensaje'], 0, 150, '…', 'UTF-8')) ?></p>
                  <span class="warehouse-delivery-status"><i></i> Enviado a vendedores<?= !empty($row['imagen_adjunta']) ? ' · Incluye imagen' : '' ?></span>
                </article>
              <?php endforeach; ?>
            </div><?php endif; ?>
          </section>
          <section class="warehouse-history-panel warehouse-help-inbox" aria-labelledby="warehouse-help-heading">
            <header><div><h2 id="warehouse-help-heading">Solicitudes de vendedores</h2><p>Apoyos y reabastecimientos pendientes de atender</p></div><span class="warehouse-history-count warehouse-help-count" id="warehouse-help-count"><?= count(array_filter($helpRows, static fn(array $row): bool => !(bool) $row['leido'])) ?></span></header>
            <div class="warehouse-help-list" id="warehouse-help-list">
              <?php if (!$helpRows): ?><div class="warehouse-history-empty">Aún no hay solicitudes de vendedores.</div>
              <?php else: foreach ($helpRows as $row): ?>
                <article class="warehouse-help-item<?= (bool) $row['leido'] ? '' : ' unread' ?>" data-help-item="<?= (int) $row['id'] ?>">
                  <div class="warehouse-history-item-top"><strong><?= warehouse_message_escape($row['asunto']) ?></strong><time><?= warehouse_message_escape(date('d/m/Y H:i', strtotime((string) $row['fecha_envio']))) ?></time></div>
                  <span class="warehouse-help-sender">De: <?= warehouse_message_escape($row['emisor_nombre']) ?></span>
                  <p><?= nl2br(warehouse_message_escape($row['mensaje'])) ?></p>
                  <?php if (($row['tipo'] ?? 'aviso') === 'solicitud_reabastecimiento' && ($row['estado'] ?? 'pendiente') === 'pendiente'): ?>
                    <form class="restock-fulfill-form" data-fulfill-restock="<?= (int) $row['id'] ?>">
                      <label>Unidades recibidas <input type="number" name="quantity_received" min="1" max="1000000" value="<?= max(1, (int) ($row['cantidad_sugerida'] ?? 1)) ?>" required></label>
                      <button type="submit" class="warehouse-help-mark-read">Actualizar stock y atender</button>
                    </form>
                  <?php elseif (($row['tipo'] ?? 'aviso') === 'solicitud_reabastecimiento'): ?>
                    <span class="warehouse-help-read-label">Atendida · <?= (int) ($row['cantidad_recibida'] ?? 0) ?> unidades agregadas</span>
                  <?php elseif (!(bool) $row['leido']): ?><button type="button" class="warehouse-help-mark-read" data-mark-help-read="<?= (int) $row['id'] ?>">Marcar como leído</button><?php else: ?><span class="warehouse-help-read-label">Leído</span><?php endif; ?>
                  <form class="warehouse-admin-reply-form" data-admin-reply="<?= (int) $row['id'] ?>">
                    <label>Responder al vendedor<textarea name="message" rows="2" maxlength="2000" required placeholder="Escribe una respuesta..."></textarea></label>
                    <button type="submit" class="warehouse-help-mark-read">Enviar respuesta</button>
                    <span class="warehouse-admin-reply-feedback" role="status"></span>
                  </form>
                </article>
              <?php endforeach; endif; ?>
            </div>
          </section>
        </section>
      </div>
    </main>
  </div>
  <div class="warehouse-message-toast" id="warehouse-admin-message-toast" role="status" aria-live="polite" hidden>
    <span class="warehouse-toast-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H8l-4 4V5Z"/><path d="M8 9h8m-8 4h5"/></svg></span>
    <span class="warehouse-toast-copy"><strong id="warehouse-admin-message-toast-title">Nueva solicitud de apoyo</strong><small id="warehouse-admin-message-toast-text"></small></span>
    <button type="button" id="warehouse-admin-message-toast-open">Ver</button>
  </div>
  <script>window.omniStockAlerts = { csrf: <?= json_encode($_SESSION['catalog_csrf']) ?>, count: <?= (int) $stockAlertCount ?> };</script>
  <script>window.omniStockMessages = { csrf: <?= json_encode($_SESSION['messages_csrf']) ?> };</script>
  <script src="../assets/js/admin-messages.js" defer></script>
  <script src="../assets/js/admin-warehouse-inbox.js" defer></script>
  <script src="../assets/js/stock-alerts.js" defer></script>
</body>
</html>
