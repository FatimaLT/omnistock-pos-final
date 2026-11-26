<?php
declare(strict_types=1);

function upload_warehouse_message_image(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
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
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if (!isset($allowed[$extension]) || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('Formato no permitido. Usa una imagen PNG, JPG o WEBP válida.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $imageInfo = getimagesize($temporaryPath);
    if ($mime !== $allowed[$extension] || $imageInfo === false || $imageInfo['mime'] !== $mime) {
        throw new RuntimeException('El archivo no es una imagen PNG, JPG o WEBP válida.');
    }

    $directory = dirname(__DIR__) . '/public/img/mensajes';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo preparar el almacenamiento de imágenes.');
    }

    $storedExtension = $extension === 'jpeg' ? 'jpg' : $extension;
    $relativePath = 'public/img/mensajes/' . bin2hex(random_bytes(16)) . '.' . $storedExtension;
    if (!move_uploaded_file($temporaryPath, dirname(__DIR__) . '/' . $relativePath)) {
        throw new RuntimeException('No se pudo guardar la imagen. Verifica los permisos de la carpeta.');
    }

    return $relativePath;
}

function delete_warehouse_message_image(?string $path): void
{
    if ($path === null || !preg_match('~^public/img/mensajes/[a-f0-9]{32}\.(?:png|jpg|webp)$~', $path)) {
        return;
    }

    $file = dirname(__DIR__) . '/' . $path;
    if (is_file($file) && !unlink($file)) {
        error_log('OmniStock could not remove warehouse message image: ' . $path);
    }
}
