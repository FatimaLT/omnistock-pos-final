<?php
declare(strict_types=1);

/**
 * OmniStock - API Exportación de Reportes
 * Genera reportes en PDF, Excel y CSV
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_authenticated_user();

$pdo = database_connection();
$format = $_GET['format'] ?? 'pdf';
$report = $_GET['report'] ?? 'ventas';
$fechaInicio = trim((string) ($_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'))));
$fechaFin = trim((string) ($_GET['fecha_fin'] ?? date('Y-m-d')));

try {
  switch ($format) {
    case 'pdf':
      exportarPDF($pdo, $report, $fechaInicio, $fechaFin);
      break;
    case 'excel':
      exportarExcel($pdo, $report, $fechaInicio, $fechaFin);
      break;
    case 'csv':
      exportarCSV($pdo, $report, $fechaInicio, $fechaFin);
      break;
    default:
      throw new Exception('Formato no soportado', 400);
  }
} catch (Exception $e) {
  http_response_code($e->getCode() ?: 500);
  echo "Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

/**
 * Exportar a PDF (usando HTML)
 */
function exportarPDF(PDO $pdo, string $report, string $fechaInicio, string $fechaFin): void {
  $datos = obtenerDatosReporte($pdo, $report, $fechaInicio, $fechaFin);
  $titulo = obtenerTituloReporte($report);
  
  $html = generarHTMLReporte($titulo, $datos, $report, $fechaInicio, $fechaFin);
  
  header('Content-Type: application/pdf; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . generarNombreArchivo($report, 'pdf') . '"');
  header('Cache-Control: no-cache, no-store, must-revalidate');
  
  // Para generar PDF, retornamos HTML que el navegador puede imprimir como PDF
  echo $html;
}

/**
 * Exportar a Excel (CSV con formato)
 */
function exportarExcel(PDO $pdo, string $report, string $fechaInicio, string $fechaFin): void {
  $datos = obtenerDatosReporte($pdo, $report, $fechaInicio, $fechaFin);
  $titulo = obtenerTituloReporte($report);
  
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . generarNombreArchivo($report, 'csv') . '"');
  header('Cache-Control: no-cache, no-store, must-revalidate');
  
  $output = fopen('php://output', 'w');
  
  // BOM para Excel
  fwrite($output, "\xEF\xBB\xBF");
  
  // Encabezado
  fputcsv($output, [$titulo . " - " . date('d/m/Y')], ';');
  fputcsv($output, ["Período: $fechaInicio a $fechaFin"], ';');
  fputcsv($output, [], ';');
  
  if (!empty($datos)) {
    // Headers
    fputcsv($output, array_keys($datos[0]), ';');
    
    // Datos
    foreach ($datos as $fila) {
      fputcsv($output, $fila, ';');
    }
  }
  
  fclose($output);
}

/**
 * Exportar a CSV
 */
function exportarCSV(PDO $pdo, string $report, string $fechaInicio, string $fechaFin): void {
  $datos = obtenerDatosReporte($pdo, $report, $fechaInicio, $fechaFin);
  
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . generarNombreArchivo($report, 'csv') . '"');
  header('Cache-Control: no-cache, no-store, must-revalidate');
  
  $output = fopen('php://output', 'w');
  
  // BOM para UTF-8
  fwrite($output, "\xEF\xBB\xBF");
  
  if (!empty($datos)) {
    fputcsv($output, array_keys($datos[0]));
    foreach ($datos as $fila) {
      fputcsv($output, $fila);
    }
  }
  
  fclose($output);
}

/**
 * Obtener datos del reporte según tipo
 */
function obtenerDatosReporte(PDO $pdo, string $report, string $fechaInicio, string $fechaFin): array {
  switch ($report) {
    case 'ventas':
      return obtenerReporteVentas($pdo, $fechaInicio, $fechaFin);
    case 'inventario':
      return obtenerReporteInventario($pdo);
    case 'productos_top':
      return obtenerReporteProductosTop($pdo, $fechaInicio, $fechaFin);
    case 'movimientos':
      return obtenerReporteMovimientos($pdo, $fechaInicio, $fechaFin);
    default:
      return [];
  }
}

/**
 * Reporte de Ventas
 */
function obtenerReporteVentas(PDO $pdo, string $fechaInicio, string $fechaFin): array {
  $stmt = $pdo->prepare('
    SELECT 
      v.id as "ID Venta",
      DATE_FORMAT(v.fecha_venta, "%d/%m/%Y %H:%i") as "Fecha",
      v.total as "Total",
      v.tipo_pago as "Método Pago",
      v.estado_venta as "Estado"
    FROM ventas v
    WHERE DATE(v.fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
    ORDER BY v.fecha_venta DESC
  ');
  $stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin,
  ]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Reporte de Inventario
 */
function obtenerReporteInventario(PDO $pdo): array {
  $stmt = $pdo->query('
    SELECT 
      p.codigo as "Código",
      p.nombre_producto as "Producto",
      p.stock_actual as "Stock Actual",
      p.stock_minimo as "Stock Mínimo",
      p.precio_compra as "P. Compra",
      p.precio_venta as "P. Venta",
      (p.stock_actual * p.precio_compra) as "Valor Inventario"
    FROM productos p
    ORDER BY p.nombre_producto ASC
  ');
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Reporte de Productos Top (Más Vendidos)
 */
function obtenerReporteProductosTop(PDO $pdo, string $fechaInicio, string $fechaFin): array {
  $stmt = $pdo->prepare('
    SELECT 
      p.codigo as "Código",
      p.nombre as "Producto",
      SUM(dv.cantidad) as "Cantidad Vendida",
      COUNT(DISTINCT v.id) as "# Transacciones",
      SUM(dv.cantidad * dv.precio_unitario) as "Ingresos"
    FROM detalle_ventas dv
    JOIN ventas v ON dv.venta_id = v.id
    JOIN productos p ON dv.producto_id = p.id
    WHERE DATE(v.fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
    GROUP BY p.id
    ORDER BY SUM(dv.cantidad) DESC
    LIMIT 20
  ');
  $stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin,
  ]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Reporte de Movimientos de Inventario
 */
function obtenerReporteMovimientos(PDO $pdo, string $fechaInicio, string $fechaFin): array {
  $stmt = $pdo->prepare('
    SELECT 
      m.id as "ID",
      DATE_FORMAT(m.fecha, "%d/%m/%Y %H:%i") as "Fecha",
      m.tipo_movimiento as "Tipo",
      p.nombre_producto as "Producto",
      m.cantidad as "Cantidad",
      m.razon as "Razón"
    FROM movimientos_inventario m
    JOIN productos p ON m.producto_id = p.id
    WHERE DATE(m.fecha) BETWEEN :fecha_inicio AND :fecha_fin
    ORDER BY m.fecha DESC
  ');
  $stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin,
  ]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generar nombre de archivo
 */
function generarNombreArchivo(string $report, string $extension): string {
  $fecha = date('Y-m-d-His');
  return "omnistock-{$report}-{$fecha}.{$extension}";
}

/**
 * Obtener título del reporte
 */
function obtenerTituloReporte(string $report): string {
  $titulos = [
    'ventas' => 'Reporte de Ventas',
    'inventario' => 'Reporte de Inventario',
    'productos_top' => 'Productos Más Vendidos',
    'movimientos' => 'Movimientos de Inventario',
  ];
  return $titulos[$report] ?? 'Reporte';
}

/**
 * Generar HTML para visualización y impresión a PDF
 */
function generarHTMLReporte(string $titulo, array $datos, string $report, string $fechaInicio, string $fechaFin): string {
  $fechaActual = date('d/m/Y H:i');
  $empresaNombre = getenv('OMNISTOCK_EMPRESA_NOMBRE') ?: 'OmniStock POS';
  
  $html = <<<HTML
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$titulo</title>
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
      .container { max-width: 900px; margin: 0 auto; }
      .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #06b6d4; padding-bottom: 20px; }
      .header h1 { color: #0f172a; font-size: 24px; margin-bottom: 5px; }
      .header p { color: #666; font-size: 12px; }
      .info { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12px; }
      .info div { display: flex; flex-direction: column; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
      th { background: #0f172a; color: white; padding: 10px; text-align: left; font-weight: bold; border: 1px solid #ccc; }
      td { padding: 8px; border: 1px solid #ddd; }
      tr:nth-child(even) { background: #f9f9f9; }
      .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 11px; color: #999; }
      @media print {
        body { padding: 0; }
        .no-print { display: none; }
      }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="header">
        <h1>$empresaNombre</h1>
        <p>$titulo</p>
      </div>
      
      <div class="info">
        <div>
          <strong>Período:</strong> $fechaInicio a $fechaFin
        </div>
        <div>
          <strong>Generado:</strong> $fechaActual
        </div>
      </div>
      
      <table>
        <thead>
          <tr>
HTML;

  if (!empty($datos)) {
    // Headers
    foreach (array_keys($datos[0]) as $header) {
      $html .= "<th>" . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . "</th>";
    }
    $html .= "</tr></thead><tbody>";
    
    // Datos
    foreach ($datos as $fila) {
      $html .= "<tr>";
      foreach ($fila as $valor) {
        $html .= "<td>" . htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8') . "</td>";
      }
      $html .= "</tr>";
    }
  }

  $html .= <<<HTML
        </tbody>
      </table>
      
      <div class="footer">
        <p>Este documento fue generado automáticamente por OmniStock POS</p>
        <p>© $(date('Y')) - Todos los derechos reservados</p>
      </div>
    </div>
  </body>
  </html>
HTML;

  return $html;
}
