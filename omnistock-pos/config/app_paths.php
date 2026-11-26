<?php
declare(strict_types=1);

function app_base_path(): string
{
    $scriptPath = '/' . trim(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $lastSlash = strrpos($scriptPath, '/');
    $scriptDirectory = $lastSlash === false ? '' : substr($scriptPath, 0, $lastSlash);
    $segments = array_values(array_filter(explode('/', $scriptDirectory), static fn(string $segment): bool => $segment !== ''));

    if ($segments && in_array(end($segments), ['admin', 'api', 'views'], true)) {
        array_pop($segments);
    }

    return $segments ? '/' . implode('/', $segments) : '';
}
