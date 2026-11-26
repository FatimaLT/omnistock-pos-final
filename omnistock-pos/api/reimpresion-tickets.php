<?php
declare(strict_types=1);

/**
 * OmniStock - API Reimpresión de Tickets
 * Permite buscar y reimprimir tickets anteriores
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
require_authenticated_user();

$pdo = database_connection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
  switch ($action) {
    case 'buscar':
      if ($method !== 'GET') {
        throw new Exception('Método no permitido', 405);
      }
      handleBuscarTickets($pdo);
      break;

    case 'obtener':
      if ($method !== 'GET') {
        throw new Exception('Método no permitido', 405);
      }
      handleObtenerTicket($pdo);
      break;

    case 'reimprimir_html':
      if ($method !== 'GET') {
        throw new Exception('Método no permitido', 405);
      }
      handleReimprimirHTML($pdo);
      break;

    default:
      throw new Exception('Acción no encontrada', 404);
  }
} catch (Exception $e) {
  http_response_code($e->getCode() ?: 500);
  echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

/**
 * Buscar tickets por número o fecha
 */
function handleBuscarTickets(PDO $pdo): void {
  $numeroVenta = trim((string) ($_GET['numero'] ?? ''));
  $fechaInicio = trim((string) ($_GET['fecha_inicio'] ?? ''));
  $fechaFin = trim((string) ($_GET['fecha_fin'] ?? date('Y-m-d')));
  $limite = min((int) ($_GET['limite'] ?? 20), 100);

  $sql = 'SELECT id, fecha_venta, total, metodo_pago FROM ventas WHERE 1=1';
  $params = [];

  if (!empty($numeroVenta)) {
    $sql .= ' AND id = :numero';
    $params[':numero'] = (int)$numeroVenta;
  }

  if (!empty($fechaInicio)) {
    $sql .= ' AND DATE(fecha_venta) >= :fecha_inicio';
    $params[':fecha_inicio'] = $fechaInicio;
  }

  $sql .= ' AND DATE(fecha_venta) <= :fecha_fin';
  $params[':fecha_fin'] = $fechaFin;

  $sql .= ' ORDER BY fecha_venta DESC LIMIT ' . (int)$limite;

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['data' => $tickets, 'count' => count($tickets)], JSON_UNESCAPED_UNICODE);
}

/**
 * Obtener detalles completos de un ticket
 */
function handleObtenerTicket(PDO $pdo): void {
  $ventaId = (int) ($_GET['id'] ?? 0);

  if ($ventaId <= 0) {
    throw new Exception('ID de venta inválido', 400);
  }

  // Obtener información de la venta
  $ventaStmt = $pdo->prepare('
    SELECT * FROM ventas WHERE id = :id LIMIT 1
  ');
  $ventaStmt->execute([':id' => $ventaId]);
  $venta = $ventaStmt->fetch(PDO::FETCH_ASSOC);

  if (!$venta) {
    throw new Exception('Venta no encontrada', 404);
  }

  // Obtener detalles de la venta
  $detallesStmt = $pdo->prepare('
    SELECT dv.*, p.codigo, p.nombre 
    FROM detalle_ventas dv
    JOIN productos p ON dv.producto_id = p.id
    WHERE dv.venta_id = :venta_id
  ');
  $detallesStmt->execute([':venta_id' => $ventaId]);
  $detalles = $detallesStmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'venta' => $venta,
    'detalles' => $detalles,
  ], JSON_UNESCAPED_UNICODE);
}

/**
 * Generar HTML del ticket para reimpresión
 */
function handleReimprimirHTML(PDO $pdo): void {
  $ventaId = (int) ($_GET['id'] ?? 0);

  if ($ventaId <= 0) {
    throw new Exception('ID de venta inválido', 400);
  }

  // Obtener información de la venta
  $ventaStmt = $pdo->prepare('
    SELECT * FROM ventas WHERE id = :id LIMIT 1
  ');
  $ventaStmt->execute([':id' => $ventaId]);
  $venta = $ventaStmt->fetch(PDO::FETCH_ASSOC);

  if (!$venta) {
    throw new Exception('Venta no encontrada', 404);
  }

  // Obtener detalles de la venta
  $detallesStmt = $pdo->prepare('
    SELECT dv.*, p.codigo, p.nombre 
    FROM detalle_ventas dv
    JOIN productos p ON dv.producto_id = p.id
    WHERE dv.venta_id = :venta_id
  ');
  $detallesStmt->execute([':venta_id' => $ventaId]);
  $detalles = $detallesStmt->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/html; charset=utf-8');
  echo generarTicketHTML($venta, $detalles);
}

/**
 * Generar HTML del ticket
 */
function generarTicketHTML(array $venta, array $detalles): string {
  $empresaNombre = getenv('OMNISTOCK_EMPRESA_NOMBRE') ?: 'OmniStock POS';
  $fechaActual = date('d/m/Y H:i:s');
  $totalArticulos = array_sum(array_column($detalles, 'cantidad'));
  $cambio = $venta['cambio'] ?? 0;
  $metodo = $venta['metodo_pago'] ?? 'efectivo';

  $html = <<<HTML
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #{$venta['id']}</title>
    <style>
      * { margin: 0; padding: 0; }
      body {
        font-family: 'Courier New', monospace;
        background: #f0f0f0;
        padding: 20px;
      }
      .ticket {
        width: 80mm;
        margin: 0 auto;
        background: white;
        padding: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.2);
      }
      .header {
        text-align: center;
        margin-bottom: 10px;
        border-bottom: 2px solid #000;
        padding-bottom: 5px;
      }
      .header h1 {
        font-size: 14px;
        font-weight: bold;
      }
      .header p {
        font-size: 10px;
        color: #666;
      }
      .ticket-info {
        font-size: 9px;
        margin-bottom: 10px;
      }
      .ticket-info div {
        display: flex;
        justify-content: space-between;
      }
      .label { font-weight: bold; }
      table {
        width: 100%;
        font-size: 9px;
        margin-bottom: 10px;
      }
      table th {
        text-align: left;
        border-bottom: 1px solid #000;
        padding: 3px 0;
      }
      table td {
        padding: 2px 0;
      }
      .qty { text-align: center; }
      .price { text-align: right; }
      .total-row {
        border-top: 1px solid #000;
        border-bottom: 2px solid #000;
        font-weight: bold;
        padding: 5px 0;
        display: flex;
        justify-content: space-between;
      }
      .payment-info {
        font-size: 9px;
        margin-bottom: 10px;
      }
      .payment-info div {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2px;
      }
      .footer {
        text-align: center;
        font-size: 8px;
        color: #999;
        margin-top: 10px;
        border-top: 1px solid #000;
        padding-top: 5px;
      }
      .reimpreso {
        text-align: center;
        color: #f00;
        font-weight: bold;
        margin-bottom: 10px;
      }
      @media print {
        body { background: none; padding: 0; }
        .no-print { display: none; }
      }
    </style>
  </head>
  <body>
    <div class="ticket">
      <div class="reimpreso">*** REIMPRESIÓN DE TICKET ***</div>
      
      <div class="header">
        <h1>$empresaNombre</h1>
        <p>Ticket de Venta</p>
      </div>

      <div class="ticket-info">
        <div><span class="label">Ticket:</span> <span>#{$venta['id']}</span></div>
        <div><span class="label">Fecha:</span> <span>{$fechaActual}</span></div>
      </div>

      <table>
        <thead>
          <tr>
            <th>Producto</th>
            <th class="qty">Cant</th>
            <th class="price">Total</th>
          </tr>
        </thead>
        <tbody>
HTML;

  foreach ($detalles as $detalle) {
    $precioTotal = $detalle['cantidad'] * $detalle['precio_unitario'];
    $html .= <<<HTML
          <tr>
            <td>{$detalle['nombre_producto']}</td>
            <td class="qty">{$detalle['cantidad']}</td>
            <td class="price">\${$precioTotal}</td>
          </tr>
HTML;
  }

  $html .= <<<HTML
        </tbody>
      </table>

      <div class="total-row">
        <span>Total:</span>
        <span>\${$venta['total']}</span>
      </div>

      <div class="payment-info">
        <div>
          <span><strong>Método de Pago:</strong></span>
          <span>$metodo</span>
        </div>
HTML;

  if (!empty($venta['referencia_transferencia'])) {
    $html .= <<<HTML
        <div>
          <span><strong>Folio:</strong></span>
          <span>{$venta['referencia_transferencia']}</span>
        </div>
HTML;
  }

  if ($cambio > 0) {
    $html .= <<<HTML
        <div>
          <span><strong>Cambio:</strong></span>
          <span>\${$cambio}</span>
        </div>
HTML;
  }

  $html .= <<<HTML
      </div>

      <div class="footer">
        <p>Gracias por su compra</p>
        <p>© $(date('Y')) - OmniStock POS</p>
      </div>
    </div>
  </body>
  </html>
HTML;

  return $html;
}

