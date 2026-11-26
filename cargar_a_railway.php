<?php
// Configuración automática con los datos de Railway
$host = getenv('OMNISTOCK_DB_HOST') ?: 'monorail.proxy.rlwy.net';
$port = getenv('OMNISTOCK_DB_PORT') ?: '33980';
$db   = getenv('OMNISTOCK_DB_NAME') ?: 'railway';
$user = getenv('OMNISTOCK_DB_USER') ?: 'root';
$pass = getenv('OMNISTOCK_DB_PASSWORD') ?: 'CcbjZAPbZovpQRziuzLcoZqFZOwnMoPB';

$conexion = new mysqli($host, $user, $pass, $db, $port);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Ruta de tu archivo SQL local
$archivo_sql = 'omnistock_db.sql';

if (!file_exists($archivo_sql)) {
    die("No se encontró el archivo omnistock_db.sql en la carpeta.");
}

$sql = file_get_contents($archivo_sql);

// Ejecutar todas las consultas del respaldo
if ($conexion->multi_query($sql)) {
    do {
        if ($result = $conexion->store_result()) {
            $result->free();
        }
    } while ($conexion->more_results() && $conexion->next_result());
    
    echo "<h1 style='color:green; font-family:sans-serif;'>¡Listo! Tus productos y base de datos se han importado a Railway correctamente.</h1>";
    echo "<p>Ya puedes cerrar esto y entrar a tu POS en Render.</p>";
} else {
    echo "<h3 style='color:red;'>Error al importar: " . $conexion->error . "</h3>";
}
