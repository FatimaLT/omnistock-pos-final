<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../config/product_catalog.php';

$checks = [
    'accepts an 18-digit CLABE' => static function (): void {
        if (!transfer_clabe_is_valid('646180157000000019')) {
            throw new RuntimeException('An 18-digit CLABE should be accepted.');
        }
    },
    'rejects a CLABE with the wrong length' => static function (): void {
        if (transfer_clabe_is_valid('64618015700000001') || transfer_clabe_is_valid('6461801570000000190')) {
            throw new RuntimeException('A CLABE with other than 18 digits should be rejected.');
        }
    },
    'rejects non-numeric CLABE values' => static function (): void {
        if (transfer_clabe_is_valid('64618015700000001A')) {
            throw new RuntimeException('A CLABE containing letters should be rejected.');
        }
    },
    'maps supported product column aliases' => static function (): void {
        $columns = [
            'id_producto' => 'id_producto',
            'descripcion_producto' => 'descripcion_producto',
            'precio_venta' => 'precio_venta',
            'existencia' => 'existencia',
            'codigo_barras' => 'codigo_barras',
        ];
        $expected = [
            'id' => 'id_producto',
            'name' => 'descripcion_producto',
            'price' => 'precio_venta',
            'stock' => 'existencia',
            'code' => 'codigo_barras',
        ];

        if (product_catalog_map($columns) !== $expected) {
            throw new RuntimeException('Supported product column aliases were not mapped correctly.');
        }
    },
    'prefers the first matching product alias' => static function (): void {
        $map = product_catalog_map(['name' => 'name', 'nombre' => 'nombre']);
        if (($map['name'] ?? null) !== 'nombre') {
            throw new RuntimeException('The canonical product alias should take precedence.');
        }
    },
    'returns mapped product values and defaults' => static function (): void {
        $row = ['precio_venta' => 12.5, 'stock' => null];
        $map = ['price' => 'precio_venta', 'stock' => 'stock'];
        if (product_catalog_value($row, $map, 'price') !== 12.5) {
            throw new RuntimeException('The mapped product value was not returned.');
        }
        if (product_catalog_value($row, $map, 'stock', 0) !== 0) {
            throw new RuntimeException('A null product value should return its default.');
        }
        if (product_catalog_value($row, $map, 'missing', 'n/a') !== 'n/a') {
            throw new RuntimeException('A missing product field should return its default.');
        }
    },
    'quotes dynamic SQL identifiers safely' => static function (): void {
        if (product_catalog_identifier('product`name') !== '`product``name`') {
            throw new RuntimeException('Backticks in a SQL identifier should be escaped.');
        }
    },
];

$failures = [];
foreach ($checks as $name => $check) {
    try {
        $check();
        fwrite(STDOUT, "PASS $name\n");
    } catch (Throwable $exception) {
        $failures[] = $name;
        fwrite(STDERR, "FAIL $name: {$exception->getMessage()}\n");
    }
}

fwrite(STDOUT, sprintf("%d checks, %d failures.\n", count($checks), count($failures)));
exit($failures === [] ? 0 : 1);
