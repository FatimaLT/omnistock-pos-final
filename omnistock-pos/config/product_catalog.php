<?php
declare(strict_types=1);

function product_catalog_columns(PDO $pdo, string $table = 'productos'): array
{
    if (!in_array($table, ['productos', 'categorias'], true)) {
        throw new InvalidArgumentException('Tabla de catálogo no permitida.');
    }

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . product_catalog_identifier($table))->fetchAll() as $column) {
        $columns[strtolower((string) $column['Field'])] = (string) $column['Field'];
    }

    return $columns;
}

function product_catalog_map(array $columns): array
{
    $aliases = [
        'id' => ['id', 'producto_id', 'id_producto'],
        'name' => ['nombre', 'name', 'producto', 'descripcion_producto'],
        'price' => ['precio', 'precio_venta', 'precio_unitario', 'price'],
        'image' => ['imagen', 'image', 'foto', 'ruta_imagen', 'url_imagen', 'imagen_url', 'imagen_producto', 'image_url', 'product_image'],
        'category' => ['categoria', 'category', 'nombre_categoria'],
        'category_id' => ['categoria_id', 'id_categoria', 'category_id'],
        'stock' => ['stock', 'existencia', 'cantidad'],
        'stock_minimum' => ['stock_minimo', 'stock_minimo_unidades', 'stock_minimum', 'minimo_stock'],
        'code' => ['codigo', 'sku', 'barcode', 'codigo_barras'],
        'description' => ['descripcion', 'description', 'detalle'],
    ];
    $map = [];

    foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
            if (isset($columns[$name])) {
                $map[$field] = $columns[$name];
                break;
            }
        }
    }

    return $map;
}

function product_catalog_identifier(string $column): string
{
    return '`' . str_replace('`', '``', $column) . '`';
}

function product_catalog_value(array $row, array $map, string $field, mixed $default = ''): mixed
{
    return isset($map[$field]) && array_key_exists($map[$field], $row) && $row[$map[$field]] !== null
        ? $row[$map[$field]]
        : $default;
}

function product_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo recibir la imagen. Intenta nuevamente.');
    }

    $maximumBytes = 5 * 1024 * 1024;
    if (!isset($file['size']) || (int) $file['size'] <= 0 || (int) $file['size'] > $maximumBytes) {
        throw new RuntimeException('La imagen debe pesar como máximo 5 MB.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    if (!isset($allowed[$extension]) || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Formato no permitido. Usa una imagen PNG, JPG o WEBP válida.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    $imageInfo = getimagesize((string) $file['tmp_name']);

    if ($mime !== $allowed[$extension] || $imageInfo === false || $imageInfo['mime'] !== $mime) {
        throw new RuntimeException('El archivo no es una imagen PNG, JPG o WEBP válida.');
    }

    $directory = dirname(__DIR__) . '/public/img/productos';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo preparar el almacenamiento de imágenes.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('No se pudo guardar la imagen. Verifica los permisos de la carpeta.');
    }

    return 'public/img/productos/' . $filename;
}

function delete_product_image(mixed $path): void
{
    if (!is_string($path) || !preg_match('~^public/img/productos/[a-f0-9]{32}\.(?:png|jpg|jpeg|webp)$~i', $path)) {
        return;
    }

    $file = dirname(__DIR__) . '/' . $path;
    if (is_file($file) && !unlink($file)) {
        error_log('OmniStock could not remove product image: ' . $path);
    }
}
