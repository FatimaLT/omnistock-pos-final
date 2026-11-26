<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/product_catalog.php';

function branch_summary_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

start_user_session();
if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['username'])) {
    branch_summary_json(['error' => 'Inicia sesión para consultar el resumen.'], 401);
}
$user = ['role' => (string) $_SESSION['role']];
if ($user['role'] !== 'admin') {
    branch_summary_json(['error' => 'El resumen está disponible únicamente para administradores.'], 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    branch_summary_json(['error' => 'Método no permitido.'], 405);
}
$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!isset($_SESSION['summary_csrf']) || !hash_equals($_SESSION['summary_csrf'], $token)) {
    branch_summary_json(['error' => 'La sesión expiró. Actualiza la página e inténtalo nuevamente.'], 403);
}

try {
    $pdo = database_connection();
    $salesStatement = $pdo->query(
        'SELECT COUNT(*) AS sale_count, COALESCE(SUM(total), 0) AS revenue,
                COALESCE(AVG(total), 0) AS average_ticket
         FROM ventas
         WHERE fecha_venta >= CURRENT_DATE - INTERVAL 29 DAY
           AND fecha_venta < CURRENT_DATE + INTERVAL 1 DAY'
    );
    $sales = $salesStatement->fetch();
    $columns = product_catalog_map(product_catalog_columns($pdo));
    $inventory = ['product_count' => 0, 'units' => 0, 'low_stock' => 0, 'stock_value' => 0];
    if (isset($columns['id'])) {
        $stock = isset($columns['stock']) ? product_catalog_identifier($columns['stock']) : null;
        $minimum = isset($columns['stock_minimum']) ? product_catalog_identifier($columns['stock_minimum']) : null;
        $price = isset($columns['price']) ? product_catalog_identifier($columns['price']) : null;
        $query = $pdo->query(
            'SELECT COUNT(*) AS product_count' .
            ($stock !== null ? ', COALESCE(SUM(' . $stock . '), 0) AS units' : ', 0 AS units') .
            ($stock !== null ? ', COALESCE(SUM(CASE WHEN ' . $stock . ' <= ' . ($minimum ?? '5') . ' THEN 1 ELSE 0 END), 0) AS low_stock' : ', 0 AS low_stock') .
            ($stock !== null && $price !== null ? ', COALESCE(SUM(' . $stock . ' * ' . $price . '), 0) AS stock_value' : ', 0 AS stock_value') .
            ' FROM productos'
        );
        $inventory = $query->fetch();
    }

    $branchConfiguration = require __DIR__ . '/../config/branches.php';
    $branches = array_map(
        static fn(array $branch): array => [
            'name' => $branch['name'],
            'address' => $branch['address'],
            'location' => number_format((float) $branch['latitude'], 4) . ', ' . number_format((float) $branch['longitude'], 4),
            'status' => 'Ubicación configurada',
        ],
        $branchConfiguration
    );

    $revenue = (float) $sales['revenue'];
    $saleCount = (int) $sales['sale_count'];
    $lowStock = (int) $inventory['low_stock'];
    $insights = [];
    $insights[] = $saleCount > 0
        ? 'En los últimos 30 días se registraron ' . $saleCount . ' ventas por $' . number_format($revenue, 2) . ' MXN; el ticket promedio fue de $' . number_format((float) $sales['average_ticket'], 2) . ' MXN.'
        : 'No hay ventas registradas en los últimos 30 días; no se puede estimar una tendencia de ingresos.';
    $insights[] = $lowStock > 0
        ? $lowStock . ' productos están en el umbral de stock bajo o por debajo. Revisa existencias antes del siguiente reabastecimiento.'
        : 'No se detectaron productos por debajo del umbral de stock configurado.';
    $insights[] = count($branches) > 0
        ? 'Hay ' . count($branches) . ' sucursal(es) con ubicación configurada. El desglose de rendimiento por sucursal requiere asignar cada venta a una sucursal.'
        : 'No hay ubicaciones de sucursales configuradas; define las coordenadas del establecimiento para habilitar su cobertura en el mapa.';

    branch_summary_json([
        'generated_at' => gmdate('c'),
        'period_days' => 30,
        'simulation' => true,
        'metrics' => [
            'revenue' => $revenue,
            'sale_count' => $saleCount,
            'average_ticket' => (float) $sales['average_ticket'],
            'product_count' => (int) $inventory['product_count'],
            'stock_units' => (int) $inventory['units'],
            'low_stock_count' => $lowStock,
            'stock_value' => (float) $inventory['stock_value'],
            'branch_count' => count($branches),
        ],
        'insights' => $insights,
        'branches' => $branches,
    ]);
} catch (Throwable $exception) {
    error_log('OmniStock branch summary API: ' . $exception->getMessage());
    branch_summary_json(['error' => 'No se pudieron calcular las métricas. Verifica la conexión a la base de datos.'], 500);
}
