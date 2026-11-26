<?php
declare(strict_types=1);

/**
 * OmniStock - API Inventario Avanzado
 * Maneja lotes, fechas de vencimiento y alertas automáticas
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
    case 'update_lote_vencimiento':
      if ($method !== 'POST') {
        throw new Exception('Método no permitido', 405);
      }
      handleUpdateLoteVencimiento($pdo);
      break;

    case 'get_productos_proximos_vencer':
      if ($method !== 'GET') {
        throw new Exception('Método no permitido', 405);
      }
      handleGetProductosProximosVencer($pdo);
      break;

    case 'get_productos_vencidos':
      if ($method !== 'GET') {
        throw new Exception('Método no permitido', 405);
      }
      handleGetProductosVencidos($pdo);
      break;

    case 'marcar_vencido':
      if ($method !== 'POST') {
        throw new Exception('Método no permitido', 405);
      }
      handleMarcarVencido($pdo);
      break;

    default:
      throw new Exception('Acción no encontrada', 404);
  }
} catch (Exception $e) {
  http_response_code($e->getCode() ?: 500);
  echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

/**
 * Actualizar lote y fecha de vencimiento de un producto
 */
function handleUpdateLoteVencimiento(PDO $pdo): void {
  $productId = (int) ($_POST['product_id'] ?? 0);
  $lote = trim((string) ($_POST['lote'] ?? ''));
  $fechaVencimiento = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
  $cantidad = (int) ($_POST['cantidad'] ?? 0);

  if ($productId <= 0 || $cantidad <= 0) {
    throw new Exception('Parámetros inválidos', 400);
  }

  if (!empty($fechaVencimiento) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
    throw new Exception('Formato de fecha inválido (use YYYY-MM-DD)', 400);
  }

  // Verificar que el lote no exista ya
  $stmt = $pdo->prepare('
    SELECT id FROM producto_lotes 
    WHERE producto_id = :producto_id AND lote = :lote AND fecha_vencimiento = :fecha_vencimiento
    LIMIT 1
  ');
  $stmt->execute([
    ':producto_id' => $productId,
    ':lote' => $lote,
    ':fecha_vencimiento' => $fechaVencimiento,
  ]);

  if ($stmt->fetch()) {
    // Actualizar cantidad si ya existe
    $updateStmt = $pdo->prepare('
      UPDATE producto_lotes 
      SET cantidad = cantidad + :cantidad, actualizado_en = NOW()
      WHERE producto_id = :producto_id AND lote = :lote AND fecha_vencimiento = :fecha_vencimiento
    ');
    $updateStmt->execute([
      ':cantidad' => $cantidad,
      ':producto_id' => $productId,
      ':lote' => $lote,
      ':fecha_vencimiento' => $fechaVencimiento,
    ]);
  } else {
    // Insertar nuevo lote
    $insertStmt = $pdo->prepare('
      INSERT INTO producto_lotes (producto_id, lote, fecha_vencimiento, cantidad, creado_en)
      VALUES (:producto_id, :lote, :fecha_vencimiento, :cantidad, NOW())
    ');
    $insertStmt->execute([
      ':producto_id' => $productId,
      ':lote' => $lote,
      ':fecha_vencimiento' => $fechaVencimiento,
      ':cantidad' => $cantidad,
    ]);
  }

  // Generar alerta si está próximo a vencer (< 7 días)
  if (!empty($fechaVencimiento)) {
    $diasAlerta = 7;
    $fechaAlerta = date('Y-m-d', strtotime($fechaVencimiento . " -$diasAlerta days"));
    $hoy = date('Y-m-d');

    if ($hoy >= $fechaAlerta && $hoy <= $fechaVencimiento) {
      generarAlertaVencimiento($pdo, $productId, $fechaVencimiento);
    }
  }

  http_response_code(201);
  echo json_encode(['success' => true, 'message' => 'Lote registrado correctamente'], JSON_UNESCAPED_UNICODE);
}

/**
 * Obtener productos próximos a vencer (< 7 días)
 */
function handleGetProductosProximosVencer(PDO $pdo): void {
  $stmt = $pdo->prepare('
    SELECT 
      pl.id,
      p.id as producto_id,
      p.nombre_producto,
      p.codigo,
      pl.lote,
      pl.fecha_vencimiento,
      pl.cantidad,
      DATEDIFF(pl.fecha_vencimiento, CURDATE()) as dias_restantes
    FROM producto_lotes pl
    JOIN productos p ON pl.producto_id = p.id
    WHERE DATEDIFF(pl.fecha_vencimiento, CURDATE()) BETWEEN 0 AND 7
      AND pl.fecha_vencimiento >= CURDATE()
    ORDER BY pl.fecha_vencimiento ASC
  ');
  $stmt->execute();
  $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['data' => $productos, 'count' => count($productos)], JSON_UNESCAPED_UNICODE);
}

/**
 * Obtener productos ya vencidos
 */
function handleGetProductosVencidos(PDO $pdo): void {
  $stmt = $pdo->prepare('
    SELECT 
      pl.id,
      p.id as producto_id,
      p.nombre_producto,
      p.codigo,
      pl.lote,
      pl.fecha_vencimiento,
      pl.cantidad,
      DATEDIFF(CURDATE(), pl.fecha_vencimiento) as dias_vencido
    FROM producto_lotes pl
    JOIN productos p ON pl.producto_id = p.id
    WHERE pl.fecha_vencimiento < CURDATE()
      AND pl.cantidad > 0
    ORDER BY pl.fecha_vencimiento DESC
  ');
  $stmt->execute();
  $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['data' => $productos, 'count' => count($productos)], JSON_UNESCAPED_UNICODE);
}

/**
 * Marcar producto como vencido (trasladar a 0 cantidad)
 */
function handleMarcarVencido(PDO $pdo): void {
  $loteId = (int) ($_POST['lote_id'] ?? 0);

  if ($loteId <= 0) {
    throw new Exception('ID de lote inválido', 400);
  }

  // Obtener información del lote antes de actualizar
  $stmt = $pdo->prepare('SELECT producto_id, cantidad FROM producto_lotes WHERE id = :id');
  $stmt->execute([':id' => $loteId]);
  $lote = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$lote) {
    throw new Exception('Lote no encontrado', 404);
  }

  // Marcar como vencido (cantidad = 0, pero mantener registro)
  $updateStmt = $pdo->prepare('
    UPDATE producto_lotes 
    SET cantidad = 0, estado_lote = "vencido", actualizado_en = NOW()
    WHERE id = :id
  ');
  $updateStmt->execute([':id' => $loteId]);

  // Registrar en auditoría
  registrarAuditoria($pdo, 'inventario_vencido', [
    'lote_id' => $loteId,
    'producto_id' => $lote['producto_id'],
    'cantidad' => $lote['cantidad'],
  ]);

  http_response_code(200);
  echo json_encode(['success' => true, 'message' => 'Producto marcado como vencido'], JSON_UNESCAPED_UNICODE);
}

/**
 * Generar alerta de vencimiento automática
 */
function generarAlertaVencimiento(PDO $pdo, int $productoId, string $fechaVencimiento): void {
  try {
    $stmt = $pdo->prepare('
      SELECT nombre_producto FROM productos WHERE id = :id LIMIT 1
    ');
    $stmt->execute([':id' => $productoId]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($producto) {
      $mensaje = "⚠️ Alerta: El producto '{$producto['nombre_producto']}' vence el {$fechaVencimiento}. Verifica tu inventario.";
      
      $insertStmt = $pdo->prepare('
        INSERT INTO mensajes_almacen (remitente_rol, receptor_rol, titulo, contenido, creado_en)
        VALUES ("sistema", "admin", "Alerta de Vencimiento", :contenido, NOW())
      ');
      $insertStmt->execute([':contenido' => $mensaje]);
    }
  } catch (Exception $e) {
    error_log("OmniStock: Error generando alerta de vencimiento: " . $e->getMessage());
  }
}

/**
 * Registrar en auditoría
 */
function registrarAuditoria(PDO $pdo, string $accion, array $datos): void {
  try {
    $stmt = $pdo->prepare('
      INSERT INTO auditoria_inventario (usuario_id, accion, datos, timestamp)
      VALUES (:usuario_id, :accion, :datos, NOW())
    ');
    $stmt->execute([
      ':usuario_id' => $_SESSION['user_id'] ?? 0,
      ':accion' => $accion,
      ':datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
    ]);
  } catch (Exception $e) {
    error_log("OmniStock: Error registrando auditoría: " . $e->getMessage());
  }
}
