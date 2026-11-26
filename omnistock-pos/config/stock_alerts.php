<?php
declare(strict_types=1);

require_once __DIR__ . '/product_catalog.php';

function critical_stock_products(PDO $pdo): array
{
    $columns = product_catalog_columns($pdo);
    $map = product_catalog_map($columns);
    if (!isset($map['id'], $map['name'], $map['stock'], $map['stock_minimum'])) {
        return [];
    }

    $imageSelection = isset($map['image'])
        ? product_catalog_identifier($map['image'])
        : 'NULL';
    $statement = $pdo->query(
        'SELECT ' . product_catalog_identifier($map['id']) . ' AS product_id, ' .
        product_catalog_identifier($map['name']) . ' AS product_name, ' .
        product_catalog_identifier($map['stock']) . ' AS current_stock, ' .
        product_catalog_identifier($map['stock_minimum']) . ' AS minimum_stock, ' .
        $imageSelection . ' AS image_path
         FROM `productos`
         WHERE ' . product_catalog_identifier($map['stock']) . ' <= ' .
        product_catalog_identifier($map['stock_minimum']) . '
         ORDER BY (' . product_catalog_identifier($map['stock_minimum']) . ' - ' .
        product_catalog_identifier($map['stock']) . ') DESC, ' .
        product_catalog_identifier($map['name'])
    );

    return $statement->fetchAll();
}
