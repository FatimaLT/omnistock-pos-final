<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/product_catalog.php';
require_once __DIR__ . '/../config/stock_alerts.php';
$user = require_admin_user();

if (!isset($_SESSION['catalog_csrf'])) {
    $_SESSION['catalog_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['catalog_csrf'];
$errorMessage = '';
$formData = [];
$editingId = '';
$databaseAvailable = false;

if (isset($_SESSION['catalog_flash'])) {
    $flash = $_SESSION['catalog_flash'];
    unset($_SESSION['catalog_flash']);
} else {
    $flash = null;
}

try {
    $pdo = database_connection();
    $columns = product_catalog_columns($pdo);
    $map = product_catalog_map($columns);

    if (!isset($map['id'], $map['name'], $map['price'])) {
        throw new RuntimeException('La tabla productos debe tener columnas reconocibles para ID, nombre y precio.');
    }
    $databaseAvailable = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $submittedToken)) {
            throw new RuntimeException('La solicitud expiró. Actualiza la página e inténtalo nuevamente.');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update_stock') {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            if (!isset($map['stock'])) {
                http_response_code(422);
                echo json_encode(['error' => 'La tabla de productos no tiene una columna de existencias.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $stock = $_POST['stock'] ?? null;
            if ($id === false || $id === null || !is_scalar($stock) || !is_numeric((string) $stock) || (float) $stock < 0 || (float) $stock > 1000000000) {
                http_response_code(422);
                echo json_encode(['error' => 'Ingresa una existencia válida, igual o mayor que cero.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $statement = $pdo->prepare(
                'UPDATE `productos` SET ' . product_catalog_identifier($map['stock']) . ' = :stock WHERE ' .
                product_catalog_identifier($map['id']) . ' = :id'
            );
            $statement->execute(['stock' => $stock, 'id' => $id]);
            $exists = $pdo->prepare(
                'SELECT 1 FROM `productos` WHERE ' . product_catalog_identifier($map['id']) . ' = :id'
            );
            $exists->execute(['id' => $id]);
            if ($exists->fetchColumn() === false) {
                http_response_code(404);
                echo json_encode(['error' => 'No se encontró el producto.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $minimumQuery = isset($map['stock_minimum'])
                ? $pdo->prepare(
                    'SELECT ' . product_catalog_identifier($map['stock_minimum']) . ' FROM `productos` WHERE ' .
                    product_catalog_identifier($map['id']) . ' = :id'
                )
                : null;
            $minimum = 5;
            if ($minimumQuery !== null) {
                $minimumQuery->execute(['id' => $id]);
                $minimum = (int) $minimumQuery->fetchColumn();
            }
            echo json_encode(['success' => true, 'stock' => (string) $stock, 'minimum' => $minimum], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id === null) {
                throw new RuntimeException('No se identificó el producto que deseas eliminar.');
            }

            $query = $pdo->prepare(
                'SELECT * FROM `productos` WHERE ' . product_catalog_identifier($map['id']) . ' = :id'
            );
            $query->execute(['id' => $id]);
            $product = $query->fetch();
            if (!$product) {
                throw new RuntimeException('El producto ya no existe o fue eliminado.');
            }

            $delete = $pdo->prepare(
                'DELETE FROM `productos` WHERE ' . product_catalog_identifier($map['id']) . ' = :id'
            );
            $delete->execute(['id' => $id]);
            if (isset($map['image'])) {
                delete_product_image($product[$map['image']] ?? null);
            }

            $_SESSION['catalog_flash'] = ['type' => 'success', 'message' => 'Producto eliminado correctamente.'];
            header('Location: index.php');
            exit;
        }

        if ($action !== 'save') {
            throw new RuntimeException('La acción solicitada no es válida.');
        }

        $editingId = trim((string) ($_POST['id'] ?? ''));
        $formData = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'price' => trim((string) ($_POST['price'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'code' => trim((string) ($_POST['code'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'stock' => trim((string) ($_POST['stock'] ?? '0')),
            'stock_minimum' => trim((string) ($_POST['stock_minimum'] ?? '5')),
        ];

        if ($formData['name'] === '' || strlen($formData['name']) > 255) {
            throw new RuntimeException('El nombre es obligatorio y no debe exceder 255 caracteres.');
        }
        if (!is_numeric($formData['price']) || (float) $formData['price'] < 0 || (float) $formData['price'] > 1000000000) {
            throw new RuntimeException('Ingresa un precio válido, igual o mayor que cero.');
        }
        if (isset($map['stock']) && (!is_numeric($formData['stock']) || (float) $formData['stock'] < 0 || (float) $formData['stock'] > 1000000000)) {
            throw new RuntimeException('Ingresa una existencia válida, igual o mayor que cero.');
        }
        if (isset($map['stock_minimum']) && (
            filter_var($formData['stock_minimum'], FILTER_VALIDATE_INT) === false ||
            (int) $formData['stock_minimum'] < 0 ||
            (int) $formData['stock_minimum'] > 1000000000
        )) {
            throw new RuntimeException('El stock mínimo debe ser un número entero igual o mayor que cero.');
        }
        if ($editingId !== '' && filter_var($editingId, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('No se identificó el producto que deseas actualizar.');
        }

        $existingProduct = null;
        if ($editingId !== '') {
            $query = $pdo->prepare(
                'SELECT * FROM `productos` WHERE ' . product_catalog_identifier($map['id']) . ' = :id'
            );
            $query->execute(['id' => (int) $editingId]);
            $existingProduct = $query->fetch();
            if (!$existingProduct) {
                throw new RuntimeException('El producto que deseas actualizar ya no existe.');
            }
        }

        $newImage = product_upload($_FILES['image'] ?? []);
        $data = [
            'name' => $formData['name'],
            'price' => $formData['price'],
        ];
        foreach (['category', 'code', 'description', 'stock', 'stock_minimum'] as $field) {
            if (isset($map[$field])) {
                $data[$field] = $formData[$field] === '' && $field !== 'stock' ? null : $formData[$field];
            }
        }
        if (isset($map['image'])) {
            if ($newImage !== null) {
                $data['image'] = $newImage;
            } elseif ($editingId !== '' && isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                $data['image'] = null;
            }
        } elseif ($newImage !== null) {
            delete_product_image($newImage);
            throw new RuntimeException('La tabla productos no tiene una columna para guardar la ruta de la imagen.');
        }

        try {
            if ($editingId === '') {
                $fields = [];
                $placeholders = [];
                $parameters = [];
                foreach ($data as $field => $value) {
                    if (!isset($map[$field])) {
                        continue;
                    }
                    $fields[] = product_catalog_identifier($map[$field]);
                    $placeholders[] = ':' . $field;
                    $parameters[$field] = $value;
                }
                $statement = $pdo->prepare(
                    'INSERT INTO `productos` (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')'
                );
                $statement->execute($parameters);
                $message = 'Producto creado correctamente.';
            } else {
                $assignments = [];
                $parameters = [];
                foreach ($data as $field => $value) {
                    if (!isset($map[$field])) {
                        continue;
                    }
                    $assignments[] = product_catalog_identifier($map[$field]) . ' = :' . $field;
                    $parameters[$field] = $value;
                }
                $parameters['id'] = (int) $editingId;
                $statement = $pdo->prepare(
                    'UPDATE `productos` SET ' . implode(', ', $assignments) . ' WHERE ' .
                    product_catalog_identifier($map['id']) . ' = :id'
                );
                $statement->execute($parameters);
                $message = 'Producto actualizado correctamente.';
            }
        } catch (Throwable $exception) {
            if ($newImage !== null) {
                delete_product_image($newImage);
            }
            throw $exception;
        }

        if ($existingProduct !== null && isset($map['image'])) {
            $oldImage = $existingProduct[$map['image']] ?? null;
            $imageChanged = $newImage !== null || isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
            if ($imageChanged && $oldImage !== ($data['image'] ?? null)) {
                delete_product_image($oldImage);
            }
        }

        $_SESSION['catalog_flash'] = ['type' => 'success', 'message' => $message];
        header('Location: index.php');
        exit;
    }

    $orderBy = ' ORDER BY ' . product_catalog_identifier($map['id']) . ' DESC';
    $products = $pdo->query('SELECT * FROM `productos`' . $orderBy)->fetchAll();
} catch (PDOException $exception) {
    error_log('OmniStock product administration: ' . $exception->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'update_stock') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo actualizar la existencia. Inténtalo nuevamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $errorMessage = 'No se pudo completar la operación. Verifica la conexión y vuelve a intentarlo.';
    $products = $products ?? [];
    $columns = $columns ?? [];
    $map = $map ?? [];
} catch (RuntimeException $exception) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'update_stock') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(422);
        echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $errorMessage = $exception->getMessage();
    $products = $products ?? [];
    $columns = $columns ?? [];
    $map = $map ?? [];
    if (isset($pdo, $map['id'])) {
        try {
            $products = $pdo->query(
                'SELECT * FROM `productos` ORDER BY ' . product_catalog_identifier($map['id']) . ' DESC'
            )->fetchAll();
        } catch (Throwable $loadException) {
            error_log('OmniStock product list after validation: ' . $loadException->getMessage());
        }
    }
} catch (Throwable $exception) {
    error_log('OmniStock product administration: ' . $exception->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'update_stock') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo actualizar la existencia. Inténtalo nuevamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $errorMessage = 'No se pudo completar la operación. Verifica la conexión y la estructura de productos.';
    $products = $products ?? [];
    $columns = $columns ?? [];
    $map = $map ?? [];
    if (isset($pdo, $map['id'])) {
        try {
            $products = $pdo->query(
                'SELECT * FROM `productos` ORDER BY ' . product_catalog_identifier($map['id']) . ' DESC'
            )->fetchAll();
        } catch (Throwable $loadException) {
            error_log('OmniStock product list after operation error: ' . $loadException->getMessage());
        }
    }
}

function catalog_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$totalProducts = count($products ?? []);
$lowStockCount = 0;
if (isset($map['stock'], $map['stock_minimum'])) {
    foreach ($products as $productRow) {
        if ((float) product_catalog_value($productRow, $map, 'stock', 0) <= (int) product_catalog_value($productRow, $map, 'stock_minimum', 5)) {
            $lowStockCount++;
        }
    }
}

$editorOpen = $_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save' && $errorMessage !== '';
if ($editorOpen && !$formData) {
    $formData = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'price' => trim((string) ($_POST['price'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'code' => trim((string) ($_POST['code'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'stock' => trim((string) ($_POST['stock'] ?? '0')),
        'stock_minimum' => trim((string) ($_POST['stock_minimum'] ?? '5')),
    ];
}

$stockAlertCount = 0;
try {
    $stockAlertCount = count(critical_stock_products($pdo ?? database_connection()));
} catch (Throwable $exception) {
    error_log('OmniStock critical stock badge: ' . $exception->getMessage());
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <title>Productos · OmniStock POS</title>
  <script src="../assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="../assets/css/app.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/theme-toggle.css">
</head>
<body class="admin-page">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="brand admin-brand" href="index.php"><span class="brand-mark">O</span><span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span></a>
      <div class="sidebar-label">ADMINISTRACIÓN</div>
      <nav class="admin-nav">
        <a class="admin-nav-link selected" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16l-1.5 13h-13L4 7Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg><span>Catálogo</span></a>
        <a class="admin-nav-link" href="dashboard.php"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
        <a class="admin-nav-link" href="reports.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/></svg><span>Reportes</span></a>
        <a class="admin-nav-link" href="messages.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H8l-4 4V5Z"/><path d="M8 9h8m-8 4h5"/></svg><span>Mensajería</span></a>
        <a class="admin-nav-link" href="users.php"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1m2-11a3 3 0 0 1 0 6m1 1a5 5 0 0 1 3 4"/></svg><span>Usuarios</span></a>
        <?php require __DIR__ . '/_stock_alert_modal.php'; ?>
        <a class="admin-nav-link" href="../index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Punto de venta</span></a>
      </nav>
      <div class="admin-sidebar-footer">
        <div class="admin-user-avatar"><?= catalog_escape(strtoupper(substr($user['username'], 0, 2))) ?></div>
        <div class="admin-user-copy"><strong><?= catalog_escape($user['username']) ?></strong><span>Administrador</span></div>
        <a class="admin-logout" href="../logout.php" title="Cerrar sesión" aria-label="Cerrar sesión">↪</a>
      </div>
    </aside>

    <main class="admin-main">
      <header class="admin-topbar"><div><span>Administración</span><b>/</b><strong>Catálogo de productos</strong></div><div class="theme-header-actions"><a href="../index.php" class="view-pos-link">Abrir POS <span>↗</span></a><?php require __DIR__ . '/../components/atoms/theme-toggle.php'; ?></div></header>
      <div class="admin-content">
        <section class="admin-heading">
          <div><p class="eyebrow">INVENTARIO</p><h1>Productos</h1><p>Administra tu catálogo, precios e inventario desde un solo lugar.</p></div>
          <button type="button" class="primary-button" id="new-product"><span>＋</span> Nuevo producto</button>
        </section>

        <section class="catalog-metrics" aria-label="Resumen del catálogo">
          <article class="metric-card"><span class="metric-icon cyan">▦</span><div><small>PRODUCTOS EN CATÁLOGO</small><strong><?= $totalProducts ?></strong></div><span class="metric-caption">registros totales</span></article>
          <article class="metric-card"><span class="metric-icon amber">◷</span><div><small>STOCK CRÍTICO</small><strong><?= $lowStockCount ?></strong></div><span class="metric-caption">al mínimo configurado</span></article>
          <article class="metric-card"><span class="metric-icon green"><?= $databaseAvailable ? '✓' : '!' ?></span><div><small>ESTADO DEL SISTEMA</small><strong class="metric-ready<?= $databaseAvailable ? '' : ' offline' ?>"><?= $databaseAvailable ? 'Conectado' : 'Sin conexión' ?></strong></div><span class="metric-caption"><?= $databaseAvailable ? 'catálogo actualizado' : 'revisa MySQL' ?></span></article>
        </section>

        <?php if ($flash !== null): ?>
          <div class="admin-alert success" role="status"><?= catalog_escape($flash['message'] ?? '') ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
          <div class="admin-alert error" role="alert"><?= catalog_escape($errorMessage) ?></div>
        <?php endif; ?>

        <section class="products-panel">
          <div class="products-toolbar">
            <div><h2>Todos los productos</h2><span><?= $totalProducts ?> productos registrados</span></div>
            <label class="admin-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="product-search" type="search" placeholder="Buscar por nombre o código..." aria-label="Buscar productos"></label>
          </div>
          <div class="products-table-wrap">
            <table class="products-table">
              <thead><tr><th>PRODUCTO</th><th>CÓDIGO</th><th>CATEGORÍA</th><th>PRECIO</th><th>EXISTENCIAS</th><th class="actions-heading">ACCIONES</th></tr></thead>
              <tbody>
              <?php foreach ($products as $productRow):
                  $productId = (string) product_catalog_value($productRow, $map, 'id');
                  $productName = (string) product_catalog_value($productRow, $map, 'name');
                  $productImage = (string) product_catalog_value($productRow, $map, 'image');
                  $productStock = product_catalog_value($productRow, $map, 'stock', null);
                  $product = [
                      'id' => $productId,
                      'name' => $productName,
                      'price' => product_catalog_value($productRow, $map, 'price', '0'),
                      'image' => $productImage,
                      'category' => product_catalog_value($productRow, $map, 'category'),
                      'code' => product_catalog_value($productRow, $map, 'code'),
                      'description' => product_catalog_value($productRow, $map, 'description'),
                      'stock' => $productStock,
                      'stock_minimum' => product_catalog_value($productRow, $map, 'stock_minimum', 5),
                  ];
                  $imageUrl = $productImage !== '' && preg_match('~^https?://~i', $productImage)
                      ? $productImage
                      : ($productImage !== '' && str_starts_with($productImage, 'public/img/productos/')
                          ? '../' . $productImage
                          : '');
                  $searchText = strtolower($productName . ' ' . $product['code'] . ' ' . $product['category']);
              ?>
                <tr data-product-row data-search="<?= catalog_escape($searchText) ?>">
                  <td><div class="table-product">
                    <?php if ($imageUrl !== ''): ?><img src="<?= catalog_escape($imageUrl) ?>" alt="" class="table-product-image" loading="lazy">
                    <?php else: ?><span class="table-product-placeholder"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg></span><?php endif; ?>
                    <span><strong><?= catalog_escape($productName) ?></strong><small>ID <?= catalog_escape($productId) ?></small></span>
                  </div></td>
                  <td class="code-cell"><?= catalog_escape($product['code'] !== '' ? $product['code'] : '—') ?></td>
                  <td><span class="category-tag"><?= catalog_escape($product['category'] !== '' ? $product['category'] : 'General') ?></span></td>
                  <td class="price-cell">$<?= number_format((float) $product['price'], 2) ?></td>
                  <td><?php if ($productStock !== null): ?><button type="button" class="stock-value<?= (float) $productStock <= (int) $product['stock_minimum'] ? ' low' : '' ?>" data-stock-edit data-id="<?= catalog_escape($productId) ?>" data-name="<?= catalog_escape($productName) ?>" data-stock="<?= catalog_escape($productStock) ?>" data-minimum="<?= catalog_escape($product['stock_minimum']) ?>"><span></span><?= catalog_escape($productStock) ?> <small>Editar</small></button><?php else: ?><span class="stock-unavailable">No definido</span><?php endif; ?></td>
                  <td><div class="row-actions">
                    <button type="button" class="row-action edit-product" title="Editar producto" aria-label="Editar <?= catalog_escape($productName) ?>" data-product="<?= catalog_escape(json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5 5 5M4 20l4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Z"/></svg></button>
                    <form method="post" class="delete-form"><input type="hidden" name="csrf_token" value="<?= catalog_escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= catalog_escape($productId) ?>"><button type="submit" class="row-action danger" title="Eliminar producto" aria-label="Eliminar <?= catalog_escape($productName) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/></svg></button></form>
                  </div></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <div class="table-empty" id="table-empty" <?= $products ? 'hidden' : '' ?>><span>▦</span><strong><?= $products ? 'No encontramos productos' : 'Aún no hay productos' ?></strong><small><?= $products ? 'Prueba con otro término de búsqueda.' : 'Agrega el primer producto a tu catálogo.' ?></small></div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <dialog class="admin-modal" id="product-modal">
    <form method="post" enctype="multipart/form-data" id="product-form" class="modal-card">
      <input type="hidden" name="csrf_token" value="<?= catalog_escape($csrfToken) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="product-id" value="<?= catalog_escape($editingId) ?>">
      <header class="modal-header"><div><span class="eyebrow">CATÁLOGO</span><h2 id="modal-title">Nuevo producto</h2></div><button type="button" class="modal-close" data-close-modal aria-label="Cerrar">×</button></header>
      <div class="modal-body">
        <div class="form-grid">
          <label class="form-field full"><span class="field-label">Nombre del producto <b class="required-marker">*</b></span><input name="name" id="product-name" maxlength="255" required value="<?= catalog_escape($formData['name'] ?? '') ?>" placeholder="Ej. Café molido 500 g"></label>
          <label class="form-field"><span class="field-label">Precio de venta <b class="required-marker">*</b></span><div class="price-input"><b>$</b><input name="price" id="product-price" type="number" min="0" max="1000000000" step="0.01" required value="<?= catalog_escape($formData['price'] ?? '') ?>" placeholder="0.00"></div></label>
          <?php if (isset($map['stock'])): ?><label class="form-field">Existencias<input name="stock" id="product-stock" type="number" min="0" max="1000000000" step="0.001" value="<?= catalog_escape($formData['stock'] ?? '0') ?>" placeholder="0"></label><?php endif; ?>
          <?php if (isset($map['stock_minimum'])): ?><label class="form-field"><span class="field-label">Stock mínimo</span><input name="stock_minimum" id="product-stock-minimum" type="number" min="0" max="1000000000" step="1" value="<?= catalog_escape($formData['stock_minimum'] ?? '5') ?>" required></label><?php endif; ?>
          <?php if (isset($map['category'])): ?><label class="form-field">Categoría<input name="category" id="product-category" maxlength="120" value="<?= catalog_escape($formData['category'] ?? '') ?>" placeholder="Ej. Abarrotes"></label><?php endif; ?>
          <?php if (isset($map['code'])): ?><label class="form-field">Código / SKU<input name="code" id="product-code" maxlength="120" value="<?= catalog_escape($formData['code'] ?? '') ?>" placeholder="Ej. CAF-0500"></label><?php endif; ?>
          <?php if (isset($map['description'])): ?><label class="form-field full">Descripción<textarea name="description" id="product-description" rows="2" maxlength="1000" placeholder="Detalles del producto"><?= catalog_escape($formData['description'] ?? '') ?></textarea></label><?php endif; ?>
          <?php if (isset($map['image'])): ?>
            <div class="form-field full image-field"><span>Imagen del producto</span><div class="image-upload-row">
              <label class="image-drop" for="product-image"><input type="file" name="image" id="product-image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"><span class="upload-icon">↑</span><span><strong>Elegir una imagen</strong><small>PNG, JPG o WEBP · Máximo 5 MB</small></span></label>
              <div class="image-preview-wrap"><img id="image-preview" src="" alt="Vista previa de imagen" hidden><span id="image-preview-empty">Vista previa</span></div>
            </div><label class="remove-image" id="remove-image-wrap" hidden><input type="checkbox" name="remove_image" value="1" id="remove-image"> Quitar imagen actual</label></div>
          <?php endif; ?>
        </div>
      </div>
      <footer class="modal-footer"><button type="button" class="secondary-button" data-close-modal>Cancelar</button><button type="submit" class="primary-button"><span>✓</span> Guardar producto</button></footer>
    </form>
  </dialog>

  <dialog class="admin-modal stock-modal" id="stock-modal">
    <form id="stock-form" class="modal-card">
      <input type="hidden" name="csrf_token" value="<?= catalog_escape($csrfToken) ?>">
      <input type="hidden" name="action" value="update_stock">
      <input type="hidden" name="id" id="stock-product-id">
      <header class="modal-header"><div><span class="eyebrow">INVENTARIO</span><h2>Actualizar existencias</h2></div><button type="button" class="modal-close" data-close-modal aria-label="Cerrar">×</button></header>
      <div class="modal-body"><p class="stock-product-name" id="stock-product-name"></p><label class="form-field">Nueva existencia<input type="number" name="stock" id="stock-quantity" min="0" max="1000000000" step="0.001" required></label><p class="stock-feedback" id="stock-feedback" role="status"></p></div>
      <footer class="modal-footer"><button type="button" class="secondary-button" data-close-modal>Cancelar</button><button type="submit" class="primary-button" id="stock-save">Guardar stock</button></footer>
    </form>
  </dialog>
  <div class="admin-toast" id="admin-toast" role="status" aria-live="polite"></div>
  <script>window.omniStockAlerts = { csrf: <?= json_encode($csrfToken) ?>, count: <?= (int) $stockAlertCount ?> };</script>
  <script>window.omniStockCatalog = { csrf: <?= json_encode($csrfToken) ?>, reopenEditor: <?= $editorOpen ? 'true' : 'false' ?>, product: <?= json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, editingId: <?= json_encode($editingId) ?> };</script>
  <script src="../assets/js/admin.js" defer></script>
  <script src="../assets/js/stock-alerts.js" defer></script>
</body>
</html>
