<?php
declare(strict_types=1);

require_once __DIR__ . '/config/env.php';

if (getenv('OMNISTOCK_ENABLE_TEST_SALE') !== '1') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app_paths.php';

$user = require_authenticated_user();
if (!in_array($user['role'], ['admin', 'vendedor'], true)) {
    http_response_code(403);
    exit('No tienes permiso para generar el ticket de prueba.');
}

if (empty($_SESSION['test_sale_csrf'])) {
    $_SESSION['test_sale_csrf'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['test_sale_csrf'], $submittedToken)) {
        http_response_code(403);
        $errorMessage = 'La sesión expiró. Recarga esta página e inténtalo nuevamente.';
    } else {
        try {
            $pdo = database_connection();
            $pdo->beginTransaction();

            $columns = [];
            foreach ($pdo->query('SHOW COLUMNS FROM `ventas`')->fetchAll() as $column) {
                $columns[strtolower((string) $column['Field'])] = (string) $column['Field'];
            }
            $referenceColumns = array_values(array_filter([
                $columns['referencia_transferencia'] ?? null,
                $columns['transferencia_referencia'] ?? null,
            ]));

            $saleData = [
                'vendedor_id' => $user['id'],
                'vendedor_nombre' => mb_substr($user['username'] . ' [PRUEBA]', 0, 80, 'UTF-8'),
                'subtotal' => '1.00',
                'iva_porcentaje' => '0.00',
                'iva' => '0.00',
                'total' => '1.00',
                'metodo_pago' => 'transferencia',
                'efectivo_recibido' => null,
                'cambio' => '0.00',
            ];
            foreach ($referenceColumns as $column) {
                $saleData[$column] = '123456789012345678';
            }
            if (!$referenceColumns) {
                throw new RuntimeException('Falta la columna de referencia de transferencia en ventas.');
            }

            $product = $pdo->query(
                'SELECT `id` FROM `productos` ORDER BY `id` LIMIT 1'
            )->fetch();
            if (!$product) {
                throw new RuntimeException('No hay productos para relacionar el detalle del ticket de prueba.');
            }

            $quotedColumns = array_map(
                static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
                array_keys($saleData)
            );
            $placeholders = array_map(
                static fn(string $column): string => ':' . $column,
                array_keys($saleData)
            );
            $saleInsert = $pdo->prepare(
                'INSERT INTO ventas (' . implode(', ', $quotedColumns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );
            $saleInsert->execute($saleData);
            $saleId = (int) $pdo->lastInsertId();

            $detailInsert = $pdo->prepare(
                'INSERT INTO detalle_ventas
                 (venta_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal_linea)
                 VALUES (:sale_id, :product_id, :product_name, 1.00, 1, 1.00)'
            );
            $detailInsert->execute([
                'sale_id' => $saleId,
                'product_id' => $product['id'],
                'product_name' => 'PRUEBA DE TRANSFERENCIA (NO ES VENTA REAL)',
            ]);

            $pdo->commit();
            unset($_SESSION['test_sale_csrf']);
            header('Location: ' . app_base_path() . '/ticket.php?id=' . $saleId, true, 303);
            exit;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('OmniStock test sale: ' . $exception->getMessage());
            $errorMessage = 'No se pudo crear el ticket de prueba. Verifica las tablas de ventas y vuelve a intentarlo.';
        }
    }
}

$csrfToken = (string) $_SESSION['test_sale_csrf'];
$basePath = app_base_path();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <title>Ticket de prueba · OmniStock</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/app.css">
  <style>
    body { display: grid; min-height: 100vh; place-items: center; padding: 24px; }
    .test-sale-card { width: min(440px, 100%); padding: 26px; border: 1px solid rgba(148, 163, 184, .16); border-radius: 14px; background: rgba(15, 23, 42, .94); box-shadow: 0 24px 70px rgba(0, 0, 0, .3); }
    .test-sale-card h1 { margin: 0 0 10px; color: #e5eef8; font-size: 22px; }
    .test-sale-card p { color: #9aaac0; font-size: 13px; line-height: 1.6; }
    .test-sale-card strong { color: #fbbf77; }
    .test-sale-card button { width: 100%; margin-top: 12px; padding: 12px; border: 0; border-radius: 8px; color: #06202a; background: #06b6d4; font-weight: 700; cursor: pointer; }
    .test-sale-error { color: #fca5a5 !important; }
  </style>
</head>
<body>
  <main class="test-sale-card">
    <p class="eyebrow">HERRAMIENTA DE PRUEBA</p>
    <h1>Generar ticket de transferencia</h1>
    <p>Creará una venta de <strong>$1.00</strong> marcada como prueba con la referencia <strong>123456789012345678</strong>. No se descontará inventario, pero el registro aparecerá en el historial y los reportes.</p>
    <?php if ($errorMessage !== ''): ?>
      <p class="test-sale-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit">Crear y abrir ticket</button>
    </form>
  </main>
</body>
</html>
