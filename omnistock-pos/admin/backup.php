<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
$user = require_admin_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!isset($_SESSION['backup_csrf']) || !hash_equals((string) $_SESSION['backup_csrf'], $csrfToken)) {
    $_SESSION['backup_message'] = 'La solicitud expiró. Actualiza la página e inténtalo nuevamente.';
    header('Location: reports.php');
    exit;
}

$backupDirectory = dirname(__DIR__) . '/backups';
$temporaryConfig = null;
$temporaryDump = null;
$process = null;
$dumpOutput = null;
$stderr = '';
set_time_limit(0);
ignore_user_abort(true);

try {
    $pdo = database_connection();
    $pdo->query('SELECT 1');
    $database = getenv('OMNISTOCK_DB_NAME') ?: 'omnistock_db';
    $host = getenv('OMNISTOCK_DB_HOST') ?: 'localhost';
    $port = getenv('OMNISTOCK_DB_PORT') ?: '3306';
    $username = getenv('OMNISTOCK_DB_USER') ?: 'root';
    $password = getenv('OMNISTOCK_DB_PASSWORD') ?: '';

    if (!preg_match('/^[a-zA-Z0-9_$-]+$/', $database)) {
        throw new RuntimeException('La configuración de base de datos no es válida.');
    }
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0750, true) && !is_dir($backupDirectory)) {
        throw new RuntimeException('No se pudo preparar la carpeta de respaldos.');
    }
    if (!is_writable($backupDirectory)) {
        throw new RuntimeException('La carpeta de respaldos no tiene permisos de escritura.');
    }

    $mysqlDumpPath = getenv('OMNISTOCK_MYSQLDUMP_PATH') ?: '';
    $candidates = [
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump.exe',
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
    ];
    if ($mysqlDumpPath !== '') {
        array_unshift($candidates, $mysqlDumpPath);
    }
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            $mysqlDumpPath = $candidate;
            break;
        }
    }
    if (!is_file($mysqlDumpPath) && $mysqlDumpPath !== 'mysqldump') {
        $mysqlDumpPath = 'mysqldump';
    }

    $optionValue = static fn(string $value): string => '"' . str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '', ''], $value) . '"';
    $config = "[client]\n"
        . 'host=' . $optionValue($host) . "\n"
        . 'port=' . $optionValue($port) . "\n"
        . 'user=' . $optionValue($username) . "\n"
        . 'password=' . $optionValue($password) . "\n"
        . "default-character-set=utf8mb4\n";

    $temporaryConfig = tempnam(sys_get_temp_dir(), 'omni-mysql-');
    if ($temporaryConfig === false || file_put_contents($temporaryConfig, $config, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo preparar la configuración segura del respaldo.');
    }
    @chmod($temporaryConfig, 0600);

    $timestamp = date('Ymd-His');
    $filename = 'omnistock-db-' . $timestamp . '-' . bin2hex(random_bytes(3)) . '.sql';
    $temporaryDump = $backupDirectory . DIRECTORY_SEPARATOR . '.' . $filename . '.tmp';
    $finalPath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;
    $dumpOutput = fopen($temporaryDump, 'xb');
    if ($dumpOutput === false) {
        $dumpOutput = null;
        throw new RuntimeException('No se pudo crear el archivo temporal de respaldo.');
    }

    $command = [
        $mysqlDumpPath,
        '--defaults-extra-file=' . $temporaryConfig,
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '--events',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        '--host=' . $host,
        '--port=' . $port,
        '--user=' . $username,
        '--databases',
        $database,
    ];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => $dumpOutput,
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        fclose($dumpOutput);
        $dumpOutput = null;
        throw new RuntimeException('No se pudo iniciar la herramienta de respaldo de MySQL.');
    }
    fclose($pipes[0]);
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $process = null;
    fclose($dumpOutput);
    $dumpOutput = null;

    clearstatcache(true, $temporaryDump);
    if ($exitCode !== 0 || !is_file($temporaryDump) || filesize($temporaryDump) < 100) {
        throw new RuntimeException('La herramienta mysqldump no pudo completar la copia de la base.');
    }
    $dumpHandle = fopen($temporaryDump, 'rb');
    $dumpHeader = $dumpHandle === false ? '' : fread($dumpHandle, 256);
    if (is_resource($dumpHandle)) {
        fclose($dumpHandle);
    }
    if ($dumpHeader === '' || (!str_contains($dumpHeader, 'MySQL dump') && !str_contains($dumpHeader, 'MariaDB dump'))) {
        throw new RuntimeException('El archivo generado no parece ser un dump SQL válido.');
    }
    if (!rename($temporaryDump, $finalPath)) {
        throw new RuntimeException('No se pudo guardar el respaldo en la carpeta local.');
    }
    $temporaryDump = null;

    session_write_close();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($finalPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    if (is_file($temporaryConfig)) {
        unlink($temporaryConfig);
        $temporaryConfig = null;
    }
    readfile($finalPath);
    exit;
} catch (Throwable $exception) {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    if (is_resource($dumpOutput)) {
        fclose($dumpOutput);
    }
    if ($temporaryConfig !== null && is_file($temporaryConfig)) {
        unlink($temporaryConfig);
        $temporaryConfig = null;
    }
    if ($temporaryDump !== null && is_file($temporaryDump)) {
        unlink($temporaryDump);
        $temporaryDump = null;
    }
    error_log('OmniStock database backup: ' . $exception->getMessage() . ($stderr !== '' ? ' ' . $stderr : ''));
    $_SESSION['backup_message'] = 'No se pudo generar el respaldo. Verifica que MySQL esté activo, mysqldump esté disponible y la carpeta de respaldos tenga permisos de escritura.';
    header('Location: reports.php');
    exit;
} finally {
    if ($temporaryConfig !== null && is_file($temporaryConfig)) {
        unlink($temporaryConfig);
    }
    if ($temporaryDump !== null && is_file($temporaryDump)) {
        unlink($temporaryDump);
    }
}
