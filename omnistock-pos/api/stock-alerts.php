<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stock_alerts.php';

$user = require_admin_user();

function stock_alerts_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = database_connection();
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $products = critical_stock_products($pdo);
        $orderLookup = $pdo->prepare(
            "SELECT id, cantidad_solicitada FROM ordenes_compra
             WHERE producto_id = :product_id AND estado = 'pendiente'
             ORDER BY id DESC LIMIT 1"
        );
        foreach ($products as &$product) {
            $product['id'] = (string) $product['product_id'];
            $product['name'] = (string) $product['product_name'];
            $product['stock'] = (float) $product['current_stock'];
            $product['minimum'] = (int) $product['minimum_stock'];
            $product['image'] = $product['image_path'];
            $orderLookup->execute(['product_id' => $product['id']]);
            $pendingOrder = $orderLookup->fetch();
            $product['pending_order'] = $pendingOrder ? (int) $pendingOrder['id'] : null;
            $product['pending_quantity'] = $pendingOrder ? (int) $pendingOrder['cantidad_solicitada'] : null;
        }
        unset($product);
        stock_alerts_json(['count' => count($products), 'products' => $products]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        stock_alerts_json(['error' => 'Método no permitido.'], 405);
    }

    $submittedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!isset($_SESSION['catalog_csrf']) || !hash_equals((string) $_SESSION['catalog_csrf'], $submittedToken)) {
        stock_alerts_json(['error' => 'La sesión expiró. Actualiza la página e inténtalo nuevamente.'], 403);
    }
    $productId = trim((string) ($_POST['product_id'] ?? ''));
    if ($productId === '' || strlen($productId) > 100) {
        stock_alerts_json(['error' => 'El producto seleccionado no es válido.'], 422);
    }

    $columns = product_catalog_columns($pdo);
    $map = product_catalog_map($columns);
    if (!isset($map['id'], $map['name'], $map['stock'], $map['stock_minimum'])) {
        stock_alerts_json(['error' => 'Falta configurar la columna de stock mínimo. Importa el esquema de reabastecimiento.'], 422);
    }

    $engineQuery = $pdo->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $engineQuery->execute(['table_name' => 'productos']);
    if (strtoupper((string) $engineQuery->fetchColumn()) !== 'INNODB') {
        stock_alerts_json(['error' => 'La tabla de productos debe usar InnoDB para generar órdenes seguras.'], 422);
    }

    $pdo->beginTransaction();
    $productQuery = $pdo->prepare(
        'SELECT ' . product_catalog_identifier($map['id']) . ' AS product_id, ' .
        product_catalog_identifier($map['name']) . ' AS product_name, ' .
        product_catalog_identifier($map['stock']) . ' AS current_stock, ' .
        product_catalog_identifier($map['stock_minimum']) . ' AS minimum_stock
         FROM `productos` WHERE ' . product_catalog_identifier($map['id']) . ' = :id FOR UPDATE'
    );
    $productQuery->execute(['id' => $productId]);
    $product = $productQuery->fetch();
    if (!$product) {
        throw new DomainException('El producto ya no existe.');
    }

    $currentStock = (float) $product['current_stock'];
    $minimumStock = (int) $product['minimum_stock'];
    if ($currentStock > $minimumStock) {
        throw new DomainException('El producto ya no tiene stock crítico. Actualiza la lista.');
    }

    $pendingQuery = $pdo->prepare(
        "SELECT id, cantidad_solicitada FROM ordenes_compra
         WHERE producto_id = :product_id AND estado = 'pendiente'
         ORDER BY id DESC LIMIT 1"
    );
    $pendingQuery->execute(['product_id' => (string) $product['product_id']]);
    $existingOrder = $pendingQuery->fetch();
    if ($existingOrder) {
        $pdo->commit();
        stock_alerts_json([
            'success' => true,
            'existing' => true,
            'order_id' => (int) $existingOrder['id'],
            'quantity' => (int) $existingOrder['cantidad_solicitada'],
            'message' => 'Ya existe una orden pendiente para este producto.',
        ]);
    }

    $quantity = critical_stock_replenishment_quantity($currentStock, $minimumStock);
    $insert = $pdo->prepare(
        "INSERT INTO ordenes_compra
         (producto_id, nombre_producto, stock_actual, stock_minimo, cantidad_solicitada, solicitada_por, solicitada_por_nombre)
         VALUES (:product_id, :name, :stock, :minimum, :quantity, :user_id, :username)"
    );
    $insert->execute([
        'product_id' => (string) $product['product_id'],
        'name' => (string) $product['product_name'],
        'stock' => number_format($currentStock, 3, '.', ''),
        'minimum' => $minimumStock,
        'quantity' => $quantity,
        'user_id' => $user['id'],
        'username' => $user['username'],
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $sellerNotice = $pdo->prepare(
        "INSERT INTO mensajes_almacen
         (emisor_id, receptor_rol, receptor_id, tipo, asunto, mensaje, producto_id, cantidad_sugerida)
         VALUES (:sender_id, 'vendedor', NULL, 'aviso', :subject, :message, :product_id, :quantity)"
    );
    $productName = (string) $product['product_name'];
    $sellerNotice->execute([
        'sender_id' => $user['id'],
        'subject' => mb_substr('Producto en camino: ' . $productName, 0, 150, 'UTF-8'),
        'message' => 'Administración generó la orden de reabastecimiento #' . $orderId .
            '. El producto ' . $productName . ' está en camino. Cantidad solicitada: ' . $quantity . '.',
        'product_id' => (string) $product['product_id'],
        'quantity' => $quantity,
    ]);
    $pdo->commit();

    stock_alerts_json([
        'success' => true,
        'existing' => false,
        'order_id' => $orderId,
        'quantity' => $quantity,
        'message' => 'Orden generada y aviso enviado a los vendedores.',
    ]);
} catch (DomainException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    stock_alerts_json(['error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OmniStock replenishment alert: ' . $exception->getMessage());
    stock_alerts_json(['error' => 'No se pudo completar la operación de reabastecimiento. Inténtalo nuevamente.'], 500);
}
