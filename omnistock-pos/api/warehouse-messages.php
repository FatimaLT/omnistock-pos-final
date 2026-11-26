<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/product_catalog.php';

$user = require_authenticated_user();
$role = $user['role'];

function warehouse_messages_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function warehouse_messages_unread_count(PDO $pdo, string $role, int $userId): int
{
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM mensajes_almacen AS m
         WHERE m.receptor_rol = :role
           AND (m.receptor_id IS NULL OR m.receptor_id = :recipient_id)
           AND NOT EXISTS (
             SELECT 1 FROM mensajes_almacen_lecturas AS r
             WHERE r.mensaje_id = m.id AND r.usuario_id = :user_id
           )'
    );
    $query->execute(['role' => $role, 'recipient_id' => $userId, 'user_id' => $userId]);

    return (int) $query->fetchColumn();
}

function warehouse_user_columns(PDO $pdo): array
{
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `usuarios`')->fetchAll() as $column) {
        $columns[strtolower((string) $column['Field'])] = (string) $column['Field'];
    }

    return $columns;
}

function warehouse_user_column(array $columns, array $aliases): ?string
{
    foreach ($aliases as $alias) {
        if (isset($columns[$alias])) {
            return $columns[$alias];
        }
    }

    return null;
}

try {
    $pdo = database_connection();
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $userColumns = warehouse_user_columns($pdo);
    $displayColumn = warehouse_user_column($userColumns, ['nombre_usuario', 'nombre', 'username', 'email', 'correo']);
    $roleColumn = warehouse_user_column($userColumns, ['tipo_usuario', 'rol', 'role']);
    if ($displayColumn === null || $roleColumn === null) {
        throw new RuntimeException('La tabla usuarios no tiene columnas reconocibles de nombre y rol.');
    }
    $userIdentifier = static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`';

    if ($method === 'GET') {
        $messages = $pdo->prepare(
            'SELECT m.id, m.emisor_id, u.' . $userIdentifier($displayColumn) . ' AS emisor_nombre,
                    m.asunto, m.mensaje, m.imagen_adjunta, m.tipo, m.producto_id,
                    m.cantidad_sugerida, m.cantidad_recibida, m.estado, m.atendida_en,
                    (r.usuario_id IS NOT NULL) AS leido, m.fecha_envio
             FROM mensajes_almacen AS m
             INNER JOIN usuarios AS u ON u.id = m.emisor_id
             LEFT JOIN mensajes_almacen_lecturas AS r
               ON r.mensaje_id = m.id AND r.usuario_id = :user_id
             WHERE m.receptor_rol = :role
               AND (m.receptor_id IS NULL OR m.receptor_id = :recipient_id)
             ORDER BY m.fecha_envio DESC, m.id DESC
             LIMIT 50'
        );
        $messages->execute(['user_id' => $user['id'], 'role' => $role, 'recipient_id' => $user['id']]);
        warehouse_messages_json([
            'messages' => $messages->fetchAll(),
            'unread_count' => warehouse_messages_unread_count($pdo, $role, $user['id']),
        ]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        warehouse_messages_json(['error' => 'Método no permitido.'], 405);
    }

    $submittedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!isset($_SESSION['messages_csrf']) || !hash_equals((string) $_SESSION['messages_csrf'], $submittedToken)) {
        warehouse_messages_json(['error' => 'La sesión expiró. Actualiza la página e inténtalo nuevamente.'], 403);
    }

    $action = (string) ($_POST['action'] ?? 'mark_read');
    if ($action === 'contact_admin') {
        if ($role !== 'vendedor') {
            warehouse_messages_json(['error' => 'Solo un vendedor puede escribir al administrador.'], 403);
        }

        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($subject === '' || mb_strlen($subject, 'UTF-8') > 150) {
            warehouse_messages_json(['error' => 'Escribe un asunto de hasta 150 caracteres.'], 422);
        }
        if ($message === '' || mb_strlen($message, 'UTF-8') > 2000) {
            warehouse_messages_json(['error' => 'Escribe un mensaje de hasta 2000 caracteres.'], 422);
        }

        $insert = $pdo->prepare(
            "INSERT INTO mensajes_almacen
             (emisor_id, receptor_rol, receptor_id, tipo, asunto, mensaje)
             VALUES (:sender_id, 'admin', NULL, 'aviso', :subject, :message)"
        );
        $insert->execute([
            'sender_id' => $user['id'],
            'subject' => $subject,
            'message' => $message,
        ]);
        warehouse_messages_json(['success' => true, 'message' => 'Mensaje enviado al administrador.']);
    }

    if ($action === 'help_request') {
        if ($role !== 'vendedor') {
            warehouse_messages_json(['error' => 'Solo un vendedor puede solicitar apoyo desde un aviso.'], 403);
        }

        $messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $note = trim((string) ($_POST['mensaje'] ?? ''));
        if ($messageId === false || $messageId === null) {
            warehouse_messages_json(['error' => 'No se encontró el aviso relacionado.'], 422);
        }
        if ($note === '' || mb_strlen($note, 'UTF-8') > 2000) {
            warehouse_messages_json(['error' => 'Describe brevemente por qué necesitas ayuda (máximo 2000 caracteres).'], 422);
        }

        $sourceQuery = $pdo->prepare(
            "SELECT asunto FROM mensajes_almacen
             WHERE id = :id AND receptor_rol = 'vendedor'
               AND (receptor_id IS NULL OR receptor_id = :recipient_id)"
        );
        $sourceQuery->execute(['id' => $messageId, 'recipient_id' => $user['id']]);
        $sourceSubject = $sourceQuery->fetchColumn();
        if ($sourceSubject === false) {
            warehouse_messages_json(['error' => 'El aviso ya no está disponible. Actualiza la bandeja e inténtalo nuevamente.'], 404);
        }

        $subject = mb_substr('Solicitud de apoyo: ' . (string) $sourceSubject, 0, 150, 'UTF-8');
        $insert = $pdo->prepare(
            "INSERT INTO mensajes_almacen
             (emisor_id, receptor_rol, asunto, mensaje, imagen_adjunta)
             VALUES (:sender_id, 'admin', :subject, :message, NULL)"
        );
        $insert->execute([
            'sender_id' => $user['id'],
            'subject' => $subject,
            'message' => 'El vendedor solicita apoyo para atender el aviso #' . $messageId . ".\n\n" . $note,
        ]);

        warehouse_messages_json(['success' => true, 'message' => 'Solicitud enviada al administrador.']);
    }

    if ($action === 'admin_reply') {
        if ($role !== 'admin') {
            warehouse_messages_json(['error' => 'Solo un administrador puede responder a vendedores.'], 403);
        }

        $messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reply = trim((string) ($_POST['message'] ?? ''));
        if ($messageId === false || $messageId === null) {
            warehouse_messages_json(['error' => 'No se encontró el mensaje que quieres responder.'], 422);
        }
        if ($reply === '' || mb_strlen($reply, 'UTF-8') > 2000) {
            warehouse_messages_json(['error' => 'Escribe una respuesta de hasta 2000 caracteres.'], 422);
        }

        $sourceQuery = $pdo->prepare(
            'SELECT m.emisor_id, m.asunto, u.' . $userIdentifier($roleColumn) . ' AS emisor_rol
             FROM mensajes_almacen AS m
             INNER JOIN usuarios AS u ON u.id = m.emisor_id
             WHERE m.id = :id AND m.receptor_rol = \'admin\''
        );
        $sourceQuery->execute(['id' => $messageId]);
        $source = $sourceQuery->fetch();
        if (!$source || $source['emisor_rol'] !== 'vendedor') {
            warehouse_messages_json(['error' => 'Solo puedes responder mensajes enviados por vendedores.'], 404);
        }

        $insert = $pdo->prepare(
            "INSERT INTO mensajes_almacen
             (emisor_id, receptor_rol, receptor_id, respuesta_a, tipo, asunto, mensaje)
             VALUES (:sender_id, 'vendedor', :recipient_id, :parent_id, 'aviso', :subject, :message)"
        );
        $insert->execute([
            'sender_id' => $user['id'],
            'recipient_id' => $source['emisor_id'],
            'parent_id' => $messageId,
            'subject' => mb_substr('Re: ' . (string) $source['asunto'], 0, 150, 'UTF-8'),
            'message' => $reply,
        ]);
        warehouse_messages_json(['success' => true, 'message' => 'Respuesta enviada al vendedor.']);
    }

    if ($action === 'restock_request') {
        if ($role !== 'vendedor') {
            warehouse_messages_json(['error' => 'Solo los vendedores pueden solicitar reabastecimiento.'], 403);
        }

        $productId = trim((string) ($_POST['product_id'] ?? ''));
        if ($productId === '' || strlen($productId) > 100) {
            warehouse_messages_json(['error' => 'El producto seleccionado no es válido.'], 422);
        }

        $map = product_catalog_map(product_catalog_columns($pdo));
        if (!isset($map['id'], $map['name'], $map['stock'], $map['stock_minimum'])) {
            warehouse_messages_json(['error' => 'Faltan columnas de producto o stock mínimo.'], 422);
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
            throw new DomainException('El producto ya no tiene stock bajo. Actualiza el catálogo.');
        }

        $duplicateQuery = $pdo->prepare(
            "SELECT id FROM mensajes_almacen
             WHERE tipo = 'solicitud_reabastecimiento'
               AND producto_id = :product_id AND emisor_id = :sender_id AND estado = 'pendiente'
             ORDER BY id DESC LIMIT 1"
        );
        $duplicateQuery->execute(['product_id' => $productId, 'sender_id' => $user['id']]);
        $existingRequestId = $duplicateQuery->fetchColumn();
        if ($existingRequestId !== false) {
            $pdo->commit();
            warehouse_messages_json([
                'success' => true,
                'duplicate' => true,
                'message' => 'Ya enviaste una solicitud pendiente para este producto.',
            ]);
        }

        $suggestedQuantity = max(1, (int) ceil($minimumStock - $currentStock));
        $insert = $pdo->prepare(
            "INSERT INTO mensajes_almacen
             (emisor_id, receptor_rol, tipo, asunto, mensaje, producto_id, cantidad_sugerida)
             VALUES (:sender_id, 'admin', 'solicitud_reabastecimiento', :subject, :message, :product_id, :quantity)"
        );
        $productName = (string) $product['product_name'];
        $insert->execute([
            'sender_id' => $user['id'],
            'subject' => mb_substr('Reabastecimiento: ' . $productName, 0, 150, 'UTF-8'),
            'message' => 'El vendedor ' . $user['username'] . ' solicita surtir el producto ' . $productName .
                '. Stock actual: ' .
                rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.') .
                '; mínimo: ' . $minimumStock . '. Cantidad sugerida: ' . $suggestedQuantity . '.',
            'product_id' => (string) $product['product_id'],
            'quantity' => $suggestedQuantity,
        ]);
        $pdo->commit();
        warehouse_messages_json([
            'success' => true,
            'message' => 'Solicitud enviada al administrador.',
            'suggested_quantity' => $suggestedQuantity,
        ]);
    }

    if ($action === 'fulfill_restock') {
        if ($role !== 'admin') {
            warehouse_messages_json(['error' => 'Solo un administrador puede atender reabastecimientos.'], 403);
        }
        $messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $receivedQuantity = filter_var($_POST['quantity_received'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($messageId === false || $messageId === null || $receivedQuantity === false) {
            warehouse_messages_json(['error' => 'Indica una cantidad recibida válida.'], 422);
        }

        $map = product_catalog_map(product_catalog_columns($pdo));
        if (!isset($map['id'], $map['stock'])) {
            warehouse_messages_json(['error' => 'No está configurada la columna de existencias del catálogo.'], 422);
        }

        $pdo->beginTransaction();
        $requestQuery = $pdo->prepare(
            "SELECT id, producto_id, estado FROM mensajes_almacen
             WHERE id = :id AND receptor_rol = 'admin'
               AND tipo = 'solicitud_reabastecimiento' FOR UPDATE"
        );
        $requestQuery->execute(['id' => $messageId]);
        $request = $requestQuery->fetch();
        if (!$request || $request['estado'] !== 'pendiente') {
            throw new DomainException('La solicitud no existe o ya fue atendida.');
        }

        $stockUpdate = $pdo->prepare(
            'UPDATE `productos` SET ' . product_catalog_identifier($map['stock']) . ' = ' .
            product_catalog_identifier($map['stock']) . ' + :quantity WHERE ' .
            product_catalog_identifier($map['id']) . ' = :product_id'
        );
        $stockUpdate->execute(['quantity' => $receivedQuantity, 'product_id' => $request['producto_id']]);
        if ($stockUpdate->rowCount() !== 1) {
            throw new DomainException('El producto relacionado ya no existe.');
        }

        $complete = $pdo->prepare(
            "UPDATE mensajes_almacen
             SET estado = 'atendida', cantidad_recibida = :quantity, atendida_por = :admin_id,
                 atendida_en = CURRENT_TIMESTAMP
             WHERE id = :id AND estado = 'pendiente'"
        );
        $complete->execute([
            'quantity' => $receivedQuantity,
            'admin_id' => $user['id'],
            'id' => $messageId,
        ]);
        $resolveAutomaticAlert = $pdo->prepare(
            "UPDATE mensajes_almacen SET estado = 'atendida', atendida_por = :admin_id,
                 atendida_en = CURRENT_TIMESTAMP
             WHERE producto_id = :product_id AND tipo = 'alerta_stock' AND estado = 'pendiente'"
        );
        $resolveAutomaticAlert->execute([
            'admin_id' => $user['id'],
            'product_id' => (string) $request['producto_id'],
        ]);
        $recordRead = $pdo->prepare(
            'INSERT IGNORE INTO mensajes_almacen_lecturas (mensaje_id, usuario_id)
             VALUES (:message_id, :user_id)'
        );
        $recordRead->execute(['message_id' => $messageId, 'user_id' => $user['id']]);
        $pdo->prepare('UPDATE mensajes_almacen SET leido = 1 WHERE id = :id')
            ->execute(['id' => $messageId]);
        $pdo->commit();
        warehouse_messages_json([
            'success' => true,
            'message' => 'Existencias actualizadas y solicitud marcada como atendida.',
        ]);
    }

    if ($action !== 'mark_read') {
        warehouse_messages_json(['error' => 'La acción solicitada no es válida.'], 422);
    }

    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || count($ids) > 50) {
        warehouse_messages_json(['error' => 'La lista de avisos no es válida.'], 422);
    }

    $validIds = [];
    foreach ($ids as $id) {
        $validId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validId === false) {
            warehouse_messages_json(['error' => 'La lista de avisos no es válida.'], 422);
        }
        $validIds[] = $validId;
    }

    if ($validIds) {
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $pdo->beginTransaction();
        $recordRead = $pdo->prepare(
            "INSERT IGNORE INTO mensajes_almacen_lecturas (mensaje_id, usuario_id)
             SELECT id, ? FROM mensajes_almacen
             WHERE receptor_rol = ? AND (receptor_id IS NULL OR receptor_id = ?) AND id IN ($placeholders)"
        );
        $recordRead->execute(array_merge([$user['id'], $role, $user['id']], $validIds));
        $updateAggregate = $pdo->prepare(
            "UPDATE mensajes_almacen AS m
             SET leido = (
               SELECT COUNT(DISTINCT r.usuario_id)
               FROM mensajes_almacen_lecturas AS r
               INNER JOIN usuarios AS u ON u.id = r.usuario_id AND u." . $userIdentifier($roleColumn) . " = ?
               WHERE r.mensaje_id = m.id
             ) >= (SELECT COUNT(*) FROM usuarios WHERE " . $userIdentifier($roleColumn) . " = ?)
             WHERE m.receptor_rol = ? AND (m.receptor_id IS NULL OR m.receptor_id = ?) AND m.id IN ($placeholders)"
        );
        $updateAggregate->execute(array_merge([$role, $role, $role, $user['id']], $validIds));
        $pdo->commit();
    }

    warehouse_messages_json([
        'success' => true,
        'unread_count' => warehouse_messages_unread_count($pdo, $role, $user['id']),
    ]);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OmniStock warehouse message API: ' . $exception->getMessage());
    warehouse_messages_json([
        'error' => 'No se pudieron cargar los avisos. Verifica que la migración de mensajería esté instalada.',
    ], 503);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OmniStock warehouse message API: ' . $exception->getMessage());
    warehouse_messages_json(['error' => 'No se pudo completar la operación de avisos.'], 500);
}
