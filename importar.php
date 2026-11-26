<?php
$host = 'monorail.proxy.rlwy.net';
$port = '33980';
$db   = 'railway';
$user = 'root';
$pass = 'CcbjZAPbZovpQRziuzLcoZqFZOwnMoPB';

$conexion = new mysqli($host, $user, $pass, $db, $port);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Lee tu archivo SQL y ejecútalo
$sql = file_get_contents('omnistock_db.sql');
if ($conexion->multi_query($sql)) {
    echo "¡Base de datos importada exitosamente en Railway!";
} else {
    echo "Error al importar: " . $conexion->error;
}
