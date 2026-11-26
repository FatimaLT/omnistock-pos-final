<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/product_catalog.php';
require_once __DIR__ . '/../config/payment.php';

start_user_session();

function sales_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['username'])) {
    sales_json(['error' => 'La sesión expiró. Inicia sesión nuevamente.'], 401);
}

$userId = (int) $_SESSION['user_id'];
$username = (string) $_SESSION['username'];
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $pdo = database_connection();

    if ($method === 'GET') {
        $statement = $pdo->prepare(
            'SELECT id, subtotal, iva, total, metodo_pago, fecha_venta
             FROM ventas
             WHERE vendedor_id = :user_id AND fecha_venta >= CURDATE() AND fecha_venta < CURDATE() + INTERVAL 1 DAY
             ORDER BY fecha_venta DESC'
        );
        $statement->execute(['user_id' => $userId]);
        $sales = $statement->fetchAll();
        $total = 0;
        foreach ($sales as &$sale) {
            $sale['id'] = (int) $sale['id'];
            $sale['total'] = (float) $sale['total'];
            $sale['subtotal'] = (float) $sale['subtotal'];
            $sale['iva'] = (float) $sale['iva'];
            $total += $sale['total'];
        }
        unset($sale);

        sales_json([
            'sales' => $sales,
            'count' => count($sales),
            'total' => round($total, 2),
        ]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        sales_json(['error' => 'Método no permitido.'], 405);
    }

    $csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!isset($_SESSION['pos_csrf']) || !hash_equals((string) $_SESSION['pos_csrf'], $csrfToken)) {
        sales_json(['error' => 'La sesión expiró. Actualiza la página e inténtalo nuevamente.'], 403);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        sales_json(['error' => 'No se pudo procesar la venta. Revisa los datos e inténtalo nuevamente.'], 422);
    }

    $paymentMethod = (string) ($input['payment_method'] ?? '');
    if (!in_array($paymentMethod, ['efectivo', 'tarjeta', 'transferencia'], true)) {
        sales_json(['error' => 'Selecciona un método de pago válido.'], 422);
    }
    $rawTransferReference = $input['transfer_reference'] ?? '';
    if ($paymentMethod === 'transferencia') {
        $transferDetails = transfer_payment_details();
        if ($transferDetails['bank'] === '' || $transferDetails['clabe'] === '' || $transferDetails['holder'] === '') {
            sales_json(['error' => 'El método de transferencia aún no está configurado. Contacta al administrador.'], 422);
        }
        if (!transfer_clabe_is_valid($transferDetails['clabe'])) {
            sales_json(['error' => 'La CLABE configurada debe contener exactamente 18 dígitos numéricos.'], 422);
        }
        if (!is_string($rawTransferReference)) {
            sales_json(['error' => 'Ingresa una referencia de transferencia válida (máximo 50 caracteres).'], 422);
        }
        $transferReference = trim($rawTransferReference);
        if ($transferReference === '' || mb_strlen($transferReference, 'UTF-8') > 50) {
            sales_json(['error' => 'Ingresa una referencia de transferencia válida (máximo 50 caracteres).'], 422);
        }
    } else {
        $transferReference = '';
    }

    $taxRate = $input['tax_rate'] ?? null;
    if (!is_numeric($taxRate) || (float) $taxRate < 0 || (float) $taxRate > 100) {
        sales_json(['error' => 'El porcentaje de IVA no es válido.'], 422);
    }

    $submittedItems = $input['items'] ?? null;
    if (!is_array($submittedItems) || count($submittedItems) === 0 || count($submittedItems) > 100) {
        sales_json(['error' => 'El ticket está vacío o contiene demasiados productos.'], 422);
    }

    $quantities = [];
    foreach ($submittedItems as $item) {
        if (!is_array($item) || !isset($item['id'], $item['quantity'])) {
            sales_json(['error' => 'El ticket contiene un producto no válido.'], 422);
        }
        $productId = trim((string) $item['id']);
        $quantity = filter_var($item['quantity'], FILTER_VALIDATE_INT);
        if ($productId === '' || strlen($productId) > 100 || $quantity === false || $quantity < 1 || $quantity > 10000) {
            sales_json(['error' => 'El ticket contiene una cantidad o producto no válido.'], 422);
        }
        $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        if ($quantities[$productId] > 10000) {
            sales_json(['error' => 'La cantidad solicitada supera el límite por producto.'], 422);
        }
    }

    $columns = product_catalog_columns($pdo);
    $map = product_catalog_map($columns);
    if (!isset($map['id'], $map['name'], $map['price'], $map['stock'])) {
        sales_json(['error' => 'El catálogo no tiene identificador, precio o columna de existencias configurados.'], 422);
    }
    $warehouseTableQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $warehouseTableQuery->execute(['table_name' => 'mensajes_almacen']);
    $warehouseMessagesAvailable = (int) $warehouseTableQuery->fetchColumn() > 0;
    if ($warehouseMessagesAvailable && !isset($map['stock_minimum'])) {
        sales_json(['error' => 'Configura la columna de stock mínimo para registrar alertas de reabastecimiento.'], 422);
    }
    $engineQuery = $pdo->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $engineQuery->execute(['table_name' => 'productos']);
    if (strtoupper((string) $engineQuery->fetchColumn()) !== 'INNODB') {
        sales_json(['error' => 'La tabla de productos debe usar InnoDB para procesar ventas de forma segura.'], 422);
    }
    $transactionTables = $pdo->query(
        "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ventas', 'detalle_ventas')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    if (
        count($transactionTables) !== 2 ||
        strtoupper((string) ($transactionTables['ventas'] ?? '')) !== 'INNODB' ||
        strtoupper((string) ($transactionTables['detalle_ventas'] ?? '')) !== 'INNODB'
    ) {
        sales_json(['error' => 'Las tablas de ventas deben estar instaladas con el esquema InnoDB requerido.'], 422);
    }

    $productQuery = $pdo->prepare(
        'SELECT * FROM `productos` WHERE ' . product_catalog_identifier($map['id']) . ' = :id FOR UPDATE'
    );
    $stockUpdate = $pdo->prepare(
        'UPDATE `productos` SET ' . product_catalog_identifier($map['stock']) . ' = ' .
        product_catalog_identifier($map['stock']) . ' - :quantity WHERE ' .
        product_catalog_identifier($map['id']) . ' = :id AND ' .
        product_catalog_identifier($map['stock']) . ' >= :available_quantity'
    );
    $pendingStockAlert = $warehouseMessagesAvailable
        ? $pdo->prepare(
            "SELECT id FROM mensajes_almacen
             WHERE producto_id = :product_id AND tipo = 'alerta_stock' AND estado = 'pendiente'
             ORDER BY id DESC LIMIT 1"
        )
        : null;
    $insertStockAlert = $warehouseMessagesAvailable
        ? $pdo->prepare(
            "INSERT INTO mensajes_almacen
             (emisor_id, receptor_rol, tipo, asunto, mensaje, producto_id, cantidad_sugerida)
             VALUES (:sender_id, 'admin', 'alerta_stock', :subject, :message, :product_id, :quantity)"
        )
        : null;
    $pdo->beginTransaction();

    $lines = [];
    $subtotalCents = 0;
    foreach ($quantities as $productId => $quantity) {
        $productQuery->execute(['id' => $productId]);
        $product = $productQuery->fetch();
        if (!$product) {
            throw new DomainException('Uno de los productos ya no está disponible. Actualiza el catálogo.');
        }

        $stock = product_catalog_value($product, $map, 'stock', null);
        if (!is_numeric($stock) || (float) $stock < $quantity) {
            $name = (string) product_catalog_value($product, $map, 'name', 'Producto');
            throw new DomainException('Existencias insuficientes para: ' . $name . '.');
        }

        $unitPriceCents = (int) round((float) product_catalog_value($product, $map, 'price', 0) * 100);
        if ($unitPriceCents < 0) {
            throw new DomainException('El precio de un producto no es válido.');
        }
        $lineCents = $unitPriceCents * $quantity;
        $subtotalCents += $lineCents;
        if ($subtotalCents > 999999999999) {
            throw new DomainException('El total de la venta supera el límite permitido.');
        }
        $lines[] = [
            'id' => $productId,
            'name' => (string) product_catalog_value($product, $map, 'name', 'Producto'),
            'unit_price_cents' => $unitPriceCents,
            'quantity' => $quantity,
            'line_total_cents' => $lineCents,
        ];

        $stockUpdate->execute([
            'quantity' => $quantity,
            'id' => $productId,
            'available_quantity' => $quantity,
        ]);
        if ($stockUpdate->rowCount() !== 1) {
            throw new DomainException('No fue posible reservar el stock solicitado. Actualiza el catálogo.');
        }

        if ($pendingStockAlert !== null && $insertStockAlert !== null) {
            $remainingStock = (float) $stock - $quantity;
            $minimumStock = (int) product_catalog_value($product, $map, 'stock_minimum', 5);
            if ($remainingStock <= $minimumStock) {
                $pendingStockAlert->execute(['product_id' => $productId]);
                if ($pendingStockAlert->fetchColumn() === false) {
                    $productName = (string) product_catalog_value($product, $map, 'name', 'Producto');
                    $suggestedQuantity = max(1, (int) ceil($minimumStock - $remainingStock));
                    $insertStockAlert->execute([
                        'sender_id' => $userId,
                        'subject' => mb_substr('Stock crítico: ' . $productName, 0, 150, 'UTF-8'),
                        'message' => $productName . ' llegó a stock crítico después de una venta. Stock actual: ' .
                            $remainingStock .
                            '; mínimo: ' . $minimumStock . '. Cantidad sugerida: ' . $suggestedQuantity . '.',
                        'product_id' => $productId,
                        'quantity' => $suggestedQuantity,
                    ]);
                }
            } else {
                $resolveStockAlerts = $pdo->prepare(
                    "UPDATE mensajes_almacen SET estado = 'atendida', atendida_en = CURRENT_TIMESTAMP
                     WHERE producto_id = :product_id AND tipo = 'alerta_stock' AND estado = 'pendiente'"
                );
                $resolveStockAlerts->execute(['product_id' => $productId]);
            }
        }
    }

    $taxRate = round((float) $taxRate, 2);
    $taxCents = (int) round($subtotalCents * $taxRate / 100);
    $totalCents = $subtotalCents + $taxCents;
    if ($totalCents > 999999999999) {
        throw new DomainException('El total de la venta supera el límite permitido.');
    }
    $cashReceivedCents = null;
    $changeCents = 0;
    if ($paymentMethod === 'efectivo') {
        $cashReceived = $input['cash_received'] ?? null;
        if (!is_numeric($cashReceived) || (float) $cashReceived < 0) {
            throw new DomainException('Ingresa el efectivo recibido.');
        }
        $cashReceivedCents = (int) round((float) $cashReceived * 100);
        if ($cashReceivedCents > 999999999999) {
            throw new DomainException('El efectivo recibido supera el límite permitido.');
        }
        if ($cashReceivedCents < $totalCents) {
            throw new DomainException('El efectivo recibido no cubre el total de la venta.');
        }
        $changeCents = $cashReceivedCents - $totalCents;
    }

    $saleColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `ventas`')->fetchAll() as $column) {
        $saleColumns[strtolower((string) $column['Field'])] = (string) $column['Field'];
    }
    $referenceColumn = $saleColumns['referencia_transferencia']
        ?? $saleColumns['transferencia_referencia']
        ?? null;
    if ($referenceColumn === null) {
        sales_json(['error' => 'Falta la columna de referencia de transferencia. Aplica la migración de ventas.'], 422);
    }
    $referenceIdentifier = '`' . str_replace('`', '``', $referenceColumn) . '`';
    $saleInsert = $pdo->prepare(
        'INSERT INTO ventas
         (vendedor_id, vendedor_nombre, subtotal, iva_porcentaje, iva, total, metodo_pago, efectivo_recibido, cambio, ' . $referenceIdentifier . ')
         VALUES (:user_id, :username, :subtotal, :tax_rate, :tax, :total, :payment_method, :cash_received, :change_amount, :transfer_reference)'
    );
    $saleInsert->execute([
        'user_id' => $userId,
        'username' => $username,
        'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
        'tax_rate' => number_format($taxRate, 2, '.', ''),
        'tax' => number_format($taxCents / 100, 2, '.', ''),
        'total' => number_format($totalCents / 100, 2, '.', ''),
        'payment_method' => $paymentMethod,
        'cash_received' => $cashReceivedCents === null ? null : number_format($cashReceivedCents / 100, 2, '.', ''),
        'change_amount' => number_format($changeCents / 100, 2, '.', ''),
        'transfer_reference' => $transferReference !== '' ? $transferReference : null,
    ]);
    $saleId = (int) $pdo->lastInsertId();

    $detailInsert = $pdo->prepare(
        'INSERT INTO detalle_ventas (venta_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal_linea)
         VALUES (:sale_id, :product_id, :product_name, :unit_price, :quantity, :line_total)'
    );
    foreach ($lines as $line) {
        $detailInsert->execute([
            'sale_id' => $saleId,
            'product_id' => $line['id'],
            'product_name' => $line['name'],
            'unit_price' => number_format($line['unit_price_cents'] / 100, 2, '.', ''),
            'quantity' => $line['quantity'],
            'line_total' => number_format($line['line_total_cents'] / 100, 2, '.', ''),
        ]);
    }

    $pdo->commit();
    sales_json([
        'success' => true,
        'sale_id' => $saleId,
        'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
        'tax_rate' => number_format($taxRate, 2, '.', ''),
        'tax' => number_format($taxCents / 100, 2, '.', ''),
        'total' => number_format($totalCents / 100, 2, '.', ''),
        'payment_method' => $paymentMethod,
        'transfer_reference' => $transferReference !== '' ? $transferReference : null,
        'cash_received' => $cashReceivedCents === null ? null : number_format($cashReceivedCents / 100, 2, '.', ''),
        'change' => number_format($changeCents / 100, 2, '.', ''),
    ]);
} catch (DomainException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sales_json(['error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OmniStock sale processing: ' . $exception->getMessage());
    sales_json(['error' => 'No se pudo completar la venta. Verifica el catálogo y vuelve a intentarlo.'], 500);
}
