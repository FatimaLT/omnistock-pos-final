<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app_paths.php';

$appBasePath = app_base_path();
$products = [
    ['name' => 'Café de muestra 250 g', 'category' => 'Abarrotes', 'code' => 'DEMO-001', 'price' => 89.00, 'stock' => 24],
    ['name' => 'Arroz de muestra 1 kg', 'category' => 'Abarrotes', 'code' => 'DEMO-002', 'price' => 34.00, 'stock' => 45],
    ['name' => 'Leche de muestra 1 L', 'category' => 'Lácteos', 'code' => 'DEMO-003', 'price' => 28.50, 'stock' => 31],
    ['name' => 'Jabón de muestra 150 g', 'category' => 'Higiene', 'code' => 'DEMO-004', 'price' => 22.00, 'stock' => 18],
    ['name' => 'Cuaderno de muestra', 'category' => 'Papelería', 'code' => 'DEMO-005', 'price' => 42.00, 'stock' => 12],
    ['name' => 'Agua de muestra 1 L', 'category' => 'Bebidas', 'code' => 'DEMO-006', 'price' => 16.00, 'stock' => 36],
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1120">
  <meta name="description" content="Demostración pública de solo lectura de OmniStock POS con productos ficticios.">
  <meta name="robots" content="noindex, nofollow">
  <title>Demo de OmniStock · Solo lectura</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/app.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/demo.css">
</head>
<body>
  <main class="public-demo">
    <header class="public-demo-header">
      <a class="brand public-demo-brand" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/login.php">
        <span class="brand-mark">O</span>
        <span class="brand-name">omni<span>stock</span><small>POS ENGINE</small></span>
      </a>
      <a class="public-demo-back" href="<?= htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8') ?>/login.php">Volver al acceso</a>
    </header>

    <section class="public-demo-intro" aria-labelledby="demo-title">
      <span class="public-demo-badge">DEMOSTRACIÓN · SOLO LECTURA</span>
      <h1 id="demo-title">Explora OmniStock</h1>
      <p>Este catálogo usa datos ficticios y no está conectado al inventario real. No permite iniciar sesión, registrar ventas ni modificar productos.</p>
    </section>

    <section class="public-demo-grid" aria-label="Productos ficticios de demostración">
      <?php foreach ($products as $product): ?>
        <article class="public-demo-card">
          <div class="public-demo-card-top">
            <span><?= htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= htmlspecialchars($product['code'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <h2><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="public-demo-card-bottom">
            <strong>$<?= number_format($product['price'], 2) ?></strong>
            <span>Existencia demo: <?= (int) $product['stock'] ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <footer class="public-demo-footer">La información de esta página es ficticia y solo sirve para explorar la interfaz.</footer>
  </main>
</body>
</html>
