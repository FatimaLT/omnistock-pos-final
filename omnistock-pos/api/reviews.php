<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

function reviews_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

start_user_session();
if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['username'])) {
    reviews_json(['error' => 'Inicia sesión para consultar o publicar reseñas.'], 401);
}
$user = [
    'id' => (int) $_SESSION['user_id'],
    'username' => (string) $_SESSION['username'],
];

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    reviews_json(['error' => 'Método no permitido.'], 405);
}

if ($method === 'POST') {
    $request = json_decode((string) file_get_contents('php://input'), true);
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_array($request) || !isset($_SESSION['reviews_csrf']) || !hash_equals($_SESSION['reviews_csrf'], $token)) {
        reviews_json(['error' => 'La sesión expiró. Actualiza la página e inténtalo nuevamente.'], 403);
    }

    $ratingValue = $request['rating'] ?? null;
    $rating = is_scalar($ratingValue) ? filter_var($ratingValue, FILTER_VALIDATE_INT) : false;
    $commentValue = $request['comment'] ?? null;
    $comment = is_string($commentValue) ? trim($commentValue) : '';
    $characterCount = preg_match_all('/./us', $comment);
    if ($rating === false || $rating < 1 || $rating > 5 || $characterCount === false || $characterCount < 10 || $characterCount > 1000) {
        reviews_json(['error' => 'Selecciona de 1 a 5 estrellas y escribe un comentario de 10 a 1000 caracteres.'], 422);
    }
}

try {
    $pdo = database_connection();
    if ($method === 'GET') {
        $statement = $pdo->query(
            'SELECT autor AS author, calificacion AS rating, comentario AS comment,
                    creada_en AS created_at, actualizada_en AS updated_at
             FROM resenas ORDER BY actualizada_en DESC LIMIT 30'
        );
        reviews_json(['reviews' => $statement->fetchAll()]);
    }

    $statement = $pdo->prepare(
        'INSERT INTO resenas (usuario_id, autor, calificacion, comentario)
         VALUES (:user_id, :author, :rating, :comment)
         ON DUPLICATE KEY UPDATE autor = :update_author, calificacion = :update_rating,
             comentario = :update_comment, actualizada_en = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'user_id' => $user['id'],
        'author' => substr($user['username'], 0, 80),
        'rating' => $rating,
        'comment' => $comment,
        'update_author' => substr($user['username'], 0, 80),
        'update_rating' => $rating,
        'update_comment' => $comment,
    ]);
    reviews_json(['success' => true, 'message' => 'Tu reseña se guardó correctamente.']);
} catch (Throwable $exception) {
    error_log('OmniStock reviews API: ' . $exception->getMessage());
    reviews_json(['error' => 'No se pudieron procesar las reseñas. Intenta nuevamente más tarde.'], 500);
}
