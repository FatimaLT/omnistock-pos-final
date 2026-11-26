<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
$user = require_authenticated_user();
$saleId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if ($saleId === false || $saleId === null || $saleId < 1) {
    http_response_code(404);
    exit('No se encontró el comprobante solicitado.');
}

try {
    $pdo = database_connection();
    $saleQuery = $pdo->prepare(
        'SELECT * FROM ventas WHERE id = :id AND (:is_admin = 1 OR vendedor_id = :user_id) LIMIT 1'
    );
    $saleQuery->execute([
        'id' => $saleId,
        'is_admin' => $user['role'] === 'admin' ? 1 : 0,
        'user_id' => $user['id'],
    ]);
    $sale = $saleQuery->fetch();
    if (!$sale) {
        http_response_code(404);
        exit('No se encontró el comprobante solicitado.');
    }

    $detailsQuery = $pdo->prepare(
        'SELECT nombre_producto, precio_unitario, cantidad, subtotal_linea
         FROM detalle_ventas WHERE venta_id = :sale_id ORDER BY id'
    );
    $detailsQuery->execute(['sale_id' => $saleId]);
    $details = $detailsQuery->fetchAll();
} catch (Throwable $exception) {
    error_log('OmniStock receipt: ' . $exception->getMessage());
    http_response_code(503);
    exit('No se pudo cargar el comprobante. Inténtalo nuevamente.');
}

function receipt_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$storeName = getenv('OMNISTOCK_STORE_NAME') ?: 'OMNISTOCK';
$satisfactionSaleId = (int) $sale['id'];
$paymentLabels = [
    'efectivo' => 'Efectivo',
    'tarjeta' => 'Tarjeta',
    'transferencia' => 'Transferencia',
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f4f4f5">
  <title>Ticket #<?= (int) $sale['id'] ?> · <?= receipt_escape($storeName) ?></title>
  <link rel="stylesheet" href="assets/css/ticket.css">
  <link rel="stylesheet" href="assets/css/receipt-satisfaction.css?v=3">
  <script src="assets/js/atomic/organisms/pos-satisfaction.js" defer></script>
</head>
<body>
  <div class="receipt-actions">
    <a href="views/productos.php">← Volver al punto de venta</a>
    <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>
  <main class="receipt">
    <header class="receipt-header">
      <div class="receipt-logo">O</div>
      <h1><?= receipt_escape($storeName) ?></h1>
      <p>COMPROBANTE DE VENTA</p>
    </header>
    <div class="receipt-meta">
      <div><span>Ticket</span><strong>#<?= (int) $sale['id'] ?></strong></div>
      <div><span>Fecha</span><strong><?= receipt_escape(date('d/m/Y H:i', strtotime((string) $sale['fecha_venta']))) ?></strong></div>
      <div><span>Atendió</span><strong><?= receipt_escape($sale['vendedor_nombre']) ?></strong></div>
    </div>
    <div class="receipt-divider"></div>
    <div class="receipt-columns"><span>DESCRIPCIÓN</span><span>IMPORTE</span></div>
    <div class="receipt-items">
      <?php foreach ($details as $detail): ?>
        <article class="receipt-item">
          <strong><?= receipt_escape($detail['nombre_producto']) ?></strong>
          <div><span><?= (int) $detail['cantidad'] ?> × $<?= number_format((float) $detail['precio_unitario'], 2) ?></span><strong>$<?= number_format((float) $detail['subtotal_linea'], 2) ?></strong></div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="receipt-divider"></div>
    <div class="receipt-totals">
      <div><span>Subtotal</span><strong>$<?= number_format((float) $sale['subtotal'], 2) ?></strong></div>
      <div><span>IVA (<?= number_format((float) $sale['iva_porcentaje'], 2) ?>%)</span><strong>$<?= number_format((float) $sale['iva'], 2) ?></strong></div>
      <div class="receipt-grand-total"><span>TOTAL</span><strong>$<?= number_format((float) $sale['total'], 2) ?></strong></div>
    </div>
    <div class="receipt-payment">
      <div><span>Forma de pago</span><strong><?= receipt_escape($paymentLabels[$sale['metodo_pago']] ?? $sale['metodo_pago']) ?></strong></div>
      <?php if ($sale['metodo_pago'] === 'efectivo'): ?>
        <div><span>Recibido</span><strong>$<?= number_format((float) $sale['efectivo_recibido'], 2) ?></strong></div>
        <div><span>Cambio</span><strong>$<?= number_format((float) $sale['cambio'], 2) ?></strong></div>
      <?php endif; ?>
      <?php
        $transferReference = $sale['referencia_transferencia'] ?? $sale['transferencia_referencia'] ?? '';
      ?>
      <?php if ($sale['metodo_pago'] === 'transferencia' && $transferReference !== ''): ?>
        <div><span>Referencia</span><strong><?= receipt_escape($transferReference) ?></strong></div>
      <?php endif; ?>
    </div>
    <footer class="receipt-footer"><strong>¡Gracias por tu compra!</strong><span>Conserva este comprobante</span></footer>
  </main>
  <section class="receipt-satisfaction-section" aria-label="Encuesta de satisfacción">
    <?php require __DIR__ . '/components/molecules/pos-satisfaction-form.php'; ?>
  </section>
</body>
</html>
