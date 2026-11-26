<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
start_user_session();

function exchange_rates_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    exchange_rates_json(['error' => 'Inicia sesión para consultar el conversor.'], 401);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    exchange_rates_json(['error' => 'Método no permitido.'], 405);
}

if ($method === 'POST') {
    if ($_SESSION['role'] !== 'admin') {
        exchange_rates_json(['error' => 'Solo un administrador puede actualizar las tasas.'], 403);
    }
    $request = json_decode((string) file_get_contents('php://input'), true);
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_array($request) || !isset($_SESSION['currency_csrf']) || !hash_equals($_SESSION['currency_csrf'], $token)) {
        exchange_rates_json(['error' => 'La sesión expiró. Actualiza la página e inténtalo nuevamente.'], 403);
    }
    $submittedRates = $request['rates'] ?? null;
    $supportedCurrencies = ['USD', 'EUR', 'GBP'];
    if (!is_array($submittedRates)) {
        exchange_rates_json(['error' => 'Envía una tasa válida para USD, EUR y GBP.'], 422);
    }
    foreach ($supportedCurrencies as $code) {
        $value = $submittedRates[$code] ?? null;
        if (!is_scalar($value) || !is_numeric((string) $value) || (float) $value < 0.0001 || (float) $value > 999999.999999) {
            exchange_rates_json(['error' => 'Cada tasa debe estar entre 0.0001 y 999,999.999999 MXN por unidad.'], 422);
        }
    }
}

try {
    $pdo = database_connection();
    if ($method === 'POST') {
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'INSERT INTO tipos_cambio (codigo, mxn_por_unidad, actualizado_por)
             VALUES (:code, :rate, :user_id)
             ON DUPLICATE KEY UPDATE mxn_por_unidad = :update_rate,
                 actualizado_por = :update_user_id, actualizado_en = CURRENT_TIMESTAMP'
        );
        foreach (['USD', 'EUR', 'GBP'] as $code) {
            $rate = (float) $submittedRates[$code];
            $statement->execute([
                'code' => $code,
                'rate' => $rate,
                'user_id' => (int) $_SESSION['user_id'],
                'update_rate' => $rate,
                'update_user_id' => (int) $_SESSION['user_id'],
            ]);
        }
        $pdo->commit();
    }

    $statement = $pdo->query('SELECT codigo, mxn_por_unidad FROM tipos_cambio');
    $rates = [];
    foreach ($statement->fetchAll() as $row) {
        $rates[(string) $row['codigo']] = (float) $row['mxn_por_unidad'];
    }
    foreach (['USD' => 17.20, 'EUR' => 18.60, 'GBP' => 22.00] as $code => $rate) {
        $rates[$code] ??= $rate;
    }
    exchange_rates_json([
        'base' => 'MXN',
        'rates' => $rates,
        'source' => 'configurable_demo',
        'as_of' => gmdate('c'),
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OmniStock exchange rates API: ' . $exception->getMessage());
    exchange_rates_json(['error' => 'No se pudieron consultar o guardar las tasas de cambio.'], 500);
}
