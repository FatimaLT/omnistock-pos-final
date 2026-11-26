<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/product_catalog.php';

start_user_session();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Inicia sesión para consultar el catálogo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function send_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function product_image_value(mixed $value, mixed $productId = null): string
{
    $image = trim((string) $value);
    if ($image !== '') {
        if (
            preg_match('~^/?(?:public/img/productos|assets/img/productos)/[a-zA-Z0-9._-]+\.(?:png|jpe?g|webp)$~i', $image) === 1 &&
            !str_contains($image, '..')
        ) {
            $relativePath = ltrim($image, '/');
            $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR .
                str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (is_file($filePath) && is_readable($filePath)) {
                return $relativePath;
            }
        } elseif (
            filter_var($image, FILTER_VALIDATE_URL) &&
            in_array(strtolower((string) parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true)
        ) {
            return $image;
        }
    }

    $safeProductId = (string) $productId;
    if ($safeProductId !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $safeProductId) === 1) {
        foreach (['assets/img/productos', 'public/img/productos'] as $directory) {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
                $relativePath = $directory . '/producto_' . $safeProductId . '.' . $extension;
                $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR .
                    str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                if (is_file($filePath) && is_readable($filePath)) {
                    return $relativePath;
                }
            }
        }
    }

    return '';
}

function catalog_table_exists(PDO $pdo, string $table): bool
{
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $query->execute(['table_name' => $table]);

    return (int) $query->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    send_json(['error' => 'Método no permitido.'], 405);
}

try {
    $pdo = database_connection();
    $columns = product_catalog_columns($pdo);
    $map = product_catalog_map($columns);
    $idColumn = $map['id'] ?? null;
    if ($idColumn === null || !isset($map['name'], $map['price'])) {
        send_json(['error' => 'La tabla productos necesita columnas reconocibles para ID, nombre y precio.'], 422);
    }

    $categoryTableMap = [];
    $hasCategoryTable = isset($map['category_id']) && catalog_table_exists($pdo, 'categorias');
    if ($hasCategoryTable) {
        $categoryTableMap = product_catalog_map(product_catalog_columns($pdo, 'categorias'));
        if (!isset($categoryTableMap['id'], $categoryTableMap['name'])) {
            $hasCategoryTable = false;
        }
    }

    $productAlias = 'p';
    $select = [
        $productAlias . '.' . product_catalog_identifier($idColumn) . ' AS product_id',
        $productAlias . '.' . product_catalog_identifier($map['name']) . ' AS product_name',
        $productAlias . '.' . product_catalog_identifier($map['price']) . ' AS product_price',
    ];
    foreach (['image', 'code', 'stock', 'stock_minimum', 'description'] as $field) {
        $select[] = isset($map[$field])
            ? $productAlias . '.' . product_catalog_identifier($map[$field]) . ' AS product_' . $field
            : 'NULL AS product_' . $field;
    }

    if ($hasCategoryTable) {
        $select[] = $productAlias . '.' . product_catalog_identifier($map['category_id']) . ' AS product_category_id';
        $select[] = 'c.' . product_catalog_identifier($categoryTableMap['name']) . ' AS product_category';
        $join = ' LEFT JOIN `categorias` AS c ON ' . $productAlias . '.' .
            product_catalog_identifier($map['category_id']) . ' = c.' .
            product_catalog_identifier($categoryTableMap['id']);
        $categoryFilterColumn = $productAlias . '.' . product_catalog_identifier($map['category_id']);
    } else {
        $select[] = isset($map['category'])
            ? $productAlias . '.' . product_catalog_identifier($map['category']) . ' AS product_category'
            : 'NULL AS product_category';
        $select[] = isset($map['category_id'])
            ? $productAlias . '.' . product_catalog_identifier($map['category_id']) . ' AS product_category_id'
            : 'NULL AS product_category_id';
        $join = '';
        $categoryFilterColumn = isset($map['category'])
            ? $productAlias . '.' . product_catalog_identifier($map['category'])
            : null;
    }

    $baseQuery = 'SELECT ' . implode(', ', $select) . ' FROM `productos` AS ' . $productAlias .
        $join . ' ORDER BY ' . $productAlias . '.' .
        product_catalog_identifier($idColumn) . ' LIMIT :product_limit';
    $allStatement = $pdo->prepare($baseQuery);
    $allStatement->bindValue(':product_limit', 300, PDO::PARAM_INT);
    $allStatement->execute();
    $allRows = $allStatement->fetchAll();

    if ($hasCategoryTable) {
        $categorySelect = $pdo->query(
            'SELECT ' . product_catalog_identifier($categoryTableMap['id']) . ' AS category_id, ' .
            product_catalog_identifier($categoryTableMap['name']) . ' AS category_name
             FROM `categorias`
             ORDER BY ' . product_catalog_identifier($categoryTableMap['name'])
        );
        $categories = [];
        foreach ($categorySelect->fetchAll() as $categoryRow) {
            $categories[(string) $categoryRow['category_id']] = [
                'id' => (string) $categoryRow['category_id'],
                'name' => (string) $categoryRow['category_name'],
                'count' => 0,
            ];
        }
    } else {
        $categories = [];
    }

    foreach ($allRows as $categoryRow) {
        if ($categoryRow['product_name'] === null || !is_numeric($categoryRow['product_price'])) {
            continue;
        }
        $categoryName = trim((string) ($categoryRow['product_category'] ?? '')) ?: 'General';
        $categoryKey = $hasCategoryTable && $categoryRow['product_category_id'] !== null
            ? (string) $categoryRow['product_category_id']
            : $categoryName;
        if (!isset($categories[$categoryKey])) {
            $categories[$categoryKey] = [
                'id' => $categoryKey,
                'name' => $categoryName,
                'count' => 0,
            ];
        }
        $categories[$categoryKey]['count']++;
    }

    $categoryId = trim((string) ($_GET['categoria'] ?? ''));
    $rows = $allRows;
    if ($categoryId !== '' && $categoryFilterColumn !== null) {
        $filteredStatement = $pdo->prepare(
            'SELECT ' . implode(', ', $select) . ' FROM `productos` AS ' . $productAlias .
            $join . ' WHERE ' . $categoryFilterColumn . ' = :category_id ORDER BY ' .
            $productAlias . '.' . product_catalog_identifier($idColumn) . ' LIMIT :product_limit'
        );
        $filteredStatement->bindValue(':category_id', $categoryId, PDO::PARAM_STR);
        $filteredStatement->bindValue(':product_limit', 300, PDO::PARAM_INT);
        $filteredStatement->execute();
        $rows = $filteredStatement->fetchAll();
    }

    $products = [];

    foreach ($rows as $row) {
        $name = $row['product_name'];
        $price = $row['product_price'];

        if ($name === null || !is_numeric($price)) {
            continue;
        }

        $categoryName = trim((string) ($row['product_category'] ?? '')) ?: 'General';
        $categoryKey = $hasCategoryTable && $row['product_category_id'] !== null
            ? (string) $row['product_category_id']
            : $categoryName;
        $products[] = [
            'id' => (string) $row['product_id'],
            'name' => (string) $name,
            'price' => (float) $price,
            'image' => product_image_value($row['product_image'], $row['product_id']),
            'category' => $categoryName,
            'category_id' => $categoryKey,
            'code' => (string) ($row['product_code'] ?? ''),
            'stock' => is_numeric($row['product_stock']) ? (float) $row['product_stock'] : null,
            'stock_minimum' => is_numeric($row['product_stock_minimum']) ? (int) $row['product_stock_minimum'] : 5,
            'description' => trim((string) ($row['product_description'] ?? '')),
        ];
    }

    $categoryList = array_values($categories);
    usort($categoryList, static function (array $a, array $b): int {
        $preferred = ['bebidas', 'botanas', 'galletas', 'abarrotes'];
        $aName = mb_strtolower($a['name'], 'UTF-8');
        $bName = mb_strtolower($b['name'], 'UTF-8');
        $aIndex = array_search($aName, $preferred, true);
        $bIndex = array_search($bName, $preferred, true);
        if ($aIndex !== false || $bIndex !== false) {
            if ($aIndex === false) return 1;
            if ($bIndex === false) return -1;
            if ($aIndex !== $bIndex) return $aIndex <=> $bIndex;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    send_json(['categories' => $categoryList, 'products' => $products]);
} catch (PDOException $exception) {
    error_log('OmniStock products endpoint: ' . $exception->getMessage());
    send_json([
        'error' => 'No se pudo cargar el catálogo. Verifica MySQL, las credenciales y la tabla productos.',
    ], 500);
}
